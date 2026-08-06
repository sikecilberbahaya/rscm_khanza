# Design Specification: Sistem Laporan Anestesi Lengkap Sesuai Standar Kemenkes

**Tanggal:** 2026-01-07  
**Status:** Approved  
**Version:** 1.0

## 1. Executive Summary

### 1.1 Problem Statement
SIMRS Khanza memiliki tabel `laporan_anestesi` yang lengkap sesuai standar Kemenkes (baris 8466-8532 di sik2.sql), namun tabel ini tidak aktif ("ghost table"). Menu "Laporan Anestesi" di DlgPasien.java hanya mencetak form kosong tanpa data. 

**Gap Analysis:**
- ❌ Tabel `laporan_anestesi` tidak ada kode Java yang INSERT/UPDATE/READ
- ❌ Obat anestesi & premedikasi tidak terdokumentasi
- ❌ Cairan & transfusi tidak tercatat
- ❌ Monitoring intra-operasi hanya snapshot, bukan chart serial
- ❌ Report hanya cetak identitas pasien (form kosong untuk isi manual)

### 1.2 Solution Overview
**Strategi:** Aktivasi tabel `laporan_anestesi` existing sebagai header/koordinator, tambah 3 tabel child baru untuk data yang belum tercatat (obat, monitoring serial, cairan/transfusi), buat form input baru `RMLaporanAnestesi.java`, dan report Jasper lengkap dengan QR code signature digital.

**Benefit:**
- ✅ Laporan anestesi data-driven sesuai standar Kemenkes
- ✅ Zero impact ke form existing (RMCatatanAnastesiSedasi, RMPenilaianPreAnastesi tetap berjalan)
- ✅ Monitoring serial per interval waktu (hybrid: 3 fase default + tambah manual)
- ✅ Dokumentasi obat anestesi lengkap dengan dosis, rute, fase
- ✅ Cairan & transfusi tercatat detail dengan balance otomatis
- ✅ Tanda tangan digital QR code (medikolegal compliance)

---

## 2. Architecture

### 2.1 Database Schema

#### A. Tabel Baru

**1. `master_obat_anestesi` - Master obat khusus anestesi**

```sql
CREATE TABLE `master_obat_anestesi` (
  `kd_obat` varchar(15) NOT NULL PRIMARY KEY,
  `nm_obat` varchar(50) NOT NULL,
  `kategori` enum('Premedikasi','Induksi','Maintenance','Analgesik','Muscle Relaxant','Reversal','Emergency') NOT NULL,
  `satuan_default` varchar(10) NOT NULL,
  `rute_default` enum('IV','IM','SC','PO','Inhalasi','Epidural','Spinal','Topikal') NOT NULL,
  FOREIGN KEY (`kd_obat`) REFERENCES `obatbhp_ok` (`kd_obat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

**Rationale:**
- Master terpisah dari `obatbhp_ok` untuk avoid breaking change di billing system (DlgObatOperasi.java hardcoded 4 parameter INSERT)
- Pre-set kategori, satuan, rute per obat anestesi untuk auto-fill saat input
- FK ke `obatbhp_ok` untuk maintain referential integrity dengan master obat umum

**2. `obat_anestesi` - Transaksi obat per pasien**

```sql
CREATE TABLE `obat_anestesi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `urutan` int NOT NULL AUTO_INCREMENT,
  `waktu` time NOT NULL,
  `fase` enum('Premedikasi','Induksi','Maintenance','Emergence') NOT NULL,
  `kd_obat` varchar(15) NOT NULL,
  `dosis` varchar(20) NOT NULL,
  `satuan` varchar(10) NOT NULL,
  `rute` enum('IV','IM','SC','PO','Inhalasi','Epidural','Spinal','Topikal') NOT NULL,
  `petugas` varchar(20),
  PRIMARY KEY (`no_rawat`, `tanggal`, `urutan`),
  FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE,
  FOREIGN KEY (`kd_obat`) REFERENCES `obatbhp_ok` (`kd_obat`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

**Key Features:**
- Timeline lengkap: `waktu` per pemberian obat
- Fase anestesi: Premedikasi → Induksi → Maintenance → Emergence
- Dosis & rute: Standar Kemenkes (nama obat, dosis, rute pemberian)
- Petugas: Track siapa yang memberi obat (NIK/kode)

**3. `monitoring_intra_anestesi` - Chart monitoring TTV serial**

```sql
CREATE TABLE `monitoring_intra_anestesi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `jam` time NOT NULL,
  `fase` enum('Pre-Induksi','Intra-Operasi','Post-Operasi','Monitoring') NOT NULL,
  `td_sistol` int,
  `td_diastol` int,
  `hr` int,
  `rr` int,
  `spo2` int,
  `etco2` int,
  `suhu` decimal(4,1),
  `kejadian` text,
  PRIMARY KEY (`no_rawat`, `tanggal`, `jam`),
  FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

**Design Pattern: Hybrid Monitoring**
- **Default 3 baris:** Pre-Induksi, Intra-Operasi, Post-Operasi (operasi sederhana)
- **Tambah manual:** Tombol "Tambah Monitoring" untuk interval 5/10/15 menit (operasi panjang/berisiko)
- **Fase "Monitoring":** Untuk baris tambahan monitoring serial
- **Kolom nullable:** Semua TTV nullable untuk fleksibilitas (tidak semua parameter selalu diukur)

**4. `cairan_transfusi_anestesi` - Detail cairan & transfusi**

```sql
CREATE TABLE `cairan_transfusi_anestesi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `urutan` int NOT NULL AUTO_INCREMENT,
  `waktu` time NOT NULL,
  `kategori` enum('Input','Output') NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `volume` int NOT NULL,
  `keterangan` varchar(100),
  PRIMARY KEY (`no_rawat`, `tanggal`, `urutan`),
  FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

**Design Pattern: Hybrid Detail + Summary**
- Tabel detail per item cairan/transfusi
- Auto-summary di form & report:
  - Total Input = SUM(volume WHERE kategori='Input')
  - Total Output = SUM(volume WHERE kategori='Output')
  - Balance = Total Input - Total Output
- `jenis` free-text: RL, NaCl 0.9%, Koloid, WB, PRC, FFP, Urine, Perdarahan, IWL, dll

#### B. Tabel Existing (Aktivasi)

**5. `laporan_anestesi` - Header/Koordinator (SUDAH ADA, TINGGAL AKTIVASI)**

Struktur lengkap sudah ada di sik2.sql baris 8466-8532:
- Primary Key: `(no_rawat, mulai)`
- Identitas: mulai, selesai, tempat_pemantauan
- Tim: operator1, operator2, asisten_operator, dokter_anestesi, penata_anestesi, onloop
- Diagnosa: diagnosa_preop, diagnosa_postop
- Anestesi: status_asa, premedikasi, jenis_anestesi
- TTV premedikasi: td, rr, hr, suhu, spo2, ekg
- Keadaan umum: bb, tb, alergi, mallampati_1/2/3/4, asa_1/2/3/4/5_e, gcs_e, gcs_m, gcs_v
- Operasi: lama_operasi, lama_anestesi, posisi, perdarahan, urine
- Pasca: komplikasi, ekstubasi, serah_terima_pasien, ruang_recovery

**Strategy:** Form baru `RMLaporanAnestesi.java` akan INSERT/UPDATE tabel ini.

### 2.2 Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                        USER WORKFLOW                                 │
└─────────────────────────────────────────────────────────────────────┘
    │
    │ 1. Klik kanan pasien → Menu "Laporan Anestesi Lengkap"
    │
    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              RMLaporanAnestesi.java (Form Input)                     │
│  ┌─────────────┬─────────────┬─────────────┬──────────────┐         │
│  │ Tab 1:      │ Tab 2:      │ Tab 3:      │ Tab 4:       │         │
│  │ Data Umum   │ Obat        │ Monitoring  │ Cairan       │         │
│  └─────────────┴─────────────┴─────────────┴──────────────┘         │
└─────────────────────────────────────────────────────────────────────┘
    │
    │ 2. Simpan (BtnSimpan)
    │
    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     DATABASE PERSISTENCE                             │
│  • laporan_anestesi (header)                                         │
│  • obat_anestesi (detail obat)                                       │
│  • monitoring_intra_anestesi (detail TTV)                            │
│  • cairan_transfusi_anestesi (detail cairan)                         │
└─────────────────────────────────────────────────────────────────────┘
    │
    │ 3. Print (BtnPrint)
    │
    ▼
┌─────────────────────────────────────────────────────────────────────┐
│           rptLaporanAnestesiLengkap.jasper (Report)                  │
│  • Query JOIN semua tabel                                            │
│  • Generate QR Code signature (operator, dokter anestesi, penata)   │
│  • PDF output                                                        │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.3 Integration Points

**A. Read-only Integration (Data Ditarik dari Form Existing)**
- `penilaian_pre_anestesi` → TTV pra-anestesi, keadaan umum, skor ASA
- `catatan_anestesi_sedasi` → Pengkajian pra-induksi, teknik anestesi
- `operasi` → Tim operasi, jenis anestesi
- `reg_periksa` → Data pasien

**B. No Duplication (Form Existing Tetap Jalan)**
- RMPenilaianPreAnastesi.java tetap aktif untuk input pra-anestesi
- RMCatatanAnastesiSedasi.java tetap aktif untuk catatan sedasi
- RMMonitoringAldrettePascaAnestesi.java tetap aktif untuk skor pemulihan
- Form baru hanya isi data yang BELUM ada di form existing

---

## 3. Component Design

### 3.1 Java Form: `RMLaporanAnestesi.java`

**Class:** `public final class RMLaporanAnestesi extends javax.swing.JDialog`

**Location:** `src/rekammedis/RMLaporanAnestesi.java`

**Pattern:** Follow existing RM form pattern (RMCatatanAnastesiSedasi, RMPenilaianPreAnastesi)

#### A. Constructor

```java
public RMLaporanAnestesi(java.awt.Frame parent, boolean modal) {
    super(parent, modal);
    initComponents();
    
    this.setIconImage(new ImageIcon(super.getClass().getResource("/picture/addressbook-edit24.png")).getImage());
    
    // Init tables
    Valid.loadCombo(cmbTempat, "OK", new String[]{"OK","ICU","Cathlab","Radiologi","Endoscopy"});
    // ... other combo boxes
}

public void setData(String norw, String tgl, String jam) {
    TNoRw.setText(norw);
    TanggalOperasi.setDate(tgl);
    JamMulai.setText(jam);
    tampilPasien();
    tampilHeader();
    tampilObat();
    tampilMonitoring();
    tampilCairan();
}
```

#### B. Layout (4 Tabs)

**Tab 1: Data Umum & Header (`panelDataUmum`)**

Components:
- TNoRw (readonly), TNmPasien (readonly), TUmur (readonly), TJK (readonly)
- TanggalOperasi (DatePicker), JamMulai (TimePicker), JamSelesai (TimePicker)
- cmbTempat (ComboBox: OK/ICU/Cathlab/Radiologi/Endoscopy)
- KdOperator1 (TextField) + BtnOperator1 (Button popup pilih dokter)
- KdAsistenOperator (TextField) + BtnAsistenOperator
- KdDokterAnestesi (TextField) + BtnDokterAnestesi
- KdPenataAnestesi (TextField) + BtnPenataAnestesi
- KdOnloop (TextField) + BtnOnloop
- TDiagnosaPreOp (TextArea), TDiagnosaPostOp (TextArea)
- cmbJenisAnestesi (ComboBox: LA/Sedasi/Regional/GA)
- cmbStatusASA (ComboBox: ASA 1/2/3/4/5/5E)
- TBB (TextField), TTB (TextField), TAlergi (TextField)
- cmbMallampati (ComboBox: 1/2/3/4)
- TGCS_E (TextField), TGCS_V (TextField), TGCS_M (TextField)
- TLamaOperasi (TextField), TLamaAnestesi (TextField)
- cmbPosisi (ComboBox: Supine/Prone/Lateral/Litotomi/Trendelenburg/dll)
- TPerdarahan (TextField), TUrine (TextField)
- TKomplikasi (TextArea)
- cmbEkstubasi (ComboBox: Ya/Tidak/Trakeostomi)
- cmbRuangRecovery (ComboBox: RR/ICU/HCU/Ruangan)

**Tab 2: Obat Anestesi (`panelObat`)**

Components:
- BtnTambahObat (Button) → popup `DlgPilihObatAnestesi`
- tbObat (JTable):
  - Kolom: Waktu | Fase | Kode Obat | Nama Obat | Dosis | Satuan | Rute | Petugas | Hapus
  - Editable: Waktu, Dosis, Satuan, Rute (Kode/Nama dari popup, Fase auto dari kategori obat)
  - Button "Hapus" per baris
- cmbFilterFase (ComboBox: Semua/Premedikasi/Induksi/Maintenance/Emergence) → filter view

**Popup: `DlgPilihObatAnestesi`**
- Query: `SELECT kd_obat, nm_obat, kategori, satuan_default, rute_default FROM master_obat_anestesi`
- Search box dengan filter kategori
- Double-click → return obat ke tbObat dengan auto-fill

**Tab 3: Monitoring TTV (`panelMonitoring`)**

Components:
- BtnTambahMonitoring (Button) → tambah baris baru
- BtnDefaultMonitoring (Button) → reset ke 3 baris default (Pre-Induksi, Intra-Operasi, Post-Operasi)
- tbMonitoring (JTable):
  - Kolom: Jam | Fase | TD (Sistol/Diastol) | HR | RR | SpO2 | EtCO2 | Suhu | Kejadian | Hapus
  - Editable: semua kecuali Hapus button
  - Default 3 baris saat load pertama kali
  - Button "Hapus" per baris

**Tab 4: Cairan & Transfusi (`panelCairan`)**

Layout: Split panel (Input kiri, Output kanan)

Left Panel - Input:
- BtnTambahInput (Button) → tambah baris baru
- tbCairanInput (JTable):
  - Kolom: Waktu | Jenis | Volume | Keterangan | Hapus
  - Editable: semua kecuali Hapus button

Right Panel - Output:
- BtnTambahOutput (Button) → tambah baris baru
- tbCairanOutput (JTable):
  - Kolom: Waktu | Jenis | Volume | Keterangan | Hapus
  - Editable: semua kecuali Hapus button

Bottom Panel - Summary (readonly, auto-calculated):
- LabelTotalInput: "Total Input: [X] ml"
- LabelTotalOutput: "Total Output: [X] ml"
- LabelBalance: "Balance Cairan: [+/-X] ml" (warna hijau jika positif, merah jika negatif)

#### C. Methods

**Data Display:**
```java
private void tampilPasien() {
    // Load data pasien dari reg_periksa
    ps = koneksi.prepareStatement("select * from reg_periksa inner join pasien ...");
    // Set TNoRw, TNmPasien, TUmur, TJK
}

private void tampilHeader() {
    // Load data laporan_anestesi jika sudah ada (mode edit)
    ps = koneksi.prepareStatement("select * from laporan_anestesi where no_rawat=? and mulai=?");
    // Fill all fields di tab Data Umum
}

private void tampilObat() {
    // Load obat_anestesi ke tbObat
    ps = koneksi.prepareStatement(
        "select obat_anestesi.*, obatbhp_ok.nm_obat " +
        "from obat_anestesi inner join obatbhp_ok on obat_anestesi.kd_obat=obatbhp_ok.kd_obat " +
        "where obat_anestesi.no_rawat=? and obat_anestesi.tanggal=? " +
        "order by obat_anestesi.waktu"
    );
    // Populate tbObat
}

private void tampilMonitoring() {
    // Load monitoring_intra_anestesi ke tbMonitoring
    ps = koneksi.prepareStatement(
        "select * from monitoring_intra_anestesi " +
        "where no_rawat=? and tanggal=? order by jam"
    );
    // Populate tbMonitoring
    // If empty → insert 3 default rows (Pre-Induksi, Intra-Operasi, Post-Operasi)
}

private void tampilCairan() {
    // Load cairan_transfusi_anestesi ke tbCairanInput & tbCairanOutput
    ps = koneksi.prepareStatement(
        "select * from cairan_transfusi_anestesi " +
        "where no_rawat=? and tanggal=? order by waktu"
    );
    // Split by kategori: Input → tbCairanInput, Output → tbCairanOutput
    hitungBalanceCairan();
}

private void hitungBalanceCairan() {
    // Sum volume dari tbCairanInput
    int totalInput = 0;
    for(r=0; r<tbCairanInput.getRowCount(); r++){
        totalInput += Integer.parseInt(tbCairanInput.getValueAt(r,2).toString());
    }
    
    // Sum volume dari tbCairanOutput
    int totalOutput = 0;
    for(r=0; r<tbCairanOutput.getRowCount(); r++){
        totalOutput += Integer.parseInt(tbCairanOutput.getValueAt(r,2).toString());
    }
    
    int balance = totalInput - totalOutput;
    
    LabelTotalInput.setText("Total Input: " + totalInput + " ml");
    LabelTotalOutput.setText("Total Output: " + totalOutput + " ml");
    LabelBalance.setText("Balance Cairan: " + (balance >= 0 ? "+" : "") + balance + " ml");
    LabelBalance.setForeground(balance >= 0 ? Color.GREEN : Color.RED);
}
```

**Data Persistence:**
```java
private void simpan() {
    if(validasi()) {
        try {
            // 1. Simpan header ke laporan_anestesi
            ps = koneksi.prepareStatement(
                "insert into laporan_anestesi values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            ps.setString(1, TNoRw.getText());
            ps.setString(2, Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText());
            // ... set all parameters from form fields
            ps.executeUpdate();
            
            // 2. Simpan obat ke obat_anestesi
            ps = koneksi.prepareStatement(
                "insert into obat_anestesi values(?,?,?,?,?,?,?,?,?,?)"
            );
            for(r=0; r<tbObat.getRowCount(); r++){
                ps.setString(1, TNoRw.getText());
                ps.setString(2, Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText());
                // ... set from tbObat row
                ps.executeUpdate();
            }
            
            // 3. Simpan monitoring ke monitoring_intra_anestesi
            ps = koneksi.prepareStatement(
                "insert into monitoring_intra_anestesi values(?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            for(r=0; r<tbMonitoring.getRowCount(); r++){
                // ... set from tbMonitoring row
                ps.executeUpdate();
            }
            
            // 4. Simpan cairan input
            ps = koneksi.prepareStatement(
                "insert into cairan_transfusi_anestesi values(?,?,?,?,?,?,?,?)"
            );
            for(r=0; r<tbCairanInput.getRowCount(); r++){
                ps.setString(5, "Input"); // kategori
                // ... set from tbCairanInput row
                ps.executeUpdate();
            }
            
            // 5. Simpan cairan output
            for(r=0; r<tbCairanOutput.getRowCount(); r++){
                ps.setString(5, "Output"); // kategori
                // ... set from tbCairanOutput row
                ps.executeUpdate();
            }
            
            JOptionPane.showMessageDialog(null, "Data berhasil disimpan");
            emptTeks();
            dispose();
            
        } catch (Exception e) {
            System.out.println("Notifikasi : " + e);
        }
    }
}

private void hapus() {
    if(validasi()) {
        try {
            // Cascade delete via FK
            Sequel.queryu("delete from laporan_anestesi where no_rawat=? and mulai=?",
                2, new String[]{TNoRw.getText(), Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText()});
            
            JOptionPane.showMessageDialog(null, "Data berhasil dihapus");
            emptTeks();
            dispose();
            
        } catch (Exception e) {
            System.out.println("Notifikasi : " + e);
        }
    }
}

private boolean validasi() {
    boolean valid = true;
    if(TNoRw.getText().trim().equals("")){
        valid = false;
        JOptionPane.showMessageDialog(null, "No Rawat tidak boleh kosong");
    } else if(KdDokterAnestesi.getText().trim().equals("")){
        valid = false;
        JOptionPane.showMessageDialog(null, "Dokter Anestesi harus dipilih");
    } // ... other validations
    return valid;
}
```

**Permission Check:**
```java
private void isCek() {
    BtnSimpan.setEnabled(akses.getlaporan_anestesi());
    BtnHapus.setEnabled(akses.getlaporan_anestesi());
    BtnEdit.setEnabled(akses.getlaporan_anestesi());
    BtnPrint.setEnabled(akses.getlaporan_anestesi());
    
    if(akses.getjml2() >= 1) {
        // Auto-fill dokter anestesi dari user login jika bukan Admin Utama
        if(akses.getkode().equals("Admin Utama")) {
            // Allow pilih dokter lain
        } else {
            KdDokterAnestesi.setText(akses.getkode());
            NmDokterAnestesi.setText(akses.getnamauser());
            BtnDokterAnestesi.setEnabled(false);
        }
    }
}
```

### 3.2 Jasper Report: `rptLaporanAnestesiLengkap.jrxml`

**Location:** `report/rptLaporanAnestesiLengkap.jrxml`

**Page Format:** A4 Portrait

**Dependencies:**
- barcode4j.jar (QR Code generation)
- JasperReports Components XSD

#### A. Main Query

```sql
SELECT 
    -- Data pasien
    p.no_rkm_medis, p.nm_pasien, p.jk, p.tmp_lahir, p.tgl_lahir, p.alamat,
    rp.umurdaftar, rp.sttsumur, rp.no_rawat, rp.tgl_registrasi,
    
    -- Data laporan anestesi
    la.mulai, la.selesai, la.tempat_pemantauan,
    la.diagnosa_preop, la.diagnosa_postop,
    la.jenis_anestesi, la.status_asa, la.premedikasi,
    la.td, la.rr, la.hr, la.suhu, la.spo2, la.ekg,
    la.bb, la.tb, la.alergi,
    la.mallampati_1, la.mallampati_2, la.mallampati_3, la.mallampati_4,
    la.asa_1, la.asa_2, la.asa_3, la.asa_4, la.asa_5_e,
    la.gcs_e, la.gcs_m, la.gcs_v,
    la.lama_operasi, la.lama_anestesi, la.posisi, la.perdarahan, la.urine,
    la.komplikasi, la.ekstubasi, la.serah_terima_pasien, la.ruang_recovery,
    
    -- Tim operasi
    la.operator1, d1.nm_dokter as nm_operator1,
    la.asisten_operator, d2.nm_dokter as nm_asisten_operator,
    la.dokter_anestesi, d3.nm_dokter as nm_dokter_anestesi,
    la.penata_anestesi, p1.nama as nm_penata_anestesi,
    la.onloop, p2.nama as nm_onloop,
    
    -- Setting RS
    s.nama_instansi, s.alamat_instansi, s.kabupaten, s.propinsi,
    s.kontak, s.email, s.logo

FROM laporan_anestesi la
INNER JOIN reg_periksa rp ON la.no_rawat = rp.no_rawat
INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
LEFT JOIN dokter d1 ON la.operator1 = d1.kd_dokter
LEFT JOIN dokter d2 ON la.asisten_operator = d2.kd_dokter
LEFT JOIN dokter d3 ON la.dokter_anestesi = d3.kd_dokter
LEFT JOIN petugas p1 ON la.penata_anestesi = p1.nip
LEFT JOIN petugas p2 ON la.onloop = p2.nip
CROSS JOIN setting s

WHERE la.no_rawat = ? AND la.mulai = ?
```

#### B. Subreport Queries

**Subreport 1: Obat Anestesi (`sr_obat_anestesi.jrxml`)**

```sql
SELECT 
    oa.waktu,
    oa.fase,
    oa.kd_obat,
    ob.nm_obat,
    oa.dosis,
    oa.satuan,
    oa.rute,
    oa.petugas
FROM obat_anestesi oa
INNER JOIN obatbhp_ok ob ON oa.kd_obat = ob.kd_obat
WHERE oa.no_rawat = $P{no_rawat} AND oa.tanggal = $P{tanggal}
ORDER BY oa.waktu
```

**Subreport 2: Monitoring (`sr_monitoring.jrxml`)**

```sql
SELECT 
    mia.jam,
    mia.fase,
    CONCAT(mia.td_sistol, '/', mia.td_diastol) as td,
    mia.hr,
    mia.rr,
    mia.spo2,
    mia.etco2,
    mia.suhu,
    mia.kejadian
FROM monitoring_intra_anestesi mia
WHERE mia.no_rawat = $P{no_rawat} AND mia.tanggal = $P{tanggal}
ORDER BY mia.jam
```

**Subreport 3: Cairan Input (`sr_cairan_input.jrxml`)**

```sql
SELECT 
    cta.waktu,
    cta.jenis,
    cta.volume,
    cta.keterangan
FROM cairan_transfusi_anestesi cta
WHERE cta.no_rawat = $P{no_rawat} 
  AND cta.tanggal = $P{tanggal}
  AND cta.kategori = 'Input'
ORDER BY cta.waktu
```

**Subreport 4: Cairan Output (`sr_cairan_output.jrxml`)**

```sql
SELECT 
    cta.waktu,
    cta.jenis,
    cta.volume,
    cta.keterangan
FROM cairan_transfusi_anestesi cta
WHERE cta.no_rawat = $P{no_rawat} 
  AND cta.tanggal = $P{tanggal}
  AND cta.kategori = 'Output'
ORDER BY cta.waktu
```

#### C. Calculated Fields

```xml
<variable name="total_input" class="java.lang.Integer" calculation="System">
    <variableExpression><![CDATA[
        SELECT SUM(volume) FROM cairan_transfusi_anestesi 
        WHERE no_rawat = $P{no_rawat} 
          AND tanggal = $P{tanggal}
          AND kategori = 'Input'
    ]]></variableExpression>
</variable>

<variable name="total_output" class="java.lang.Integer" calculation="System">
    <variableExpression><![CDATA[
        SELECT SUM(volume) FROM cairan_transfusi_anestesi 
        WHERE no_rawat = $P{no_rawat} 
          AND tanggal = $P{tanggal}
          AND kategori = 'Output'
    ]]></variableExpression>
</variable>

<variable name="balance_cairan" class="java.lang.Integer" calculation="System">
    <variableExpression><![CDATA[$V{total_input} - $V{total_output}]]></variableExpression>
</variable>
```

#### D. QR Code Implementation

**XML Structure:**

```xml
<!-- QR Code Operator -->
<componentElement>
    <reportElement x="50" y="750" width="80" height="80" uuid="uuid-operator-qr"/>
    <jr:QRCode xmlns:jr="http://jasperreports.sourceforge.net/jasperreports/components" 
               xsi:schemaLocation="http://jasperreports.sourceforge.net/jasperreports/components 
                                   http://jasperreports.sourceforge.net/xsd/components.xsd"
               errorCorrectionLevel="H">
        <jr:codeExpression><![CDATA[$P{qr_operator}]]></jr:codeExpression>
    </jr:QRCode>
</componentElement>

<!-- QR Code Dokter Anestesi -->
<componentElement>
    <reportElement x="230" y="750" width="80" height="80" uuid="uuid-anestesi-qr"/>
    <jr:QRCode xmlns:jr="http://jasperreports.sourceforge.net/jasperreports/components"
               errorCorrectionLevel="H">
        <jr:codeExpression><![CDATA[$P{qr_dokter_anestesi}]]></jr:codeExpression>
    </jr:QRCode>
</componentElement>

<!-- QR Code Penata Anestesi -->
<componentElement>
    <reportElement x="410" y="750" width="80" height="80" uuid="uuid-penata-qr"/>
    <jr:QRCode xmlns:jr="http://jasperreports.sourceforge.net/jasperreports/components"
               errorCorrectionLevel="H">
        <jr:codeExpression><![CDATA[$P{qr_penata_anestesi}]]></jr:codeExpression>
    </jr:QRCode>
</componentElement>
```

**Java Parameter Generation:**

```java
private void cetakLaporan() {
    Map<String, Object> param = new HashMap<>();
    param.put("no_rawat", TNoRw.getText());
    param.put("tanggal", Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText());
    param.put("namars", akses.getnamars());
    param.put("alamatrs", akses.getalamatrs());
    param.put("kotars", akses.getkabupatenrs());
    param.put("propinsirs", akses.getpropinsirs());
    param.put("kontakrs", akses.getkontakrs());
    param.put("emailrs", akses.getemailrs());
    param.put("logo", Sequel.cariGambar("select logo from setting"));
    
    // Generate QR Code Operator
    String fingerOperator = Sequel.cariIsi(
        "select sha1(sidikjari.sidikjari) from sidikjari " +
        "inner join pegawai on pegawai.id=sidikjari.id " +
        "where pegawai.nik=?", 
        KdOperator1.getText()
    );
    param.put("qr_operator", 
        "Dikeluarkan di " + akses.getnamars() + 
        ", Kabupaten/Kota " + akses.getkabupatenrs() +
        "\nDitandatangani secara elektronik oleh " + NmOperator1.getText() +
        "\nSebagai Operator" +
        "\nID " + (fingerOperator.equals("") ? KdOperator1.getText() : fingerOperator) +
        "\n" + Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText()
    );
    
    // Generate QR Code Dokter Anestesi
    String fingerAnestesi = Sequel.cariIsi(
        "select sha1(sidikjari.sidikjari) from sidikjari " +
        "inner join pegawai on pegawai.id=sidikjari.id " +
        "where pegawai.nik=?", 
        KdDokterAnestesi.getText()
    );
    param.put("qr_dokter_anestesi", 
        "Dikeluarkan di " + akses.getnamars() + 
        ", Kabupaten/Kota " + akses.getkabupatenrs() +
        "\nDitandatangani secara elektronik oleh " + NmDokterAnestesi.getText() +
        "\nSebagai Dokter Anestesi" +
        "\nID " + (fingerAnestesi.equals("") ? KdDokterAnestesi.getText() : fingerAnestesi) +
        "\n" + Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText()
    );
    
    // Generate QR Code Penata Anestesi
    String fingerPenata = Sequel.cariIsi(
        "select sha1(sidikjari.sidikjari) from sidikjari " +
        "inner join pegawai on pegawai.id=sidikjari.id " +
        "where pegawai.nip=?", 
        KdPenataAnestesi.getText()
    );
    param.put("qr_penata_anestesi", 
        "Dikeluarkan di " + akses.getnamars() + 
        ", Kabupaten/Kota " + akses.getkabupatenrs() +
        "\nDitandatangani secara elektronik oleh " + NmPenataAnestesi.getText() +
        "\nSebagai Penata Anestesi" +
        "\nID " + (fingerPenata.equals("") ? KdPenataAnestesi.getText() : fingerPenata) +
        "\n" + Valid.SetTgl(TanggalOperasi.getSelectedItem()) + " " + JamMulai.getText()
    );
    
    Valid.MyReport("report/rptLaporanAnestesiLengkap.jasper", "report", 
                   "::[ Laporan Anestesi ]::", param);
}
```

**QR Code String Format:**
```
Dikeluarkan di RSUD KOTA SEMARANG, Kabupaten/Kota SEMARANG
Ditandatangani secara elektronik oleh Dr. Ahmad Sutanto, Sp.An
Sebagai Dokter Anestesi
ID a3f5c9b21e8d7f6a4c2b1d9e8f7a6b5c4d3e2f1
13-04-2026 11:44:01
```

---

## 4. User Interface & Menu Integration

### 4.1 Menu Access Points

**Pattern:** Konsisten dengan form anestesi existing (RMPenilaianPreAnastesi, RMCatatanAnastesiSedasi)

#### A. Popup Menu (Klik Kanan Pasien)

**Location 1: `DlgReg.java` (Registrasi)**
```java
// Add menu item di MnRMOperasi submenu
MnLaporanAnestesiLengkap = new JMenuItem("Laporan Anestesi Lengkap");
MnLaporanAnestesiLengkap.addActionListener(new java.awt.event.ActionListener() {
    public void actionPerformed(java.awt.event.ActionEvent evt) {
        MnLaporanAnestesiLengkapActionPerformed(evt);
    }
});
MnRMOperasi.add(MnLaporanAnestesiLengkap);

// Handler
private void MnLaporanAnestesiLengkapActionPerformed(java.awt.event.ActionEvent evt) {
    if(tbPetugas.getSelectedRow() != -1) {
        RMLaporanAnestesi form = new RMLaporanAnestesi(null, false);
        form.setSize(internalFrame1.getWidth()-20, internalFrame1.getHeight()-20);
        form.setLocationRelativeTo(internalFrame1);
        form.setData(
            tbPetugas.getValueAt(tbPetugas.getSelectedRow(),1).toString(),
            Sequel.cariIsi("select tgl_registrasi from reg_periksa where no_rawat=?", tbPetugas.getValueAt(tbPetugas.getSelectedRow(),1).toString()),
            Sequel.cariIsi("select jam_reg from reg_periksa where no_rawat=?", tbPetugas.getValueAt(tbPetugas.getSelectedRow(),1).toString())
        );
        form.setVisible(true);
    }
}
```

**Location 2: `DlgKamarInap.java` (Rawat Inap)**
```java
// Same pattern di popup menu rawat inap
MnLaporanAnestesiLengkap = new JMenuItem("Laporan Anestesi Lengkap");
MnLaporanAnestesiLengkap.addActionListener(...);
MnRMOperasi.add(MnLaporanAnestesiLengkap);
```

**Location 3: `DlgKasirRalan.java` (Kasir Ralan)**
```java
// Same pattern di popup menu kasir
```

#### B. Button Panel

**Location 4: `DlgRawatJalan.java`**
```java
BtnLaporanAnestesiLengkap = new JButton("Lap. Anestesi");
BtnLaporanAnestesiLengkap.setIcon(new ImageIcon(getClass().getResource("/picture/category.png")));
BtnLaporanAnestesiLengkap.addActionListener(new java.awt.event.ActionListener() {
    public void actionPerformed(java.awt.event.ActionEvent evt) {
        BtnLaporanAnestesiLengkapActionPerformed(evt);
    }
});
PanelInput.add(BtnLaporanAnestesiLengkap);
```

**Location 5: `DlgRawatInap.java`**
```java
// Same pattern di panel rawat inap
```

**Location 6: `DlgBookingOperasi.java` (Prioritas Tinggi)**
```java
BtnLaporanAnestesiLengkap = new JButton("Laporan Anestesi Lengkap");
BtnLaporanAnestesiLengkap.setIcon(new ImageIcon(getClass().getResource("/picture/category.png")));
BtnLaporanAnestesiLengkap.addActionListener(...);
// Posisi tombol di sebelah BtnPreAnastesi & BtnCatatanAnastesiSedasi
```

#### C. Menu Utama

**Location 7: `frmUtama.java` (Toolbar)**
```java
// Menu Bar: Rekam Medis > RM Operasi > Laporan Anestesi Lengkap
btnLaporanAnestesiLengkap = new JMenuItem("Laporan Anestesi Lengkap");
btnLaporanAnestesiLengkap.setIcon(new ImageIcon(getClass().getResource("/picture/category.png")));
btnLaporanAnestesiLengkap.addActionListener(...);
menuRMOperasi.add(btnLaporanAnestesiLengkap);
```

### 4.2 Permission Integration

#### A. Database Permission

**SQL Migration:**
```sql
-- Permission sudah ada, tinggal diaktifkan
-- Kolom: laporan_anestesi enum('true','false')
-- Sudah ada di tabel user line 27085

-- Set default Admin Utama
UPDATE user SET laporan_anestesi='true' WHERE id_user='1';

-- Set untuk user dokter anestesi
UPDATE user SET laporan_anestesi='true' 
WHERE user.id_user IN (
    SELECT pegawai.id FROM pegawai 
    INNER JOIN dokter ON pegawai.nik = dokter.kd_dokter 
    WHERE dokter.kd_sps = 'AN'  -- Spesialis Anestesi
);
```

#### B. Java akses.java Integration

**Variable sudah ada (line 193-248):**
```java
private static boolean laporan_anestesi = false;
```

**Admin auto-enable (line 1160-1414):**
```java
if(var.equals("Admin Utama")) {
    akses.laporan_anestesi = true;
    // ... other permissions
}
```

**Load dari DB (line 2358-2612):**
```java
akses.laporan_anestesi = rs2.getBoolean("laporan_anestesi");
```

**Reset logout (line 3579-3833):**
```java
akses.laporan_anestesi = false;
```

**Getter method (line 4814-5068):**
```java
public static boolean getlaporan_anestesi() {
    return akses.laporan_anestesi;
}
```

**Note:** Typo di existing getter `getlaporan_anastesi()` (missing 'e'), tapi untuk backward compatibility tetap dipertahankan. Form baru pakai yang benar: `getlaporan_anestesi()`.

---

## 5. Implementation Checklist

### 5.1 Database Migration

```sql
-- Script: migrations/2026-01-07_laporan_anestesi_lengkap.sql

-- 1. Master obat anestesi
CREATE TABLE IF NOT EXISTS `master_obat_anestesi` (
  `kd_obat` varchar(15) NOT NULL PRIMARY KEY,
  `nm_obat` varchar(50) NOT NULL,
  `kategori` enum('Premedikasi','Induksi','Maintenance','Analgesik','Muscle Relaxant','Reversal','Emergency') NOT NULL,
  `satuan_default` varchar(10) NOT NULL,
  `rute_default` enum('IV','IM','SC','PO','Inhalasi','Epidural','Spinal','Topikal') NOT NULL,
  FOREIGN KEY (`kd_obat`) REFERENCES `obatbhp_ok` (`kd_obat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 2. Obat anestesi transaksi
CREATE TABLE IF NOT EXISTS `obat_anestesi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `urutan` int NOT NULL AUTO_INCREMENT,
  `waktu` time NOT NULL,
  `fase` enum('Premedikasi','Induksi','Maintenance','Emergence') NOT NULL,
  `kd_obat` varchar(15) NOT NULL,
  `dosis` varchar(20) NOT NULL,
  `satuan` varchar(10) NOT NULL,
  `rute` enum('IV','IM','SC','PO','Inhalasi','Epidural','Spinal','Topikal') NOT NULL,
  `petugas` varchar(20),
  PRIMARY KEY (`no_rawat`, `tanggal`, `urutan`),
  FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE,
  FOREIGN KEY (`kd_obat`) REFERENCES `obatbhp_ok` (`kd_obat`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 3. Monitoring intra anestesi
CREATE TABLE IF NOT EXISTS `monitoring_intra_anestesi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `jam` time NOT NULL,
  `fase` enum('Pre-Induksi','Intra-Operasi','Post-Operasi','Monitoring') NOT NULL,
  `td_sistol` int,
  `td_diastol` int,
  `hr` int,
  `rr` int,
  `spo2` int,
  `etco2` int,
  `suhu` decimal(4,1),
  `kejadian` text,
  PRIMARY KEY (`no_rawat`, `tanggal`, `jam`),
  FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 4. Cairan & transfusi
CREATE TABLE IF NOT EXISTS `cairan_transfusi_anestesi` (
  `no_rawat` varchar(17) NOT NULL,
  `tanggal` datetime NOT NULL,
  `urutan` int NOT NULL AUTO_INCREMENT,
  `waktu` time NOT NULL,
  `kategori` enum('Input','Output') NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `volume` int NOT NULL,
  `keterangan` varchar(100),
  PRIMARY KEY (`no_rawat`, `tanggal`, `urutan`),
  FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 5. Seed data master_obat_anestesi (common drugs)
INSERT INTO master_obat_anestesi VALUES
('OBT001', 'Midazolam', 'Premedikasi', 'mg', 'IV'),
('OBT002', 'Fentanyl', 'Induksi', 'mcg', 'IV'),
('OBT003', 'Propofol', 'Induksi', 'mg', 'IV'),
('OBT004', 'Rocuronium', 'Maintenance', 'mg', 'IV'),
('OBT005', 'Atropine', 'Emergency', 'mg', 'IV'),
('OBT006', 'Efedrin', 'Emergency', 'mg', 'IV'),
('OBT007', 'Neostigmin', 'Reversal', 'mg', 'IV'),
('OBT008', 'Bupivacaine', 'Maintenance', 'mg', 'Spinal'),
('OBT009', 'Lidocaine', 'Maintenance', 'mg', 'Epidural'),
('OBT010', 'Ketamine', 'Induksi', 'mg', 'IV');

-- 6. Update permission (optional, already exists)
-- ALTER TABLE user ADD COLUMN laporan_anestesi enum('true','false') NULL DEFAULT NULL;
UPDATE user SET laporan_anestesi='true' WHERE id_user='1';
```

### 5.2 Java Code Files

**New Files:**
1. `src/rekammedis/RMLaporanAnestesi.java` - Main form (4 tabs)
2. `src/rekammedis/DlgPilihObatAnestesi.java` - Popup pilih obat anestesi

**Modified Files:**
1. `src/simrskhanza/DlgReg.java` - Add menu popup
2. `src/simrskhanza/DlgKamarInap.java` - Add menu popup
3. `src/simrskhanza/DlgKasirRalan.java` - Add menu popup
4. `src/rekammedis/DlgRawatJalan.java` - Add button
5. `src/rekammedis/DlgRawatInap.java` - Add button
6. `src/permintaan/DlgBookingOperasi.java` - Add button (prioritas tinggi)
7. `src/simrskhanza/frmUtama.java` - Add menu bar item
8. `src/fungsi/akses.java` - Already has permission variable, no changes needed

### 5.3 Report Files

**New Files:**
1. `report/rptLaporanAnestesiLengkap.jrxml` - Main report
2. `report/rptLaporanAnestesiLengkap.jasper` - Compiled
3. `report/sr_obat_anestesi.jrxml` - Subreport obat
4. `report/sr_obat_anestesi.jasper` - Compiled
5. `report/sr_monitoring.jrxml` - Subreport monitoring
6. `report/sr_monitoring.jasper` - Compiled
7. `report/sr_cairan_input.jrxml` - Subreport cairan input
8. `report/sr_cairan_input.jasper` - Compiled
9. `report/sr_cairan_output.jrxml` - Subreport cairan output
10. `report/sr_cairan_output.jasper` - Compiled

**Modified Files:**
1. `src/simrskhanza/DlgPasien.java` - Fix existing menu "Laporan Anestesi" (line 5151-5173) to call new report instead of broken `rptLaporanAnestesia.jasper`

---

## 6. Data Validation Rules

### 6.1 Required Fields

**Tab 1: Data Umum**
- No Rawat (auto, readonly)
- Tanggal Operasi
- Jam Mulai
- Dokter Anestesi
- Diagnosa Pre-Op
- Jenis Anestesi
- Status ASA

**Tab 2: Obat**
- Minimal 1 obat (premedikasi atau induksi)

**Tab 3: Monitoring**
- Minimal 1 baris monitoring (default 3 baris auto-generated)

**Tab 4: Cairan**
- Optional (tidak wajib, tapi jika diisi harus lengkap per baris)

### 6.2 Business Rules

1. **Timeline Consistency:**
   - Jam Selesai > Jam Mulai
   - Lama Operasi & Lama Anestesi calculated atau manual input
   - Waktu obat harus dalam range Jam Mulai - Jam Selesai
   - Jam monitoring harus dalam range Jam Mulai - Jam Selesai

2. **Team Validation:**
   - Dokter Anestesi wajib
   - Operator1 wajib (link ke tabel operasi jika ada)
   - Penata Anestesi, Asisten, Onloop optional

3. **Clinical Constraints:**
   - TD Sistol > TD Diastol
   - HR, RR, SpO2, Suhu dalam range wajar (warning jika di luar range normal, tidak block)
   - GCS: E(1-4), V(1-5), M(1-6), Total(3-15)
   - ASA: 1-5 (5E = emergency)
   - Mallampati: 1-4

4. **Obat Constraints:**
   - Kode obat harus exist di `obatbhp_ok`
   - Dosis > 0
   - Rute sesuai dengan kategori obat (warning jika tidak umum, misal: Propofol via IM)

5. **Cairan Balance:**
   - Total Input, Output, Balance calculated realtime
   - Warning jika balance sangat negatif (fluid overload) atau sangat positif (dehidrasi)

### 6.3 Permission Check

```java
// Di setiap action button
if(!akses.getlaporan_anestesi()) {
    JOptionPane.showMessageDialog(null, "Anda tidak memiliki hak akses untuk fitur ini");
    return;
}

// Auto-fill petugas dari user login (non-Admin Utama)
if(!akses.getkode().equals("Admin Utama")) {
    // Lock selection dokter anestesi jika user = dokter anestesi
    if(userIsDokterAnestesi()) {
        KdDokterAnestesi.setText(akses.getkode());
        NmDokterAnestesi.setText(akses.getnamauser());
        BtnDokterAnestesi.setEnabled(false);
    }
}
```

---

## 7. Testing Strategy

### 7.1 Unit Testing

**Database Layer:**
- [ ] Insert laporan_anestesi (all fields)
- [ ] Insert obat_anestesi (multiple rows)
- [ ] Insert monitoring_intra_anestesi (default 3 rows + tambahan)
- [ ] Insert cairan_transfusi_anestesi (input & output)
- [ ] Update laporan_anestesi (edit mode)
- [ ] Delete cascade (hapus laporan → child tables auto delete)
- [ ] FK constraint (insert dengan kd_obat invalid → reject)

**Business Logic:**
- [ ] Timeline validation (Jam Selesai > Jam Mulai)
- [ ] Clinical range validation (TD, HR, GCS)
- [ ] Balance cairan calculation
- [ ] Required field validation

### 7.2 Integration Testing

**Form Integration:**
- [ ] Open form dari DlgReg popup menu
- [ ] Open form dari DlgKamarInap popup menu
- [ ] Open form dari DlgBookingOperasi button
- [ ] Load data existing (edit mode)
- [ ] Save new data (insert mode)
- [ ] Delete data
- [ ] Print report

**Report Integration:**
- [ ] Main query returns data
- [ ] Subreport obat populated
- [ ] Subreport monitoring populated
- [ ] Subreport cairan input/output populated
- [ ] QR code generated (operator, dokter anestesi, penata anestesi)
- [ ] Balance cairan calculated correct
- [ ] Page layout A4 portrait fit

**Permission Integration:**
- [ ] Admin Utama = full access
- [ ] User dengan permission = access granted
- [ ] User tanpa permission = buttons disabled
- [ ] Non-admin dokter anestesi = auto-fill & lock selection

### 7.3 User Acceptance Testing

**Scenario 1: New Laporan Anestesi (Operasi Sederhana)**
1. User klik kanan pasien di DlgKamarInap → pilih "Laporan Anestesi Lengkap"
2. Form terbuka, no_rawat auto-filled
3. User isi Tab 1 (data umum, tim, diagnosa, jenis anestesi, TTV)
4. User isi Tab 2 (tambah 3 obat: premedikasi, induksi, maintenance)
5. User skip Tab 3 (monitoring default 3 baris sudah cukup)
6. User isi Tab 4 (cairan: RL 1000ml input, urine 300ml + perdarahan 150ml output)
7. User klik Simpan → success message
8. User klik Print → PDF generated dengan QR code signature

**Expected Result:**
- ✅ Data tersimpan di 4 tabel (laporan_anestesi + 3 child tables)
- ✅ Report lengkap dengan obat, monitoring, cairan, QR code
- ✅ Balance cairan: +550ml

**Scenario 2: Edit Existing Laporan**
1. User open form edit dari pasien yang sudah ada laporan
2. Form auto-load data existing (4 tabs semua terisi)
3. User edit obat (tambah 1 obat emergence: Efedrin)
4. User edit monitoring (tambah 2 baris interval monitoring)
5. User klik Simpan → success message
6. User klik Print → PDF updated

**Expected Result:**
- ✅ Data updated di database
- ✅ Report reflect perubahan

**Scenario 3: Delete Laporan**
1. User open form edit
2. User klik Hapus → confirmation dialog
3. User confirm → success message

**Expected Result:**
- ✅ Data deleted dari laporan_anestesi
- ✅ Child tables auto-deleted (cascade)

**Scenario 4: Permission Test**
1. Login sebagai user tanpa permission `laporan_anestesi`
2. User klik kanan pasien → menu "Laporan Anestesi Lengkap" tidak muncul (atau disabled)
3. Login sebagai dokter anestesi dengan permission
4. User open form → Dokter Anestesi auto-filled & locked

**Expected Result:**
- ✅ User tanpa permission tidak bisa akses
- ✅ Dokter anestesi auto-filled dari user login

---

## 8. Deployment Plan

### 8.1 Pre-Deployment

1. **Backup Database:**
   ```bash
   mysqldump -u root -p sik2 > backup_sik2_2026-01-07.sql
   ```

2. **Code Review:**
   - Review RMLaporanAnestesi.java (logic, validation, SQL injection prevention)
   - Review report queries (performance, index usage)
   - Review menu integration (consistency)

3. **Staging Testing:**
   - Deploy ke staging server
   - Run full test suite
   - UAT dengan user dokter anestesi & admin

### 8.2 Deployment Steps

**Step 1: Database Migration (5 menit)**
```bash
mysql -u root -p sik2 < migrations/2026-01-07_laporan_anestesi_lengkap.sql
```

**Step 2: Code Deployment (10 menit)**
```bash
# Copy Java class files
cp build/classes/rekammedis/RMLaporanAnestesi*.class D:\_1.0.1\build\classes\rekammedis\
cp build/classes/rekammedis/DlgPilihObatAnestesi*.class D:\_1.0.1\build\classes\rekammedis\

# Copy modified files
cp build/classes/simrskhanza/DlgReg*.class D:\_1.0.1\build\classes\simrskhanza\
# ... (all modified files)

# Copy report files
cp report/rptLaporanAnestesiLengkap.jasper D:\_1.0.1\report\
cp report/sr_*.jasper D:\_1.0.1\report\
```

**Step 3: Permission Setup (5 menit)**
```sql
-- Set permission untuk user yang berhak
UPDATE user SET laporan_anestesi='true' 
WHERE user.id_user IN (
    SELECT id FROM (
        SELECT u.id_user as id FROM user u
        INNER JOIN pegawai p ON u.id_user = p.id
        INNER JOIN dokter d ON p.nik = d.kd_dokter
        WHERE d.kd_sps = 'AN'  -- Spesialis Anestesi
    ) as tmp
);

-- Set untuk Admin Utama
UPDATE user SET laporan_anestesi='true' WHERE id_user='1';
```

**Step 4: Restart Application (2 menit)**
```bash
# Restart SIMRS Khanza
# (tutup aplikasi, buka lagi)
```

**Step 5: Smoke Test (10 menit)**
- Login sebagai Admin Utama
- Buka DlgKamarInap → klik kanan pasien → cek menu "Laporan Anestesi Lengkap" muncul
- Open form → cek 4 tabs render correct
- Input dummy data → Simpan → cek database
- Print → cek PDF generated dengan QR code

### 8.3 Post-Deployment

1. **Monitor Logs:**
   - Check console output untuk error
   - Check database slow query log (performance monitoring query report)

2. **User Training:**
   - Training dokter anestesi & penata anestesi (2 jam)
   - Demo workflow lengkap
   - Handout quick reference guide

3. **Rollback Plan (jika gagal):**
   ```bash
   # Restore database
   mysql -u root -p sik2 < backup_sik2_2026-01-07.sql
   
   # Revert code
   git revert <commit-hash>
   
   # Restart application
   ```

---

## 9. Performance Considerations

### 9.1 Database Indexes

```sql
-- Tambahkan index untuk query performance
CREATE INDEX idx_obat_anestesi_lookup ON obat_anestesi(no_rawat, tanggal);
CREATE INDEX idx_monitoring_lookup ON monitoring_intra_anestesi(no_rawat, tanggal);
CREATE INDEX idx_cairan_lookup ON cairan_transfusi_anestesi(no_rawat, tanggal);
CREATE INDEX idx_master_obat_kategori ON master_obat_anestesi(kategori);
```

### 9.2 Query Optimization

**Report Main Query:**
- JOIN only necessary tables
- Use LEFT JOIN (not CROSS JOIN) untuk optional data
- Filter by PK (no_rawat, tanggal) → fast lookup

**Subreport Queries:**
- Parameter-driven (no_rawat, tanggal passed from main report)
- Simple ORDER BY (jam/waktu) → index scannable

**Expected Performance:**
- Form load: <1 second (data 1 pasien)
- Save: <2 seconds (4 tabel insert/update transactional)
- Report generation: <3 seconds (1 halaman A4 dengan subreports & QR codes)

### 9.3 Memory Management

**Java Heap:**
- Form instance: dispose() after close (release memory)
- Table model: clear data saat emptTeks()
- Report: Valid.MyReport() handle connection pooling

**Jasper Report:**
- Subreport: LAZY loading (only load when accessed)
- QR Code: generated on-demand per parameter
- No caching (data anestesi dynamic, tidak perlu cache)

---

## 10. Security Considerations

### 10.1 SQL Injection Prevention

```java
// GOOD: PreparedStatement
ps = koneksi.prepareStatement("select * from laporan_anestesi where no_rawat=? and mulai=?");
ps.setString(1, TNoRw.getText());
ps.setString(2, tanggal);

// BAD: String concatenation (AVOID)
// String sql = "select * from laporan_anestesi where no_rawat='" + TNoRw.getText() + "'";
```

**All queries MUST use PreparedStatement dengan parameterized input.**

### 10.2 Permission Check

```java
// Check di setiap action
if(!akses.getlaporan_anestesi()) {
    JOptionPane.showMessageDialog(null, "Akses ditolak");
    return;
}

// Lock data per-user (non-Admin Utama)
if(!akses.getkode().equals("Admin Utama")) {
    // User hanya bisa edit data yang dia buat
    String petugas = Sequel.cariIsi(
        "select dokter_anestesi from laporan_anestesi where no_rawat=? and mulai=?",
        TNoRw.getText(), tanggal
    );
    if(!petugas.equals(akses.getkode())) {
        JOptionPane.showMessageDialog(null, "Anda hanya bisa edit data yang Anda buat");
        BtnSimpan.setEnabled(false);
        BtnHapus.setEnabled(false);
    }
}
```

### 10.3 Data Integrity

**Foreign Key Constraints:**
- ON DELETE CASCADE: Hapus laporan → auto hapus child records
- ON UPDATE CASCADE: Update kd_obat → auto update references
- FK validation: Insert kd_obat invalid → reject

**Transaction:**
```java
try {
    koneksi.setAutoCommit(false);
    
    // 1. Insert laporan_anestesi
    ps1.executeUpdate();
    
    // 2. Insert obat_anestesi (multiple rows)
    for(...) { ps2.executeUpdate(); }
    
    // 3. Insert monitoring (multiple rows)
    for(...) { ps3.executeUpdate(); }
    
    // 4. Insert cairan (multiple rows)
    for(...) { ps4.executeUpdate(); }
    
    koneksi.commit();
    
} catch (Exception e) {
    koneksi.rollback();
    throw e;
}
```

### 10.4 Audit Trail

**Optional: Tambah tabel audit log**
```sql
CREATE TABLE audit_laporan_anestesi (
    id int AUTO_INCREMENT PRIMARY KEY,
    no_rawat varchar(17),
    tanggal datetime,
    action enum('INSERT','UPDATE','DELETE'),
    user_id varchar(20),
    timestamp datetime DEFAULT CURRENT_TIMESTAMP,
    data_before text,
    data_after text
);
```

**Trigger:**
```sql
-- Log setiap perubahan laporan_anestesi
CREATE TRIGGER trg_audit_laporan_anestesi_update
AFTER UPDATE ON laporan_anestesi
FOR EACH ROW
INSERT INTO audit_laporan_anestesi VALUES(
    NULL, NEW.no_rawat, NEW.mulai, 'UPDATE', @current_user, NOW(),
    JSON_OBJECT('old', OLD.*), JSON_OBJECT('new', NEW.*)
);
```

---

## 11. Future Enhancements

### 11.1 Short-term (v1.1)

1. **Auto-sync obat ke billing:**
   - Trigger/batch job: obat_anestesi → beri_obat_operasi (untuk tagihan)
   - Mapping: dosis → jumlah, harga dari obatbhp_ok

2. **Monitoring chart visualization:**
   - JFreeChart: Line chart TD & HR over time di Tab 3
   - Export chart ke PDF report (embedded image)

3. **Template premedikasi:**
   - Master template premedikasi per jenis anestesi (GA, Spinal, Epidural)
   - Quick fill button: load template → auto populate obat premedikasi

### 11.2 Long-term (v2.0)

1. **Mobile app integration:**
   - Android app untuk monitoring realtime di OK
   - Push data TTV setiap 5 menit dari monitor pasien
   - Auto-sync ke tabel monitoring_intra_anestesi

2. **Analytics dashboard:**
   - Aggregate report: pola komplikasi per jenis anestesi
   - Analisis pola penggunaan obat anestesi per dokter
   - KPI: rata-rata lama anestesi, komplikasi rate, dll

3. **AI-assisted:**
   - Prediksi komplikasi based on pre-op assessment (ML model)
   - Rekomendasi dosis obat based on BB, usia, jenis anestesi
   - Anomaly detection: TTV di luar range → alert

---

## 12. Known Limitations

### 12.1 Current Scope

1. **No real-time monitoring:**
   - Input TTV manual, bukan auto dari monitor pasien
   - Future: integration dengan monitor device (HL7/FHIR)

2. **No anesthetic gas tracking:**
   - Obat inhalasi (Sevoflurane, Isoflurane, N2O) hanya dicatat nama, tidak ada tracking MAC (Minimum Alveolar Concentration)
   - Future: tambah kolom mac_value di obat_anestesi untuk obat kategori 'Inhalasi'

3. **No ventilator settings:**
   - Parameter ventilator (mode, tidal volume, PEEP, FiO2) tidak tercatat detail
   - Existing: ada di `catatan_anestesi_sedasi` sebagai checkbox teknik ventilasi, tapi tidak detail parameter

4. **No pain scale post-op:**
   - Skor nyeri pasca operasi tidak ada di form ini (ada di form terpisah: RMCatatanPengkajianPaskaOperasi)

### 12.2 Technical Constraints

1. **Java Swing UI:**
   - Desktop-only, tidak web-based
   - Tidak responsive untuk screen kecil

2. **Single transaction:**
   - Simpan semua data sekaligus (header + obat + monitoring + cairan)
   - Tidak auto-save incremental

3. **Report static PDF:**
   - Tidak ada export ke Excel/CSV
   - Tidak ada interactive report (drill-down)

---

## 13. Appendix

### 13.1 Glossary

- **ASA:** American Society of Anesthesiologists Physical Status Classification
- **GCS:** Glasgow Coma Scale
- **Mallampati:** Klasifikasi kesulitan intubasi based on airway anatomy
- **EtCO2:** End-tidal CO2 (kadar CO2 akhir ekspirasi)
- **SpO2:** Saturasi oksigen darah
- **MAC:** Minimum Alveolar Concentration (untuk obat inhalasi)
- **PACU:** Post-Anesthesia Care Unit (Ruang Recovery)

### 13.2 Reference Standards

1. **Permenkes No. 4 Tahun 2018:** Standar Rekam Medis Indonesia
2. **WHO Surgical Safety Checklist:** Sign In, Time Out, Sign Out
3. **PERDATIN (Perhimpunan Dokter Anestesi dan Terapi Intensif Indonesia):** Standar praktik anestesi
4. **PMK No. 5 Tahun 2022:** Standar Akreditasi RS (SNARS Ed. 1.1)

### 13.3 Contact & Support

**Developer:** [Nama Tim Developer]  
**Product Owner:** [Nama PIC RS]  
**Support Email:** support@simrskhanza.com  
**Documentation:** [Link internal wiki/docs]

---

**END OF SPECIFICATION**

---

## Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-07 | AI Agent | Initial specification approved |
