## Checklist Manual - Cetak Telaah Resep Per Data

### Prasyarat
- Sudah deploy build terbaru yang memuat perubahan `InventoryTelaahResep` dan report `rptTelaahResepPerData`.
- File report tersedia di runtime: `report/rptTelaahResepPerData.jrxml` dan `report/rptTelaahResepPerData.jasper`.
- Struktur tabel `telaah_farmasi` sudah dimigrasikan pakai `update_telaah_resep_validasi2.sql`.

### Skenario Uji Utama
1. Buka menu `InventoryTelaahResep` dan pastikan tabel terisi data.
2. Klik kanan satu baris data, pastikan menu `Cetak Telaah Per Data` muncul.
3. Pilih `Cetak Telaah Per Data`.
4. Pastikan report tampil portrait dan hanya memuat data resep yang dipilih.
5. Verifikasi header pasien tampil lengkap (No.Rawat, No.RM, Nama, Umur, JK, alamat, poli/ruang, dokter).
6. Verifikasi detail obat tampil gabungan non-racikan dan racikan sesuai urutan.
7. Verifikasi bagian hasil telaah tampil (status + catatan validasi 1/2 sesuai data).
8. Verifikasi QR validator 1 selalu tampil jika NIP validator 1 tersedia.
9. Verifikasi QR validator 2:
   - Jika validasi 2 sudah diisi (`Sesuai`/`Tidak Sesuai`): QR validator 2 tampil.
   - Jika validasi 2 belum diisi (`Belum`): placeholder tanda `-` tampil, tidak error.

### Skenario Otorisasi Validasi 2
1. Login sebagai user validator 1 (bukan Admin Utama), pilih data dengan `status_validasi2='Sesuai'`.
2. Coba hapus data, pastikan ditolak.
3. Login sebagai `Admin Utama`, hapus data yang sama, pastikan diizinkan.

### Skenario Guard Error
1. Klik cetak saat tidak ada baris yang dipilih, pastikan muncul pesan validasi dan tidak crash.
2. Uji data yang field validasi 2 kosong/null, pastikan report tetap terbuka.
3. (Opsional) Rename sementara file report runtime, klik cetak, pastikan aplikasi menampilkan pesan gagal report tanpa force close.

### Kriteria Lulus
- Semua skenario utama dan guard berjalan tanpa exception.
- Data report konsisten dengan baris yang dipilih di tabel.
- Rule otorisasi validasi 2 terpenuhi.
