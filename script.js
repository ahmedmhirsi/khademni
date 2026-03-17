/* ========================================================
   KHADEMNI — Interactive Scripts
   ======================================================== */

document.addEventListener('DOMContentLoaded', () => {

  /* ---------- Navbar Scroll Effect ---------- */
  const navbar = document.getElementById('navbar');
  const handleScroll = () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  };
  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  /* ---------- Mobile Menu Toggle ---------- */
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');

  navToggle.addEventListener('click', () => {
    navToggle.classList.toggle('active');
    navLinks.classList.toggle('open');
    document.body.style.overflow = navLinks.classList.contains('open') ? 'hidden' : '';
  });

  // Close mobile menu when clicking a link
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      navToggle.classList.remove('active');
      navLinks.classList.remove('open');
      document.body.style.overflow = '';
    });
  });

  /* ---------- Counter Animation ---------- */
  const counters = document.querySelectorAll('[data-count]');
  const animateCounter = (el) => {
    const target = parseInt(el.dataset.count, 10);
    const duration = 2000;
    const start = performance.now();
    const format = (n) => n.toLocaleString('en-US');

    const step = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      // easeOutQuart
      const eased = 1 - Math.pow(1 - progress, 4);
      el.textContent = format(Math.floor(target * eased));
      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = format(target) + '+';
    };
    requestAnimationFrame(step);
  };

  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        counterObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(c => counterObserver.observe(c));

  /* ---------- Scroll Reveal ---------- */
  const revealElements = () => {
    const els = document.querySelectorAll(
      '.category-card, .job-card, .step-card, .featured-card, .section__header, .cta__content'
    );
    els.forEach(el => el.classList.add('reveal'));

    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
          // Staggered animation
          setTimeout(() => {
            entry.target.classList.add('revealed');
          }, i * 80);
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    els.forEach(el => revealObserver.observe(el));
  };
  revealElements();

  /* ---------- Tab Switching (How It Works) ---------- */
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = {
    candidates: document.getElementById('tab-candidates'),
    companies: document.getElementById('tab-companies')
  };

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;

      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      Object.entries(tabContents).forEach(([key, el]) => {
        if (key === tab) {
          el.classList.remove('hidden');
          // Re-trigger reveal animation
          el.querySelectorAll('.step-card').forEach((card, i) => {
            card.classList.remove('revealed');
            setTimeout(() => card.classList.add('revealed'), i * 120);
          });
        } else {
          el.classList.add('hidden');
        }
      });
    });
  });

  /* ---------- Bookmark Toggle ---------- */
  document.querySelectorAll('.job-card__bookmark').forEach(btn => {
    btn.addEventListener('click', () => {
      const icon = btn.querySelector('i');
      icon.classList.toggle('far');
      icon.classList.toggle('fas');
      icon.style.color = icon.classList.contains('fas') ? '#ec4899' : '';
    });
  });

  /* ---------- Search Button Ripple ---------- */
  const searchBtn = document.getElementById('searchBtn');
  searchBtn.addEventListener('click', (e) => {
    const rect = searchBtn.getBoundingClientRect();
    const ripple = document.createElement('span');
    ripple.style.cssText = `
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.3);
      width: 20px; height: 20px;
      left: ${e.clientX - rect.left - 10}px;
      top: ${e.clientY - rect.top - 10}px;
      animation: ripple 0.6s ease-out forwards;
      pointer-events: none;
    `;
    searchBtn.style.position = 'relative';
    searchBtn.style.overflow = 'hidden';
    searchBtn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
  });

  // Add ripple keyframes
  const style = document.createElement('style');
  style.textContent = `
    @keyframes ripple {
      to { transform: scale(15); opacity: 0; }
    }
  `;
  document.head.appendChild(style);

  /* ---------- Smooth Scroll for Anchor Links ---------- */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  /* ---------- Parallax on Hero Shapes ---------- */
  const shapes = document.querySelectorAll('.hero__bg-shapes .shape');
  let ticking = false;

  window.addEventListener('mousemove', (e) => {
    if (!ticking) {
      requestAnimationFrame(() => {
        const x = (e.clientX / window.innerWidth - 0.5) * 2;
        const y = (e.clientY / window.innerHeight - 0.5) * 2;
        shapes.forEach((shape, i) => {
          const factor = (i + 1) * 15;
          shape.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
        });
        ticking = false;
      });
      ticking = true;
    }
  });

  /* ---------- Pause marquee on hover ---------- */
  const marqueeTrack = document.querySelector('.marquee__track');
  if (marqueeTrack) {
    marqueeTrack.addEventListener('mouseenter', () => {
      marqueeTrack.style.animationPlayState = 'paused';
    });
    marqueeTrack.addEventListener('mouseleave', () => {
      marqueeTrack.style.animationPlayState = 'running';
    });
  }

});
