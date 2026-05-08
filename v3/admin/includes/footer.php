
        </main><!-- /.page-content -->

    </div><!-- /.main-content -->

</div><!-- /.admin-wrapper -->

<!-- ── Toast container ───────────────────────────────────────────────────── -->
<div id="toast-container" aria-live="polite"></div>

<style>
#toast-container {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    display: flex; flex-direction: column-reverse; gap: .45rem;
    pointer-events: none; max-width: 340px;
}
.toast {
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: .7rem 1rem;
    display: flex; align-items: flex-start; gap: .6rem; font-size: .875rem;
    color: var(--text-primary); box-shadow: 0 8px 28px rgba(0,0,0,.45);
    animation: toastIn .22s ease; pointer-events: auto;
}
.toast.out { animation: toastOut .25s ease forwards; }
.toast-success { border-left: 3px solid var(--success); }
.toast-error   { border-left: 3px solid var(--danger); }
.toast-info    { border-left: 3px solid var(--gold); }
.toast-success .t-ic { color: var(--success); }
.toast-error   .t-ic { color: var(--danger); }
.toast-info    .t-ic { color: var(--gold); }
.t-ic  { flex-shrink: 0; font-size: 1rem; margin-top: .1rem; }
.t-msg { flex: 1; line-height: 1.45; }
.t-cls { background: none; border: none; color: var(--text-muted);
    cursor: pointer; padding: 0 0 0 .35rem; font-size: .85rem;
    line-height: 1; flex-shrink: 0; }
.t-cls:hover { color: var(--text-primary); }
@keyframes toastIn  { from { opacity:0; transform:translateX(18px); } to { opacity:1; transform:none; } }
@keyframes toastOut { from { opacity:1; transform:none; } to { opacity:0; transform:translateX(18px); } }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function showToast(msg, type, dur) {
    type = type || 'success';
    dur  = dur  || 4000;
    var icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    var t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.innerHTML =
        '<i class="fas ' + (icons[type] || 'fa-info-circle') + ' t-ic"></i>' +
        '<span class="t-msg">' + msg + '</span>' +
        '<button class="t-cls" onclick="this.closest(\'.toast\').remove()" title="Fechar">&#10005;</button>';
    document.getElementById('toast-container').appendChild(t);
    setTimeout(function() {
        t.classList.add('out');
        setTimeout(function() { t.remove(); }, 260);
    }, dur);
}

// Processar flash messages do PHP
(function() {
    var f = window._flash || {};
    if (f.s) showToast(f.s, 'success');
    if (f.e) showToast(f.e, 'error');
})();
</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
