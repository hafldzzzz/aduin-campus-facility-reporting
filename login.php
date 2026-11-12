<?php
require_once __DIR__ . '/config/database.php';
if ($signedIn = currentUser()) {
    $destination = ['admin'=>'admin/dashboard.php','petugas'=>'petugas/dashboard.php','pengguna'=>'user/dashboard.php'];
    header('Location: ' . $destination[$signedIn['role']]);
    exit;
}
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim(postString('email'));
    $password = postString('password');
    if (!rateLimit('login_ip', clientAddress(), 120, 900)
        || !rateLimit('login_account', strtolower($email), 12, 900)) {
        http_response_code(429);
        $error = 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam 15 menit.';
        safeLog('login.throttled');
    } elseif ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } elseif (strlen($email) > 254 || strlen($password) > 4096 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email atau password tidak valid.';
    } else {
        $account = dbOne($conn, 'SELECT id_user,nama,email,password,no_wa,role,fakultas FROM users WHERE email = ? LIMIT 1', 's', [$email]);
        // Valid dummy hash makes an unknown email perform the same expensive verification.
        $hash = $account['password'] ?? password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash);
        if (!$account || !$valid || !in_array($account['role'], ['admin','petugas','pengguna'], true)) {
            $error = 'Email atau password yang Anda masukkan salah. Jika akun lama belum dimigrasikan, hubungi administrator untuk pemulihan akses.';
            safeLog('login.failed');
        } else {
            if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                dbStatement($conn, 'UPDATE users SET password = ? WHERE id_user = ? AND password = ?', 'sis', [$newHash,$account['id_user'],$account['password']]);
                $account['password'] = $newHash;
            }
            session_regenerate_id(true);
            $_SESSION = ['id_user'=>(int)$account['id_user'],'user_id'=>(int)$account['id_user'],
                'auth_version'=>hash('sha256',$account['password']), 'last_activity'=>time()];
            foreach (['nama','email','role','fakultas','no_wa'] as $field) {
                $_SESSION[$field] = $account[$field];
            }
            csrfToken();
            $destination = ['admin'=>'admin/dashboard.php','petugas'=>'petugas/dashboard.php','pengguna'=>'user/dashboard.php'];
            header('Location: ' . $destination[$account['role']], true, 303);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login | ADUIN
    </title>


    <link
        rel="icon"
        type="image/png"
        href="assets/img/logo-aduin.png"
    >


    <link
        rel="stylesheet"
        href="https://unpkg.com/lenis@1.3.26/dist/lenis.css"
    >


    <script nonce="<?= e(cspNonce()) ?>"
        src="https://unpkg.com/lucide@latest"
    ></script>


    <script nonce="<?= e(cspNonce()) ?>"
        src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/gsap.min.js"
    ></script>


<style>

/* =========================================================
   RESET
========================================================= */

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

}


html {

    scroll-behavior: auto;

}


body {

    min-height: 100vh;

    overflow-x: hidden;

    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    color: #0f172a;

    background: #f8fafc;

}


/* =========================================================
   PAGE
========================================================= */

.login-page {

    min-height: 100vh;

    display: flex;

    align-items: stretch;

}


/* =========================================================
   LEFT VISUAL
========================================================= */

.login-visual {

    position: relative;

    width: 50%;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 50px;

    overflow: hidden;

    background:
        linear-gradient(
            145deg,
            #0f5de4 0%,
            #2563eb 48%,
            #3b82f6 100%
        );

}


.login-visual::before {

    content: "";

    position: absolute;

    inset: 0;

    opacity: .16;

    background-image:
        radial-gradient(
            rgba(255,255,255,.75) 1px,
            transparent 1px
        );

    background-size: 26px 26px;

}


.login-visual::after {

    content: "";

    position: absolute;

    width: 460px;

    height: 460px;

    right: -210px;

    bottom: -220px;

    border-radius: 50%;

    border: 70px solid rgba(255,255,255,.08);

}


/* =========================================================
   DECORATION
========================================================= */

.visual-circle {

    position: absolute;

    border-radius: 50%;

    background: rgba(255,255,255,.07);

}


.visual-circle.one {

    width: 210px;

    height: 210px;

    top: -80px;

    left: -70px;

}


.visual-circle.two {

    width: 130px;

    height: 130px;

    right: 15%;

    top: 13%;

}


.visual-circle.three {

    width: 85px;

    height: 85px;

    left: 16%;

    bottom: 12%;

}


/* =========================================================
   VISUAL CONTENT
========================================================= */

.visual-content {

    position: relative;

    z-index: 2;

    width: min(470px, 100%);

    color: #fff;

}


.visual-brand {

    display: flex;

    align-items: center;

    gap: 11px;

    margin-bottom: 48px;

}


.visual-logo {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;

    border-radius: 13px;

    background: rgba(255,255,255,.95);

}


.visual-logo img {

    width: 100%;

    height: 100%;

    object-fit: contain;

}


.visual-brand-name {

    font-size: 18px;

    font-weight: 800;

    line-height: 1.1;

}


.visual-brand-subtitle {

    margin-top: 4px;

    color: rgba(255,255,255,.75);

    font-size: 9px;

}


/* =========================================================
   VISUAL HEADING
========================================================= */

.visual-content h1 {

    max-width: 450px;

    font-size: clamp(
        35px,
        4vw,
        52px
    );

    line-height: 1.06;

    letter-spacing: -1.8px;

    font-weight: 850;

}


.visual-content h1 span {

    color: #dbeafe;

}


.visual-description {

    max-width: 410px;

    margin-top: 20px;

    color: rgba(255,255,255,.76);

    font-size: 13px;

    line-height: 1.75;

}


/* =========================================================
   FEATURES
========================================================= */

.visual-features {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 10px;

    max-width: 410px;

    margin-top: 32px;

}


.visual-feature {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 11px 12px;

    border: 1px solid rgba(255,255,255,.13);

    border-radius: 10px;

    background: rgba(255,255,255,.08);

    backdrop-filter: blur(10px);

}


.visual-feature-icon {

    width: 30px;

    height: 30px;

    min-width: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    color: #fff;

    background: rgba(255,255,255,.12);

}


.visual-feature-icon svg {

    width: 15px;

    height: 15px;

}


.visual-feature span {

    color: rgba(255,255,255,.85);

    font-size: 9px;

    line-height: 1.35;

}


/* =========================================================
   RIGHT FORM SIDE
========================================================= */

.login-form-side {

    width: 50%;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 45px;

    background: #fff;

}


.login-container {

    width: min(410px, 100%);

}


/* =========================================================
   BACK TO HOME
========================================================= */

.back-home {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 28px;

    color: #64748b;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

    transition:
        color .2s ease,
        transform .2s ease;

}


.back-home:hover {

    color: #2563eb;

    transform: translateX(-2px);

}


.back-home svg {

    width: 14px;

    height: 14px;

}


/* =========================================================
   MOBILE BRAND
========================================================= */

.mobile-brand {

    display: none;

}


/* =========================================================
   FORM HEADER
========================================================= */

.form-eyebrow {

    margin-bottom: 7px;

    color: #2563eb;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: .11em;

}


.form-header h2 {

    color: #0f172a;

    font-size: 28px;

    line-height: 1.15;

    letter-spacing: -.7px;

    font-weight: 800;

}


.form-header p {

    margin-top: 8px;

    color: #64748b;

    font-size: 11px;

    line-height: 1.6;

}


/* =========================================================
   ERROR
========================================================= */

.login-error {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    margin-top: 20px;

    padding: 11px 12px;

    color: #b91c1c;

    background: #fef2f2;

    border: 1px solid #fecaca;

    border-radius: 10px;

    font-size: 9px;

    line-height: 1.5;

}


.login-error svg {

    width: 15px;

    height: 15px;

    min-width: 15px;

    margin-top: 1px;

}


/* =========================================================
   FORM
========================================================= */

.login-form {

    margin-top: 25px;

}


.form-group {

    margin-bottom: 17px;

}


.form-label {

    display: block;

    margin-bottom: 7px;

    color: #334155;

    font-size: 9px;

    font-weight: 700;

}


.input-wrapper {

    position: relative;

}


.input-icon {

    position: absolute;

    left: 13px;

    top: 50%;

    width: 16px;

    height: 16px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}


.form-input {

    width: 100%;

    height: 46px;

    padding:
        0
        43px
        0
        39px;

    color: #334155;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 10px;

    outline: none;

    font-family: inherit;

    font-size: 10px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;

}


.form-input::placeholder {

    color: #c0c9d5;

}


.form-input:focus {

    border-color: #60a5fa;

    background: #fff;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.08);

}


/* =========================================================
   PASSWORD TOGGLE
========================================================= */

.password-toggle {

    position: absolute;

    top: 50%;

    right: 11px;

    width: 30px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 0;

    color: #94a3b8;

    background: transparent;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    transform: translateY(-50%);

    transition: .2s ease;

}


.password-toggle:hover {

    color: #2563eb;

    background: #eff6ff;

}


.password-toggle svg {

    width: 15px;

    height: 15px;

}


/* =========================================================
   SUBMIT
========================================================= */

.login-submit {

    width: 100%;

    height: 46px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    margin-top: 5px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    border-radius: 10px;

    cursor: pointer;

    font-family: inherit;

    font-size: 10px;

    font-weight: 800;

    box-shadow:
        0 8px 20px rgba(37,99,235,.18);

    transition:
        background .2s ease,
        transform .2s ease,
        box-shadow .2s ease;

}


.login-submit:hover {

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-1px);

    box-shadow:
        0 11px 25px rgba(37,99,235,.24);

}


.login-submit:active {

    transform: scale(.98);

}


.login-submit:disabled {

    opacity: .75;

    cursor: wait;

    transform: none;

}


.login-submit svg {

    width: 15px;

    height: 15px;

}


/* =========================================================
   FOOTER
========================================================= */

.login-footer {

    margin-top: 28px;

    padding-top: 17px;

    border-top: 1px solid #eef2f7;

    text-align: center;

}


.login-footer p {

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.5;

}


/* =========================================================
   TRANSITION
========================================================= */

.page-transition {

    position: fixed;

    inset: 0;

    z-index: 99999;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #fff;

    pointer-events: none;

}


.transition-logo {

    width: 52px;

    height: 52px;

    overflow: hidden;

    border-radius: 14px;

}


.transition-logo img {

    width: 100%;

    height: 100%;

    object-fit: contain;

}


/* =========================================================
   SPINNER
========================================================= */

.loading-spinner {

    width: 15px;

    height: 15px;

    border: 2px solid rgba(255,255,255,.4);

    border-top-color: #fff;

    border-radius: 50%;

    animation:
        loginSpin .7s linear infinite;

}


@keyframes loginSpin {

    to {

        transform: rotate(360deg);

    }

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 900px) {

    .login-visual {

        width: 45%;

        padding: 35px;

    }


    .login-form-side {

        width: 55%;

        padding: 35px;

    }


    .visual-content h1 {

        font-size: 38px;

    }


    .visual-description {

        font-size: 11px;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 720px) {

    .login-page {

        min-height: 100vh;

        display: block;

        background: #f8fafc;

    }


    .login-visual {

        display: none;

    }


    .login-form-side {

        width: 100%;

        min-height: 100vh;

        display: flex;

        align-items: flex-start;

        justify-content: center;

        padding:
            22px
            16px
            35px;

        background:
            linear-gradient(
                180deg,
                #f8fbff 0%,
                #ffffff 40%
            );

    }


    .login-container {

        width: 100%;

        max-width: 430px;

    }


    /* -----------------------------------------
       BACK HOME
    ----------------------------------------- */

    .back-home {

        margin-bottom: 22px;

        font-size: 9px;

    }


    /* -----------------------------------------
       MOBILE BRAND
    ----------------------------------------- */

    .mobile-brand {

        display: flex;

        align-items: center;

        gap: 10px;

        margin-bottom: 30px;

    }


    .mobile-brand-logo {

        width: 43px;

        height: 43px;

        min-width: 43px;

        display: flex;

        align-items: center;

        justify-content: center;

        overflow: hidden;

    }


    .mobile-brand-logo img {

        width: 100%;

        height: 100%;

        object-fit: contain;

    }


    .mobile-brand-name {

        color: #0f172a;

        font-size: 16px;

        font-weight: 800;

        line-height: 1.1;

    }


    .mobile-brand-subtitle {

        margin-top: 3px;

        color: #64748b;

        font-size: 8px;

    }


    /* -----------------------------------------
       HEADER
    ----------------------------------------- */

    .form-eyebrow {

        font-size: 8px;

    }


    .form-header h2 {

        font-size: 27px;

    }


    .form-header p {

        font-size: 10px;

    }


    .login-form {

        margin-top: 24px;

    }


    .form-group {

        margin-bottom: 15px;

    }


    .form-input {

        height: 48px;

        font-size: 11px;

        border-radius: 11px;

    }


    .login-submit {

        height: 48px;

        font-size: 10px;

        border-radius: 11px;

    }


    .login-footer {

        margin-top: 25px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 420px) {

    .login-form-side {

        padding:
            19px
            13px
            30px;

    }


    .back-home {

        margin-bottom: 20px;

    }


    .mobile-brand {

        margin-bottom: 27px;

    }


    .mobile-brand-logo {

        width: 40px;

        height: 40px;

        min-width: 40px;

    }


    .mobile-brand-name {

        font-size: 15px;

    }


    .mobile-brand-subtitle {

        font-size: 7px;

    }


    .form-header h2 {

        font-size: 25px;

    }


    .form-header p {

        font-size: 9px;

    }


    .login-error {

        font-size: 8px;

    }


    .form-label {

        font-size: 8px;

    }


    .form-input {

        height: 47px;

        font-size: 10px;

    }


    .login-submit {

        height: 47px;

    }

}


/* =========================================================
   VERY SMALL
========================================================= */

@media (max-width: 350px) {

    .login-form-side {

        padding-left: 11px;

        padding-right: 11px;

    }


    .back-home {

        margin-bottom: 18px;

        font-size: 8px;

    }


    .form-header h2 {

        font-size: 23px;

    }


    .form-header p {

        font-size: 8px;

    }

}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation: none !important;

        transition: none !important;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     PAGE TRANSITION
========================================================= -->

<div class="page-transition">

    <div class="transition-logo">

        <img
            src="assets/img/logo-aduin.png"
            alt="ADUIN"
        >

    </div>

</div>



<main class="login-page">


    <!-- =====================================================
         LEFT VISUAL
    ====================================================== -->

    <section class="login-visual">


        <div class="visual-circle one"></div>

        <div class="visual-circle two"></div>

        <div class="visual-circle three"></div>


        <div class="visual-content">


            <!-- BRAND -->

            <div class="visual-brand">

                <div class="visual-logo">

                    <img
                        src="assets/img/logo-aduin.png"
                        alt="Logo ADUIN"
                    >

                </div>


                <div>

                    <div class="visual-brand-name">
                        ADUIN
                    </div>

                    <div class="visual-brand-subtitle">
                        Platform Aduan Fasilitas Kampus
                    </div>

                </div>

            </div>



            <!-- HEADING -->

            <h1>

                Sampaikan Aduan,

                <span>
                    Wujudkan Kampus Lebih Baik.
                </span>

            </h1>


            <!-- DESCRIPTION -->

            <p class="visual-description">

                Laporkan kerusakan atau masalah fasilitas
                kampus dengan mudah. Pantau proses penanganan
                pengaduan Anda secara transparan.

            </p>


            <!-- FEATURES -->

            <div class="visual-features">


                <div class="visual-feature">

                    <div class="visual-feature-icon">

                        <i data-lucide="file-plus-2"></i>

                    </div>

                    <span>
                        Buat pengaduan dengan mudah
                    </span>

                </div>


                <div class="visual-feature">

                    <div class="visual-feature-icon">

                        <i data-lucide="clock-3"></i>

                    </div>

                    <span>
                        Pantau status pengaduan
                    </span>

                </div>


                <div class="visual-feature">

                    <div class="visual-feature-icon">

                        <i data-lucide="image"></i>

                    </div>

                    <span>
                        Sertakan foto kerusakan
                    </span>

                </div>


                <div class="visual-feature">

                    <div class="visual-feature-icon">

                        <i data-lucide="check-circle-2"></i>

                    </div>

                    <span>
                        Penanganan lebih terorganisir
                    </span>

                </div>


            </div>


        </div>

    </section>



    <!-- =====================================================
         RIGHT FORM
    ====================================================== -->

    <section class="login-form-side">


        <div class="login-container">


            <!-- =================================================
                 BACK HOME
            ================================================= -->

            <a
                href="index.php"
                class="back-home"
            >

                <i data-lucide="arrow-left"></i>

                <span>
                    Kembali ke Beranda
                </span>

            </a>



            <!-- =================================================
                 MOBILE BRAND
            ================================================= -->

            <div class="mobile-brand">

                <div class="mobile-brand-logo">

                    <img
                        src="assets/img/logo-aduin.png"
                        alt="Logo ADUIN"
                    >

                </div>


                <div>

                    <div class="mobile-brand-name">
                        ADUIN
                    </div>

                    <div class="mobile-brand-subtitle">
                        Platform Aduan Fasilitas Kampus
                    </div>

                </div>

            </div>



            <!-- =================================================
                 HEADER
            ================================================= -->

            <div class="form-header">

                <div class="form-eyebrow">
                    SELAMAT DATANG KEMBALI
                </div>

                <h2>
                    Masuk ke ADUIN
                </h2>

                <p>
                    Silakan masuk untuk mengelola dan memantau
                    pengaduan fasilitas kampus.
                </p>

            </div>



            <!-- =================================================
                 ERROR
            ================================================= -->

            <?php if ($error !== ''): ?>

                <div
                    class="login-error"
                    id="loginError"
                >

                    <i data-lucide="circle-alert"></i>

                    <span>
                        <?= htmlspecialchars($error) ?>
                    </span>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 LOGIN FORM
            ================================================= -->

            <form
                method="POST"
                class="login-form"
                id="loginForm"
                autocomplete="on"
            >
                <?= csrfField() ?>


                <!-- EMAIL -->

                <div class="form-group">

                    <label
                        for="email"
                        class="form-label"
                    >
                        Email
                    </label>


                    <div class="input-wrapper">

                        <i
                            data-lucide="mail"
                            class="input-icon"
                        ></i>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="Masukkan email Anda"
                            value="<?= htmlspecialchars($email) ?>"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label
                        for="password"
                        class="form-label"
                    >
                        Password
                    </label>


                    <div class="input-wrapper">

                        <i
                            data-lucide="lock-keyhole"
                            class="input-icon"
                        ></i>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Masukkan password Anda"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Tampilkan password"
                        >

                            <i data-lucide="eye"></i>

                        </button>

                    </div>

                </div>



                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="login-submit"
                    id="loginSubmit"
                >

                    <i data-lucide="log-in"></i>

                    <span>
                        Masuk
                    </span>

                </button>


            </form>



            <!-- =================================================
                 FOOTER
            ================================================= -->

            <div class="login-footer">

                <p>
                    ADUIN · Platform Aduan Fasilitas Kampus
                </p>

            </div>


        </div>

    </section>


</main>



<script nonce="<?= e(cspNonce()) ?>">

/* =========================================================
   DOM READY
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           LUCIDE
        ================================================= */

        if (window.lucide) {

            lucide.createIcons();

        }



        /* =================================================
           PAGE TRANSITION
        ================================================= */

        const transition =
            document.querySelector(
                ".page-transition"
            );


        if (
            transition &&
            typeof gsap !== "undefined"
        ) {

            gsap.to(
                transition,
                {
                    opacity: 0,

                    duration: .55,

                    delay: .15,

                    ease: "power2.out",

                    onComplete: function () {

                        transition.style.display =
                            "none";

                    }

                }
            );

        } else if (transition) {

            transition.style.display =
                "none";

        }



        /* =================================================
           PASSWORD TOGGLE
        ================================================= */

        const password =
            document.getElementById(
                "password"
            );


        const passwordToggle =
            document.getElementById(
                "passwordToggle"
            );


        if (
            password &&
            passwordToggle
        ) {

            passwordToggle.addEventListener(
                "click",
                function () {


                    const isPassword =
                        password.type === "password";


                    password.type =
                        isPassword
                            ? "text"
                            : "password";


                    passwordToggle.setAttribute(
                        "aria-label",
                        isPassword
                            ? "Sembunyikan password"
                            : "Tampilkan password"
                    );


                    passwordToggle.innerHTML =
                        isPassword
                            ? '<i data-lucide="eye-off"></i>'
                            : '<i data-lucide="eye"></i>';


                    if (window.lucide) {

                        lucide.createIcons();

                    }

                }
            );

        }



        /* =================================================
           LOGIN FORM
        ================================================= */

        const form =
            document.getElementById(
                "loginForm"
            );


        const submitButton =
            document.getElementById(
                "loginSubmit"
            );


        if (
            form &&
            submitButton
        ) {

            form.addEventListener(
                "submit",
                function () {


                    submitButton.disabled =
                        true;


                    submitButton.innerHTML = `
                        <span class="loading-spinner"></span>
                        <span>Memproses...</span>
                    `;

                }
            );

        }



        /* =================================================
           ERROR SHAKE
        ================================================= */

        const loginError =
            document.getElementById(
                "loginError"
            );


        if (
            loginError &&
            typeof gsap !== "undefined"
        ) {

            gsap.fromTo(
                loginError,

                {
                    x: -5
                },

                {
                    x: 5,

                    duration: .06,

                    repeat: 5,

                    yoyo: true,

                    ease: "power1.inOut",

                    clearProps: "x"

                }
            );

        }



        /* =================================================
           FORM ENTRANCE
        ================================================= */

        if (
            typeof gsap !== "undefined"
        ) {


            gsap.from(
                ".back-home, .mobile-brand, .form-header, .login-form, .login-footer",

                {
                    opacity: 0,

                    y: 18,

                    duration: .65,

                    stagger: .08,

                    delay: .25,

                    ease: "power3.out"

                }
            );


            gsap.from(
                ".visual-brand, .visual-content h1, .visual-description, .visual-feature",

                {
                    opacity: 0,

                    y: 20,

                    duration: .7,

                    stagger: .09,

                    delay: .25,

                    ease: "power3.out"

                }
            );

        }

    }
);

</script>


</body>

</html>
