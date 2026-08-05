-- Update struktur tabel telaah_farmasi untuk fitur validator ke-2 telaah resep

ALTER TABLE telaah_farmasi
    ADD COLUMN IF NOT EXISTS nip2 VARCHAR(20) NULL AFTER nip,
    ADD COLUMN IF NOT EXISTS status_validasi2 ENUM('Belum','Sesuai','Tidak Sesuai') NOT NULL DEFAULT 'Belum' AFTER nip2,
    ADD COLUMN IF NOT EXISTS catatan_validasi2 TEXT NULL AFTER status_validasi2,
    ADD COLUMN IF NOT EXISTS tgl_validasi2 DATETIME NULL AFTER catatan_validasi2;

-- Jaga data lama agar status validasi ke-2 tetap konsisten
UPDATE telaah_farmasi
SET status_validasi2 = 'Belum'
WHERE status_validasi2 IS NULL OR status_validasi2 = '';
