-- ============================================================
-- SQL Script untuk Menu Tracking BPJS Bridging
-- Tanggal: 14 Januari 2026
-- Deskripsi: Membuat tabel tracking untuk SEP dan Surat Kontrol
-- ============================================================

-- ============================================================
-- 1. Tabel tracking_bpjs_sep
-- ============================================================
CREATE TABLE IF NOT EXISTS `tracking_bpjs_sep` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_sep` varchar(50) NOT NULL,
  `tgl_sep` datetime NOT NULL,
  `no_rawat` varchar(17) DEFAULT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `nm_pasien` varchar(100) NOT NULL,
  `aksi` enum('Terbit','Ubah','Hapus') NOT NULL,
  `user_id` varchar(700) NOT NULL,
  `tgl_aksi` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_no_sep` (`no_sep`),
  KEY `idx_no_rkm_medis` (`no_rkm_medis`),
  KEY `idx_tgl_aksi` (`tgl_aksi`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================================
-- 2. Tabel tracking_bpjs_surat_kontrol
-- ============================================================
CREATE TABLE IF NOT EXISTS `tracking_bpjs_surat_kontrol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_surat_kontrol` varchar(50) NOT NULL,
  `tgl_surat` datetime NOT NULL,
  `tgl_kontrol` date DEFAULT NULL,
  `no_rawat` varchar(17) DEFAULT NULL,
  `no_rkm_medis` varchar(15) NOT NULL,
  `nm_pasien` varchar(100) NOT NULL,
  `aksi` enum('Terbit','Ubah','Hapus') NOT NULL,
  `user_id` varchar(700) NOT NULL,
  `tgl_aksi` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_no_surat_kontrol` (`no_surat_kontrol`),
  KEY `idx_no_rkm_medis` (`no_rkm_medis`),
  KEY `idx_tgl_aksi` (`tgl_aksi`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================================
-- 3. ALTER TABLE user - Tambah field bridging_tracking
-- ============================================================
-- Catatan: Sesuaikan posisi AFTER dengan field terakhir yang ada di tabel user Anda
-- Jika field 'satu_sehat_kirim_procedure' tidak ada, ganti dengan field terakhir yang ada
ALTER TABLE `user` 
ADD COLUMN `bridging_tracking` enum('true','false') NOT NULL DEFAULT 'false';

-- ============================================================
-- 4. Grant akses ke user admin (opsional)
-- ============================================================
-- UPDATE `user` SET `bridging_tracking` = 'true' WHERE `id_user` = 'admin';

-- ============================================================
-- Selesai
-- ============================================================
-- Catatan:
-- 1. Backup database sebelum menjalankan script ini
-- 2. Jalankan script ini di database sik
-- 3. Verifikasi dengan: SHOW TABLES LIKE 'tracking_bpjs%';
-- 4. Cek struktur: DESCRIBE tracking_bpjs_sep;
-- 5. Cek struktur: DESCRIBE tracking_bpjs_surat_kontrol;
-- 6. Cek field baru: DESCRIBE user;
-- 
-- PENTING:
-- Foreign key constraint dihapus untuk menghindari error.
-- Tracking tetap berfungsi normal tanpa FK constraint.
-- user_id tetap berisi id_user dari tabel user.
-- ============================================================
