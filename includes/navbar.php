<?php

/* =========================================================
   DEFAULT VALUE
========================================================= */

$basePath = $basePath ?? '';

$activePage = $activePage ?? '';

currentUser();
$role = $_SESSION['role'] ?? '';

$namaUser = $_SESSION['nama'] ?? '';


/* =========================================================
   HELPER ACTIVE
========================================================= */

function navbarActive($page, $activePage)
{
    return $page === $activePage
        ? 'active'
        : '';
}

?>

<?php if (in_array($role, ['admin', 'petugas', 'pengguna'], true)): ?>
<form id="aduinLogoutForm" action="<?= e($basePath) ?>logout.php" method="POST" hidden>
    <?= csrfField() ?>
</form>
<?php endif; ?>




<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="navbar-inner">


        <!-- =====================================================
             BRAND
        ===================================================== -->

        <a
            href="<?= $basePath ?>index.php"
            class="brand"
        >

            <div class="brand-logo">

                <img
                    src="<?= $basePath ?>assets/img/logo-aduin.png"
                    alt="Logo ADUIN"
                >

            </div>


            <div class="brand-text">

                <div class="brand-name">
                    ADUIN
                </div>

                <div class="brand-subtitle">
                    Platform Aduan Fasilitas Kampus
                </div>

            </div>

        </a>



        <!-- =====================================================
             PUBLIC NAV
        ===================================================== -->

        <?php if ($role === ''): ?>

            <div class="nav-menu">

                <a
                    href="<?= $basePath ?>index.php"
                    class="<?= navbarActive('home', $activePage) ?>"
                >
                    Beranda
                </a>


                <a
                    href="<?= $basePath ?>index.php#tentang"
                    class="<?= navbarActive('tentang', $activePage) ?>"
                >
                    Tentang
                </a>


                <a
                    href="<?= $basePath ?>index.php#fasilitas"
                    class="<?= navbarActive('fasilitas', $activePage) ?>"
                >
                    Fasilitas
                </a>


                <a
                    href="<?= $basePath ?>index.php#cara-melapor"
                    class="<?= navbarActive('cara-melapor', $activePage) ?>"
                >
                    Cara Melapor
                </a>


                <a
                    href="<?= $basePath ?>tracking.php"
                    class="<?= navbarActive('tracking', $activePage) ?>"
                >
                    Lacak Pengaduan
                </a>

            </div>


            <a
                href="<?= $basePath ?>login.php"
                class="login-btn"
            >

                <i data-lucide="log-in"></i>

                <span>
                    Login
                </span>

            </a>


        <?php endif; ?>



        <!-- =====================================================
             ADMIN NAV
        ===================================================== -->

        <?php if ($role === 'admin'): ?>

            <div class="nav-menu">

                <a
                    href="<?= $basePath ?>admin/dashboard.php"
                    class="<?= navbarActive('dashboard', $activePage) ?>"
                >
                    Dashboard
                </a>


                <a
                    href="<?= $basePath ?>admin/pengaduan.php"
                    class="<?= navbarActive('pengaduan', $activePage) ?>"
                >
                    Pengaduan
                </a>


                <a
                    href="<?= $basePath ?>admin/laporan-bulanan.php"
                    class="<?= navbarActive('laporan-bulanan', $activePage) ?>"
                >
                    Laporan Bulanan
                </a>


                <a
                    href="<?= $basePath ?>admin/pengguna.php"
                    class="<?= navbarActive('pengguna', $activePage) ?>"
                >
                    Pengguna
                </a>


                <a
                    href="<?= $basePath ?>admin/petugas.php"
                    class="<?= navbarActive('petugas', $activePage) ?>"
                >
                    Petugas
                </a>

            </div>


            <div class="navbar-user">

                <span class="navbar-user-name">

                    <?= htmlspecialchars(
                        $namaUser
                    ) ?>

                </span>


                <a
                    href="<?= $basePath ?>logout.php" data-logout
                    class="navbar-logout"
                >

                    <i data-lucide="log-out"></i>

                    <span>
                        Logout
                    </span>

                </a>

            </div>

        <?php endif; ?>



        <!-- =====================================================
             PETUGAS NAV
        ===================================================== -->

        <?php if ($role === 'petugas'): ?>

            <div class="nav-menu">

                <a
                    href="<?= $basePath ?>petugas/dashboard.php"
                    class="<?= navbarActive('dashboard', $activePage) ?>"
                >
                    Dashboard
                </a>


                <a
                    href="<?= $basePath ?>petugas/tugas.php"
                    class="<?= navbarActive('tugas', $activePage) ?>"
                >
                    Tugas Pengaduan
                </a>


                <a
                    href="<?= $basePath ?>petugas/riwayat.php"
                    class="<?= navbarActive('riwayat', $activePage) ?>"
                >
                    Riwayat
                </a>


                <a
                    href="<?= $basePath ?>petugas/profil.php"
                    class="<?= navbarActive('profil', $activePage) ?>"
                >
                    Profil
                </a>

            </div>


            <div class="navbar-user">

                <span class="navbar-user-name">

                    <?= htmlspecialchars(
                        $namaUser
                    ) ?>

                </span>


                <a
                    href="<?= $basePath ?>logout.php" data-logout
                    class="navbar-logout"
                >

                    <i data-lucide="log-out"></i>

                    <span>
                        Logout
                    </span>

                </a>

            </div>

        <?php endif; ?>



        <!-- =====================================================
             USER NAV
        ===================================================== -->

        <?php if ($role === 'pengguna'): ?>

            <div class="nav-menu">

                <a
                    href="<?= $basePath ?>user/dashboard.php"
                    class="<?= navbarActive('dashboard', $activePage) ?>"
                >
                    Dashboard
                </a>


                <a
                    href="<?= $basePath ?>user/buat-pengaduan.php"
                    class="<?= navbarActive('buat-pengaduan', $activePage) ?>"
                >
                    Buat Pengaduan
                </a>


                <a
                    href="<?= $basePath ?>user/riwayat.php"
                    class="<?= navbarActive('riwayat', $activePage) ?>"
                >
                    Riwayat Pengaduan
                </a>

            </div>


            <div class="navbar-user">

                <span class="navbar-user-name">

                    <?= htmlspecialchars(
                        $namaUser
                    ) ?>

                </span>


                <a
                    href="<?= $basePath ?>logout.php" data-logout
                    class="navbar-logout"
                >

                    <i data-lucide="log-out"></i>

                    <span>
                        Logout
                    </span>

                </a>

            </div>

        <?php endif; ?>



        <!-- =====================================================
             MOBILE BUTTON
        ===================================================== -->

        <button
            type="button"
            class="mobile-menu-btn"
            aria-label="Buka menu"
            aria-expanded="false"
        >

            <i data-lucide="menu"></i>

        </button>

    </div>

</nav>



<!-- =========================================================
     MOBILE MENU
========================================================= -->

<div class="mobile-menu">

    <div class="mobile-menu-inner">


        <!-- =====================================================
             PUBLIC MOBILE
        ===================================================== -->

        <?php if ($role === ''): ?>

            <a
                href="<?= $basePath ?>index.php"
                class="mobile-menu-link <?= navbarActive('home', $activePage) ?>"
            >

                <i data-lucide="house"></i>

                <span>
                    Beranda
                </span>

            </a>


            <a
                href="<?= $basePath ?>index.php#tentang"
                class="mobile-menu-link <?= navbarActive('tentang', $activePage) ?>"
            >

                <i data-lucide="info"></i>

                <span>
                    Tentang
                </span>

            </a>


            <a
                href="<?= $basePath ?>index.php#fasilitas"
                class="mobile-menu-link <?= navbarActive('fasilitas', $activePage) ?>"
            >

                <i data-lucide="building-2"></i>

                <span>
                    Fasilitas
                </span>

            </a>


            <a
                href="<?= $basePath ?>index.php#cara-melapor"
                class="mobile-menu-link <?= navbarActive('cara-melapor', $activePage) ?>"
            >

                <i data-lucide="circle-help"></i>

                <span>
                    Cara Melapor
                </span>

            </a>


            <a
                href="<?= $basePath ?>tracking.php"
                class="mobile-menu-link <?= navbarActive('tracking', $activePage) ?>"
            >

                <i data-lucide="search"></i>

                <span>
                    Lacak Pengaduan
                </span>

            </a>


            <div class="mobile-menu-divider"></div>


            <a
                href="<?= $basePath ?>login.php"
                class="mobile-menu-login"
            >

                <i data-lucide="log-in"></i>

                <span>
                    Login
                </span>

            </a>

        <?php endif; ?>



        <!-- =====================================================
             USER MOBILE
        ===================================================== -->

        <?php if ($role === 'pengguna'): ?>

            <a
                href="<?= $basePath ?>user/dashboard.php"
                class="mobile-menu-link <?= navbarActive('dashboard', $activePage) ?>"
            >

                <i data-lucide="layout-dashboard"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="<?= $basePath ?>user/buat-pengaduan.php"
                class="mobile-menu-link <?= navbarActive('buat-pengaduan', $activePage) ?>"
            >

                <i data-lucide="file-plus-2"></i>

                <span>
                    Buat Pengaduan
                </span>

            </a>


            <a
                href="<?= $basePath ?>user/riwayat.php"
                class="mobile-menu-link <?= navbarActive('riwayat', $activePage) ?>"
            >

                <i data-lucide="history"></i>

                <span>
                    Riwayat Pengaduan
                </span>

            </a>


            <div class="mobile-menu-divider"></div>


            <a
                href="<?= $basePath ?>logout.php" data-logout
                class="mobile-menu-login"
            >

                <i data-lucide="log-out"></i>

                <span>
                    Logout
                </span>

            </a>

        <?php endif; ?>



        <!-- =====================================================
             ADMIN MOBILE
        ===================================================== -->

        <?php if ($role === 'admin'): ?>

            <a
                href="<?= $basePath ?>admin/dashboard.php"
                class="mobile-menu-link <?= navbarActive('dashboard', $activePage) ?>"
            >

                <i data-lucide="layout-dashboard"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="<?= $basePath ?>admin/pengaduan.php"
                class="mobile-menu-link <?= navbarActive('pengaduan', $activePage) ?>"
            >

                <i data-lucide="file-text"></i>

                <span>
                    Pengaduan
                </span>

            </a>


            <a
                href="<?= $basePath ?>admin/laporan-bulanan.php"
                class="mobile-menu-link <?= navbarActive('laporan-bulanan', $activePage) ?>"
            >
                <i data-lucide="calendar-days"></i>
                <span>Laporan Bulanan</span>
            </a>


            <a
                href="<?= $basePath ?>admin/pengguna.php"
                class="mobile-menu-link <?= navbarActive('pengguna', $activePage) ?>"
            >

                <i data-lucide="users"></i>

                <span>
                    Pengguna
                </span>

            </a>


            <a
                href="<?= $basePath ?>admin/petugas.php"
                class="mobile-menu-link <?= navbarActive('petugas', $activePage) ?>"
            >

                <i data-lucide="user-cog"></i>

                <span>
                    Petugas
                </span>

            </a>


            <div class="mobile-menu-divider"></div>


            <a
                href="<?= $basePath ?>logout.php" data-logout
                class="mobile-menu-login"
            >

                <i data-lucide="log-out"></i>

                <span>
                    Logout
                </span>

            </a>

        <?php endif; ?>



        <!-- =====================================================
             PETUGAS MOBILE
        ===================================================== -->

        <?php if ($role === 'petugas'): ?>

            <a
                href="<?= $basePath ?>petugas/dashboard.php"
                class="mobile-menu-link <?= navbarActive('dashboard', $activePage) ?>"
            >

                <i data-lucide="layout-dashboard"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="<?= $basePath ?>petugas/tugas.php"
                class="mobile-menu-link <?= navbarActive('tugas', $activePage) ?>"
            >

                <i data-lucide="clipboard-list"></i>

                <span>
                    Tugas Pengaduan
                </span>

            </a>


            <a
                href="<?= $basePath ?>petugas/riwayat.php"
                class="mobile-menu-link <?= navbarActive('riwayat', $activePage) ?>"
            >

                <i data-lucide="history"></i>

                <span>
                    Riwayat
                </span>

            </a>


            <a
                href="<?= $basePath ?>petugas/profil.php"
                class="mobile-menu-link <?= navbarActive('profil', $activePage) ?>"
            >

                <i data-lucide="user-round"></i>

                <span>
                    Profil
                </span>

            </a>


            <div class="mobile-menu-divider"></div>


            <a
                href="<?= $basePath ?>logout.php" data-logout
                class="mobile-menu-login"
            >

                <i data-lucide="log-out"></i>

                <span>
                    Logout
                </span>

            </a>

        <?php endif; ?>

    </div>

</div>



<script nonce="<?= e(cspNonce()) ?>">

/* =========================================================
   ADUIN NAVBAR
   MOBILE MENU CONTROLLER
========================================================= */

(function () {
    document.addEventListener('change', function (event) {
        const filter = event.target.closest('select[data-submit-filter]');
        if (filter && filter.form) filter.form.submit();
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-logout]');
        if (!link) return;
        const form = document.getElementById('aduinLogoutForm');
        if (!form) return;
        event.preventDefault();
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    });

    function initADUINMobileMenu() {

        const button =
            document.querySelector(
                ".mobile-menu-btn"
            );

        const menu =
            document.querySelector(
                ".mobile-menu"
            );


        if (!button || !menu) {
            return;
        }


        /* Jangan inisialisasi dua kali */

        if (
            button.dataset.menuReady === "true"
        ) {
            return;
        }


        button.dataset.menuReady = "true";


        /* =====================================================
           OPEN
        ===================================================== */

        function openMenu() {

            menu.classList.add(
                "open"
            );


            button.setAttribute(
                "aria-expanded",
                "true"
            );


            button.setAttribute(
                "aria-label",
                "Tutup menu"
            );


            button.innerHTML = `
                <i data-lucide="x"></i>
            `;


            if (window.lucide) {

                lucide.createIcons();

            }

        }



        /* =====================================================
           CLOSE
        ===================================================== */

        function closeMenu() {

            menu.classList.remove(
                "open"
            );


            button.setAttribute(
                "aria-expanded",
                "false"
            );


            button.setAttribute(
                "aria-label",
                "Buka menu"
            );


            button.innerHTML = `
                <i data-lucide="menu"></i>
            `;


            if (window.lucide) {

                lucide.createIcons();

            }

        }



        /* =====================================================
           TOGGLE
        ===================================================== */

        button.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                event.stopPropagation();


                if (
                    menu.classList.contains(
                        "open"
                    )
                ) {

                    closeMenu();

                } else {

                    openMenu();

                }

            }
        );



        /* =====================================================
           CLICK LINK
        ===================================================== */

        menu.addEventListener(
            "click",
            function (event) {

                const link =
                    event.target.closest(
                        "a"
                    );


                if (!link) {
                    return;
                }


                closeMenu();

            }
        );



        /* =====================================================
           CLICK OUTSIDE
        ===================================================== */

        document.addEventListener(
            "click",
            function (event) {

                if (
                    !menu.classList.contains(
                        "open"
                    )
                ) {
                    return;
                }


                if (
                    !menu.contains(
                        event.target
                    ) &&
                    !button.contains(
                        event.target
                    )
                ) {

                    closeMenu();

                }

            }
        );



        /* =====================================================
           ESC
        ===================================================== */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Escape" &&
                    menu.classList.contains(
                        "open"
                    )
                ) {

                    closeMenu();

                }

            }
        );



        /* =====================================================
           RESIZE
        ===================================================== */

        window.addEventListener(
            "resize",
            function () {

                if (
                    window.innerWidth > 900
                ) {

                    closeMenu();

                }

            }
        );


        /* =====================================================
           INITIAL
        ===================================================== */

        button.setAttribute(
            "aria-expanded",
            "false"
        );

        button.setAttribute(
            "aria-label",
            "Buka menu"
        );

    }



    /* =========================================================
       DOM READY
    ========================================================= */

    if (
        document.readyState ===
        "loading"
    ) {

        document.addEventListener(
            "DOMContentLoaded",
            initADUINMobileMenu
        );

    } else {

        initADUINMobileMenu();

    }

})();

</script>
