ALTER TABLE surat_bebas_tato MODIFY `hasilperiksa` ENUM ('Emergency', 'Non Emergency') NOT NULL; 
ALTER TABLE reg_periksa MODIFY stts ENUM ('Belum', 'TTV', 'Sudah', 'Batal', 'Berkas Diterima', 'Dirujuk', 'Meninggal', 'Dirawat', 'Pulang Paksa');
ALTER TABLE asuhan_gizi MODIFY pola_makan VARCHAR(500);
ALTER TABLE resep_obat ADD COLUMN iterasi ENUM('Tanpa Iterasi', 'Iterasi 1 Kali', 'Iterasi 2 Kali');

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

ALTER TABLE `user` ADD COLUMN `bridging_tracking` enum('true','false') NOT NULL DEFAULT 'false';
ALTER TABLE `user` ADD COLUMN `satu_sehat_kirim_episode_of_care` enum('true','false') NOT NULL DEFAULT 'false';

-- ============================================================
-- Fitur Panggil Pasien Kasir Rawat Jalan
-- ============================================================
CREATE TABLE IF NOT EXISTS `antrian_display` (
  `kd_display` varchar(10) NOT NULL,
  `nm_display` varchar(100) NOT NULL,
  PRIMARY KEY (`kd_display`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `antrian_poli_display` (
  `kd_poli` varchar(10) NOT NULL,
  `kd_display` varchar(10) NOT NULL,
  PRIMARY KEY (`kd_poli`),
  KEY `idx_kd_display` (`kd_display`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `antrian_panggil_ralan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(20) NOT NULL,
  `nm_pasien` varchar(100) NOT NULL,
  `no_reg` varchar(20) NOT NULL,
  `nm_poli` varchar(100) NOT NULL,
  `kd_display` varchar(10) NOT NULL,
  `waktu_panggil` datetime NOT NULL DEFAULT current_timestamp(),
  `sudah_tampil` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_kd_display_sudah` (`kd_display`,`sudah_tampil`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
