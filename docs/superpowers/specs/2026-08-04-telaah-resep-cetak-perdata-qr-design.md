# Desain: Cetak Telaah Resep Per Data (Klik Kanan) + QR 2 Validator

Tanggal: 2026-08-04  
Area: `InventoryTelaahResep`  
Status: Approved for planning

## Latar Belakang

Saat ini cetak telaah resep masih berorientasi list/rekap. User membutuhkan cetak **per data telaah** dari baris yang dipilih (klik kanan), dengan format portrait, data pasien lengkap, detail pemberian obat, hasil telaah, dan tanda tangan digital 2 validator berbentuk QR code.

## Tujuan

1. Menambahkan aksi cetak **per no_resep terpilih** lewat klik kanan tabel telaah.
2. Menyediakan report portrait baru khusus detail satu telaah resep.
3. Menampilkan data pasien + asal pelayanan sesuai konteks rawat jalan/inap.
4. Menampilkan daftar obat gabungan non-racikan dan racikan.
5. Menampilkan QR validator 1 dan validator 2 beserta nama dan waktu validasi.

## Ruang Lingkup

### In Scope

- Popup menu klik kanan pada tabel telaah resep (`tbObat`) dengan item cetak per data.
- Jasper report baru portrait untuk satu data telaah.
- Query detail header pasien, hasil telaah, detail obat.
- Blok tanda tangan validator 1 dan validator 2 berbentuk QR.

### Out of Scope

- Perubahan perilaku tombol cetak rekap existing (`BtnPrint`).
- Perubahan skema besar di luar yang dibutuhkan report ini.
- Perubahan workflow validasi telaah (selain membaca datanya untuk report).

## Desain Solusi

## 1) UX / Alur Pengguna

1. User membuka `InventoryTelaahResep`.
2. User memilih satu baris telaah.
3. User klik kanan pada tabel → pilih menu `Cetak Telaah Per Data`.
4. Sistem memproses report detail berdasarkan `no_resep` baris terpilih.
5. Report tampil di viewer Jasper.

Guard:
- Jika tidak ada baris terpilih, tampil pesan validasi dan proses dibatalkan.

## 2) Perubahan UI Java (InventoryTelaahResep)

File: `src/inventory/InventoryTelaahResep.java`

- Tambah `JPopupMenu` khusus tabel telaah.
- Tambah `JMenuItem` mis. `mnCetakPerDataTelaah`.
- Hubungkan popup ke `tbObat` (dan/atau scroll tabel).
- Tambah handler action menu untuk menjalankan cetak report per data.
- Handler mengambil `no_resep` dari baris aktif, menyiapkan parameter RS, lalu memanggil `Valid.MyReportqry(...)` atau `Valid.MyReport(...)` sesuai pola paling stabil di class ini.

Catatan kompatibilitas:
- Perilaku tombol `BtnPrint` yang existing tetap utuh (rekap/list).

## 3) Desain Data Header Pasien

Sumber utama:
- `telaah_farmasi`
- `resep_obat`
- `reg_periksa`
- `pasien`
- `dokter`
- `petugas` (validator 1)
- `petugas as petugas2` (validator 2)

Tambahan untuk asal:
- `poliklinik` (rawat jalan)
- `kamar_inap`, `kamar`, `bangsal` (rawat inap)

Field header yang wajib tampil:
- Nomor resep
- Dokter peresep
- Asal resep (status resep)
- Asal pelayanan
  - Rawat jalan: nama poli
  - Rawat inap: bangsal/kamar
- Nomor rawat
- Nomor RM
- Nama pasien
- Tanggal lahir
- Umur
- Jenis kelamin

Aturan `asal`:
- Jika ditemukan data ranap aktif/relevan untuk `no_rawat`, gunakan bangsal/kamar.
- Selain itu gunakan poli rawat jalan.
- Jika tidak tersedia, tampilkan `-`.

## 4) Desain Data Pemberian Obat (Gabungan)

Kolom output:
- `nama_obat`
- `cara_pakai`
- `jumlah`

Sumber data digabung (`UNION ALL`):
1. Non-racikan dari `resep_dokter` + `databarang`.
2. Racikan dari `resep_dokter_racikan` + `resep_dokter_racikan_detail` + `databarang`.

Format nama item racikan:
- Tetap terbaca sebagai item obat, mis. `Racikan <no_racik> - <nama_brng>`.

Urutan data:
- Diurutkan stabil agar mudah dibaca (kelompok non-racikan lalu racikan, atau by kode/nama sesuai pola data lokal).

## 5) Hasil Telaah

Menampilkan blok ringkasan hasil telaah dari `telaah_farmasi`, mencakup item-item telaah resep dan telaah obat beserta keterangan yang sudah disimpan.

Tujuan tampilan:
- Ringkas dan terbaca di portrait.
- Tidak mengubah makna data existing.

## 6) Blok Tanda Tangan QR Validator

### Validator 1
- Nama: dari `petugas.nama` (berdasarkan `telaah_farmasi.nip`).
- Waktu validasi 1: gunakan waktu simpan telaah resep yaitu `resep_obat.tgl_perawatan + resep_obat.jam` (keputusan user).
- QR content memuat minimal: no resep, identitas validator1, waktu validasi.

### Validator 2
- Nama: dari `petugas2.nama` (berdasarkan `telaah_farmasi.nip2`).
- Waktu validasi 2: `telaah_farmasi.tgl_validasi2`.
- Status validasi 2: `status_validasi2`.
- QR content memuat minimal: no resep, identitas validator2, status, waktu validasi2, catatan (jika ada).

Kondisi belum validasi2:
- Jika `nip2` null/kosong atau `status_validasi2='Belum'`, tampilkan placeholder (`Belum divalidasi`) dan QR validator2 disembunyikan/kosong.

Implementasi QR:
- Mengikuti pola `<jr:QRCode ...>` yang sudah dipakai di report lain pada repositori.

## 7) Desain Report Jasper Baru

File baru:
- `report/rptTelaahResepPerData.jrxml`

Karakteristik:
- Portrait (`pageWidth=595`, `pageHeight=842`), mengikuti format header portrait report existing.
- Header RS konsisten dengan report lain (parameter `namars`, `alamatrs`, `kotars`, `propinsirs`, `kontakrs`, `emailrs`, `logo`).
- Band detail dibagi section:
  1) Identitas pasien/resep
  2) Data pemberian obat
  3) Hasil telaah
  4) Tanda tangan QR dua validator

Output compile:
- `report/rptTelaahResepPerData.jasper`

## 8) Error Handling

- Tidak ada baris dipilih saat klik menu: tampil pesan dan return.
- Data detail tidak ditemukan untuk `no_resep` terpilih: tampil pesan dan return.
- Nilai nullable (validator2/catatan/asal): tampil fallback `-` atau placeholder.

## 9) Dampak & Risiko

File terdampak utama:
- `src/inventory/InventoryTelaahResep.java`
- `report/rptTelaahResepPerData.jrxml`
- `report/rptTelaahResepPerData.jasper`

Risiko teknis:
- Query gabungan obat racikan/non-racikan perlu sinkron format kolom.
- Penentuan asal rawat jalan/inap harus robust pada data yang tidak lengkap.
- Compile `.jasper` harus menggunakan environment kompatibel library runtime setempat.

Mitigasi:
- Gunakan query incremental dan validasi hasil di sample data RJ + RI.
- Gunakan fallback null-safe di ekspresi jasper.
- Uji manual per skenario utama.

## 10) Rencana Uji Manual

1. Pilih data rawat jalan, klik kanan cetak per data → asal dari poli.
2. Pilih data rawat inap, klik kanan cetak per data → asal dari bangsal/kamar.
3. Data dengan non-racikan + racikan → keduanya muncul di tabel obat.
4. Data dengan validator2 sudah sesuai → QR validator2 + nama + waktu muncul.
5. Data belum validasi2 → placeholder validator2 muncul, QR tidak tampil.
6. Klik menu tanpa seleksi baris → pesan validasi tampil.

## 11) Kriteria Selesai

- Menu klik kanan cetak per data aktif dan stabil.
- Report portrait per data tampil dengan header RS konsisten.
- Data pasien, obat, hasil telaah, dan 2 validator sesuai mapping.
- QR dua validator tampil sesuai kondisi data.
- Tombol cetak rekap lama tetap berfungsi seperti sebelumnya.
