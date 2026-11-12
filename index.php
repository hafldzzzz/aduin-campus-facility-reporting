<?php
require_once "config/database.php";
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="assets/img/logo-aduin.png">

<title>ADUIN | Platform Aduan Fasilitas Kampus</title>

    <title>ADUIN | Platform Aduan Fasilitas Kampus</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <meta
        name="description"
        content="Sistem Informasi Pengaduan dan Pelaporan Fasilitas Kampus"
    >


    <!-- =====================================================
         LENIS CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://unpkg.com/lenis@1.3.26/dist/lenis.css"
    >


    <!-- =====================================================
         SWIPER CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css"
    >


    <!-- =====================================================
         LUCIDE
    ====================================================== -->

    <script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lucide@latest"></script>

    <script nonce="<?= e(cspNonce()) ?>">
    lucide.createIcons();
</script>


    <style>

        /* =====================================================
           ROOT
        ====================================================== */

        :root {

            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #eff6ff;

            --cyan: #0ea5e9;

            --dark: #0f172a;
            --dark-2: #1e293b;

            --text: #475569;
            --muted: #64748b;
            --light-text: #94a3b8;

            --border: #e2e8f0;

            --background: #f8fafc;
            --white: #ffffff;

            --success: #16a34a;
            --success-bg: #f0fdf4;

            --container: 1160px;

            --radius-sm: 12px;
            --radius-md: 18px;
            --radius-lg: 24px;

            --shadow-sm:
                0 8px 25px rgba(15, 23, 42, .05);

            --shadow-md:
                0 18px 45px rgba(15, 23, 42, .08);

            --shadow-lg:
                0 30px 70px rgba(15, 23, 42, .12);

        }


        /* =====================================================
           RESET
        ====================================================== */

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: auto;
        }

        body {

            margin: 0;

            overflow-x: hidden;

            font-family:
                "Segoe UI",
                Arial,
                Helvetica,
                sans-serif;

            color: var(--dark);

            background: var(--white);

            -webkit-font-smoothing: antialiased;

        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font-family: inherit;
        }

        img {
            max-width: 100%;
            display: block;
        }


        /* =====================================================
           LENIS
        ====================================================== */

        html.lenis,
        html.lenis body {

            height: auto;

        }

        .lenis.lenis-smooth {

            scroll-behavior: auto !important;

        }

        .lenis.lenis-smooth [data-lenis-prevent] {

            overscroll-behavior: contain;

        }

        .lenis.lenis-stopped {

            overflow: hidden;

        }


        /* =====================================================
           CONTAINER
        ====================================================== */

        .container {

            width:
                min(
                    calc(100% - 48px),
                    var(--container)
                );

            margin-left: auto;
            margin-right: auto;

        }


        /* =====================================================
           NAVBAR
        ====================================================== */

        .navbar {

            position: fixed;

            top: 0;
            left: 0;

            width: 100%;

            z-index: 1000;

            padding: 18px 0;

            transition:
                padding .3s ease,
                background .3s ease,
                box-shadow .3s ease;

        }

        .navbar.scrolled {

            padding: 10px 0;

            background:
                rgba(255, 255, 255, .90);

            backdrop-filter:
                blur(18px);

            box-shadow:
                0 8px 30px
                rgba(15, 23, 42, .07);

        }

        .navbar-inner {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

        }


        /* =====================================================
           BRAND
        ====================================================== */

        .brand {

            display: flex;

            align-items: center;

            gap: 11px;

            flex-shrink: 0;

        }

        .brand-icon {

            width: 43px;
            height: 43px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--cyan)
                );

            box-shadow:
                0 8px 20px
                rgba(37, 99, 235, .22);

        }

        .brand-icon svg {

            width: 22px;
            height: 22px;

        }

        .brand-name {

            font-size: 14px;

            font-weight: 700;

            line-height: 1.2;

            color: var(--dark);

        }

        .brand-subtitle {

            margin-top: 3px;

            font-size: 10px;

            color: var(--muted);

        }


        /* =====================================================
           NAV MENU
        ====================================================== */

        .nav-menu {

            display: flex;

            align-items: center;

            gap: 30px;

            margin-left: auto;

            margin-right: 8px;

        }

        .nav-menu a {

            position: relative;

            color: #475569;

            font-size: 13px;

            font-weight: 600;

            transition:
                color .25s ease;

        }

        .nav-menu a:hover {

            color: var(--primary);

        }

        .nav-menu a::after {

            content: "";

            position: absolute;

            left: 50%;

            bottom: -8px;

            width: 0;

            height: 2px;

            border-radius: 20px;

            background:
                var(--primary);

            transform:
                translateX(-50%);

            transition:
                width .25s ease;

        }

        .nav-menu a:hover::after {

            width: 18px;

        }


        /* =====================================================
           LOGIN
        ====================================================== */

        .login-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            min-height: 42px;

            padding: 0 17px;

            border-radius: 10px;

            color: white;

            background:
                var(--primary);

            font-size: 13px;

            font-weight: 700;

            box-shadow:
                0 8px 20px
                rgba(37, 99, 235, .18);

            transition:
                background .25s ease,
                box-shadow .25s ease;

        }

        .login-btn svg {

            width: 17px;
            height: 17px;

        }

        .login-btn:hover {

            background:
                var(--primary-dark);

            box-shadow:
                0 12px 28px
                rgba(37, 99, 235, .25);

        }


        /* =====================================================
           HERO
        ====================================================== */

        .hero {

            position: relative;

            display: flex;

            align-items: center;

            min-height: 690px;

            padding:
                135px 0
                80px;

            overflow: hidden;

            background:

                radial-gradient(
                    circle at 85% 20%,
                    rgba(37, 99, 235, .13),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 8% 85%,
                    rgba(14, 165, 233, .09),
                    transparent 28%
                ),

                linear-gradient(
                    180deg,
                    #f8fbff 0%,
                    #ffffff 100%
                );

        }

        .hero::before {

            content: "";

            position: absolute;

            width: 520px;
            height: 520px;

            right: -250px;
            top: -220px;

            border-radius: 50%;

            border:
                1px solid
                rgba(37, 99, 235, .08);

            pointer-events: none;

        }

        .hero::after {

            content: "";

            position: absolute;

            width: 380px;
            height: 380px;

            left: -220px;
            bottom: -240px;

            border-radius: 50%;

            background:
                rgba(14, 165, 233, .05);

            pointer-events: none;

        }

        .hero-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1.02fr)
                minmax(420px, .98fr);

            align-items: center;

            gap: 70px;

        }


        /* =====================================================
           HERO CONTENT
        ====================================================== */

        .hero-content {

            position: relative;

            z-index: 2;

        }

        .hero-badge {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding:
                8px 13px;

            border-radius: 50px;

            color:
                #1d4ed8;

            background:
                rgba(239, 246, 255, .90);

            border:
                1px solid
                #dbeafe;

            font-size: 11px;

            font-weight: 750;

        }

        .hero-badge svg {

            width: 15px;
            height: 15px;

        }

        .hero-title {

            max-width: 650px;

            margin:
                22px 0
                20px;

            font-size:
                clamp(
                    44px,
                    5vw,
                    62px
                );

            line-height: 1.04;

            letter-spacing:
                -2.5px;

            color:
                var(--dark);

        }

        .hero-title .gradient {

            display: block;

            background:
                linear-gradient(
                    90deg,
                    #2563eb,
                    #0284c7
                );

            -webkit-background-clip:
                text;

            background-clip:
                text;

            color:
                transparent;

        }

        .hero-description {

            max-width: 590px;

            margin:
                0 0
                28px;

            color:
                var(--muted);

            font-size: 15px;

            line-height: 1.8;

        }


        /* =====================================================
           HERO ACTION
        ====================================================== */

        .hero-actions {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 27px;

        }

        .hero-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-height: 46px;

            padding:
                0 18px;

            border-radius: 11px;

            font-size: 13px;

            font-weight: 700;

            transition:
                box-shadow .25s ease,
                border-color .25s ease;

        }

        .hero-button svg {

            width: 17px;
            height: 17px;

        }

        .hero-button-primary {

            color: white;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    #1677e8
                );

            box-shadow:
                0 10px 25px
                rgba(37, 99, 235, .22);

        }

        .hero-button-primary:hover {

            box-shadow:
                0 15px 30px
                rgba(37, 99, 235, .28);

        }

        .hero-button-secondary {

            color:
                #334155;

            background:
                white;

            border:
                1px solid
                var(--border);

        }

        .hero-button-secondary:hover {

            border-color:
                #bfdbfe;

            box-shadow:
                var(--shadow-sm);

        }


        /* =====================================================
           TRUST
        ====================================================== */

        .hero-trust {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 18px;

        }

        .trust-item {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            color:
                #64748b;

            font-size: 11px;

        }

        .trust-item svg {

            width: 16px;

            color:
                var(--primary);

        }


        /* =====================================================
           HERO DASHBOARD
        ====================================================== */

        .hero-visual {

            position: relative;

            z-index: 2;

            width: 100%;

            max-width: 510px;

            margin-left: auto;

        }

        .dashboard-card {

            padding:
                25px;

            border-radius:
                var(--radius-lg);

            background:
                rgba(255,255,255,.94);

            border:
                1px solid
                rgba(226,232,240,.9);

            box-shadow:
                var(--shadow-lg);

            backdrop-filter:
                blur(18px);

            transform-style:
                preserve-3d;

        }


        /* dashboard header */

        .dashboard-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom:
                21px;

        }

        .dashboard-heading small {

            display: block;

            margin-bottom:
                5px;

            color:
                var(--muted);

            font-size:
                11px;

        }

        .dashboard-heading strong {

            font-size:
                18px;

            letter-spacing:
                -.2px;

        }

        .system-status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                7px 10px;

            border-radius:
                30px;

            color:
                #15803d;

            background:
                var(--success-bg);

            font-size:
                10px;

            font-weight:
                750;

            white-space:
                nowrap;

        }

        .status-dot {

            width: 6px;
            height: 6px;

            border-radius:
                50%;

            background:
                #22c55e;

            box-shadow:
                0 0 0 4px
                rgba(34,197,94,.1);

        }


        /* status */

        .status-row {

            display: flex;

            align-items: center;

            gap: 13px;

            padding:
                13px;

            border-radius:
                14px;

            transition:
                background .25s ease,
                transform .25s ease;

        }

        .status-row:hover {

            background:
                #f8fafc;

            transform:
                translateX(4px);

        }

        .status-icon {

            width: 41px;
            height: 41px;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                12px;

            color:
                var(--primary);

            background:
                var(--primary-light);

        }

        .status-icon svg {

            width:
                20px;

            height:
                20px;

        }

        .status-row.completed
        .status-icon {

            color:
                var(--success);

            background:
                var(--success-bg);

        }

        .status-info {

            flex:
                1;

            min-width:
                0;

        }

        .status-info strong {

            display:
                block;

            margin-bottom:
                3px;

            font-size:
                13px;

        }

        .status-info span {

            display:
                block;

            color:
                var(--light-text);

            font-size:
                10px;

        }

        .status-check {

            width:
                23px;

            height:
                23px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            color:
                white;

            background:
                var(--primary);

        }

        .status-check svg {

            width:
                13px;

            height:
                13px;

        }


        /* progress */

        .progress-line {

            height:
                4px;

            margin:
                4px 18px
                8px;

            overflow:
                hidden;

            border-radius:
                20px;

            background:
                #e2e8f0;

        }

        .progress-line span {

            display:
                block;

            width:
                72%;

            height:
                100%;

            border-radius:
                inherit;

            background:
                linear-gradient(
                    90deg,
                    var(--primary),
                    #38bdf8
                );

        }


        /* dashboard footer */

        .dashboard-footer {

            display:
                grid;

            grid-template-columns:
                repeat(2,1fr);

            gap:
                10px;

            margin-top:
                18px;

            padding-top:
                18px;

            border-top:
                1px solid
                #f1f5f9;

        }

        .dashboard-stat {

            padding:
                13px;

            border-radius:
                13px;

            background:
                #f8fafc;

        }

        .dashboard-stat small {

            display:
                block;

            margin-bottom:
                5px;

            color:
                var(--light-text);

            font-size:
                9px;

            text-transform:
                uppercase;

            letter-spacing:
                .5px;

        }

        .dashboard-stat strong {

            font-size:
                17px;

        }


        /* notification */

        .notification-card {

            position:
                absolute;

            right:
                -30px;

            bottom:
                -25px;

            display:
                flex;

            align-items:
                center;

            gap:
                10px;

            padding:
                11px 14px;

            border-radius:
                14px;

            background:
                white;

            border:
                1px solid
                var(--border);

            box-shadow:
                var(--shadow-md);

        }

        .notification-icon {

            width:
                35px;

            height:
                35px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                10px;

            color:
                var(--primary);

            background:
                var(--primary-light);

        }

        .notification-icon svg {

            width:
                17px;

            height:
                17px;

        }

        .notification-text strong {

            display:
                block;

            font-size:
                10px;

        }

        .notification-text span {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--light-text);

            font-size:
                9px;

        }


        /* =====================================================
           STAT
        ====================================================== */

        .stat-section {

            position:
                relative;

            z-index:
                5;

            margin-top:
                -32px;

        }

        .stat-grid {

            display:
                grid;

            grid-template-columns:
                repeat(3,1fr);

            gap:
                14px;

        }

        .stat-card {

            display:
                flex;

            align-items:
                center;

            gap:
                13px;

            min-height:
                86px;

            padding:
                17px 19px;

            border-radius:
                17px;

            background:
                white;

            border:
                1px solid
                var(--border);

            box-shadow:
                var(--shadow-sm);

            transition:
                transform .3s ease,
                box-shadow .3s ease;

        }

        .stat-card:hover {

            transform:
                translateY(-4px);

            box-shadow:
                var(--shadow-md);

        }

        .stat-icon {

            width:
                43px;

            height:
                43px;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                12px;

            color:
                var(--primary);

            background:
                var(--primary-light);

        }

        .stat-icon svg {

            width:
                20px;

            height:
                20px;

        }

        .stat-content strong {

            display:
                block;

            margin-bottom:
                3px;

            font-size:
                17px;

        }

        .stat-content span {

            color:
                var(--light-text);

            font-size:
                10px;

        }


        /* =====================================================
           SECTION
        ====================================================== */

        .section {

            padding:
                88px 0;

        }

        .section-heading {

            max-width:
                650px;

            margin:
                0 auto
                38px;

            text-align:
                center;

        }

        .section-label {

            display:
                inline-block;

            margin-bottom:
                10px;

            color:
                var(--primary);

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                1.1px;

            text-transform:
                uppercase;

        }

        .section-heading h2 {

            margin:
                0 0
                12px;

            font-size:
                34px;

            line-height:
                1.2;

            letter-spacing:
                -.8px;

        }

        .section-heading p {

            margin:
                0;

            color:
                var(--muted);

            font-size:
                14px;

            line-height:
                1.75;

        }


        /* =====================================================
           FEATURES
        ====================================================== */

        .features-grid {

            display:
                grid;

            grid-template-columns:
                repeat(4,1fr);

            gap:
                15px;

        }

        .feature-card {

            min-height:
                205px;

            padding:
                23px;

            border-radius:
                var(--radius-md);

            background:
                white;

            border:
                1px solid
                var(--border);

            transition:
                transform .3s ease,
                box-shadow .3s ease,
                border-color .3s ease;

        }

        .feature-card:hover {

            transform:
                translateY(-6px);

            border-color:
                #bfdbfe;

            box-shadow:
                var(--shadow-md);

        }

        .feature-icon {

            width:
                49px;

            height:
                49px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            margin-bottom:
                18px;

            border-radius:
                14px;

            color:
                var(--primary);

            background:
                var(--primary-light);

        }

        .feature-icon svg {

            width:
                23px;

            height:
                23px;

        }

        .feature-card h3 {

            margin:
                0 0
                8px;

            font-size:
                15px;

        }

        .feature-card p {

            margin:
                0;

            color:
                var(--muted);

            font-size:
                12px;

            line-height:
                1.7;

        }


        /* =====================================================
           FACILITIES
        ====================================================== */

        .facility-section {

            background:
                #f8fafc;

            border-top:
                1px solid
                #f1f5f9;

            border-bottom:
                1px solid
                #f1f5f9;

        }

        .facility-slider {

            padding:
                4px
                5px
                50px;

        }

        .facility-card {

            min-height:
                220px;

            padding:
                25px;

            border-radius:
                var(--radius-md);

            background:
                white;

            border:
                1px solid
                var(--border);

            transition:
                transform .3s ease,
                box-shadow .3s ease,
                border-color .3s ease;

        }

        .facility-card:hover {

            transform:
                translateY(-6px);

            border-color:
                #bfdbfe;

            box-shadow:
                var(--shadow-md);

        }

        .facility-icon {

            width:
                50px;

            height:
                50px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            margin-bottom:
                18px;

            border-radius:
                14px;

            color:
                var(--primary);

            background:
                var(--primary-light);

        }

        .facility-icon svg {

            width:
                23px;

            height:
                23px;

        }

        .facility-card h3 {

            margin:
                0 0
                8px;

            font-size:
                15px;

        }

        .facility-card p {

            margin:
                0;

            color:
                var(--muted);

            font-size:
                12px;

            line-height:
                1.7;

        }


        /* =====================================================
           SWIPER
        ====================================================== */

        .swiper-button-next,
        .swiper-button-prev {

            width:
                39px;

            height:
                39px;

            margin-top:
                -25px;

            border-radius:
                50%;

            background:
                white;

            box-shadow:
                0 8px 20px
                rgba(15,23,42,.10);

        }

        .swiper-button-next::after,
        .swiper-button-prev::after {

            font-size:
                13px;

            font-weight:
                800;

            color:
                var(--primary);

        }

        .swiper-pagination-bullet {

            width:
                6px;

            height:
                6px;

            opacity:
                1;

            background:
                #cbd5e1;

        }

        .swiper-pagination-bullet-active {

            width:
                22px;

            border-radius:
                20px;

            background:
                var(--primary);

        }


        /* =====================================================
           STEPS
        ====================================================== */

        .steps-grid {

            display:
                grid;

            grid-template-columns:
                repeat(4,1fr);

            gap:
                15px;

        }

        .step-card {

            position:
                relative;

            min-height:
                210px;

            padding:
                24px;

            border-radius:
                var(--radius-md);

            background:
                white;

            border:
                1px solid
                var(--border);

            transition:
                transform .3s ease,
                box-shadow .3s ease;

        }

        .step-card:hover {

            transform:
                translateY(-6px);

            box-shadow:
                var(--shadow-md);

        }

        .step-number {

            position:
                absolute;

            top:
                16px;

            right:
                18px;

            color:
                #dbeafe;

            font-size:
                26px;

            font-weight:
                900;

        }

        .step-icon {

            width:
                48px;

            height:
                48px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            margin-bottom:
                18px;

            border-radius:
                13px;

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--cyan)
                );

        }

        .step-icon svg {

            width:
                21px;

            height:
                21px;

        }

        .step-card h3 {

            margin:
                0 0
                8px;

            font-size:
                15px;

        }

        .step-card p {

            margin:
                0;

            color:
                var(--muted);

            font-size:
                12px;

            line-height:
                1.7;

        }


        /* =====================================================
           CTA
        ====================================================== */

        .cta {

            margin:
                0 0
                70px;

        }

        .cta-inner {

            position:
                relative;

            overflow:
                hidden;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                35px;

            padding:
                52px 58px;

            border-radius:
                25px;

            background:
                linear-gradient(
                    135deg,
                    #1d4ed8,
                    #2563eb 55%,
                    #0284c7
                );

            box-shadow:
                0 25px 60px
                rgba(37,99,235,.20);

        }

        .cta-inner::before {

            content:
                "";

            position:
                absolute;

            width:
                380px;

            height:
                380px;

            right:
                -160px;

            top:
                -220px;

            border-radius:
                50%;

            background:
                rgba(255,255,255,.07);

        }

        .cta-inner::after {

            content:
                "";

            position:
                absolute;

            width:
                240px;

            height:
                240px;

            left:
                -130px;

            bottom:
                -170px;

            border-radius:
                50%;

            background:
                rgba(255,255,255,.05);

        }

        .cta-content {

            position:
                relative;

            z-index:
                2;

            color:
                white;

        }

        .cta-content small {

            font-size:
                11px;

            color:
                rgba(255,255,255,.70);

        }

        .cta-content h2 {

            margin:
                8px 0
                9px;

            font-size:
                30px;

            letter-spacing:
                -.5px;

        }

        .cta-content p {

            max-width:
                570px;

            margin:
                0;

            color:
                rgba(255,255,255,.76);

            font-size:
                13px;

            line-height:
                1.7;

        }

        .cta-button {

            position:
                relative;

            z-index:
                2;

            flex-shrink:
                0;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            padding:
                13px 19px;

            border-radius:
                11px;

            color:
                #1d4ed8;

            background:
                white;

            font-size:
                13px;

            font-weight:
                750;

            transition:
                box-shadow .25s ease;

        }

        .cta-button svg {

            width:
                16px;

            height:
                16px;

        }

        .cta-button:hover {

            box-shadow:
                0 12px 25px
                rgba(15,23,42,.15);

        }


        /* =====================================================
           FOOTER
        ====================================================== */

        .footer {

            padding:
                28px 0;

            color:
                #94a3b8;

            background:
                #0f172a;

        }

        .footer-inner {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

        }

        .footer-brand {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            color:
                white;

            font-size:
                13px;

            font-weight:
                700;

        }

        .footer-brand svg {

            width:
                19px;

            color:
                #60a5fa;

        }

        .footer-copy {

            font-size:
                10px;

        }


        /* =====================================================
           TABLET
        ====================================================== */

        @media (max-width: 1050px) {

            .nav-menu {

                gap:
                    20px;

            }

            .hero-grid {

                grid-template-columns:
                    1fr
                    1fr;

                gap:
                    45px;

            }

            .hero-title {

                font-size:
                    50px;

            }

            .features-grid,
            .steps-grid {

                grid-template-columns:
                    repeat(2,1fr);

            }

        }


        /* =====================================================
           MOBILE MENU
        ====================================================== */

        .mobile-menu-btn {

            display: none;

            width: 42px;
            height: 42px;

            align-items: center;
            justify-content: center;

            border: 1px solid var(--border);
            border-radius: 11px;

            background: #ffffff;
            color: var(--dark);

            cursor: pointer;

            box-shadow: 0 5px 16px rgba(15, 23, 42, .06);

            transition:
                background .25s ease,
                color .25s ease,
                border-color .25s ease,
                transform .25s ease;
        }

        .mobile-menu-btn:hover {
            background: var(--primary-light);
            border-color: #bfdbfe;
            color: var(--primary);
        }

        .mobile-menu-btn svg {
            width: 20px;
            height: 20px;
        }

        .mobile-menu {

            position: fixed;

            top: 0;
            left: 0;
            right: 0;

            z-index: 995;

            padding: 92px 16px 16px;

            pointer-events: none;
            visibility: hidden;

            opacity: 0;

            transform: translateY(-12px);

            transition:
                opacity .28s ease,
                transform .28s ease,
                visibility .28s ease;
        }

        .mobile-menu::before {
            content: "";

            position: absolute;
            inset: 0;

            background: rgba(255,255,255,.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);

            border-bottom: 1px solid var(--border);
            box-shadow: 0 18px 40px rgba(15,23,42,.08);

            z-index: -1;
        }

        .mobile-menu.open {
            pointer-events: auto;
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }

        .mobile-menu-inner {

            width: min(100%, 520px);
            margin: 0 auto;

            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .mobile-menu-link,
        .mobile-menu-login {

            display: flex;
            align-items: center;
            gap: 12px;

            min-height: 48px;
            padding: 0 14px;

            border-radius: 12px;

            text-decoration: none;

            font-size: 13px;
            font-weight: 650;

            transition:
                background .2s ease,
                color .2s ease,
                transform .2s ease;
        }

        .mobile-menu-link {
            color: #334155;
            background: transparent;
        }

        .mobile-menu-link:hover {
            color: var(--primary);
            background: var(--primary-light);
            transform: translateX(3px);
        }

        .mobile-menu-link svg,
        .mobile-menu-login svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .mobile-menu-divider {
            height: 1px;
            margin: 7px 0;
            background: var(--border);
        }

        .mobile-menu-login {
            justify-content: center;
            color: white;
            background: var(--primary);
            box-shadow: 0 9px 22px rgba(37,99,235,.18);
        }

        .mobile-menu-login:hover {
            color: white;
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        body.mobile-menu-open {
            overflow: hidden;
        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 760px) {

            .container {

                width:
                    min(
                        calc(100% - 32px),
                        var(--container)
                    );

            }


            .nav-menu {

                display: none;

            }


            .mobile-menu-btn {

                display: inline-flex;

            }


            .login-btn {

                min-height:
                    39px;

                padding:
                    0 13px;

            }


            .hero {

                padding:
                    120px 0
                    65px;

                min-height:
                    auto;

            }


            .hero-grid {

                grid-template-columns:
                    1fr;

                gap:
                    45px;

            }


            .hero-content {

                text-align:
                    center;

            }


            .hero-badge {

                justify-content:
                    center;

            }


            .hero-title {

                margin-top:
                    19px;

                font-size:
                    42px;

                letter-spacing:
                    -1.7px;

            }


            .hero-description {

                margin-left:
                    auto;

                margin-right:
                    auto;

                font-size:
                    14px;

            }


            .hero-actions {

                flex-direction:
                    column;

            }


            .hero-button {

                width:
                    100%;

            }


            .hero-trust {

                justify-content:
                    center;

            }


            .hero-visual {

                max-width:
                    500px;

                margin:
                    0 auto;

            }


            .notification-card {

                right:
                    8px;

                bottom:
                    -24px;

            }


            .stat-section {

                margin-top:
                    -20px;

            }


            .stat-grid {

                grid-template-columns:
                    1fr;

            }


            .section {

                padding:
                    70px 0;

            }


            .section-heading {

                margin-bottom:
                    30px;

            }


            .section-heading h2 {

                font-size:
                    29px;

            }


            .features-grid,
            .steps-grid {

                grid-template-columns:
                    1fr;

            }


            .swiper-button-next,
            .swiper-button-prev {

                display:
                    none;

            }


            .cta {

                margin-bottom:
                    55px;

            }


            .cta-inner {

                flex-direction:
                    column;

                align-items:
                    flex-start;

                padding:
                    38px 28px;

            }


            .cta-content h2 {

                font-size:
                    27px;

            }


            .cta-button {

                width:
                    100%;

            }


            .footer-inner {

                flex-direction:
                    column;

                align-items:
                    flex-start;

            }

        }


        /* =====================================================
           SMALL MOBILE
        ====================================================== */

        @media (max-width: 430px) {

            .brand-subtitle {

                display:
                    none;

            }

            .brand-logo {

                width: 39px;
                height: 39px;
                min-width: 39px;
                min-height: 39px;

            }

            .brand-logo img {

                width: 39px;
                height: 39px;
                max-width: 39px;
                max-height: 39px;

            }


            .brand-name {

                font-size:
                    13px;

            }

            .brand-icon {

                width:
                    39px;

                height:
                    39px;

            }

            .login-btn span {

                display:
                    none;

            }

            .login-btn {

                width:
                    39px;

                height:
                    39px;

                padding:
                    0;

            }

            .hero-title {

                font-size:
                    37px;

            }

            .dashboard-card {

                padding:
                    20px;

            }

            .notification-card {

                position:
                    relative;

                right:
                    auto;

                bottom:
                    auto;

                width:
                    100%;

                margin-top:
                    12px;

            }

        }


        /* =====================================================
           REDUCED MOTION
        ====================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                animation-duration:
                    .01ms !important;

                animation-iteration-count:
                    1 !important;

                transition-duration:
                    .01ms !important;

            }

        }

    
        /* =====================================================
           ADUIN NAVBAR LOGO - FINAL FIX
        ====================================================== */

        .navbar .brand {
            display: flex !important;
            align-items: center !important;
            gap: 11px !important;
            flex-shrink: 0 !important;
            text-decoration: none !important;
        }

        .navbar .brand-logo {
            width: 43px !important;
            height: 43px !important;
            min-width: 43px !important;
            min-height: 43px !important;
            max-width: 43px !important;
            max-height: 43px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
            flex-shrink: 0 !important;
            border-radius: 10px !important;
        }

        .navbar .brand-logo img {
            width: 43px !important;
            height: 43px !important;
            min-width: 43px !important;
            min-height: 43px !important;
            max-width: 43px !important;
            max-height: 43px !important;
            object-fit: contain !important;
            display: block !important;
        }

        .navbar .brand-text {
            display: flex !important;
            flex-direction: column !important;
        }

        .navbar .brand-name {
            font-size: 15px !important;
            font-weight: 800 !important;
            line-height: 1.1 !important;
            color: #0f172a !important;
        }

        .navbar .brand-subtitle {
            margin-top: 4px !important;
            font-size: 9px !important;
            font-weight: 500 !important;
            line-height: 1.2 !important;
            color: #64748b !important;
        }

        @media (max-width: 430px) {
            .navbar .brand-logo {
                width: 39px !important;
                height: 39px !important;
                min-width: 39px !important;
                min-height: 39px !important;
                max-width: 39px !important;
                max-height: 39px !important;
            }

            .navbar .brand-logo img {
                width: 39px !important;
                height: 39px !important;
                min-width: 39px !important;
                min-height: 39px !important;
                max-width: 39px !important;
                max-height: 39px !important;
            }
        }

    </style>

</head>


<body>




<!-- =========================================================
     NAVBAR
========================================================= -->

<?php

$basePath = '';

$activePage = '';

include 'includes/navbar.php';

?>


<!-- =========================================================
     MOBILE MENU
========================================================= -->

<div class="mobile-menu" id="mobileMenu">

    <div class="mobile-menu-inner">

        <a href="#beranda" class="mobile-menu-link">
            <i data-lucide="house"></i>
            <span>Beranda</span>
        </a>

        <a href="#tentang" class="mobile-menu-link">
            <i data-lucide="info"></i>
            <span>Tentang</span>
        </a>

        <a href="#fasilitas" class="mobile-menu-link">
            <i data-lucide="building-2"></i>
            <span>Fasilitas</span>
        </a>

        <a href="#cara-melapor" class="mobile-menu-link">
            <i data-lucide="clipboard-list"></i>
            <span>Cara Melapor</span>
        </a>

        <a href="tracking.php" class="mobile-menu-link">
            <i data-lucide="search"></i>
            <span>Lacak Pengaduan</span>
        </a>

        <div class="mobile-menu-divider"></div>

        <a href="login.php" class="mobile-menu-login">
            <i data-lucide="log-in"></i>
            <span>Login</span>
        </a>

    </div>

</div>



<!-- =========================================================
     HERO
========================================================= -->

<section
    class="hero"
    id="beranda">


    <div class="container hero-grid">


        <div class="hero-content">


            <div class="hero-badge hero-animation">

                <i data-lucide="sparkles"></i>

                Sistem Pengaduan Fasilitas Kampus

            </div>


            <h1 class="hero-title hero-animation">

                Laporkan Masalah

                <span class="gradient">
                    Fasilitas Kampus
                </span>

                dengan Mudah

            </h1>


            <p class="hero-description hero-animation">

                Sampaikan laporan kerusakan fasilitas kampus
                secara cepat, mudah, dan terstruktur.
                Pantau proses penanganannya sampai selesai.

            </p>


            <div class="hero-actions hero-animation">


                <a
                    href="login.php"
                    class="hero-button hero-button-primary">

                    <i data-lucide="send"></i>

                    Buat Pengaduan

                </a>


                <a
                    href="tracking.php"
                    class="hero-button hero-button-secondary">

                    <i data-lucide="search"></i>

                    Lacak Laporan

                </a>


            </div>


            <div class="hero-trust hero-animation">


                <div class="trust-item">

                    <i data-lucide="shield-check"></i>

                    Data Aman

                </div>


                <div class="trust-item">

                    <i data-lucide="zap"></i>

                    Proses Cepat

                </div>


                <div class="trust-item">

                    <i data-lucide="eye"></i>

                    Transparan

                </div>


            </div>


        </div>



        <div class="hero-visual hero-dashboard">


            <div class="dashboard-card">


                <div class="dashboard-header">


                    <div class="dashboard-heading">

                        <small>
                            Monitoring Pengaduan
                        </small>

                        <strong>
                            Status Laporan
                        </strong>

                    </div>


                    <div class="system-status">

                        <span class="status-dot"></span>

                        Sistem Aktif

                    </div>


                </div>


                <div class="status-row">


                    <div class="status-icon">

                        <i data-lucide="file-plus-2"></i>

                    </div>


                    <div class="status-info">

                        <strong>
                            Menunggu
                        </strong>

                        <span>
                            Laporan diterima sistem
                        </span>

                    </div>


                    <div class="status-check">

                        <i data-lucide="check"></i>

                    </div>


                </div>


                <div class="status-row">


                    <div class="status-icon">

                        <i data-lucide="wrench"></i>

                    </div>


                    <div class="status-info">

                        <strong>
                            Diproses
                        </strong>

                        <span>
                            Laporan sedang ditangani
                        </span>

                    </div>


                    <div class="status-check">

                        <i data-lucide="check"></i>

                    </div>


                </div>


                <div class="progress-line">

                    <span></span>

                </div>


                <div
                    class="status-row completed">


                    <div class="status-icon">

                        <i data-lucide="check-circle-2"></i>

                    </div>


                    <div class="status-info">

                        <strong>
                            Selesai
                        </strong>

                        <span>
                            Pengaduan telah ditangani
                        </span>

                    </div>


                    <div class="status-check">

                        <i data-lucide="check"></i>

                    </div>


                </div>


                <div class="dashboard-footer">


                    <div class="dashboard-stat">

                        <small>
                            Kategori
                        </small>

                        <strong>
                            10+
                        </strong>

                    </div>


                    <div class="dashboard-stat">

                        <small>
                            Pemantauan
                        </small>

                        <strong>
                            Real-time
                        </strong>

                    </div>


                </div>


            </div>


            <div class="notification-card">


                <div class="notification-icon">

                    <i data-lucide="bell"></i>

                </div>


                <div class="notification-text">

                    <strong>
                        Laporan Terpantau
                    </strong>

                    <span>
                        Pantau status dengan mudah
                    </span>

                </div>


            </div>


        </div>


    </div>

</section>



<!-- =========================================================
     STATISTICS
========================================================= -->

<section class="stat-section">

    <div class="container stat-grid">


        <div class="stat-card">

            <div class="stat-icon">

                <i data-lucide="layers-3"></i>

            </div>


            <div class="stat-content">

                <strong>
                    10+
                </strong>

                <span>
                    Kategori Fasilitas
                </span>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i data-lucide="clipboard-check"></i>

            </div>


            <div class="stat-content">

                <strong>
                    3 Tahap
                </strong>

                <span>
                    Status Pengaduan
                </span>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i data-lucide="users"></i>

            </div>


            <div class="stat-content">

                <strong>
                    Terintegrasi
                </strong>

                <span>
                    Pengguna & Petugas
                </span>

            </div>

        </div>


    </div>

</section>



<!-- =========================================================
     TENTANG
========================================================= -->

<section
    class="section"
    id="tentang">


    <div class="container">


        <div class="section-heading">


            <span class="section-label">
                Tentang Sistem
            </span>


            <h2>
                Pengaduan Fasilitas Jadi Lebih Mudah
            </h2>


            <p>
                Sistem dirancang untuk membantu civitas kampus
                menyampaikan laporan fasilitas secara lebih
                mudah, terstruktur, dan transparan.
            </p>


        </div>


        <div class="features-grid">


            <div class="feature-card">


                <div class="feature-icon">

                    <i data-lucide="file-plus-2"></i>

                </div>


                <h3>
                    Pelaporan Mudah
                </h3>


                <p>
                    Buat laporan kerusakan melalui
                    formulir yang sederhana dan mudah dipahami.
                </p>


            </div>


            <div class="feature-card">


                <div class="feature-icon">

                    <i data-lucide="image-plus"></i>

                </div>


                <h3>
                    Upload Foto
                </h3>


                <p>
                    Sertakan foto kondisi fasilitas
                    sebagai bukti pendukung laporan.
                </p>


            </div>


            <div class="feature-card">


                <div class="feature-icon">

                    <i data-lucide="map-pin"></i>

                </div>


                <h3>
                    Lokasi Jelas
                </h3>


                <p>
                    Sertakan lokasi fasilitas agar
                    petugas dapat menemukan lokasi dengan mudah.
                </p>


            </div>


            <div class="feature-card">


                <div class="feature-icon">

                    <i data-lucide="activity"></i>

                </div>


                <h3>
                    Pantau Status
                </h3>


                <p>
                    Pantau laporan mulai dari menunggu,
                    diproses, hingga selesai.
                </p>


            </div>


        </div>

    </div>

</section>



<!-- =========================================================
     FASILITAS
========================================================= -->

<section
    class="section facility-section"
    id="fasilitas">


    <div class="container">


        <div class="section-heading">


            <span class="section-label">
                Kategori Pengaduan
            </span>


            <h2>
                Fasilitas yang Dapat Dilaporkan
            </h2>


            <p>
                Berbagai fasilitas kampus dapat dilaporkan
                melalui sistem sesuai dengan kategori yang tersedia.
            </p>


        </div>


        <div class="swiper facility-slider">


            <div class="swiper-wrapper">


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="school"></i>

                        </div>

                        <h3>
                            Ruang Kelas
                        </h3>

                        <p>
                            Kerusakan ruang kelas,
                            pintu, jendela, papan tulis,
                            dan fasilitas pembelajaran.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="armchair"></i>

                        </div>

                        <h3>
                            Meja & Kursi
                        </h3>

                        <p>
                            Laporkan meja atau kursi
                            yang rusak dan tidak layak digunakan.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="snowflake"></i>

                        </div>

                        <h3>
                            AC
                        </h3>

                        <p>
                            Laporkan AC yang tidak dingin,
                            bocor, atau mengalami kerusakan.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="bath"></i>

                        </div>

                        <h3>
                            Toilet
                        </h3>

                        <p>
                            Laporkan kerusakan toilet,
                            wastafel, atau saluran air.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="zap"></i>

                        </div>

                        <h3>
                            Listrik
                        </h3>

                        <p>
                            Laporkan masalah lampu,
                            stop kontak, atau instalasi listrik.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="presentation"></i>

                        </div>

                        <h3>
                            Proyektor
                        </h3>

                        <p>
                            Laporkan perangkat presentasi
                            yang mengalami masalah.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="wifi"></i>

                        </div>

                        <h3>
                            Jaringan / Internet
                        </h3>

                        <p>
                            Laporkan koneksi internet
                            atau jaringan kampus yang bermasalah.
                        </p>

                    </div>

                </div>


                <div class="swiper-slide">

                    <div class="facility-card">

                        <div class="facility-icon">

                            <i data-lucide="sparkles"></i>

                        </div>

                        <h3>
                            Kebersihan
                        </h3>

                        <p>
                            Laporkan kondisi fasilitas
                            yang membutuhkan penanganan.
                        </p>

                    </div>

                </div>


            </div>


            <div class="swiper-button-prev"></div>

            <div class="swiper-button-next"></div>

            <div class="swiper-pagination"></div>


        </div>

    </div>

</section>



<!-- =========================================================
     CARA MELAPOR
========================================================= -->

<section
    class="section"
    id="cara-melapor">


    <div class="container">


        <div class="section-heading">


            <span class="section-label">
                Cara Melapor
            </span>


            <h2>
                Empat Langkah Sederhana
            </h2>


            <p>
                Proses pelaporan dibuat sederhana
                agar dapat digunakan dengan mudah.
            </p>


        </div>


        <div class="steps-grid">


            <div class="step-card">


                <span class="step-number">
                    01
                </span>


                <div class="step-icon">

                    <i data-lucide="log-in"></i>

                </div>


                <h3>
                    Login
                </h3>


                <p>
                    Masuk menggunakan akun
                    yang telah terdaftar.
                </p>


            </div>


            <div class="step-card">


                <span class="step-number">
                    02
                </span>


                <div class="step-icon">

                    <i data-lucide="file-edit"></i>

                </div>


                <h3>
                    Buat Laporan
                </h3>


                <p>
                    Isi kategori, lokasi,
                    dan deskripsi kerusakan.
                </p>


            </div>


            <div class="step-card">


                <span class="step-number">
                    03
                </span>


                <div class="step-icon">

                    <i data-lucide="camera"></i>

                </div>


                <h3>
                    Upload Bukti
                </h3>


                <p>
                    Tambahkan foto kondisi
                    fasilitas sebagai bukti.
                </p>


            </div>


            <div class="step-card">


                <span class="step-number">
                    04
                </span>


                <div class="step-icon">

                    <i data-lucide="check-circle-2"></i>

                </div>


                <h3>
                    Pantau
                </h3>


                <p>
                    Pantau laporan hingga
                    proses penanganan selesai.
                </p>


            </div>


        </div>

    </div>

</section>



<!-- =========================================================
     CTA
========================================================= -->

<section class="cta">


    <div class="container">


        <div class="cta-inner">


            <div class="cta-content">


                <small>
                    Ada fasilitas yang bermasalah?
                </small>


                <h2>
                    Yuk, laporkan sekarang.
                </h2>


                <p>
                    Bantu menciptakan lingkungan kampus
                    yang nyaman dengan melaporkan fasilitas
                    yang mengalami kerusakan.
                </p>


            </div>


            <a
                href="login.php"
                class="cta-button">

                <i data-lucide="send"></i>

                Mulai Melapor

            </a>


        </div>

    </div>

</section>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">


    <div class="container footer-inner">


        <div class="footer-brand">

            <i data-lucide="building-2"></i>

            ADUIN

        </div>
        


        <div class="footer-copy">

            &copy;
            <?= date("Y"); ?>

            Platform Aduan Fasilitas Kampus.

        </div>


    </div>

</footer>



<!-- =========================================================
     SWIPER JS
========================================================= -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js">
</script>


<!-- =========================================================
     GSAP
========================================================= -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js">
</script>


<script nonce="<?= e(cspNonce()) ?>"
    src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js">
</script>


<!-- =========================================================
     LENIS JS
========================================================= -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js">
</script>



<script nonce="<?= e(cspNonce()) ?>">

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =====================================================
           LUCIDE
        ===================================================== */

        lucide.createIcons();



        /* =====================================================
           GSAP + SCROLLTRIGGER
        ===================================================== */

        gsap.registerPlugin(
            ScrollTrigger
        );



        /* =====================================================
           LENIS
           
           Lenis digunakan sebagai smooth scrolling engine.
           RAF Lenis dijalankan melalui GSAP ticker supaya
           sinkron dengan ScrollTrigger.
        ===================================================== */

        const lenis =
            new Lenis({

                duration: 1.05,

                smoothWheel: true,

                syncTouch: false,

                touchMultiplier: 1.2,

                wheelMultiplier: 0.9

            });


        lenis.on(
            "scroll",
            ScrollTrigger.update
        );


        gsap.ticker.add(
            function (time) {

                lenis.raf(
                    time * 1000
                );

            }
        );


        gsap.ticker.lagSmoothing(
            0
        );



        /* =====================================================
           ANCHOR LINK + LENIS
        ===================================================== */

        document
            .querySelectorAll(
                'a[href^="#"]'
            )
            .forEach(
                function (link) {


                    link.addEventListener(
                        "click",
                        function (event) {

                            const targetId =
                                link.getAttribute(
                                    "href"
                                );


                            if (
                                targetId ===
                                "#"
                            ) {

                                return;

                            }


                            const target =
                                document.querySelector(
                                    targetId
                                );


                            if (
                                !target
                            ) {

                                return;

                            }


                            event.preventDefault();


                            lenis.scrollTo(
                                target,
                                {

                                    offset:
                                        -80,

                                    duration:
                                        1.2,

                                    easing:
                                        function (t) {

                                            return 1 -
                                                Math.pow(
                                                    1 - t,
                                                    4
                                                );

                                        }

                                }
                            );

                        }
                    );


                }
            );



        /* =====================================================
           HERO ENTRANCE
        ===================================================== */

        const heroTimeline =
            gsap.timeline({

                defaults: {

                    ease:
                        "power2.out"

                }

            });


        heroTimeline

            .from(
                ".navbar .brand",
                {

                    y:
                        -15,

                    opacity:
                        0,

                    duration:
                        .5

                }
            )

            .from(
                ".nav-menu a",
                {

                    y:
                        -10,

                    opacity:
                        0,

                    duration:
                        .35,

                    stagger:
                        .06

                },
                "-=.25"
            )

            .from(
                ".login-btn",
                {

                    y:
                        -10,

                    opacity:
                        0,

                    duration:
                        .35

                },
                "-=.25"
            )

            .from(
                ".hero-badge",
                {

                    y:
                        20,

                    opacity:
                        0,

                    duration:
                        .5

                }
            )

            .from(
                ".hero-title",
                {

                    y:
                        30,

                    opacity:
                        0,

                    duration:
                        .65

                },
                "-=.25"
            )

            .from(
                ".hero-description",
                {

                    y:
                        20,

                    opacity:
                        0,

                    duration:
                        .5

                },
                "-=.3"
            )

            .from(
                ".hero-actions",
                {

                    y:
                        15,

                    opacity:
                        0,

                    duration:
                        .45

                },
                "-=.25"
            )

            .from(
                ".hero-trust",
                {

                    y:
                        10,

                    opacity:
                        0,

                    duration:
                        .4

                },
                "-=.2"
            )

            .from(
                ".hero-dashboard",
                {

                    x:
                        45,

                    opacity:
                        0,

                    scale:
                        .96,

                    duration:
                        .75

                },
                "-=.55"
            )

            .from(
                ".notification-card",
                {

                    y:
                        20,

                    opacity:
                        0,

                    duration:
                        .4

                },
                "-=.25"
            );



        /* =====================================================
           DASHBOARD FLOAT
        ===================================================== */

        gsap.to(
            ".dashboard-card",
            {

                y:
                    -6,

                duration:
                    3,

                repeat:
                    -1,

                yoyo:
                    true,

                ease:
                    "sine.inOut"

            }
        );



        /* =====================================================
           STATISTICS
        ===================================================== */

        gsap.fromTo(

            ".stat-card",

            {

                y:
                    25,

                opacity:
                    0

            },

            {

                y:
                    0,

                opacity:
                    1,

                duration:
                    .55,

                stagger:
                    .1,

                ease:
                    "power2.out",

                scrollTrigger: {

                    trigger:
                        ".stat-grid",

                    start:
                        "top 88%",

                    once:
                        true

                }

            }

        );



        /* =====================================================
           SECTION HEADINGS
        ===================================================== */

        gsap.utils
            .toArray(
                ".section-heading"
            )
            .forEach(
                function (element) {


                    gsap.fromTo(

                        element,

                        {

                            y:
                                30,

                            opacity:
                                0

                        },

                        {

                            y:
                                0,

                            opacity:
                                1,

                            duration:
                                .65,

                            ease:
                                "power2.out",

                            scrollTrigger: {

                                trigger:
                                    element,

                                start:
                                    "top 88%",

                                once:
                                    true

                            }

                        }

                    );


                }
            );



        /* =====================================================
           FEATURE CARDS
        ===================================================== */

        gsap.fromTo(

            ".feature-card",

            {

                y:
                    30,

                opacity:
                    0

            },

            {

                y:
                    0,

                opacity:
                    1,

                duration:
                    .55,

                stagger:
                    .1,

                ease:
                    "power2.out",

                scrollTrigger: {

                    trigger:
                        ".features-grid",

                    start:
                        "top 85%",

                    once:
                        true

                }

            }

        );



        /* =====================================================
           STEP CARDS
        ===================================================== */

        gsap.fromTo(

            ".step-card",

            {

                y:
                    30,

                opacity:
                    0

            },

            {

                y:
                    0,

                opacity:
                    1,

                duration:
                    .55,

                stagger:
                    .1,

                ease:
                    "power2.out",

                scrollTrigger: {

                    trigger:
                        ".steps-grid",

                    start:
                        "top 85%",

                    once:
                        true

                }

            }

        );



        /* =====================================================
           CTA
        ===================================================== */

        gsap.fromTo(

            ".cta-inner",

            {

                y:
                    30,

                opacity:
                    0

            },

            {

                y:
                    0,

                opacity:
                    1,

                duration:
                    .7,

                ease:
                    "power2.out",

                scrollTrigger: {

                    trigger:
                        ".cta-inner",

                    start:
                        "top 88%",

                    once:
                        true

                }

            }

        );



        /* =====================================================
           SWIPER
        ===================================================== */

        new Swiper(

            ".facility-slider",

            {

                loop:
                    true,

                grabCursor:
                    true,

                speed:
                    700,

                spaceBetween:
                    15,

                autoplay: {

                    delay:
                        3000,

                    disableOnInteraction:
                        false,

                    pauseOnMouseEnter:
                        true

                },

                pagination: {

                    el:
                        ".swiper-pagination",

                    clickable:
                        true

                },

                navigation: {

                    nextEl:
                        ".swiper-button-next",

                    prevEl:
                        ".swiper-button-prev"

                },

                breakpoints: {

                    0: {

                        slidesPerView:
                            1

                    },

                    600: {

                        slidesPerView:
                            2

                    },

                    900: {

                        slidesPerView:
                            3

                    },

                    1150: {

                        slidesPerView:
                            4

                    }

                }

            }

        );



        /* =====================================================
           NAVBAR SCROLL
        ===================================================== */

        const navbar =
            document.getElementById(
                "navbar"
            );


        lenis.on(
            "scroll",
            function (event) {

                if (
                    event.animatedScroll >
                    30
                ) {

                    navbar.classList.add(
                        "scrolled"
                    );

                } else {

                    navbar.classList.remove(
                        "scrolled"
                    );

                }

            }
        );



        /* =====================================================
           MOBILE MENU
        ===================================================== */

        const mobileMenuBtn =
            document.getElementById(
                "mobileMenuBtn"
            );

        const mobileMenu =
            document.getElementById(
                "mobileMenu"
            );

        const mobileMenuLinks =
            document.querySelectorAll(
                ".mobile-menu-link, .mobile-menu-login"
            );


        function setMobileMenu(open) {

            if (!mobileMenuBtn || !mobileMenu) {
                return;
            }

            mobileMenu.classList.toggle(
                "open",
                open
            );

            document.body.classList.toggle(
                "mobile-menu-open",
                open
            );

            mobileMenuBtn.setAttribute(
                "aria-expanded",
                open ? "true" : "false"
            );

            mobileMenuBtn.setAttribute(
                "aria-label",
                open
                    ? "Tutup menu navigasi"
                    : "Buka menu navigasi"
            );

            mobileMenuBtn.innerHTML =
                open
                    ? '<i data-lucide="x"></i>'
                    : '<i data-lucide="menu"></i>';

            if (window.lucide) {
                lucide.createIcons();
            }

            if (open) {

                gsap.fromTo(
                    ".mobile-menu-link, .mobile-menu-login",
                    {
                        y: -8,
                        opacity: 0
                    },
                    {
                        y: 0,
                        opacity: 1,
                        duration: .25,
                        stagger: .045,
                        ease: "power2.out"
                    }
                );

            }
        }


        if (mobileMenuBtn) {

            mobileMenuBtn.addEventListener(
                "click",
                function () {

                    const isOpen =
                        mobileMenu.classList.contains(
                            "open"
                        );

                    setMobileMenu(!isOpen);

                }
            );

        }


        mobileMenuLinks.forEach(
            function (link) {

                link.addEventListener(
                    "click",
                    function () {

                        setMobileMenu(false);

                    }
                );

            }
        );


        window.addEventListener(
            "resize",
            function () {

                if (window.innerWidth > 760) {
                    setMobileMenu(false);
                }

            }
        );


        /* =====================================================
           BUTTON HOVER
        ===================================================== */

        document
            .querySelectorAll(
                ".hero-button, .login-btn, .cta-button"
            )
            .forEach(
                function (button) {


                    button.addEventListener(
                        "mouseenter",
                        function () {

                            gsap.to(
                                button,
                                {

                                    y:
                                        -2,

                                    duration:
                                        .2,

                                    ease:
                                        "power2.out"

                                }
                            );

                        }
                    );


                    button.addEventListener(
                        "mouseleave",
                        function () {

                            gsap.to(
                                button,
                                {

                                    y:
                                        0,

                                    duration:
                                        .2,

                                    ease:
                                        "power2.out"

                                }
                            );

                        }
                    );


                }
            );



        /* =====================================================
           REFRESH SCROLLTRIGGER
        ===================================================== */

        setTimeout(
            function () {

                ScrollTrigger.refresh();

            },
            700
        );


    }
);

</script>


</body>

</html>