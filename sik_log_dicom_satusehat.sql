-- =============================================================================
-- sik_log_dicom_satusehat.sql
-- Tabel tracking pengiriman DICOM study ke SatuSehat via DICOMROUTER
-- Database: sik_bridging_radiologi
-- =============================================================================
-- Jalankan di database sik_bridging_radiologi:
--   mysql -u root -p sik_bridging_radiologi < sik_log_dicom_satusehat.sql
-- =============================================================================

USE `sik_bridging_radiologi`;

-- -----------------------------------------------------------------------------
-- Tabel: log_kirim_dicom_satusehat
-- Menyimpan riwayat setiap study yang dikirim (atau gagal dikirim) ke SatuSehat
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `log_kirim_dicom_satusehat` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `study_orthanc_id` varchar(64)  NOT NULL COMMENT 'ID study di Orthanc',
  `study_uid`        varchar(100) NOT NULL COMMENT 'StudyInstanceUID DICOM',
  `no_rontgen`       varchar(17)  DEFAULT NULL COMMENT 'AccessionNumber / no_rontgen di order_out',
  `no_rm`            varchar(15)  DEFAULT NULL COMMENT 'PatientID / no rekam medis',
  `nama_pasien`      varchar(40)  DEFAULT NULL,
  `modality`         varchar(10)  DEFAULT NULL COMMENT 'CT, MR, CR, DX, US, XA, RF, PT, NM',
  `jumlah_instance`  int(5)       DEFAULT 0    COMMENT 'Jumlah instance DICOM dalam study',
  `status`           enum('PENDING','TERKIRIM','GAGAL') NOT NULL DEFAULT 'PENDING',
  `pesan_error`      text         DEFAULT NULL COMMENT 'Isi pesan error jika status = GAGAL',
  `retry_count`      int(3)       NOT NULL DEFAULT 0 COMMENT 'Berapa kali sudah di-retry',
  `waktu_masuk`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu instance pertama diterima Orthanc',
  `waktu_kirim`      datetime     DEFAULT NULL COMMENT 'Waktu berhasil dikirim ke DICOMROUTER',
  `waktu_update`     datetime     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_study`        (`study_orthanc_id`),
  INDEX `idx_no_rontgen`       (`no_rontgen`),
  INDEX `idx_status`           (`status`),
  INDEX `idx_waktu_masuk`      (`waktu_masuk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Tracking pengiriman DICOM study ke SatuSehat via DICOMROUTER';


-- =============================================================================
-- Query-query berguna untuk monitoring dan troubleshooting
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Ringkasan jumlah per status
-- -----------------------------------------------------------------------------
-- SELECT status, COUNT(*) AS jumlah
-- FROM log_kirim_dicom_satusehat
-- GROUP BY status;

-- -----------------------------------------------------------------------------
-- 2. Daftar study yang GAGAL terkirim beserta pesan error
-- -----------------------------------------------------------------------------
-- SELECT id, study_orthanc_id, no_rontgen, nama_pasien, modality,
--        retry_count, pesan_error, waktu_masuk
-- FROM log_kirim_dicom_satusehat
-- WHERE status = 'GAGAL'
-- ORDER BY waktu_masuk DESC;

-- -----------------------------------------------------------------------------
-- 3. Study yang berhasil TERKIRIM hari ini
-- -----------------------------------------------------------------------------
-- SELECT id, study_orthanc_id, no_rontgen, nama_pasien, modality,
--        jumlah_instance, waktu_masuk, waktu_kirim
-- FROM log_kirim_dicom_satusehat
-- WHERE status = 'TERKIRIM'
--   AND DATE(waktu_kirim) = CURDATE()
-- ORDER BY waktu_kirim DESC;

-- -----------------------------------------------------------------------------
-- 4. JOIN dengan order_out — lihat status lengkap per nomor rontgen
-- -----------------------------------------------------------------------------
-- SELECT
--     l.no_rontgen,
--     l.nama_pasien,
--     l.modality,
--     l.jumlah_instance,
--     l.status             AS status_kirim_dicom,
--     l.retry_count,
--     l.waktu_masuk,
--     l.waktu_kirim,
--     o.statusupdate       AS statusupdate_order_out,
--     o.expertise_finding,
--     o.dokter_radiolog
-- FROM log_kirim_dicom_satusehat l
-- LEFT JOIN order_out o ON l.no_rontgen = o.no_rontgen
-- ORDER BY l.waktu_masuk DESC
-- LIMIT 100;

-- -----------------------------------------------------------------------------
-- 5. Study PENDING yang belum terkirim lebih dari 1 jam (perlu investigasi)
-- -----------------------------------------------------------------------------
-- SELECT id, study_orthanc_id, no_rontgen, nama_pasien, modality,
--        waktu_masuk, TIMESTAMPDIFF(MINUTE, waktu_masuk, NOW()) AS menit_tunggu
-- FROM log_kirim_dicom_satusehat
-- WHERE status = 'PENDING'
--   AND waktu_masuk < NOW() - INTERVAL 1 HOUR
-- ORDER BY waktu_masuk ASC;

-- -----------------------------------------------------------------------------
-- 6. Statistik harian — jumlah terkirim per hari (7 hari terakhir)
-- -----------------------------------------------------------------------------
-- SELECT DATE(waktu_kirim) AS tanggal,
--        COUNT(*) AS jumlah_terkirim,
--        SUM(jumlah_instance) AS total_instance
-- FROM log_kirim_dicom_satusehat
-- WHERE status = 'TERKIRIM'
--   AND waktu_kirim >= NOW() - INTERVAL 7 DAY
-- GROUP BY DATE(waktu_kirim)
-- ORDER BY tanggal DESC;
