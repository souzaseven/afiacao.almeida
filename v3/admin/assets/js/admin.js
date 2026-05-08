// ── Auto-dismiss de alertas ──────────────────────────────────────────────────
document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .4s, margin .4s';
        el.style.opacity = '0';
        el.style.marginBottom = '0';
        setTimeout(() => el.remove(), 400);
    }, 4000);
});

// ── Ativar link da sidebar conforme URL atual ────────────────────────────────
document.querySelectorAll('.sidebar .nav-item').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href && location.pathname.endsWith(href)) {
        link.classList.add('active');
    }
});
