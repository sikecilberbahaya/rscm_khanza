-- =====================================================
-- VALIDASI SOAP OLEH DOKTER
-- Alter tabel pemeriksaan + tabel audit
-- DB target: sik  (sudah tereksekusi di 127.0.0.1:3308)
-- =====================================================

-- RAWAT JALAN
ALTER TABLE pemeriksaan_ralan
ADD COLUMN verifikasi ENUM('Belum','Sudah') NOT NULL DEFAULT 'Belum' AFTER evaluasi,
ADD COLUMN tgl_verifikasi DATETIME NULL AFTER verifikasi,
ADD COLUMN nip_verifikator VARCHAR(20) NULL AFTER tgl_verifikasi,
ADD COLUMN catatan_validasi TEXT NULL AFTER nip_verifikator;

ALTER TABLE pemeriksaan_ralan ADD INDEX idx_verifikasi (verifikasi);

-- RAWAT INAP
ALTER TABLE pemeriksaan_ranap
ADD COLUMN verifikasi ENUM('Belum','Sudah') NOT NULL DEFAULT 'Belum' AFTER evaluasi,
ADD COLUMN tgl_verifikasi DATETIME NULL AFTER verifikasi,
ADD COLUMN nip_verifikator VARCHAR(20) NULL AFTER tgl_verifikasi,
ADD COLUMN catatan_validasi TEXT NULL AFTER nip_verifikator;

ALTER TABLE pemeriksaan_ranap ADD INDEX idx_verifikasi (verifikasi);

-- AUDIT LOG VALIDASI SOAP
CREATE TABLE IF NOT EXISTS `audit_validasi_soap` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `nip_pembuat` varchar(20) NOT NULL,
  `nip_verifikator` varchar(20) NOT NULL,
  `tgl_verifikasi` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'VALIDASI',
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `idx_no_rawat` (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
