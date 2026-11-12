/* =========================================================
   D16 - INTERNAL PAGE ANIMATION
   GSAP + Lenis + Lucide
   Sistem Informasi Pengaduan Kampus
========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  /* =====================================================
       LUCIDE
    ===================================================== */

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  /* =====================================================
       GSAP + SCROLLTRIGGER
    ===================================================== */

  if (typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
    gsap.registerPlugin(ScrollTrigger);
  }

  /* =====================================================
       LENIS
    ===================================================== */

  let lenis = null;

  if (typeof Lenis !== "undefined") {
    lenis = new Lenis({
      duration: 1.05,
      smoothWheel: true,
      syncTouch: false,
      wheelMultiplier: 0.9,
      touchMultiplier: 1.2,
    });

    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }

    requestAnimationFrame(raf);
  }

  /* =====================================================
       PAGE TRANSITION
       
       PENTING:
       Hanya overlay yang bergerak.
       Navbar TIDAK digerakkan.
    ===================================================== */

  const transition = document.querySelector(".page-transition");

  if (transition && typeof gsap !== "undefined") {
    gsap.killTweensOf(transition);

    gsap.set(transition, {
      display: "block",
      opacity: 1,
    });

    gsap.to(transition, {
      opacity: 0,

      duration: 0.55,

      delay: 0.05,

      ease: "power2.out",

      onComplete: function () {
        transition.style.display = "none";
      },
    });
  } else if (transition) {
    transition.style.opacity = "0";

    setTimeout(function () {
      transition.style.display = "none";
    }, 600);
  }

  /* =====================================================
       INTERNAL PAGE HEADER
       
       Hanya untuk halaman yang menggunakan
       .page-header
    ===================================================== */

  if (typeof gsap !== "undefined") {
    const pageHeaders = document.querySelectorAll(".page-header");

    if (pageHeaders.length) {
      gsap.from(pageHeaders, {
        opacity: 0,

        y: 18,

        duration: 0.55,

        stagger: 0.06,

        delay: 0.12,

        ease: "power3.out",
      });
    }

    /* =================================================
           STAT CARD
        ================================================= */

    const statCards = document.querySelectorAll(".stat-card");

    if (statCards.length) {
      gsap.from(statCards, {
        opacity: 0,

        y: 18,

        duration: 0.5,

        stagger: 0.06,

        delay: 0.18,

        ease: "power3.out",
      });
    }

    /* =================================================
           INTERNAL CARD
           
           Hanya card yang diberi class
           .internal-card
        ================================================= */

    const internalCards = document.querySelectorAll(".internal-card");

    if (internalCards.length) {
      gsap.from(internalCards, {
        opacity: 0,

        y: 18,

        duration: 0.5,

        stagger: 0.06,

        delay: 0.2,

        ease: "power3.out",
      });
    }
  }

  /* =====================================================
       SCROLL REVEAL
       
       Hanya elemen yang secara eksplisit
       memiliki class .scroll-reveal
    ===================================================== */

  const revealElements = document.querySelectorAll(".scroll-reveal");

  if (
    revealElements.length &&
    typeof gsap !== "undefined" &&
    typeof ScrollTrigger !== "undefined"
  ) {
    revealElements.forEach(function (element) {
      gsap.from(element, {
        scrollTrigger: {
          trigger: element,

          start: "top 88%",

          once: true,
        },

        opacity: 0,

        y: 25,

        duration: 0.6,

        ease: "power3.out",
      });
    });
  }

  /* =====================================================
       NAVIGATION TRANSITION
       
       Navbar tetap di posisi yang sama.
       Yang bergerak hanya overlay.
    ===================================================== */

  const pageLinks = document.querySelectorAll("a.page-link");

  pageLinks.forEach(function (link) {
    link.addEventListener("click", function (event) {
      const href = this.getAttribute("href");

      if (!href) {
        return;
      }

      if (href === "#" || href.startsWith("javascript:")) {
        return;
      }

      /*
                Jangan override anchor
                */

      if (
        href.startsWith("#") ||
        href.includes("#tentang") ||
        href.includes("#fasilitas") ||
        href.includes("#cara-melapor")
      ) {
        return;
      }

      /*
                External link
                */

      if (href.startsWith("http://") || href.startsWith("https://")) {
        return;
      }

      event.preventDefault();

      /*
                Jika ada transition
                */

      if (transition && typeof gsap !== "undefined") {
        transition.style.display = "block";

        gsap.killTweensOf(transition);

        gsap.set(transition, {
          opacity: 0,
        });

        gsap.to(transition, {
          opacity: 1,

          duration: 0.32,

          ease: "power2.inOut",

          onComplete: function () {
            window.location.href = href;
          },
        });
      } else {
        window.location.href = href;
      }
    });
  });

  /* =====================================================
       LOGOUT
    ===================================================== */

  const logoutLinks = document.querySelectorAll(".logout-link");

  logoutLinks.forEach(function (link) {
    link.addEventListener("click", function (event) {
      event.preventDefault();

      const href = this.getAttribute("href");

      if (transition && typeof gsap !== "undefined") {
        transition.style.display = "block";

        gsap.killTweensOf(transition);

        gsap.set(transition, {
          opacity: 0,
        });

        gsap.to(transition, {
          opacity: 1,

          duration: 0.32,

          ease: "power2.inOut",

          onComplete: function () {
            window.location.href = href;
          },
        });
      } else {
        window.location.href = href;
      }
    });
  });

  /* =====================================================
       REDUCED MOTION
    ===================================================== */

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    if (lenis) {
      lenis.destroy();
    }
  }
});
