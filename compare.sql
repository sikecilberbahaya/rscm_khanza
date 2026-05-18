

































CREATE ALGORITHM = UNDEFINED DEFINER = `yaneka`@`%` SQL SECURITY DEFINER VIEW `sik2`.`v_barang_inventaris_per_lokasi` AS select `bi`.`no_inventaris` AS `no_inventaris`,`b`.`kd_brng` AS `kd_brng`,`b`.`nm_brng` AS `nm_brng`,`bi`.`no_seri` AS `no_seri`,`bi`.`id_ruang` AS `id_ruang`,`r`.`nama_ruang` AS `lokasi`,`bi`.`tgl_penempatan` AS `tgl_penempatan`,`bi`.`kondisi` AS `kondisi`,`k`.`nama_kategori` AS `nama_kategori`,`j`.`nama_jenis` AS `nama_jenis`,`m`.`nama_merk` AS `nama_merk`,`b`.`harga_beli` AS `harga_beli` from (((((`inv_barang_inventaris` `bi` join `inv_barang` `b` on(`bi`.`kd_brng` = `b`.`kd_brng`)) left join `inventaris_ruang` `r` on(`bi`.`id_ruang` = `r`.`id_ruang`)) left join `inventaris_kategori` `k` on(`b`.`kd_kategori` = `k`.`id_kategori`)) left join `inventaris_jenis` `j` on(`b`.`kd_jenis` = `j`.`id_jenis`)) left join `inventaris_merk` `m` on(`b`.`kd_merk` = `m`.`id_merk`));

CREATE ALGORITHM = UNDEFINED DEFINER = `yaneka`@`%` SQL SECURITY DEFINER VIEW `sik2`.`v_laporan_barang_inventaris` AS select `b`.`kd_brng` AS `kd_brng`,`b`.`nm_brng` AS `nm_brng`,`b`.`keterangan` AS `keterangan`,`k`.`nama_kategori` AS `nama_kategori`,`j`.`nama_jenis` AS `nama_jenis`,`m`.`nama_merk` AS `nama_merk`,`b`.`harga_beli` AS `harga_beli`,ifnull(`s`.`jumlah_stock`,0) AS `jumlah_stock`,ifnull(`s`.`jumlah_stock`,0) * `b`.`harga_beli` AS `nilai_stock` from ((((`inv_barang` `b` left join `inv_stock_inventaris` `s` on(`b`.`kd_brng` = `s`.`kd_brng`)) left join `inventaris_kategori` `k` on(`b`.`kd_kategori` = `k`.`id_kategori`)) left join `inventaris_jenis` `j` on(`b`.`kd_jenis` = `j`.`id_jenis`)) left join `inventaris_merk` `m` on(`b`.`kd_merk` = `m`.`id_merk`));

CREATE ALGORITHM = UNDEFINED DEFINER = `yaneka`@`%` SQL SECURITY DEFINER VIEW `sik2`.`v_laporan_stock_bhp` AS select `b`.`kd_brng` AS `kd_brng`,`b`.`nm_brng` AS `nm_brng`,`b`.`keterangan` AS `keterangan`,`k`.`nama_kategori` AS `nama_kategori`,`s`.`satuan` AS `satuan`,`b`.`stok_minimal` AS `stok_minimal`,`b`.`stok_saat_ini` AS `stok_saat_ini`,`b`.`harga_beli` AS `harga_beli`,`b`.`stok_saat_ini` * `b`.`harga_beli` AS `nilai_stock`,case when `b`.`stok_saat_ini` <= 0 then 'kosong' when `b`.`stok_saat_ini` <= `b`.`stok_minimal` then 'rendah' else 'aman' end AS `status_stok` from ((`inv_barang_bhp` `b` left join `inventaris_kategori` `k` on(`b`.`kd_kategori` = `k`.`id_kategori`)) left join `kodesatuan` `s` on(`b`.`id_satuan` = `s`.`kode_sat`));

CREATE ALGORITHM = UNDEFINED DEFINER = `yaneka`@`%` SQL SECURITY DEFINER VIEW `sik2`.`v_laporan_stock_inventaris` AS select `b`.`kd_brng` AS `kd_brng`,`b`.`nm_brng` AS `nm_brng`,`k`.`nama_kategori` AS `nama_kategori`,`j`.`nama_jenis` AS `nama_jenis`,`m`.`nama_merk` AS `nama_merk`,`b`.`harga_beli` AS `harga_beli`,ifnull(`s`.`jumlah_stock`,0) AS `jumlah_stock`,ifnull(`s`.`jumlah_stock`,0) * `b`.`harga_beli` AS `nilai_stock` from ((((`inv_barang` `b` left join `inv_stock_inventaris` `s` on(`b`.`kd_brng` = `s`.`kd_brng`)) left join `inventaris_kategori` `k` on(`b`.`kd_kategori` = `k`.`id_kategori`)) left join `inventaris_jenis` `j` on(`b`.`kd_jenis` = `j`.`id_jenis`)) left join `inventaris_merk` `m` on(`b`.`kd_merk` = `m`.`id_merk`));

DROP TABLE IF EXISTS `sik2`.`antrian_loket`;

DROP TABLE IF EXISTS `sik2`.`antripendaftaran`;

DROP TABLE IF EXISTS `sik2`.`antripendaftaran_nomor`;



DROP TABLE IF EXISTS `sik2`.`satu_sehat_episode_of_care`;

DROP TABLE IF EXISTS `sik2`.`satu_sehat_imagingstudy_radiologi`;



DROP TABLE IF EXISTS `sik2`.`tracking_bpjs_sep`;

DROP TABLE IF EXISTS `sik2`.`tracking_bpjs_surat_kontrol`;

