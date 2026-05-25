<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Mapping Display Panggil Pasien</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; color: #333; }

  .top-bar {
    background: #0d2d6e; color: #fff;
    padding: 14px 24px;
    font-size: 1.1rem; font-weight: 600;
    letter-spacing: 0.5px;
  }

  .container { max-width: 1000px; margin: 30px auto; padding: 0 16px; display: flex; flex-direction: column; gap: 28px; }

  .card {
    background: #fff; border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
  }
  .card-header {
    background: #0d2d6e; color: #fff;
    padding: 12px 20px; font-size: 0.95rem; font-weight: 600;
  }
  .card-body { padding: 20px; }

  table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  th { background: #e8edf5; padding: 10px 12px; text-align: left; font-weight: 600; }
  td { padding: 9px 12px; border-top: 1px solid #eee; vertical-align: middle; }
  tr:hover td { background: #f9fafc; }

  .btn {
    display: inline-block; padding: 6px 14px; border-radius: 4px;
    cursor: pointer; font-size: 0.85rem; border: none; text-decoration: none;
  }
  .btn-danger  { background: #dc3545; color: #fff; }
  .btn-primary { background: #0d2d6e; color: #fff; }
  .btn-success { background: #28a745; color: #fff; }
  .btn:hover { opacity: 0.87; }

  form.inline-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  input[type=text], select {
    padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px;
    font-size: 0.88rem; min-width: 160px;
  }
  input[type=text]:focus, select:focus { outline: none; border-color: #0d2d6e; }

  .msg { padding: 10px 14px; border-radius: 4px; margin-bottom: 14px; font-size: 0.88rem; }
  .msg-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
  .msg-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

  .badge {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 0.78rem; font-weight: 600;
    background: #e0e7ff; color: #0d2d6e;
  }
  .badge-none { background: #f0f0f0; color: #999; }

  .display-url { font-size: 0.78rem; color: #555; margin-top: 3px; }
  .display-url a { color: #0d2d6e; text-decoration: none; }
  .display-url a:hover { text-decoration: underline; }
</style>
</head>
<body>
<?php
require_once('../conf/conf.php');

$kon = bukakoneksi();
$msg = '';
$msgType = '';

/* ============ HANDLE ACTIONS ============ */

// Tambah display group
if (isset($_POST['act']) && $_POST['act'] === 'add_display') {
    $kd = validTeks(trim($_POST['kd_display'] ?? ''));
    $nm = validTeks(trim($_POST['nm_display'] ?? ''));
    if ($kd === '' || $nm === '') {
        $msg = 'Kode dan Nama Display wajib diisi.'; $msgType = 'err';
    } else {
        $kd_esc = mysqli_real_escape_string($kon, $kd);
        $nm_esc = mysqli_real_escape_string($kon, $nm);
        $chk = mysqli_query($kon, "SELECT kd_display FROM antrian_display WHERE kd_display='$kd_esc'");
        if (mysqli_num_rows($chk) > 0) {
            $msg = "Kode display '$kd' sudah ada."; $msgType = 'err';
        } else {
            mysqli_query($kon, "INSERT INTO antrian_display (kd_display, nm_display) VALUES ('$kd_esc','$nm_esc')");
            $msg = "Display '$nm' berhasil ditambahkan."; $msgType = 'ok';
        }
    }
}

// Hapus display group
if (isset($_POST['act']) && $_POST['act'] === 'del_display') {
    $kd = validTeks(trim($_POST['kd_display'] ?? ''));
    $kd_esc = mysqli_real_escape_string($kon, $kd);
    mysqli_query($kon, "DELETE FROM antrian_poli_display WHERE kd_display='$kd_esc'");
    mysqli_query($kon, "DELETE FROM antrian_display WHERE kd_display='$kd_esc'");
    $msg = 'Display berhasil dihapus (beserta semua mapping poli).'; $msgType = 'ok';
}

// Set mapping poli → display
if (isset($_POST['act']) && $_POST['act'] === 'set_mapping') {
    $kd_poli   = validTeks(trim($_POST['kd_poli']   ?? ''));
    $kd_disp   = validTeks(trim($_POST['kd_display'] ?? ''));
    $kp_esc    = mysqli_real_escape_string($kon, $kd_poli);
    if ($kd_disp === '' || $kd_disp === '-') {
        // Remove mapping
        mysqli_query($kon, "DELETE FROM antrian_poli_display WHERE kd_poli='$kp_esc'");
        $msg = 'Mapping poli dihapus.'; $msgType = 'ok';
    } else {
        $kd_esc = mysqli_real_escape_string($kon, $kd_disp);
        $chk = mysqli_query($kon, "SELECT kd_poli FROM antrian_poli_display WHERE kd_poli='$kp_esc'");
        if (mysqli_num_rows($chk) > 0) {
            mysqli_query($kon, "UPDATE antrian_poli_display SET kd_display='$kd_esc' WHERE kd_poli='$kp_esc'");
        } else {
            mysqli_query($kon, "INSERT INTO antrian_poli_display (kd_poli, kd_display) VALUES ('$kp_esc','$kd_esc')");
        }
        $msg = 'Mapping poli berhasil disimpan.'; $msgType = 'ok';
    }
}

// Hapus riwayat panggilan
if (isset($_POST['act']) && $_POST['act'] === 'clear_riwayat') {
    mysqli_query($kon, "DELETE FROM antrian_panggil_ralan WHERE DATE(waktu_panggil) = CURDATE()");
    $msg = 'Riwayat panggilan hari ini berhasil dihapus.'; $msgType = 'ok';
}

/* ============ FETCH DATA ============ */
$displays = [];
$r = mysqli_query($kon, "SELECT kd_display, nm_display FROM antrian_display ORDER BY nm_display");
while ($row = mysqli_fetch_assoc($r)) { $displays[] = $row; }

$polis = [];
$r2 = mysqli_query($kon, "SELECT p.kd_poli, p.nm_poli, apd.kd_display
                           FROM poliklinik p
                           LEFT JOIN antrian_poli_display apd ON p.kd_poli = apd.kd_poli
                           ORDER BY p.nm_poli");
while ($row = mysqli_fetch_assoc($r2)) { $polis[] = $row; }

$riwayat = [];
$r3 = mysqli_query($kon, "SELECT no_reg, nm_pasien, nm_poli, kd_display, waktu_panggil
                           FROM antrian_panggil_ralan
                           WHERE DATE(waktu_panggil) = CURDATE()
                           ORDER BY id DESC LIMIT 20");
while ($row = mysqli_fetch_assoc($r3)) { $riwayat[] = $row; }
?>

<div class="top-bar">&#128266; Konfigurasi Display Panggil Pasien Rawat Jalan</div>

<div class="container">

<?php if ($msg): ?>
<div class="msg msg-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg, ENT_QUOTES); ?></div>
<?php endif; ?>

<!-- ====== DISPLAY GROUPS ====== -->
<div class="card">
  <div class="card-header">&#128247; Daftar Display Group</div>
  <div class="card-body">

    <form method="post" class="inline-form" style="margin-bottom:16px;">
      <input type="hidden" name="act" value="add_display">
      <input type="text" name="kd_display" placeholder="Kode (mis. D01)" maxlength="10" required>
      <input type="text" name="nm_display" placeholder="Nama (mis. Lantai 1)" maxlength="100" required>
      <button type="submit" class="btn btn-success">+ Tambah Display</button>
    </form>

    <?php if (empty($displays)): ?>
      <p style="color:#999;font-size:.9rem;">Belum ada display. Tambahkan di atas.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Kode</th><th>Nama Display</th><th>URL Display</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($displays as $d): ?>
        <tr>
          <td><span class="badge"><?php echo htmlspecialchars($d['kd_display'], ENT_QUOTES); ?></span></td>
          <td><?php echo htmlspecialchars($d['nm_display'], ENT_QUOTES); ?></td>
          <td class="display-url">
            <?php
              $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
              $host  = $_SERVER['HTTP_HOST'];
              $base  = dirname(dirname($_SERVER['SCRIPT_NAME']));
              $url   = $proto.'://'.$host.$base.'/antrian_kasir_ralan/index.php?display='.urlencode($d['kd_display']);
            ?>
            <a href="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" target="_blank"><?php echo htmlspecialchars($url, ENT_QUOTES); ?></a>
          </td>
          <td>
            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus display ini?');">
              <input type="hidden" name="act" value="del_display">
              <input type="hidden" name="kd_display" value="<?php echo htmlspecialchars($d['kd_display'], ENT_QUOTES); ?>">
              <button type="submit" class="btn btn-danger">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- ====== MAPPING POLI ====== -->
<div class="card">
  <div class="card-header">&#128204; Mapping Poliklinik → Display</div>
  <div class="card-body">
    <?php if (empty($displays)): ?>
      <p style="color:#999;font-size:.9rem;">Tambahkan display group terlebih dahulu.</p>
    <?php else: ?>
    <table>
      <thead><tr><th style="width:45%">Poliklinik</th><th style="width:30%">Display yang Digunakan</th><th>Simpan</th></tr></thead>
      <tbody>
      <?php foreach ($polis as $p): ?>
        <tr>
          <td><?php echo htmlspecialchars($p['nm_poli'], ENT_QUOTES); ?>
              <br><small style="color:#999"><?php echo htmlspecialchars($p['kd_poli'], ENT_QUOTES); ?></small></td>
          <td>
            <?php if ($p['kd_display']): ?>
              <span class="badge"><?php echo htmlspecialchars($p['kd_display'], ENT_QUOTES); ?></span>
            <?php else: ?>
              <span class="badge badge-none">Belum diset</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" class="inline-form">
              <input type="hidden" name="act"     value="set_mapping">
              <input type="hidden" name="kd_poli" value="<?php echo htmlspecialchars($p['kd_poli'], ENT_QUOTES); ?>">
              <select name="kd_display">
                <option value="-">-- Hapus mapping --</option>
                <?php foreach ($displays as $d): ?>
                  <option value="<?php echo htmlspecialchars($d['kd_display'], ENT_QUOTES); ?>"
                    <?php if ($d['kd_display'] === $p['kd_display']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($d['nm_display'].' ('.$d['kd_display'].')', ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- ====== RIWAYAT HARI INI ====== -->
<div class="card">
  <div class="card-header">&#128221; Riwayat Panggilan Hari Ini</div>
  <div class="card-body">
    <form method="post" style="margin-bottom:14px;" onsubmit="return confirm('Hapus semua riwayat hari ini?');">
      <input type="hidden" name="act" value="clear_riwayat">
      <button type="submit" class="btn btn-danger">Hapus Riwayat Hari Ini</button>
    </form>
    <?php if (empty($riwayat)): ?>
      <p style="color:#999;font-size:.9rem;">Belum ada panggilan hari ini.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>No. Reg</th><th>Nama Pasien</th><th>Poliklinik</th><th>Display</th><th>Waktu Panggil</th></tr></thead>
      <tbody>
      <?php foreach ($riwayat as $rv): ?>
        <tr>
          <td><?php echo htmlspecialchars($rv['no_reg'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars($rv['nm_pasien'], ENT_QUOTES); ?></td>
          <td><?php echo htmlspecialchars($rv['nm_poli'], ENT_QUOTES); ?></td>
          <td><span class="badge"><?php echo htmlspecialchars($rv['kd_display'], ENT_QUOTES); ?></span></td>
          <td><?php echo htmlspecialchars($rv['waktu_panggil'], ENT_QUOTES); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</div><!-- /container -->
<?php mysqli_close($kon); ?>
</body>
</html>
