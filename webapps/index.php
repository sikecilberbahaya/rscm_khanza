<?php
    header("X-Robots-Tag: noindex", true);
    session_start();
    require_once('conf/conf.php');
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
    header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT"); 
    header("Cache-Control: no-store, no-cache, must-revalidate"); 
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    $action      = isset($_GET['aksi'])?$_GET['aksi']:NULL;
    if($action=="Keluar"){
        session_start();
        $_SESSION["ses_admin_login"]=null;
        unset($_SESSION["ses_admin_login"]); 
        session_destroy();
        exit(header("Location:index.php"));
    }
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edukasi, Konfirmasi & Persetujuan</title>
    <link href="css/login.css" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="conf/validator.js"></script>
    <script>
        function PopupCenter(pageURL, title,w,h) {
            var left = (screen.width/2)-(w/2);
            var top = (screen.height/2)-(h/2);
            var targetWin = window.open (pageURL, title, 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width='+w+', height='+h+', top='+top+', left='+left);        
        }
    </script>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .dashboard-grid a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 12px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            text-decoration: none;
            color: #334155;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.25s ease;
            text-align: center;
            min-height: 110px;
        }
        .dashboard-grid a:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }
        .dashboard-grid a img {
            width: 36px;
            height: 36px;
            margin-bottom: 8px;
            opacity: 0.7;
        }
        .dashboard-grid a:hover img {
            opacity: 1;
        }
        .dashboard-grid a.logout {
            border-color: #fecaca;
            color: #dc2626;
        }
        .dashboard-grid a.logout:hover {
            background: #fef2f2;
            border-color: #fca5a5;
        }
        .dashboard-grid a.logout img {
            opacity: 0.6;
        }
        .dashboard-card {
            max-width: 720px;
        }
    </style>
</head>
<body>
    <div class="bg-decoration">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    <?php 
       $sesilogin=isset($_SESSION['ses_admin_login'])?$_SESSION['ses_admin_login']:NULL;
       if ($sesilogin==USERHYBRIDWEB.PASHYBRIDWEB){
            echo "
            <div class=\"login-container\">
                <div class=\"login-card dashboard-card\">
                    <div class=\"login-header\">
                        <div class=\"login-icon\">
                            <svg width=\"28\" height=\"28\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">
                                <path d=\"M22 12h-4l-3 9L9 3l-3 9H2\"/>
                            </svg>
                        </div>
                        <h1>Edukasi, Konfirmasi & Persetujuan</h1>
                        <p>Pilih menu dibawah</p>
                    </div>
                    <div style='width: 100%; overflow: auto;'> 
                        <div class=\"dashboard-grid\">
                            <a target=_blank href=persetujuanumum/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/5868931_architecture_building_coronavirus_hospital_corona_icon.png'/>Persetujuan Umum                                                  
                            </a>
                            <a target=_blank href=persetujuantindakan/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/6771569_education_learning_pencil_school_signature_icon.png'/>Persetujuan/Penolakan Tindakan                                                  
                            </a>
                            <a target=_blank href=perencanaanpemulangan/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/6141469_coronavirus_covid_covid19_hospital_infected_icon.png'/>Perencanaan Pemulangan Pasien                                                  
                            </a>
                            <a target=_blank href=penyerahanresep/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/1360485894_add-notes.png'/>Penyerahan Resep Rawat Jalan                                                 
                            </a>
                            <a target=_blank href=pernyataanumum/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/Edit-Male-User.png'/>Pernyataan Pasien Umum                                                  
                            </a>
                            <a target=_blank href=pulangaps/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/5947112_clinic_doctor_healthcare_hospital_medical_icon.png'/>Pernyataan Pulang Atas Permintaan Sendiri                                                 
                            </a>
                            <a target=_blank href=persetujuantransferruang/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/6009596_case_coronavirus_covid19_hospital_patient_icon.png'/>Persetujuan Transfer Antar Ruang                                                
                            </a>
                            <a target=_blank href=persetujuanrawatinap/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/5983455_bed_hospital_medical_patient_icon.png'/>Persetujuan Rawat Inap                                                
                            </a>
                            <a target=_blank href=penundaanpelayanan/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/9160890_clock_commerce_shopping_online_store_icon.png'/>Persetujuan Penundaan Pelayanan                                                
                            </a>
                            <a target=_blank href=penolakananjuranmedis/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/8960611_hospitals_hospital_building_medic_health_icon.png'/>Penolakan Anjuran Medis                                               
                            </a>
                            <a target=_blank href=pengkajianrestrain/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/3841816_chain_hyperlink_interface_link_multimedia_icon.png'/>Persetujuan Restrain                                               
                            </a>
                            <a target=_blank href=pelaksanaanedukasi/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/11211449_book_library_learning_knowledge_education_icon.png'/>Bukti Pelaksanaan Informasi & Edukasi                                               
                            </a>
                            <a target=_blank href=layanankedokteranfisikrehabilitasi/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/4082071_healthcare_hospital_medical_icon.png'/>Bukti Pelayanan Kedokteran Fisik & Rehabilitasi                                               
                            </a>
                            <a target=_blank href=layananprogramkfr/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/8960631_crutches_crutch_orthopedic_physiotherapy_rehabilitation_icon.png'/>Bukti Pelayanan Program KFR                                            
                            </a>
                            <a target=_blank href=persetujuanpemeriksaanhiv/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/6217201_corona_coronavirus_test_tube_virus_icon.png'/>Bukti Persetujuan Pemeriksaan HIV                                           
                            </a>
                            <a target=_blank href=pernyataanmemilihdpjp/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/5898997_avatar_doctor_man_mask_user_icon.png'/>Pernyataan Memilih DPJP                                          
                            </a>
                            <a target=_blank href=pernyataanmenerimabarang/login.php?iyem=".encrypt_decrypt("{\"usere\":\"".USERHYBRIDWEB."\",\"passwordte\":\"".PASHYBRIDWEB."\"}","e").">                                                 
                               <img src='images/file-manager.png'/>Serah Terima Anggota Tubuh/Barang                                          
                            </a>
                            <a href='?aksi=Keluar' class=\"logout\">                                                 
                               <img src='images/1360484978_application-pgp-signature.png'/>Keluar                                               
                            </a>
                        </div>
                    </div>
                </div>
            </div>";
       }else{
            $BtnLogin=isset($_POST['BtnLogin'])?$_POST['BtnLogin']:NULL;
            if (isset($BtnLogin)) {
                $usere      = validTeks4($_POST['usere'],30);
                $passworde  = validTeks4($_POST['passworde'],30);
                if(getOne("select count(admin.passworde) from admin where admin.usere=aes_encrypt('$usere','nur') and admin.passworde=aes_encrypt('$passworde','windi')")>0){
                    $_SESSION["ses_admin_login"]= USERHYBRIDWEB.PASHYBRIDWEB;
                    exit(header("Location:index.php"));
                }else if(getOne("select count(user.password) from user where user.id_user=aes_encrypt('$usere','nur') and user.password=aes_encrypt('$passworde','windi')")>0){
                    $_SESSION["ses_admin_login"]= USERHYBRIDWEB.PASHYBRIDWEB;
                    exit(header("Location:index.php"));
                }else{
                    echo "<div class=\"login-container\">
                            <div class=\"login-card\">
                                <div class=\"login-header\">
                                    <div class=\"login-icon\">
                                        <svg width=\"28\" height=\"28\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">
                                            <path d=\"M22 12h-4l-3 9L9 3l-3 9H2\"/>
                                        </svg>
                                    </div>
                                    <h1>Edukasi, Konfirmasi & Persetujuan</h1>
                                    <p>Silahkan login untuk melanjutkan</p>
                                </div>
                                <div class=\"alert alert-error\">Login gagal. Username atau password salah...!</div>
                                <form id=\"pengenmasuk-form\" role=\"form\" onsubmit=\"return validasiIsi();\" method=\"post\" action=\"\" enctype=multipart/form-data>
                                    <div class=\"form-group\">
                                        <input type=\"password\" name=\"usere\" class=\"form-control\" pattern=\"[a-zA-Z0-9, ./_]{1,30}\" title=\" a-zA-Z0-9, ./_ (Maksimal 30 karakter)\" required placeholder=\"User Login\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi1'));\" id=\"TxtIsi1\" autocomplete=\"off\" maxlength=\"30\" autofocus/>
                                        <span id=\"MsgIsi1\" class=\"error-msg\"></span>
                                    </div>
                                    <div class=\"form-group\">
                                        <input type=\"password\" name=\"passworde\" class=\"form-control\" pattern=\"[a-zA-Z0-9, ./_]{1,30}\" title=\" a-zA-Z0-9, ./_ (Maksimal 30 karakter)\" required placeholder=\"Password\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi2'));\" id=\"TxtIsi2\" autocomplete=\"off\" maxlength=\"30\"/>
                                        <span id=\"MsgIsi2\" class=\"error-msg\"></span>
                                    </div>
                                    <div class=\"btn-group\">
                                        <button name=\"BtnLogin\" type=\"submit\" class=\"btn-login\">Log In</button>
                                        <button name=\"BtnKosong\" type=\"reset\" class=\"btn-reset\">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </div>";
                }
            }else{
                echo "<div class=\"login-container\">
                        <div class=\"login-card\">
                            <div class=\"login-header\">
                                <div class=\"login-icon\">
                                    <svg width=\"28\" height=\"28\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">
                                        <path d=\"M22 12h-4l-3 9L9 3l-3 9H2\"/>
                                    </svg>
                                </div>
                                <h1>Edukasi, Konfirmasi & Persetujuan</h1>
                                <p>Silahkan login untuk melanjutkan</p>
                            </div>
                            <form id=\"pengenmasuk-form\" role=\"form\" onsubmit=\"return validasiIsi();\" method=\"post\" action=\"\" enctype=multipart/form-data>
                                <div class=\"form-group\">
                                    <input type=\"password\" name=\"usere\" class=\"form-control\" pattern=\"[a-zA-Z0-9, ./_]{1,30}\" title=\" a-zA-Z0-9, ./_ (Maksimal 30 karakter)\" required placeholder=\"User Login\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi1'));\" id=\"TxtIsi1\" autocomplete=\"off\" maxlength=\"30\" autofocus/>
                                    <span id=\"MsgIsi1\" class=\"error-msg\"></span>
                                </div>
                                <div class=\"form-group\">
                                    <input type=\"password\" name=\"passworde\" class=\"form-control\" pattern=\"[a-zA-Z0-9, ./_]{1,30}\" title=\" a-zA-Z0-9, ./_ (Maksimal 30 karakter)\" required placeholder=\"Password\" onkeydown=\"setDefault(this, document.getElementById('MsgIsi2'));\" id=\"TxtIsi2\" autocomplete=\"off\" maxlength=\"30\"/>
                                    <span id=\"MsgIsi2\" class=\"error-msg\"></span>
                                </div>
                                <div class=\"btn-group\">
                                    <button name=\"BtnLogin\" type=\"submit\" class=\"btn-login\">Log In</button>
                                    <button name=\"BtnKosong\" type=\"reset\" class=\"btn-reset\">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>";
            }      
       }
    ?>
</body>
</html>
