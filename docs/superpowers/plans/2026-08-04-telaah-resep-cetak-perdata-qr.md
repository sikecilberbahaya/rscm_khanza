# Cetak Telaah Resep Per Data (Klik Kanan) + QR 2 Validator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan cetak detail satu data telaah resep dari klik kanan tabel, format portrait, berisi data pasien, data obat, hasil telaah, dan QR 2 validator.

**Architecture:** UI menambahkan popup menu pada `tbObat` untuk aksi per baris terpilih. Data report diambil dengan satu query per `no_resep` yang mengembalikan header + detail obat (non-racikan dan racikan) secara flat row. Jasper baru portrait menggunakan field query langsung, termasuk QR validator 1/2.

**Tech Stack:** Java Swing (NetBeans Form), MySQL/MariaDB SQL, JasperReports JRXML/JASPER 6.x.

## Global Constraints

- Jangan ubah perilaku `BtnPrint` existing (rekap tetap sama).
- Trigger cetak per data hanya dari klik kanan baris tabel telaah.
- Validator 1 timestamp memakai `resep_obat.tgl_perawatan + resep_obat.jam`.
- Validator 2 timestamp memakai `telaah_farmasi.tgl_validasi2`.
- Data obat gabungan wajib mencakup non-racikan + racikan.
- Header report portrait harus mengikuti pola header portrait report existing.

---

### Task 1: Tambah Popup Klik Kanan & Aksi Cetak Per Data

**Files:**
- Modify: `src/inventory/InventoryTelaahResep.java`

**Interfaces:**
- Consumes: `tbObat`, `Valid.MyReportqry(...)`, parameter RS existing (`param`).
- Produces:
  - `private void initPopupCetakPerData()`
  - `private void cetakTelaahPerData()`
  - `private javax.swing.JPopupMenu PopupTelaah;`
  - `private javax.swing.JMenuItem MnCetakPerData;`

- [ ] **Step 1: Write the failing test**

Manual failing check (sebelum kode):

```text
1) Buka form InventoryTelaahResep
2) Klik kanan pada baris tabel telaah
Expected (sebelum perubahan): menu "Cetak Telaah Per Data" tidak ada
```

- [ ] **Step 2: Run test to verify it fails**

Run app seperti biasa, lalu cek UI manual di atas.  
Expected: FAIL terhadap requirement (menu belum tersedia).

- [ ] **Step 3: Write minimal implementation**

Tambahkan inisialisasi popup dan handler:

```java
private javax.swing.JPopupMenu PopupTelaah;
private javax.swing.JMenuItem MnCetakPerData;

private void initPopupCetakPerData() {
    PopupTelaah = new javax.swing.JPopupMenu();
    MnCetakPerData = new javax.swing.JMenuItem("Cetak Telaah Per Data");
    MnCetakPerData.addActionListener(evt -> cetakTelaahPerData());
    PopupTelaah.add(MnCetakPerData);
    tbObat.setComponentPopupMenu(PopupTelaah);
}

private void cetakTelaahPerData() {
    if (tbObat.getSelectedRow() < 0) {
        JOptionPane.showMessageDialog(rootPane,"Silahkan anda pilih data terlebih dahulu..!!");
        return;
    }
    String noResep = tbObat.getValueAt(tbObat.getSelectedRow(),0).toString();
    // query dipasang pada Task 2
}
```

Panggil `initPopupCetakPerData();` di constructor setelah tabel siap.

- [ ] **Step 4: Run test to verify it passes**

Run app, ulangi check manual: klik kanan pada baris tabel.  
Expected: PASS, menu `Cetak Telaah Per Data` muncul.

- [ ] **Step 5: Commit**

```bash
git add src/inventory/InventoryTelaahResep.java
git commit -m "feat(telaah_resep): tambah popup klik kanan cetak per data"
```

### Task 2: Implement Query Per Data (Header + Obat Gabungan) dan Panggil Jasper

**Files:**
- Modify: `src/inventory/InventoryTelaahResep.java`

**Interfaces:**
- Consumes: `cetakTelaahPerData()`, `Valid.MyReportqry(...)`, tabel DB (`telaah_farmasi`, `resep_obat`, `reg_periksa`, `pasien`, `dokter`, `petugas`, `resep_dokter`, `resep_dokter_racikan`, `resep_dokter_racikan_detail`, `databarang`, `kamar_inap`, `kamar`, `bangsal`, `poliklinik`).
- Produces:
  - `private String buildQueryCetakTelaahPerData(String noResep)`
  - pemanggilan `Valid.MyReportqry("rptTelaahResepPerData.jasper", "report", ... , query, param)`

- [ ] **Step 1: Write the failing test**

Manual failing check (setelah Task 1, sebelum query):

```text
Klik menu "Cetak Telaah Per Data"
Expected (sebelum Task 2): report gagal/blank karena query belum ada
```

- [ ] **Step 2: Run test to verify it fails**

Jalankan aksi menu.  
Expected: FAIL (belum menampilkan data report sesuai requirement).

- [ ] **Step 3: Write minimal implementation**

Tambahkan builder query flat-row (header + detail obat union all):

```java
private String buildQueryCetakTelaahPerData(String noResep) {
    return "select h.no_resep,h.tgl_perawatan,h.jam,h.no_rawat,h.no_rkm_medis,h.nm_pasien,h.tgl_lahir,h.umur,h.jk,"+
           "h.nm_dokter,h.asal_resep,h.asal_pelayanan,h.nip,h.nama_validator1,h.nip2,h.nama_validator2,"+
           "h.status_validasi2,h.catatan_validasi2,h.tgl_validasi2,"+
           "d.nama_obat,d.cara_pakai,d.jumlah_obat,"+
           "h.resep_identifikasi_pasien,h.resep_ket_identifikasi_pasien,h.resep_tepat_obat,h.resep_ket_tepat_obat,"+
           "h.resep_tepat_dosis,h.resep_ket_tepat_dosis,h.resep_tepat_cara_pemberian,h.resep_ket_tepat_cara_pemberian,"+
           "h.resep_tepat_waktu_pemberian,h.resep_ket_tepat_waktu_pemberian,h.resep_ada_tidak_duplikasi_obat,h.resep_ket_ada_tidak_duplikasi_obat,"+
           "h.resep_interaksi_obat,h.resep_ket_interaksi_obat,h.resep_kontra_indikasi_obat,h.resep_ket_kontra_indikasi_obat,"+
           "h.obat_tepat_pasien,h.obat_tepat_obat,h.obat_tepat_dosis,h.obat_tepat_cara_pemberian,h.obat_tepat_waktu_pemberian "+
           "from (/* header query by no_resep */) h "+
           "left join (/* non-racikan union all racikan detail */) d on d.no_resep=h.no_resep "+
           "where h.no_resep='"+noResep+"'";
}
```

Isi subquery:
- Header: join master + `case when` asal pelayanan (poli vs bangsal/kamar).
- Detail non-racikan: `resep_dokter` + `databarang`.
- Detail racikan: `resep_dokter_racikan_detail` + `databarang`, cara pakai dari `resep_dokter_racikan.aturan_pakai`.

Di `cetakTelaahPerData()`:

```java
String query = buildQueryCetakTelaahPerData(noResep);
Valid.MyReportqry("rptTelaahResepPerData.jasper","report","::[ Telaah Resep Per Data ]::",query,param);
```

- [ ] **Step 4: Run test to verify it passes**

Manual:

```text
1) Klik kanan data rawat jalan → cetak
2) Klik kanan data rawat inap → cetak
Expected: viewer report tampil, data header terisi, asal pelayanan sesuai konteks
```

- [ ] **Step 5: Commit**

```bash
git add src/inventory/InventoryTelaahResep.java
git commit -m "feat(telaah_resep): query cetak per data dengan obat gabungan"
```

### Task 3: Buat JRXML Portrait Per Data + QR 2 Validator

**Files:**
- Create: `report/rptTelaahResepPerData.jrxml`
- Test: `report/rptTelaahResepPerData.jasper` (hasil compile)

**Interfaces:**
- Consumes: field dari query Task 2.
- Produces: layout portrait final berisi header RS, identitas pasien, daftar obat, hasil telaah, tanda tangan QR validator 1/2.

- [ ] **Step 1: Write the failing test**

```text
Sebelum JRXML dibuat, pemanggilan report "rptTelaahResepPerData.jasper" akan gagal file not found.
```

- [ ] **Step 2: Run test to verify it fails**

Trigger menu cetak per data.  
Expected: FAIL, report belum ada.

- [ ] **Step 3: Write minimal implementation**

Gunakan template portrait report existing, lalu isi:

```xml
<jasperReport ... pageWidth="595" pageHeight="842" columnWidth="555" ...>
  <!-- parameter header RS -->
  <parameter name="namars" class="java.lang.String"/>
  <parameter name="alamatrs" class="java.lang.String"/>
  <parameter name="logo" class="java.io.InputStream"/>

  <!-- fields dari query -->
  <field name="no_resep" class="java.lang.String"/>
  <field name="nm_pasien" class="java.lang.String"/>
  <field name="nama_obat" class="java.lang.String"/>
  <field name="cara_pakai" class="java.lang.String"/>
  <field name="jumlah_obat" class="java.lang.String"/>
  <field name="nama_validator1" class="java.lang.String"/>
  <field name="nama_validator2" class="java.lang.String"/>
  <field name="tgl_validasi2" class="java.sql.Timestamp"/>

  <!-- QR validator 1 -->
  <componentElement>
    <jr:QRCode ...>
      <jr:codeExpression><![CDATA["NoResep:"+$F{no_resep}+"|V1:"+$F{nama_validator1}+"|W1:"+$F{tgl_perawatan}+" "+$F{jam}]]></jr:codeExpression>
    </jr:QRCode>
  </componentElement>

  <!-- QR validator 2 -->
  <componentElement>
    <printWhenExpression><![CDATA[$F{nama_validator2}!=null && !$F{nama_validator2}.equals("")]]></printWhenExpression>
    <jr:QRCode ...>
      <jr:codeExpression><![CDATA["NoResep:"+$F{no_resep}+"|V2:"+$F{nama_validator2}+"|St:"+$F{status_validasi2}+"|W2:"+$F{tgl_validasi2}]]></jr:codeExpression>
    </jr:QRCode>
  </componentElement>
</jasperReport>
```

Gunakan group/section agar header pasien tidak terulang per baris obat.

- [ ] **Step 4: Run test to verify it passes**

Compile JRXML ke JASPER, lalu jalankan cetak:

```bash
# contoh compile sesuai environment tim
java -cp "<jasper-classpath>" CompileRpt report/rptTelaahResepPerData.jrxml report/rptTelaahResepPerData.jasper
```

Expected: PASS, report portrait tampil lengkap termasuk QR.

- [ ] **Step 5: Commit**

```bash
git add report/rptTelaahResepPerData.jrxml report/rptTelaahResepPerData.jasper
git commit -m "feat(report): tambah jasper portrait telaah resep per data dengan qr validator"
```

### Task 4: Hardening, Null Safety, dan Verifikasi End-to-End

**Files:**
- Modify: `src/inventory/InventoryTelaahResep.java`
- Modify: `report/rptTelaahResepPerData.jrxml`

**Interfaces:**
- Consumes: implementasi Task 1-3.
- Produces: behavior stabil pada data kosong/null + validasi manual checklist selesai.

- [ ] **Step 1: Write the failing test**

Manual failing scenarios:

```text
A) no row selected
B) validator2 belum validasi
C) data ranap tidak punya kamar aktif
```

Expected sebelum hardening: ada output kurang rapi/blank tanpa fallback.

- [ ] **Step 2: Run test to verify it fails**

Eksekusi A/B/C manual di app.  
Expected: FAIL pada salah satu fallback.

- [ ] **Step 3: Write minimal implementation**

Tambahkan fallback aman:

```java
String aman(String v){ return (v==null||v.trim().equals(""))?"-":v; }
```

Pada JRXML gunakan ekspresi null-safe untuk field optional.

- [ ] **Step 4: Run test to verify it passes**

Checklist manual final:

```text
1) RJ print ok (asal poli)
2) RI print ok (asal bangsal/kamar)
3) Non-racikan+r racikan tampil
4) QR validator1 tampil
5) QR validator2 hanya saat sudah validasi2
6) BtnPrint lama tetap jalan
```

Expected: seluruh skenario PASS.

- [ ] **Step 5: Commit**

```bash
git add src/inventory/InventoryTelaahResep.java report/rptTelaahResepPerData.jrxml
git commit -m "fix(telaah_resep): hardening cetak per data dan null safety report"
```

## Spec Coverage Check

- Klik kanan per data: Task 1.
- Report portrait header RS: Task 3.
- Data pasien lengkap + asal RJ/RI: Task 2 + Task 3.
- Data obat gabungan non-racikan/racikan: Task 2.
- Hasil telaah: Task 3.
- QR dua validator + waktu validasi: Task 3.
- Guard/no selection + fallback data kosong: Task 4.

## Placeholder Scan

- Tidak ada TBD/TODO.
- Semua task punya file target, langkah implementasi, command uji, dan commit.

## Type/Signature Consistency

- `initPopupCetakPerData()`, `cetakTelaahPerData()`, `buildQueryCetakTelaahPerData(String)` konsisten dipakai lintas task.
