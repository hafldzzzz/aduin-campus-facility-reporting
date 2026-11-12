/* =========================================================
   ADUIN - SCRIPT.JS
   Shared Script
========================================================= */

/* =========================================================
   DOM READY
========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  /* =====================================================
       LUCIDE ICON
    ===================================================== */

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  /* =====================================================
       NAVBAR ELEMENT
    ===================================================== */

  const navbar = document.getElementById("navbar");

  /* =====================================================
       MOBILE NAVBAR ELEMENT
    ===================================================== */

  const mobileMenuBtn = document.getElementById("mobileMenuBtn");

  const mobileMenu = document.getElementById("mobileMenu");

  /* =====================================================
       NAVBAR SCROLL EFFECT
    ===================================================== */

  if (navbar) {
    function updateNavbar() {
      if (window.scrollY > 25) {
        navbar.classList.add("scrolled");
      } else {
        navbar.classList.remove("scrolled");
      }
    }

    updateNavbar();

    window.addEventListener("scroll", updateNavbar, {
      passive: true,
    });
  }

  /* =====================================================
       MOBILE MENU
    ===================================================== */

  if (mobileMenuBtn && mobileMenu) {
    /* -----------------------------------------------
           Fungsi membuka menu
        ------------------------------------------------ */

    function openMobileMenu() {
      mobileMenu.classList.add("open");

      mobileMenuBtn.setAttribute("aria-expanded", "true");

      mobileMenuBtn.setAttribute("aria-label", "Tutup menu navigasi");

      mobileMenuBtn.innerHTML = '<i data-lucide="x"></i>';

      if (typeof lucide !== "undefined") {
        lucide.createIcons();
      }
    }

    /* -----------------------------------------------
           Fungsi menutup menu
        ------------------------------------------------ */

    function closeMobileMenu() {
      mobileMenu.classList.remove("open");

      mobileMenuBtn.setAttribute("aria-expanded", "false");

      mobileMenuBtn.setAttribute("aria-label", "Buka menu navigasi");

      mobileMenuBtn.innerHTML = '<i data-lucide="menu"></i>';

      if (typeof lucide !== "undefined") {
        lucide.createIcons();
      }
    }

    /* -----------------------------------------------
           Fungsi toggle
        ------------------------------------------------ */

    function toggleMobileMenu(event) {
      if (event) {
        event.preventDefault();

        event.stopPropagation();
      }

      const isOpen = mobileMenu.classList.contains("open");

      if (isOpen) {
        closeMobileMenu();
      } else {
        openMobileMenu();
      }
    }

    /* -----------------------------------------------
           CLICK HAMBURGER
        ------------------------------------------------ */

    mobileMenuBtn.addEventListener("click", toggleMobileMenu);

    /* -----------------------------------------------
           LINK DI MOBILE MENU
        ------------------------------------------------ */

    const mobileMenuLinks = mobileMenu.querySelectorAll("a");

    mobileMenuLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        closeMobileMenu();
      });
    });

    /* -----------------------------------------------
           CLICK DI LUAR MENU
        ------------------------------------------------ */

    document.addEventListener("click", function (event) {
      const clickedInsideButton = mobileMenuBtn.contains(event.target);

      const clickedInsideMenu = mobileMenu.contains(event.target);

      if (!clickedInsideButton && !clickedInsideMenu) {
        closeMobileMenu();
      }
    });

    /* -----------------------------------------------
           ESC KEY
        ------------------------------------------------ */

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeMobileMenu();
      }
    });

    /* -----------------------------------------------
           RESIZE
        ------------------------------------------------ */

    window.addEventListener("resize", function () {
      if (window.innerWidth > 900) {
        closeMobileMenu();
      }
    });
  }

  /* =====================================================
       PUBLIC NAVBAR ACTIVE SECTION
    ===================================================== */

  const desktopSectionLinks = document.querySelectorAll(
    ".nav-menu a[data-section]",
  );

  const mobileSectionLinks = document.querySelectorAll(
    ".mobile-menu-link[data-section]",
  );

  const allSectionLinks = [...desktopSectionLinks, ...mobileSectionLinks];

  const sections = [
    document.getElementById("beranda"),
    document.getElementById("tentang"),
    document.getElementById("fasilitas"),
    document.getElementById("cara-melapor"),
  ].filter(Boolean);

  /*
    |--------------------------------------------------------------------------
    | Jalankan hanya kalau halaman mempunyai section public
    |--------------------------------------------------------------------------
    */

  if (allSectionLinks.length > 0 && sections.length > 0) {
    /* -----------------------------------------------
           Set active menu
        ------------------------------------------------ */

    function setActiveSection(sectionId) {
      allSectionLinks.forEach(function (link) {
        link.classList.remove("active");
      });

      allSectionLinks.forEach(function (link) {
        const linkSection = link.getAttribute("data-section");

        if (linkSection === sectionId) {
          link.classList.add("active");
        }
      });
    }

    /* -----------------------------------------------
           Klik menu public
        ------------------------------------------------ */

    allSectionLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        const sectionId = this.getAttribute("data-section");

        if (sectionId) {
          setActiveSection(sectionId);
        }
      });
    });

    /* -----------------------------------------------
           Active berdasarkan URL hash
        ------------------------------------------------ */

    function updateFromHash() {
      const hash = window.location.hash.replace("#", "");

      const validSection = sections.find(function (section) {
        return section.id === hash;
      });

      if (validSection) {
        setActiveSection(validSection.id);
      }
    }

    /* -----------------------------------------------
           Saat halaman pertama dibuka
        ------------------------------------------------ */

    if (window.location.hash) {
      updateFromHash();
    } else {
      setActiveSection("beranda");
    }

    /* -----------------------------------------------
           Ketika hash berubah
        ------------------------------------------------ */

    window.addEventListener("hashchange", updateFromHash);

    /* -----------------------------------------------
           Active berdasarkan scroll
        ------------------------------------------------ */

    function updateActiveOnScroll() {
      const scrollPosition = window.scrollY + 180;

      let currentSection = "beranda";

      sections.forEach(function (section) {
        if (scrollPosition >= section.offsetTop) {
          currentSection = section.id;
        }
      });

      setActiveSection(currentSection);
    }

    window.addEventListener("scroll", updateActiveOnScroll, {
      passive: true,
    });

    updateActiveOnScroll();
  }

  /* =====================================================
       RE-INITIALIZE LUCIDE
    ===================================================== */

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
});
