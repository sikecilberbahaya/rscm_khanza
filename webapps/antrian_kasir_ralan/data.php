<?php
/**
 * Polling endpoint - Panggil Pasien Kasir Rawat Jalan
 * GET ?display=D01
 * Returns JSON: {status:'ok', id, no_reg, nm_pasien, nm_poli} or {status:'empty'}
 */
require_once('../conf/conf.php');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$kd_display = isset($_GET['display']) ? validTeks(trim($_GET['display'])) : '';

if ($kd_display === '') {
    echo json_encode(['status' => 'error', 'message' => 'Parameter display diperlukan']);
    exit;
}

$konektor = bukakoneksi();

$sql = "SELECT id, no_reg, nm_pasien, nm_poli
        FROM antrian_panggil_ralan
        WHERE kd_display = '" . mysqli_real_escape_string($konektor, $kd_display) . "'
          AND sudah_tampil = 0
        ORDER BY id ASC
        LIMIT 1";

$result = mysqli_query($konektor, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);

    // Mark as displayed
    $id = (int)$row['id'];
    mysqli_query($konektor, "UPDATE antrian_panggil_ralan SET sudah_tampil = 1 WHERE id = $id");

    echo json_encode([
        'status'    => 'ok',
        'id'        => $id,
        'no_reg'    => $row['no_reg'],
        'nm_pasien' => $row['nm_pasien'],
        'nm_poli'   => $row['nm_poli']
    ]);
} else {
    echo json_encode(['status' => 'empty']);
}

mysqli_close($konektor);
