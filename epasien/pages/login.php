<?php
    if(strpos($_SERVER['REQUEST_URI'],"pages")){
        exit(header("Location:../index.php"));
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In Pasien</title>
    <link rel="stylesheet" type="text/css" href="../../webapps/css/login.css">
    <link rel="stylesheet" type="text/css" href="capca.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <h1>Log In Pasien</h1>
                <p>Masukkan nomor rekam medis dan password Anda</p>
            </div>
            <div class="login-info">
                Jika anda pasien lama atau pernah berobat sebelumnya, untuk nomor rekam medis dan password login bisa Anda tanyakan kepada petugas Kami saat Anda melakukan registrasi secara offline. Dan password bisa Anda ubah setelah login di aplikasi EPasien. Jika Anda pasien baru dan belum pernah periksa sebelumnya, silahkan melakukan booking atau buat janji melalui menu utama EPasien ini. Setelah admin kami melakukan verifikasi data, Anda akan mendapat password login dan antrian periksa sesuai booking Anda.
            </div>
            <?php 
                $BtnLogin=isset($_POST['BtnLogin'])?$_POST['BtnLogin']:NULL;
                if (isset($BtnLogin)) {
                    if(@$_SESSION["Capcay"]!= getOne2("select aes_encrypt(".cleankar($_POST["inputcaptcha"]).",'windi')")){
                        echo "<form id=\"appointment-form\" role=\"form\" onsubmit=\"return validasiIsi();\" method=\"post\" action=\"\" enctype=multipart/form-data>
                                    <div class=\"alert alert-error\">Captcha tidak sesuai, silahkan ulangi ...!</div>
                                    <div class=\"form-group\">
                                        <label for=\"norme\">Nomer Rekam Medis</label>
                                        <input type=\"password\" class=\"form-control\" name=\"norme\" pattern=\"[A-Z0-9-]{1,65}\" title=\" A-Z0-9- (Maksimal 65 karakter)\" required placeholder=\"Masukkan Nomor Rekam Medis\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi1'));\" id=\"TxtIsi1\" autocomplete=\"off\" autofocus/>
                                        <span id=\"MsgIsi1\" class=\"error-msg\"></span>
                                    </div>
                                    <div class=\"form-group\">
                                        <label for=\"passworde\">Password</label>
                                        <input type=\"password\" class=\"form-control\" name=\"passworde\" required placeholder=\"Masukkan Password\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi2'));\" id=\"TxtIsi2\" autocomplete=\"off\" />
                                        <span id=\"MsgIsi2\" class=\"error-msg\"></span>
                                    </div>
                                    <div class=\"form-group\">
                                        <label for=\"captcha\">Captcha</label>
                                        <div class=\"captcha-row\">
                                            <img src=\"pages/captcha.php\" alt=\"gambar\" />
                                            <input type=\"text\" class=\"form-control\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi3'));\" id=\"TxtIsi3\" name=\"inputcaptcha\" pattern=\"[0-9]{1,6}\" title=\" 0-9 (Maksimal 6 karakter)\" required placeholder=\"Masukkan Captcha\" autocomplete=\"off\" />
                                        </div>
                                        <span id=\"MsgIsi3\" class=\"error-msg\"></span>
                                    </div>
                                    <button type=\"submit\" class=\"btn-login\" id=\"cf-submit\" name=\"BtnLogin\">Log In</button>
                               </form>";
                    }else{
                        unset($_SESSION['Capcay']);
                        $usere      = cleankar($_POST['norme']);
                        $passworde  = validTeks($_POST['passworde']);
                        if(strlen($usere)>30){
                            header('Location: https://www.google.com');
                        }else{
                            if(getOne2("select count(*) from personal_pasien where md5(no_rkm_medis)=md5('$usere') and password=aes_encrypt('$passworde','windi')")>0){
                                $_SESSION["ses_pasien"]= encrypt_decrypt($usere,"e");
                                exit(header("Location:index.php"));
                            }else{
                                echo "<form id=\"appointment-form\" role=\"form\" onsubmit=\"return validasiIsi();\" method=\"post\" action=\"\" enctype=multipart/form-data>
                                            <div class=\"alert alert-error\">Maaf, gagal login. Nomor rekam medis atau password ada yang salah ...!</div>
                                            <div class=\"form-group\">
                                                <label for=\"norme\">Nomer Rekam Medis</label>
                                                <input type=\"password\" class=\"form-control\" name=\"norme\" pattern=\"[A-Z0-9-]{1,65}\" title=\" A-Z0-9- (Maksimal 65 karakter)\" required placeholder=\"Masukkan Nomor Rekam Medis\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi1'));\" id=\"TxtIsi1\" autocomplete=\"off\" autofocus/>
                                                <span id=\"MsgIsi1\" class=\"error-msg\"></span>
                                            </div>
                                            <div class=\"form-group\">
                                                <label for=\"passworde\">Password</label>
                                                <input type=\"password\" class=\"form-control\" name=\"passworde\" required placeholder=\"Masukkan Password\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi2'));\" id=\"TxtIsi2\" autocomplete=\"off\" />
                                                <span id=\"MsgIsi2\" class=\"error-msg\"></span>
                                            </div>
                                            <div class=\"form-group\">
                                                <label for=\"captcha\">Captcha</label>
                                                <div class=\"captcha-row\">
                                                    <img src=\"pages/captcha.php\" alt=\"gambar\" />
                                                    <input type=\"text\" class=\"form-control\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi3'));\" id=\"TxtIsi3\" name=\"inputcaptcha\" pattern=\"[0-9]{1,6}\" title=\" 0-9 (Maksimal 6 karakter)\" required placeholder=\"Masukkan Captcha\" autocomplete=\"off\" />
                                                </div>
                                                <span id=\"MsgIsi3\" class=\"error-msg\"></span>
                                            </div>
                                            <button type=\"submit\" class=\"btn-login\" id=\"cf-submit\" name=\"BtnLogin\">Log In</button>
                                       </form>";
                            }
                        }
                    }
                }else{
                    echo "<form id=\"appointment-form\" role=\"form\" onsubmit=\"return validasiIsi();\" method=\"post\" action=\"\" enctype=multipart/form-data>
                                <div class=\"form-group\">
                                    <label for=\"norme\">Nomer Rekam Medis</label>
                                    <input type=\"password\" class=\"form-control\" name=\"norme\" pattern=\"[A-Z0-9-]{1,65}\" title=\" A-Z0-9- (Maksimal 65 karakter)\" required placeholder=\"Masukkan Nomor Rekam Medis\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi1'));\" id=\"TxtIsi1\" autocomplete=\"off\" autofocus/>
                                    <span id=\"MsgIsi1\" class=\"error-msg\"></span>
                                </div>
                                <div class=\"form-group\">
                                    <label for=\"passworde\">Password</label>
                                    <input type=\"password\" class=\"form-control\" name=\"passworde\" required placeholder=\"Masukkan Password\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi2'));\" id=\"TxtIsi2\" autocomplete=\"off\" />
                                    <span id=\"MsgIsi2\" class=\"error-msg\"></span>
                                </div>
                                <div class=\"form-group\">
                                    <label for=\"captcha\">Captcha</label>
                                    <div class=\"captcha-row\">
                                        <img src=\"pages/captcha.php\" alt=\"gambar\" />
                                        <input type=\"text\" class=\"form-control\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi3'));\" id=\"TxtIsi3\" name=\"inputcaptcha\" pattern=\"[0-9]{1,6}\" title=\" 0-9 (Maksimal 6 karakter)\" required placeholder=\"Masukkan Captcha\" autocomplete=\"off\" />
                                    </div>
                                    <span id=\"MsgIsi3\" class=\"error-msg\"></span>
                                </div>
                                <button type=\"submit\" class=\"btn-login\" id=\"cf-submit\" name=\"BtnLogin\">Log In</button>
                           </form>";
                }
            ?>
        </div>
    </div>
</body>
</html>
