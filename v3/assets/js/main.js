/* ===================================================
   AFIAÇÃO ALMEIDA — Landing Page Scripts
   =================================================== */

(function () {
  'use strict';

  // ── Helpers ──────────────────────────────────────
  const $  = (s, ctx = document) => ctx.querySelector(s);
  const $$ = (s, ctx = document) => [...ctx.querySelectorAll(s)];

  // ── Navbar scroll behaviour ───────────────────────
  const navbar = $('#navbar');
  function updateNavbar() {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  }
  window.addEventListener('scroll', updateNavbar, { passive: true });
  updateNavbar();

  // ── Active nav link on scroll ─────────────────────
  const sections  = $$('section[id], footer');
  const navLinks  = $$('.nav-link');
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        navLinks.forEach(l => l.classList.remove('active'));
        const link = $(`a[href="#${e.target.id}"]`);
        if (link) link.classList.add('active');
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });
  sections.forEach(s => io.observe(s));

  // ── Mobile hamburger menu ─────────────────────────
  const hamburger = $('#hamburger');
  const navLinksEl = $('#navLinks');
  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    navLinksEl.classList.toggle('mobile-open');
  });
  navLinksEl.addEventListener('click', e => {
    if (e.target.classList.contains('nav-link')) {
      hamburger.classList.remove('open');
      navLinksEl.classList.remove('mobile-open');
    }
  });

  // ── Smooth scroll for anchors ─────────────────────
  document.addEventListener('click', e => {
    const a = e.target.closest('a[href^="#"]');
    if (!a) return;
    const target = $(a.getAttribute('href'));
    if (!target) return;
    e.preventDefault();
    const offset = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--nav-h')) || 72;
    window.scrollTo({ top: target.offsetTop - offset, behavior: 'smooth' });
  });

  // ── Animate on scroll (IntersectionObserver) ──────
  const animIo = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        animIo.unobserve(e.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
  $$('.animate-on-scroll').forEach(el => animIo.observe(el));

  // ── Hero particle system ──────────────────────────
  function createParticles() {
    const container = $('#heroParticles');
    if (!container) return;
    for (let i = 0; i < 20; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 4 + 2;
      p.style.cssText = `
        width:${size}px; height:${size}px;
        left:${Math.random() * 100}%;
        animation-duration:${Math.random() * 15 + 10}s;
        animation-delay:${Math.random() * 15}s;
      `;
      container.appendChild(p);
    }
  }
  createParticles();

  // ── Stats counter animation ───────────────────────
  function animateCounter(el) {
    const target = parseInt(el.dataset.target);
    if (!target) return;
    const duration = 1800;
    const start    = performance.now();
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const ease     = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.floor(ease * target);
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  const counterIo = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        $$('[data-target]', e.target.closest('.hero-stats') || e.target).forEach(animateCounter);
        counterIo.unobserve(e.target);
      }
    });
  }, { threshold: 0.5 });
  const statsEl = $('.hero-stats');
  if (statsEl) counterIo.observe(statsEl);

  // ── Before/After comparison slider ───────────────
  const slider   = $('#comparisonSlider');
  const handle   = $('#comparisonHandle');
  const afterDiv = slider?.querySelector('.comparison-after');

  if (slider && handle && afterDiv) {
    let dragging = false;

    function setPosition(x) {
      const rect = slider.getBoundingClientRect();
      const pct  = Math.max(5, Math.min(95, ((x - rect.left) / rect.width) * 100));
      afterDiv.style.clipPath = `inset(0 ${100 - pct}% 0 0)`;
      handle.style.left = `${pct}%`;
    }

    slider.addEventListener('mousedown', e => { dragging = true; setPosition(e.clientX); });
    slider.addEventListener('touchstart', e => { dragging = true; setPosition(e.touches[0].clientX); }, { passive: true });
    window.addEventListener('mousemove', e => { if (dragging) setPosition(e.clientX); });
    window.addEventListener('touchmove', e => { if (dragging) setPosition(e.touches[0].clientX); }, { passive: true });
    window.addEventListener('mouseup',   () => { dragging = false; });
    window.addEventListener('touchend',  () => { dragging = false; });
  }

  // ── Testimonials carousel ─────────────────────────
  const cards    = $$('.testimonial-card');
  const dots     = $$('.dot');
  const prevBtn  = $('#prevTestimonial');
  const nextBtn  = $('#nextTestimonial');
  let currentIdx = 0;
  let autoTimer;

  function showTestimonial(idx) {
    cards.forEach(c => c.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    currentIdx = (idx + cards.length) % cards.length;
    cards[currentIdx].classList.add('active');
    dots[currentIdx].classList.add('active');
  }

  function startAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(() => showTestimonial(currentIdx + 1), 5000);
  }

  if (cards.length) {
    showTestimonial(0);
    startAuto();
    prevBtn?.addEventListener('click', () => { showTestimonial(currentIdx - 1); startAuto(); });
    nextBtn?.addEventListener('click', () => { showTestimonial(currentIdx + 1); startAuto(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { showTestimonial(i); startAuto(); }));
  }

  // ── Gallery modal ─────────────────────────────────
  const modal      = $('#galleryModal');
  const modalClose = $('#modalClose');
  const modalImg   = $('#modalImg');
  const modalTitle = $('#modalTitle');

  $$('.gallery-item').forEach(item => {
    item.addEventListener('click', () => {
      const img   = item.querySelector('.gallery-img');
      const title = item.dataset.title || '';
      modalImg.style.background = getComputedStyle(img).background;
      modalTitle.textContent    = title;
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
  });

  function closeModal() {
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }
  modalClose?.addEventListener('click', closeModal);
  modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // ── Contact form → WhatsApp ───────────────────────
  const form = $('#contactForm');
  form?.addEventListener('submit', e => {
    e.preventDefault();
    const nome     = $('#nome').value.trim();
    const telefone = $('#telefone').value.trim();
    const servico  = $('#servico').value;
    const mensagem = $('#mensagem').value.trim();

    let text = `Olá! Meu nome é *${nome}*`;
    if (telefone) text += `, meu WhatsApp é ${telefone}`;
    text += `.`;
    if (servico) text += `\n\n*Serviço desejado:* ${servico}`;
    if (mensagem) text += `\n\n*Mensagem:* ${mensagem}`;
    text += `\n\nAguardo seu retorno. 😊`;

    window.open(`https://wa.me/5565967626677?text=${encodeURIComponent(text)}`, '_blank');
  });

  // ── Back to top button ────────────────────────────
  const backToTop = $('#backToTop');
  window.addEventListener('scroll', () => {
    backToTop?.classList.toggle('visible', window.scrollY > 400);
  }, { passive: true });
  backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // ── Footer year ───────────────────────────────────
  const yearEl = $('#currentYear');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

})();
