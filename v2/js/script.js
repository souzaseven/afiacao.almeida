/* ============================================================
   CONFIGURAÇÃO
   ============================================================ */
const WA_NUMBER = '556596762667';
const WA_URL    = `https://wa.me/${WA_NUMBER}`;

/* ============================================================
   HEADER – SCROLL EFFECT
   ============================================================ */
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
  header.classList.toggle('scrolled', window.scrollY > 50);
}, { passive: true });

/* ============================================================
   MENU HAMBURGUER
   ============================================================ */
const hamburger = document.getElementById('hamburger');
const nav       = document.getElementById('nav');

hamburger.addEventListener('click', () => {
  const isOpen = nav.classList.toggle('open');
  hamburger.classList.toggle('active', isOpen);
  hamburger.setAttribute('aria-expanded', String(isOpen));
});

nav.querySelectorAll('.nav__link').forEach(link => {
  link.addEventListener('click', () => {
    nav.classList.remove('open');
    hamburger.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
  });
});

document.addEventListener('click', e => {
  if (!nav.contains(e.target) && !hamburger.contains(e.target)) {
    nav.classList.remove('open');
    hamburger.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
  }
});

/* ============================================================
   SCROLL REVEAL (Intersection Observer)
   ============================================================ */
const revealObs = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add('visible');
    revealObs.unobserve(entry.target);
  });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

/* ============================================================
   GALERIA + MODAL
   ============================================================ */
const modal       = document.getElementById('modal');
const modalImg    = document.getElementById('modalImg');
const modalCap    = document.getElementById('modalCaption');
const modalClose  = document.getElementById('modalClose');

document.querySelectorAll('.galeria__item').forEach(item => {
  item.addEventListener('click', () => openModal(item.dataset.src, item.dataset.caption, item.querySelector('img').alt));
  item.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openModal(item.dataset.src, item.dataset.caption, item.querySelector('img').alt);
    }
  });
});

function openModal(src, caption, alt) {
  modalImg.src    = src;
  modalImg.alt    = alt || '';
  modalCap.textContent = caption || '';
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  modalClose.focus();
}

function closeModal() {
  modal.classList.remove('active');
  document.body.style.overflow = '';
  modalImg.src = '';
}

modalClose.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('active')) closeModal(); });

/* ============================================================
   FORMULÁRIO → WHATSAPP
   ============================================================ */
document.getElementById('whatsappForm').addEventListener('submit', e => {
  e.preventDefault();
  const nome      = document.getElementById('nome').value.trim();
  const mensagem  = document.getElementById('mensagem').value.trim();
  const errorEl   = document.getElementById('formError');

  if (!nome || !mensagem) {
    errorEl.textContent = 'Por favor, preencha seu nome e mensagem.';
    return;
  }
  errorEl.textContent = '';

  const texto = `Olá, meu nome é ${nome}. ${mensagem}`;
  window.open(`${WA_URL}?text=${encodeURIComponent(texto)}`, '_blank', 'noopener');
});

/* ============================================================
   HORÁRIOS – DESTAQUE DO DIA ATUAL
   ============================================================ */
(function destacarHoje() {
  const map = { 0:'dom', 1:'seg', 2:'ter', 3:'qua', 4:'qui', 5:'sex', 6:'sab' };
  const idx  = new Date().getDay();
  const card = document.querySelector(`.dia--${map[idx]}`);
  if (!card) return;
  card.classList.add('is-hoje');
  if (idx === 0) card.classList.add('is-fechado');
})();

/* ============================================================
   ANTES E DEPOIS – SLIDER DE COMPARAÇÃO
   ============================================================ */
(function initComparacao() {
  const wrap   = document.getElementById('comparacaoWrap');
  const after  = document.getElementById('comparacaoAfter');
  const handle = document.getElementById('comparacaoHandle');
  if (!wrap) return;

  let dragging = false;

  function setPos(clientX) {
    const rect = wrap.getBoundingClientRect();
    let pct    = ((clientX - rect.left) / rect.width) * 100;
    pct = Math.min(95, Math.max(5, pct));
    after.style.clipPath  = `inset(0 ${(100 - pct).toFixed(2)}% 0 0)`;
    handle.style.left     = `${pct.toFixed(2)}%`;
  }

  /* Mouse */
  wrap.addEventListener('mousedown',  e => { dragging = true; setPos(e.clientX); });
  window.addEventListener('mouseup',  ()  => { dragging = false; });
  window.addEventListener('mousemove', e => { if (dragging) setPos(e.clientX); });

  /* Touch */
  wrap.addEventListener('touchstart', e => { dragging = true; setPos(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('touchend', ()  => { dragging = false; });
  window.addEventListener('touchmove', e => { if (dragging) setPos(e.touches[0].clientX); }, { passive: true });
})();
