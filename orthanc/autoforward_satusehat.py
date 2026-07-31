# =============================================================================
# autoforward_satusehat.py — Orthanc Python Plugin
# Auto-forward DICOM study ke modality DICOMROUTER (SatuSehat) dengan debounce
# =============================================================================
#
# CARA INSTALASI:
#   1. Pastikan paket orthanc-python sudah terinstall:
#        sudo apt install orthanc-python          # Debian/Ubuntu
#        # atau via Docker: image orthancteam/orthanc sudah built-in
#
#   2. Pastikan mysql-connector-python terinstall di Python yang dipakai Orthanc:
#        pip3 install mysql-connector-python
#
#   3. Salin file ini ke /etc/orthanc/autoforward_satusehat.py
#
#   4. Sesuaikan variabel DB_* di bawah (atau set environment variable sebelum
#      menjalankan Orthanc):
#        export DB_HOST=127.0.0.1
#        export DB_PORT=3306
#        export DB_NAME=sik_bridging_radiologi
#        export DB_USER=root
#        export DB_PASS=password
#
#   5. Buat direktori log:
#        sudo mkdir -p /var/log/orthanc
#        sudo chown orthanc:orthanc /var/log/orthanc
#
#   6. Daftarkan di orthanc.json:
#        {
#          "PythonScript": "/etc/orthanc/autoforward_satusehat.py",
#          "Python": { "Enable": true }
#        }
#
#   7. Restart Orthanc:
#        sudo systemctl restart orthanc
# =============================================================================

import orthanc
import threading
import json
import time
import logging
import os

try:
    import mysql.connector
    MYSQL_AVAILABLE = True
except ImportError:
    MYSQL_AVAILABLE = False
    orthanc.LogWarning('[SatuSehat] mysql-connector-python tidak ditemukan. '
                       'Fitur pencatatan DB dinonaktifkan.')

# ---------------------------------------------------------------------------
# Konfigurasi database MySQL — baca dari environment variable atau hardcode
# Sesuaikan nilai default di bawah jika tidak menggunakan environment variable
# ---------------------------------------------------------------------------
DB_HOST = os.environ.get('DB_HOST', '172.16.1.27')
DB_PORT = int(os.environ.get('DB_PORT', '3306'))
DB_NAME = os.environ.get('DB_NAME', 'sik_rad')
DB_USER = os.environ.get('DB_USER', 'yaneka')
DB_PASS = os.environ.get('DB_PASS', 'lopakun')

# ---------------------------------------------------------------------------
# Konfigurasi plugin
# ---------------------------------------------------------------------------
MODALITY_TARGET   = 'DICOMROUTER'      # Harus sama persis dengan orthanc.json
DEBOUNCE_SECONDS  = 30                  # Tunggu 30 detik setelah instance terakhir
MAX_RETRY         = 3                   # Maksimum percobaan ulang jika gagal
RETRY_INTERVALS   = [60, 120, 180]      # Interval retry dalam detik

# Hanya kirim modality yang relevan
ALLOWED_MODALITIES = {'CT', 'MR', 'CR', 'DX', 'US', 'XA', 'RF', 'PT', 'NM'}

# AET sumber yang harus di-skip untuk menghindari loop
SKIP_AET = 'SATUSEHAT_ROUTER'

# ---------------------------------------------------------------------------
# Setup logging
# ---------------------------------------------------------------------------
LOG_FILE = '/var/log/orthanc/satusehat_dicom.log'

_log_handler = logging.FileHandler(LOG_FILE)
_log_handler.setFormatter(logging.Formatter(
    '%(asctime)s [%(levelname)s] %(message)s', datefmt='%Y-%m-%d %H:%M:%S'))

logger = logging.getLogger('satusehat_dicom')
logger.setLevel(logging.INFO)
if not logger.handlers:
    logger.addHandler(_log_handler)

# ---------------------------------------------------------------------------
# State: dictionary studyOrthancId -> threading.Timer
# ---------------------------------------------------------------------------
_pending_timers = {}
_pending_lock   = threading.Lock()


# ===========================================================================
# Fungsi helper: koneksi MySQL
# ===========================================================================
def _get_db_connection():
    if not MYSQL_AVAILABLE:
        return None
    return mysql.connector.connect(
        host=DB_HOST,
        port=DB_PORT,
        database=DB_NAME,
        user=DB_USER,
        password=DB_PASS,
        connection_timeout=10,
        autocommit=True,
    )


def _get_study_tags(study_orthanc_id):
    """Ambil informasi tags dari study Orthanc."""
    try:
        study_info = json.loads(orthanc.RestApiGet(f'/studies/{study_orthanc_id}'))
        instances   = study_info.get('Instances', [])
        patient_main = study_info.get('PatientMainDicomTags', {})
        study_main   = study_info.get('MainDicomTags', {})

        modality = ''
        if instances:
            first_tags = json.loads(
                orthanc.RestApiGet(f'/instances/{instances[0]}/tags?simplify'))
            modality = first_tags.get('Modality', '')

        return {
            'study_uid':       study_main.get('StudyInstanceUID', ''),
            'no_rontgen':      study_main.get('AccessionNumber', ''),
            'no_rm':           patient_main.get('PatientID', ''),
            'nama_pasien':     patient_main.get('PatientName', ''),
            'modality':        modality,
            'jumlah_instance': len(instances),
        }
    except Exception as exc:
        logger.error(f'[{study_orthanc_id}] Gagal ambil tags study: {exc}')
        return {}


# ===========================================================================
# Fungsi pencatatan ke database
# ===========================================================================
def _upsert_log_pending(study_orthanc_id, tags):
    """Buat record PENDING di log_kirim_dicom_satusehat jika belum ada."""
    if not MYSQL_AVAILABLE:
        return
    try:
        conn = _get_db_connection()
        if conn is None:
            return
        cur = conn.cursor()
        cur.execute(
            """INSERT INTO log_kirim_dicom_satusehat
               (study_orthanc_id, study_uid, no_rontgen, no_rm, nama_pasien,
                modality, jumlah_instance, status)
               VALUES (%s, %s, %s, %s, %s, %s, %s, 'PENDING')
               ON DUPLICATE KEY UPDATE
                 jumlah_instance = VALUES(jumlah_instance),
                 waktu_update    = NOW()""",
            (study_orthanc_id,
             tags.get('study_uid', ''),
             tags.get('no_rontgen') or None,
             tags.get('no_rm') or None,
             tags.get('nama_pasien') or None,
             tags.get('modality') or None,
             tags.get('jumlah_instance', 0))
        )
        cur.close()
        conn.close()
    except Exception as exc:
        logger.error(f'[{study_orthanc_id}] Gagal upsert log PENDING: {exc}')


def _mark_log_terkirim(study_orthanc_id):
    """Update log menjadi TERKIRIM dan update order_out.statusupdate='2'."""
    if not MYSQL_AVAILABLE:
        return
    try:
        conn = _get_db_connection()
        if conn is None:
            return
        cur = conn.cursor()

        # Update log
        cur.execute(
            """UPDATE log_kirim_dicom_satusehat
               SET status='TERKIRIM', waktu_kirim=NOW(), pesan_error=NULL
               WHERE study_orthanc_id=%s""",
            (study_orthanc_id,)
        )

        # Update order_out: ambil no_rontgen dari log
        cur.execute(
            "SELECT no_rontgen FROM log_kirim_dicom_satusehat "
            "WHERE study_orthanc_id=%s",
            (study_orthanc_id,)
        )
        row = cur.fetchone()
        if row and row[0]:
            cur.execute(
                "UPDATE order_out SET statusupdate='2' WHERE no_rontgen=%s",
                (row[0],)
            )
            logger.info(f'[{study_orthanc_id}] order_out.statusupdate diset ke 2 '
                        f'untuk no_rontgen={row[0]}')

        cur.close()
        conn.close()
    except Exception as exc:
        logger.error(f'[{study_orthanc_id}] Gagal update log TERKIRIM: {exc}')


def _mark_log_gagal(study_orthanc_id, pesan_error, retry_count):
    """Update log menjadi GAGAL dengan pesan error dan jumlah retry."""
    if not MYSQL_AVAILABLE:
        return
    try:
        conn = _get_db_connection()
        if conn is None:
            return
        cur = conn.cursor()
        cur.execute(
            """UPDATE log_kirim_dicom_satusehat
               SET status='GAGAL', pesan_error=%s, retry_count=%s
               WHERE study_orthanc_id=%s""",
            (str(pesan_error)[:65535], retry_count, study_orthanc_id)
        )
        cur.close()
        conn.close()
    except Exception as exc:
        logger.error(f'[{study_orthanc_id}] Gagal update log GAGAL: {exc}')


# ===========================================================================
# Fungsi pengiriman study ke DICOMROUTER (dengan retry)
# ===========================================================================
def _send_study(study_orthanc_id, retry_count=0):
    """Kirim study ke modality DICOMROUTER. Retry otomatis jika gagal."""
    logger.info(
        f'[{study_orthanc_id}] Mengirim ke {MODALITY_TARGET} '
        f'(attempt {retry_count + 1}/{MAX_RETRY + 1})')

    try:
        payload = json.dumps([study_orthanc_id])
        orthanc.RestApiPost(f'/modalities/{MODALITY_TARGET}/store', payload)
        logger.info(f'[{study_orthanc_id}] Berhasil dikirim ke {MODALITY_TARGET}.')
        _mark_log_terkirim(study_orthanc_id)

    except Exception as exc:
        logger.error(
            f'[{study_orthanc_id}] Gagal kirim ke {MODALITY_TARGET}: {exc}')
        if retry_count < MAX_RETRY:
            interval = RETRY_INTERVALS[min(retry_count, len(RETRY_INTERVALS) - 1)]
            logger.info(
                f'[{study_orthanc_id}] Retry ke-{retry_count + 1} '
                f'dijadwalkan dalam {interval}s...')
            _mark_log_gagal(study_orthanc_id, exc, retry_count + 1)
            t = threading.Timer(
                interval, _send_study, args=[study_orthanc_id, retry_count + 1])
            t.daemon = True
            t.start()
        else:
            logger.error(
                f'[{study_orthanc_id}] Semua {MAX_RETRY} retry habis. '
                f'Study tidak terkirim.')
            _mark_log_gagal(study_orthanc_id, exc, retry_count + 1)


def _debounce_send(study_orthanc_id):
    """
    Dipanggil setelah debounce timer habis.
    Hapus entry dari pending_timers lalu kirim.
    """
    with _pending_lock:
        _pending_timers.pop(study_orthanc_id, None)
    _send_study(study_orthanc_id)


# ===========================================================================
# Callback on_change — dipanggil Orthanc setiap ada perubahan
# ===========================================================================
def on_change(change_type, level, resource_id):
    """
    Callback utama Orthanc. Hanya proses event NewInstance.
    Debounce: reset timer jika instance baru masuk dalam 30 detik.
    """
    if change_type != orthanc.ChangeType.NEW_INSTANCE:
        return

    instance_id = resource_id
    try:
        # Periksa asal instance — hindari loop dari DICOMROUTER
        instance_info = json.loads(
            orthanc.RestApiGet(f'/instances/{instance_id}'))
        metadata_str = ''
        try:
            metadata_str = orthanc.RestApiGet(
                f'/instances/{instance_id}/metadata/RemoteAET').strip()
        except Exception:
            pass

        if metadata_str == SKIP_AET:
            orthanc.LogInfo(
                f'[SatuSehat] Skip instance {instance_id} — '
                f'berasal dari {SKIP_AET}')
            return

        # Periksa modality
        tags = json.loads(
            orthanc.RestApiGet(f'/instances/{instance_id}/tags?simplify'))
        modality = tags.get('Modality', '')
        if modality not in ALLOWED_MODALITIES:
            orthanc.LogInfo(
                f'[SatuSehat] Skip modality {modality} '
                f'(instance {instance_id})')
            return

        # Ambil study Orthanc ID
        study_orthanc_id = instance_info.get('ParentStudy', '')
        if not study_orthanc_id:
            return

        study_uid = tags.get('StudyInstanceUID', study_orthanc_id)
        orthanc.LogInfo(
            f'[SatuSehat] Instance baru — study={study_uid}, '
            f'modality={modality}, id={instance_id}')

        # Catat PENDING ke DB (idempoten via ON DUPLICATE KEY)
        study_tags = _get_study_tags(study_orthanc_id)
        _upsert_log_pending(study_orthanc_id, study_tags)

        # Debounce: batalkan timer lama, buat yang baru
        with _pending_lock:
            old_timer = _pending_timers.get(study_orthanc_id)
            if old_timer is not None:
                old_timer.cancel()
            t = threading.Timer(
                DEBOUNCE_SECONDS, _debounce_send, args=[study_orthanc_id])
            t.daemon = True
            _pending_timers[study_orthanc_id] = t
            t.start()

        logger.info(
            f'[{study_orthanc_id}] Debounce direset — '
            f'pengiriman dijadwalkan dalam {DEBOUNCE_SECONDS}s.')

    except Exception as exc:
        orthanc.LogError(
            f'[SatuSehat] Error saat memproses instance {instance_id}: {exc}')


# ===========================================================================
# REST endpoint tambahan
# ===========================================================================
def _db_query(sql, params=None):
    """Helper: jalankan query SELECT, kembalikan list of dict."""
    if not MYSQL_AVAILABLE:
        return []
    conn = _get_db_connection()
    if conn is None:
        return []
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(sql, params or ())
        rows = cur.fetchall()
        cur.close()
        conn.close()
        return rows
    except Exception as exc:
        logger.error(f'[DB] Query error: {exc}')
        return []


def _json_response(output, payload):
    """Kirim respons JSON ke Orthanc REST output."""
    output.AnswerBuffer(
        json.dumps(payload, indent=2, default=str).encode('utf-8'),
        'application/json')


def satusehat_ringkasan(output, uri, **kwargs):
    """
    GET /satusehat/ringkasan
    Ringkasan jumlah per status + study pending yang sedang menunggu timer.
    """
    rows = _db_query(
        "SELECT status, COUNT(*) AS jumlah "
        "FROM log_kirim_dicom_satusehat GROUP BY status")
    ringkasan = {r['status']: r['jumlah'] for r in rows}

    with _pending_lock:
        pending_realtime = list(_pending_timers.keys())

    _json_response(output, {
        'ringkasan_db':     ringkasan,
        'pending_realtime': {
            'jumlah':      len(pending_realtime),
            'study_ids':   pending_realtime,
        },
    })


def satusehat_log(output, uri, **kwargs):
    """
    GET /satusehat/log?status=GAGAL&tanggal=YYYY-MM-DD&limit=50
    Daftar log pengiriman DICOM.
    """
    # Orthanc meneruskan query string sebagai bagian dari `uri`
    import urllib.parse
    parsed   = urllib.parse.urlparse(uri)
    qs       = urllib.parse.parse_qs(parsed.query)
    status   = qs.get('status',  [None])[0]
    tanggal  = qs.get('tanggal', [None])[0]
    limit    = int(qs.get('limit', ['50'])[0])

    conditions = []
    params     = []
    if status:
        conditions.append('status = %s')
        params.append(status)
    if tanggal:
        conditions.append('DATE(waktu_masuk) = %s')
        params.append(tanggal)

    where = ('WHERE ' + ' AND '.join(conditions)) if conditions else ''
    rows  = _db_query(
        f"SELECT * FROM log_kirim_dicom_satusehat "
        f"{where} ORDER BY waktu_masuk DESC LIMIT %s",
        params + [limit])

    _json_response(output, {'total': len(rows), 'data': rows})


def satusehat_status(output, uri, **kwargs):
    """
    GET /satusehat/status/{no_rontgen}
    Status pengiriman per nomor rontgen — JOIN dengan order_out.
    """
    # Ekstrak no_rontgen dari URI: /satusehat/status/<no_rontgen>
    no_rontgen = uri.rstrip('/').split('/')[-1]
    rows = _db_query(
        """SELECT l.id, l.study_orthanc_id, l.study_uid, l.no_rontgen,
                  l.no_rm, l.nama_pasien, l.modality, l.jumlah_instance,
                  l.status, l.pesan_error, l.retry_count,
                  l.waktu_masuk, l.waktu_kirim,
                  o.statusupdate AS statusupdate_order_out,
                  o.expertise_finding, o.expertise_conclusion,
                  o.dokter_radiolog
           FROM log_kirim_dicom_satusehat l
           LEFT JOIN order_out o ON l.no_rontgen = o.no_rontgen
           WHERE l.no_rontgen = %s
           ORDER BY l.waktu_masuk DESC LIMIT 10""",
        (no_rontgen,))

    if not rows:
        output.SendHttpStatus(404)
        _json_response(output, {'error': f'no_rontgen {no_rontgen!r} tidak ditemukan'})
        return

    _json_response(output, {'no_rontgen': no_rontgen, 'data': rows})


# ===========================================================================
# Registrasi callback dan REST endpoint
# ===========================================================================
orthanc.RegisterOnChangeCallback(on_change)

orthanc.RegisterRestCallback('/satusehat/ringkasan',        satusehat_ringkasan)
orthanc.RegisterRestCallback('/satusehat/log',              satusehat_log)
orthanc.RegisterRestCallback('/satusehat/status/(.*)',      satusehat_status)

orthanc.LogInfo(
    '[SatuSehat] Plugin autoforward_satusehat.py aktif. '
    f'Target: {MODALITY_TARGET}, Debounce: {DEBOUNCE_SECONDS}s, '
    f'DB: {DB_USER}@{DB_HOST}:{DB_PORT}/{DB_NAME}')
logger.info(
    f'Plugin dimuat — target={MODALITY_TARGET}, debounce={DEBOUNCE_SECONDS}s, '
    f'db={DB_USER}@{DB_HOST}:{DB_PORT}/{DB_NAME}')
