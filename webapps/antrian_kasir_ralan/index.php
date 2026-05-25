<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Antrian Panggil Pasien</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    background: #0a0a1a;
    color: #fff;
    font-family: 'Segoe UI', Arial, sans-serif;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  /* Header */
  #header {
    background: #0d2d6e;
    padding: 14px 30px;
    display: flex;
    align-items: center;
    gap: 16px;
    border-bottom: 3px solid #1a5fd9;
    flex-shrink: 0;
  }
  #header h1 {
    font-size: 1.3rem;
    font-weight: 600;
    letter-spacing: 1px;
  }
  #display-badge {
    background: #1a5fd9;
    color: #fff;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
    margin-left: auto;
  }

  /* Main call area */
  #main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    gap: 10px;
  }

  #label-nomor-reg {
    font-size: 1.2rem;
    color: #8ab4f8;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 6px;
  }

  #nomor-reg {
    font-size: 5rem;
    font-weight: 900;
    color: #ffd600;
    letter-spacing: 4px;
    line-height: 1;
    text-shadow: 0 0 30px rgba(255,214,0,0.4);
  }

  #nama-pasien {
    font-size: 3.2rem;
    font-weight: 700;
    color: #ffffff;
    text-align: center;
    margin-top: 10px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.5);
  }

  #label-poli {
    font-size: 1rem;
    color: #8ab4f8;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 16px;
  }

  #nm-poli {
    font-size: 2.2rem;
    font-weight: 600;
    color: #69ff47;
    text-align: center;
    text-shadow: 0 0 20px rgba(105,255,71,0.35);
  }

  /* Idle text */
  #idle-text {
    font-size: 1.4rem;
    color: #444;
    display: none;
  }

  /* Divider */
  #divider {
    width: 90%;
    height: 2px;
    background: #1a3a6e;
    flex-shrink: 0;
    margin: 0 auto;
  }

  /* History */
  #history-section {
    flex-shrink: 0;
    padding: 12px 30px 16px;
    max-height: 180px;
    overflow: hidden;
  }
  #history-title {
    font-size: 0.85rem;
    color: #556;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  #history-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  #history-list li {
    display: flex;
    gap: 14px;
    font-size: 0.92rem;
    color: #aaa;
    padding: 5px 10px;
    background: #111;
    border-radius: 6px;
    border-left: 3px solid #1a5fd9;
  }
  #history-list li .h-reg  { color: #ffd600; font-weight: 600; min-width: 80px; }
  #history-list li .h-nama { flex: 1; }
  #history-list li .h-poli { color: #69ff47; min-width: 160px; text-align: right; }

  /* Pulse animation */
  @keyframes pulseYellow {
    0%   { text-shadow: 0 0 20px rgba(255,214,0,0.4); }
    50%  { text-shadow: 0 0 60px rgba(255,214,0,0.9); }
    100% { text-shadow: 0 0 20px rgba(255,214,0,0.4); }
  }
  .announcing #nomor-reg { animation: pulseYellow 0.8s ease-in-out 3; }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .fadein { animation: fadeIn 0.5s ease forwards; }
</style>
</head>
<body>

<div id="header">
  <h1>&#128266; ANTRIAN PANGGIL PASIEN</h1>
  <span id="display-badge">DISPLAY: <?php
    $disp = isset($_GET['display']) ? htmlspecialchars(strtoupper(trim($_GET['display'])), ENT_QUOTES) : '-';
    require_once('../conf/conf.php');
    $kon = bukakoneksi();
    $dres = mysqli_query($kon, "SELECT nm_display FROM antrian_display WHERE kd_display='" . mysqli_real_escape_string($kon, (isset($_GET['display'])?trim($_GET['display']):'')) . "' LIMIT 1");
    if ($dres && mysqli_num_rows($dres) > 0) {
        $drow = mysqli_fetch_assoc($dres);
        echo htmlspecialchars($drow['nm_display'], ENT_QUOTES);
    } else {
        echo $disp;
    }
    mysqli_close($kon);
  ?></span>
</div>

<div id="main">
  <div id="idle-text">&#9201; Menunggu panggilan...</div>

  <div id="call-area" style="display:none; text-align:center;">
    <div id="label-nomor-reg">NOMOR REGISTRASI</div>
    <div id="nomor-reg"></div>
    <div id="nama-pasien"></div>
    <div id="label-poli">SILAHKAN MENUJU</div>
    <div id="nm-poli"></div>
  </div>
</div>

<div id="divider"></div>

<div id="history-section">
  <div id="history-title">&#128221; Riwayat Panggilan</div>
  <ul id="history-list"></ul>
</div>

<script>
(function () {
  'use strict';

  var displayParam = new URLSearchParams(window.location.search).get('display') || '';
  var history = [];
  var speaking = false;
  var speechQueue = [];

  function getCallArea()  { return document.getElementById('call-area'); }
  function getIdleText()  { return document.getElementById('idle-text'); }
  function getNoReg()     { return document.getElementById('nomor-reg'); }
  function getNama()      { return document.getElementById('nama-pasien'); }
  function getNmPoli()    { return document.getElementById('nm-poli'); }
  function getHistList()  { return document.getElementById('history-list'); }
  var mainDiv = document.getElementById('main');

  // Show idle on load
  getIdleText().style.display = 'block';

  function updateDisplay(data) {
    getIdleText().style.display = 'none';
    var area = getCallArea();
    area.style.display = 'block';
    area.classList.remove('fadein');
    void area.offsetWidth; // reflow
    area.classList.add('fadein');

    getNoReg().textContent  = data.no_reg;
    getNama().textContent   = data.nm_pasien;
    getNmPoli().textContent = data.nm_poli;

    mainDiv.classList.add('announcing');
    setTimeout(function () { mainDiv.classList.remove('announcing'); }, 2500);

    // Add to history (max 5)
    history.unshift(data);
    if (history.length > 5) history.pop();
    renderHistory();
  }

  function renderHistory() {
    var ul = getHistList();
    ul.innerHTML = '';
    history.forEach(function (d) {
      var li = document.createElement('li');
      li.innerHTML = '<span class="h-reg">' + esc(d.no_reg) + '</span>' +
                     '<span class="h-nama">' + esc(d.nm_pasien) + '</span>' +
                     '<span class="h-poli">' + esc(d.nm_poli) + '</span>';
      ul.appendChild(li);
    });
  }

  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  /* ---- Web Speech API ---- */
  function speak(text, onEnd) {
    if (!window.speechSynthesis) { if (onEnd) onEnd(); return; }
    var utt = new SpeechSynthesisUtterance(text);
    utt.lang  = 'id-ID';
    utt.rate  = 0.88;
    utt.pitch = 1.0;
    utt.volume = 1.0;
    utt.onend = function () { if (onEnd) onEnd(); };
    utt.onerror = function () { if (onEnd) onEnd(); };
    window.speechSynthesis.speak(utt);
  }

  function announcePatient(data) {
    var text = 'Nomor registrasi ' + data.no_reg + ', ' +
               data.nm_pasien + ', silahkan menuju ' + data.nm_poli;
    var count = 0;
    function repeat() {
      if (count >= 3) return;
      count++;
      speak(text, function () {
        if (count < 3) {
          setTimeout(repeat, 800);
        }
      });
    }
    repeat();
  }

  /* ---- Polling ---- */
  function poll() {
    if (!displayParam) return;
    fetch('data.php?display=' + encodeURIComponent(displayParam) + '&_=' + Date.now())
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.status === 'ok') {
          updateDisplay(d);
          announcePatient(d);
        }
      })
      .catch(function () { /* network error - ignore */ });
  }

  // Unlock audio context on first click (required by browser autoplay policy)
  document.addEventListener('click', function () {
    if (window.speechSynthesis) {
      window.speechSynthesis.cancel();
    }
  }, { once: true });

  setInterval(poll, 3000);
  poll(); // immediate first check
})();
</script>

</body>
</html>
