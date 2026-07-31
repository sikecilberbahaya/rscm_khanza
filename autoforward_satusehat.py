# ============================================================
#  Orthanc Python Plugin
#  Auto-Forward ke SatuSehat DICOM Router (DICOMROUTER)
#  Disesuaikan dengan repo rscm_khanza / ApiOrthanc.java
# ============================================================
import orthanc
import threading
import json
import time
import logging

# ── Konfigurasi ──────────────────────────────────────────────
MODALITY_NAME   = "DICOMROUTER"   # harus sama dengan orthanc.json & ApiOrthanc.java
DELAY_SECONDS   = 30              # tunggu 30 detik setelah instance terakhir masuk
MODALITY_FILTER = ['CT', 'MR', 'CR', 'DX', 'US', 'XA', 'RF', 'PT', 'NM']  # modality yang dikirim

# ── Logging ──────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='[SatuSehat DICOM] %(asctime)s %(levelname)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
log = logging.getLogger(__name__)

# ── State: study yang sedang menunggu timer ───────────────────
pending_studies = {}   # { study_orthanc_id: threading.Timer }
lock = threading.Lock()


# ─────────────────────────────────────────────────────────────
# Fungsi: kirim study ke DICOMROUTER via REST API Orthanc
# Setara dengan ApiOrthanc.java → kirimKeModality(studyId)
# POST /modalities/DICOMROUTER/store
# ─────────────────────────────────────────────────────────────
def kirim_ke_dicomrouter(study_orthanc_id):
    try:
        # Ambil daftar instance dalam study
        study_info = json.loads(
            orthanc.RestApiGet(f'/studies/{study_orthanc_id}')
        )
        instances  = study_info.get('Instances', [])
        patient_id = study_info.get('PatientMainDicomTags', {}).get('PatientID', 'Unknown')
        study_uid  = study_info.get('MainDicomTags', {}).get('StudyInstanceUID', '')

        log.info(f'Mengirim study ke {MODALITY_NAME}')
        log.info(f'  → Study    : {study_orthanc_id}')
        log.info(f'  → PatientID: {patient_id}')
        log.info(f'  → UID      : {study_uid}')
        log.info(f'  → Instances: {len(instances)}')

        # Kirim ke DICOMROUTER — setara ApiOrthanc.java kirimKeModality()
        # POST /modalities/DICOMROUTER/store dengan body ["studyId"]
        payload  = json.dumps([study_orthanc_id])
        response = orthanc.RestApiPost(
            f'/modalities/{MODALITY_NAME}/store',
            payload
        )
        result = json.loads(response) if response else {}

        log.info(f'Berhasil dikirim ke {MODALITY_NAME}. Response: {result}')

    except Exception as e:
        log.error(f'GAGAL kirim study {study_orthanc_id} ke {MODALITY_NAME}: {e}')
    finally:
        # Hapus dari pending setelah selesai
        with lock:
            pending_studies.pop(study_orthanc_id, None)


# ─────────────────────────────────────────────────────────────
# Fungsi: jadwalkan pengiriman dengan debounce (reset timer
#         jika instance baru masih masuk dalam DELAY_SECONDS)
# ─────────────────────────────────────────────────────────────
def jadwalkan_pengiriman(study_orthanc_id):
    with lock:
        # Batalkan timer lama jika ada
        timer_lama = pending_studies.get(study_orthanc_id)
        if timer_lama:
            timer_lama.cancel()
            log.debug(f'Timer direset untuk study: {study_orthanc_id}')

        # Buat timer baru
        timer = threading.Timer(
            DELAY_SECONDS,
            kirim_ke_dicomrouter,
            args=[study_orthanc_id]
        )
        pending_studies[study_orthanc_id] = timer
        timer.start()
        log.info(f'Timer {DELAY_SECONDS}s dimulai untuk study: {study_orthanc_id}')


# ─────────────────────────────────────────────────────────────
# Callback: dipanggil Orthanc setiap ada perubahan (NewInstance)
# Setara dengan ApiOrthanc.java → AmbilSeries() + kirimKeModality()
# ─────────────────────────────────────────────────────────────
def on_change(change_type, resource_type, resource_id):
    if change_type != orthanc.ChangeType.NEW_INSTANCE:
        return

    instance_id = resource_id
    try:
        # Ambil tags instance
        tags = json.loads(
            orthanc.RestApiGet(f'/instances/{instance_id}/tags?simplify')
        )
        modality = tags.get('Modality', '')

        # Filter modality — hanya kirim yang relevan radiologi
        if modality not in MODALITY_FILTER:
            log.debug(f'Skip instance {instance_id}: modality={modality} tidak difilter')
            return

        # Ambil parent Study
        instance_info = json.loads(
            orthanc.RestApiGet(f'/instances/{instance_id}')
        )
        study_orthanc_id = instance_info.get('ParentStudy')
        if not study_orthanc_id:
            log.warning(f'Instance {instance_id} tidak punya ParentStudy')
            return

        log.info(f'Instance baru masuk | Modality: {modality} | Study: {study_orthanc_id}')

        # Jadwalkan pengiriman ke DICOMROUTER
        jadwalkan_pengiriman(study_orthanc_id)

    except Exception as e:
        log.error(f'Error saat memproses instance {instance_id}: {e}')


# ─────────────────────────────────────────────────────────────
# Callback: dipanggil saat Orthanc menerima instance via
#           DICOM C-STORE (dari alat radiologi / PACS lain)
# Tambahan: hindari loop jika berasal dari DICOMROUTER sendiri
# ─────────────────────────────────────────────────────────────
def on_stored_instance(dicom, instance_id):
    try:
        instance_info = json.loads(
            orthanc.RestApiGet(f'/instances/{instance_id}')
        )

        # Cek asal kiriman — hindari loop
        # Jika berasal dari DICOMROUTER, jangan dikirim ulang
        tags = json.loads(
            orthanc.RestApiGet(f'/instances/{instance_id}/tags?simplify')
        )
        source_aet = tags.get('SourceApplicationEntityTitle', '')
        if source_aet == 'SATUSEHAT_ROUTER':
            log.debug(f'Skip instance {instance_id}: berasal dari DICOMROUTER (loop prevention)')
            return

        modality = tags.get('Modality', '')
        if modality not in MODALITY_FILTER:
            return

        study_orthanc_id = instance_info.get('ParentStudy')
        if study_orthanc_id:
            jadwalkan_pengiriman(study_orthanc_id)

    except Exception as e:
        log.error(f'Error on_stored_instance {instance_id}: {e}')


# ─────────────────────────────────────────────────────────────
# REST Endpoint tambahan: cek status pending studies
# GET /satusehat/pending
# ─────────────────────────────────────────────────────────────
def get_pending_studies(output, uri, **kwargs):
    with lock:
        result = {
            'pending_count': len(pending_studies),
            'pending_studies': list(pending_studies.keys()),
            'modality_target': MODALITY_NAME,
            'delay_seconds': DELAY_SECONDS
        }
    output.AnswerBuffer(
        json.dumps(result, indent=2),
        'application/json'
    )


# ─────────────────────────────────────────────────────────────
# REST Endpoint: kirim ulang study secara manual
# POST /satusehat/kirim-ulang
# Body: {"study_id": "orthanc-study-id"}
# ─────────────────────────────────────────────────────────────
def kirim_ulang_manual(output, uri, **kwargs):
    try:
        body = json.loads(kwargs.get('body', '{}'))
        study_id = body.get('study_id')
        if not study_id:
            output.SendHttpStatus(400, 'study_id diperlukan')
            return

        # Jalankan langsung tanpa delay
        t = threading.Thread(target=kirim_ke_dicomrouter, args=[study_id])
        t.daemon = True
        t.start()

        output.AnswerBuffer(
            json.dumps({'status': 'queued', 'study_id': study_id}),
            'application/json'
        )
    except Exception as e:
        output.SendHttpStatus(500, str(e))


# ─────────────────────────────────────────────────────────────
# Registrasi ke Orthanc
# ─────────────────────────────────────────────────────────────
orthanc.RegisterOnChangeCallback(on_change)
orthanc.RegisterOnStoredInstanceCallback(on_stored_instance)
orthanc.RegisterRestCallback('/satusehat/pending', get_pending_studies)
orthanc.RegisterRestCallback('/satusehat/kirim-ulang', kirim_ulang_manual)

log.info('=' * 55)
log.info(' SatuSehat DICOM Auto-Forward Plugin AKTIF')
log.info(f' Target Modality : {MODALITY_NAME}')
log.info(f' Delay           : {DELAY_SECONDS} detik')
log.info(f' Filter Modality : {MODALITY_FILTER}')
log.info('=' * 55)