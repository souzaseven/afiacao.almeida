<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle   = 'Serviços';
$currentPage = 'servicos';
$breadcrumb  = 'Tabela de serviços e preços';

// ── Ações POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar') {
        $id        = (int)($_POST['id'] ?? 0);
        $nome      = trim($_POST['nome']      ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco     = (float)str_replace(',', '.', str_replace('.', '', $_POST['preco'] ?? '0'));
        $ativo     = ($_POST['ativo'] ?? '1') === '1' ? 1 : 0;

        if ($nome === '') {
            $_SESSION['flash_error'] = 'O nome do serviço é obrigatório.';
            header('Location: servicos.php');
            exit;
        }

        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE servicos SET nome=?, descricao=?, preco=?, ativo=? WHERE id=?")
                    ->execute([$nome, $descricao, $preco, $ativo, $id]);
                $_SESSION['flash_success'] = 'Serviço atualizado com sucesso.';
            } else {
                $pdo->prepare("INSERT INTO servicos (nome, descricao, preco, ativo) VALUES (?,?,?,?)")
                    ->execute([$nome, $descricao, $preco, $ativo]);
                $_SESSION['flash_success'] = 'Serviço cadastrado com sucesso.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Erro ao salvar: ' . $e->getMessage();
        }

        header('Location: servicos.php');
        exit;
    }

    if ($action === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $uso = $pdo->prepare("SELECT COUNT(*) FROM itens_os WHERE servico_id = ?");
            $uso->execute([$id]);
            if ($uso->fetchColumn() > 0) {
                $pdo->prepare("UPDATE servicos SET ativo=0 WHERE id=?")->execute([$id]);
                $_SESSION['flash_success'] = 'Serviço desativado (está vinculado a ordens de serviço).';
            } else {
                $pdo->prepare("DELETE FROM servicos WHERE id=?")->execute([$id]);
                $_SESSION['flash_success'] = 'Serviço excluído com sucesso.';
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }
        header('Location: servicos.php');
        exit;
    }

    if ($action === 'toggle_ativo') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE servicos SET ativo = CASE WHEN ativo=1 THEN 0 ELSE 1 END WHERE id=?")
                ->execute([$id]);
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Erro ao alterar status.';
        }
        header('Location: servicos.php');
        exit;
    }
}

// ── Filtro GET (ativos / inativos / todos) ────────────────────────────────────
$filtro = $_GET['filtro'] ?? 'ativos';

$where  = '';
$params = [];
if ($filtro === 'ativos')   { $where = 'WHERE s.ativo = 1'; }
elseif ($filtro === 'inativos') { $where = 'WHERE s.ativo = 0'; }

$servicos = $pdo->prepare(
    "SELECT s.*, (SELECT COUNT(*) FROM itens_os i WHERE i.servico_id = s.id) AS uso
     FROM servicos s $where ORDER BY s.nome ASC"
);
$servicos->execute($params);
$servicos = $servicos->fetchAll(PDO::FETCH_ASSOC);

// ── Totais ────────────────────────────────────────────────────────────────────
$totais = $pdo->query(
    "SELECT COUNT(*) AS total, SUM(ativo) AS ativos,
            SUM(CASE WHEN ativo=0 THEN 1 ELSE 0 END) AS inativos,
            ROUND(AVG(preco),2) AS preco_medio
     FROM servicos"
)->fetch(PDO::FETCH_ASSOC);

function formatPreco(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}

$topbarActions = '<button class="btn btn-primary btn-sm" onclick="openModal()">
    <i class="fas fa-plus"></i> Novo Serviço
</button>';

$extraHead = '<style>
/* ── Modal principal ────────────────────────────── */
.drawer-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.65); backdrop-filter: blur(3px);
    z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
}
.drawer-overlay.open { display: flex; }
.drawer {
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-xl); width: 100%; max-width: 500px;
    max-height: 90vh; display: flex; flex-direction: column;
    transform: scale(.95) translateY(12px); opacity: 0;
    transition: transform .22s ease, opacity .22s ease;
}
.drawer-overlay.open .drawer { transform: scale(1) translateY(0); opacity: 1; }
.drawer-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.drawer-title { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
.drawer-body  { padding: 1.5rem; overflow-y: auto; flex: 1; }
.drawer-footer {
    display: flex; gap: .75rem; justify-content: flex-end;
    padding: 1rem 1.5rem; border-top: 1px solid var(--border); flex-shrink: 0;
}
.btn-close-modal {
    background: none; border: none; color: var(--text-muted); cursor: pointer;
    font-size: 1.1rem; padding: .25rem; line-height: 1; transition: color var(--transition);
}
.btn-close-modal:hover { color: var(--text-primary); }

/* ── Modal de confirmação ───────────────────────── */
.confirm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.75); z-index: 1100;
    align-items: center; justify-content: center; padding: 1rem;
}
.confirm-overlay.open { display: flex; }
.confirm-box {
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem 1.5rem;
    max-width: 360px; width: 100%; text-align: center;
    transform: scale(.92); opacity: 0;
    transition: transform .18s ease, opacity .18s ease;
}
.confirm-overlay.open .confirm-box { transform: scale(1); opacity: 1; }
.confirm-icon { font-size: 2rem; color: var(--danger); margin-bottom: .75rem; }
.confirm-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: .4rem; }
.confirm-msg { font-size: .875rem; color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.5; }
.confirm-msg strong { color: var(--text-primary); }
.confirm-actions { display: flex; gap: .75rem; justify-content: center; }

/* ── Formulário ─────────────────────────────────── */
.form-group { margin-bottom: 1.1rem; }
.form-label {
    display: block; font-size: .78rem; font-weight: 600;
    color: var(--text-secondary); margin-bottom: .35rem; letter-spacing: .03em;
}
.form-label .req { color: var(--rose); margin-left: 2px; }
.form-control, .form-select {
    width: 100%; background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-md); color: var(--text-primary);
    font-size: .875rem; padding: .55rem .85rem;
    transition: border-color var(--transition); font-family: inherit;
}
.form-control:focus, .form-select:focus {
    outline: none; border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201,168,76,.12);
}
textarea.form-control { resize: vertical; min-height: 70px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.input-prefix { position: relative; }
.input-prefix .prefix {
    position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: .82rem; font-weight: 600; pointer-events: none;
}
.input-prefix .form-control { padding-left: 2.4rem; }
.toggle-wrap { display: flex; align-items: center; gap: .75rem; margin-top: .35rem; }
.toggle-switch { position: relative; width: 42px; height: 24px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-track {
    position: absolute; inset: 0; background: var(--surface-2);
    border: 1px solid var(--border); border-radius: 100px;
    cursor: pointer; transition: background .2s;
}
.toggle-switch input:checked + .toggle-track { background: var(--gold); border-color: var(--gold); }
.toggle-track::after {
    content: ""; position: absolute; left: 3px; top: 3px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fff; transition: transform .2s;
}
.toggle-switch input:checked + .toggle-track::after { transform: translateX(18px); }
.toggle-label { font-size: .875rem; color: var(--text-secondary); }

/* ── KPIs ───────────────────────────────────────── */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi  { background: var(--surface-1); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1rem 1.2rem; }
.kpi-val { font-size: 1.55rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
.kpi-lbl { font-size: .75rem; color: var(--text-muted); margin-top: .3rem; }

/* ── Tabela ─────────────────────────────────────── */
.table-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.table-toolbar {
    display: flex; gap: .75rem; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); flex-wrap: wrap;
}
.search-wrap { position: relative; flex: 1; min-width: 180px; }
.search-wrap i { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: .85rem; }
.search-wrap .form-control { padding-left: 2.1rem; }
.filter-tabs { display: flex; gap: .25rem; }
.filter-tab {
    padding: .35rem .8rem; border-radius: var(--radius-md); font-size: .78rem;
    font-weight: 600; color: var(--text-muted); text-decoration: none;
    transition: background var(--transition), color var(--transition);
}
.filter-tab:hover { background: var(--surface-2); color: var(--text-primary); }
.filter-tab.active { background: rgba(201,168,76,.15); color: var(--gold); }
.table-footer {
    padding: .65rem 1.25rem; border-top: 1px solid var(--border);
    font-size: .78rem; color: var(--text-muted);
}
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    padding: .7rem 1rem; font-size: .72rem; font-weight: 700;
    color: var(--text-muted); letter-spacing: .06em; text-transform: uppercase;
    text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap;
}
.data-table td {
    padding: .85rem 1rem; font-size: .875rem; color: var(--text-primary);
    border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(255,255,255,.02); }
.nome-cell .nome { font-weight: 600; }
.nome-cell .desc { font-size: .78rem; color: var(--text-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px; }
.preco-val { font-weight: 700; color: var(--gold); font-size: .95rem; }
.uso-badge { font-size: .75rem; color: var(--text-muted); }
.actions-cell { display: flex; gap: .5rem; align-items: center; justify-content: flex-end; }
.btn-icon {
    width: 32px; height: 32px; border-radius: var(--radius-md);
    border: 1px solid var(--border); background: var(--surface-2); color: var(--text-muted);
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    font-size: .82rem; transition: all var(--transition); text-decoration: none;
}
.btn-icon:hover { border-color: var(--gold); color: var(--gold); background: rgba(201,168,76,.08); }
.btn-icon.danger:hover { border-color: var(--danger); color: var(--danger); background: rgba(248,113,113,.08); }
.empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
.empty-state i { font-size: 2rem; margin-bottom: .75rem; opacity: .4; display: block; }
</style>';

require_once 'includes/header.php';
?>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi">
        <div class="kpi-val"><?= (int)$totais['total'] ?></div>
        <div class="kpi-lbl">Total de serviços</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--gold)"><?= (int)$totais['ativos'] ?></div>
        <div class="kpi-lbl">Ativos</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--text-muted)"><?= (int)$totais['inativos'] ?></div>
        <div class="kpi-lbl">Inativos</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="font-size:1.25rem"><?= formatPreco((float)($totais['preco_medio'] ?? 0)) ?></div>
        <div class="kpi-lbl">Preço médio</div>
    </div>
</div>

<!-- Tabela -->
<div class="table-card">
    <div class="table-toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input
                type="text"
                id="searchInput"
                class="form-control"
                placeholder="Buscar por nome ou descrição…"
                autocomplete="off"
            >
        </div>

        <div class="filter-tabs">
            <a href="?filtro=ativos"   class="filter-tab<?= $filtro === 'ativos'   ? ' active' : '' ?>">Ativos</a>
            <a href="?filtro=inativos" class="filter-tab<?= $filtro === 'inativos' ? ' active' : '' ?>">Inativos</a>
            <a href="?filtro=todos"    class="filter-tab<?= $filtro === 'todos'    ? ' active' : '' ?>">Todos</a>
        </div>
    </div>

    <?php if (empty($servicos)): ?>
        <div class="empty-state">
            <i class="fas fa-wrench"></i>
            <p>Nenhum serviço encontrado.</p>
        </div>
    <?php else: ?>
    <table class="data-table" id="dataTable">
        <thead>
            <tr>
                <th>Serviço</th>
                <th>Preço</th>
                <th>Uso em OS</th>
                <th>Status</th>
                <th style="text-align:right">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($servicos as $s): ?>
            <tr>
                <td>
                    <div class="nome-cell">
                        <div class="nome"><?= htmlspecialchars($s['nome']) ?></div>
                        <?php if ($s['descricao']): ?>
                            <div class="desc"><?= htmlspecialchars($s['descricao']) ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td><span class="preco-val"><?= formatPreco((float)$s['preco']) ?></span></td>
                <td>
                    <span class="uso-badge">
                        <?= (int)$s['uso'] ?> <?= (int)$s['uso'] === 1 ? 'vez' : 'vezes' ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $s['ativo'] ? 'badge-success' : 'badge-neutral' ?>">
                        <?= $s['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td>
                    <div class="actions-cell">
                        <!-- Toggle ativo -->
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="action" value="toggle_ativo">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-icon" title="<?= $s['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                <i class="fas fa-<?= $s['ativo'] ? 'toggle-on' : 'toggle-off' ?>"
                                   style="color:<?= $s['ativo'] ? 'var(--gold)' : 'var(--text-muted)' ?>"></i>
                            </button>
                        </form>

                        <!-- Editar -->
                        <button
                            class="btn-icon"
                            title="Editar"
                            onclick="openModal(<?= htmlspecialchars(json_encode([
                                'id'        => $s['id'],
                                'nome'      => $s['nome'],
                                'descricao' => $s['descricao'] ?? '',
                                'preco'     => number_format((float)$s['preco'], 2, ',', '.'),
                                'ativo'     => (int)$s['ativo'],
                            ]), ENT_QUOTES) ?>)">
                            <i class="fas fa-pen"></i>
                        </button>

                        <!-- Excluir -->
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button
                                type="button"
                                class="btn-icon danger"
                                title="Excluir"
                                onclick="confirmDelete(this.closest('form'), '<?= htmlspecialchars(addslashes($s['nome'])) ?>')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="table-footer">
        <span id="resultCount"><?= count($servicos) ?> serviço<?= count($servicos) !== 1 ? 's' : '' ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- Modal principal -->
<div class="drawer-overlay" id="drawerOverlay">
    <div class="drawer">
        <div class="drawer-header">
            <span class="drawer-title" id="modalTitle">Novo Serviço</span>
            <button class="btn-close-modal" onclick="closeModal()" title="Fechar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="formServico">
            <input type="hidden" name="action" value="salvar">
            <input type="hidden" name="id" id="fId" value="0">
            <div class="drawer-body">
                <div class="form-group">
                    <label class="form-label" for="fNome">Nome do serviço <span class="req">*</span></label>
                    <input type="text" id="fNome" name="nome" class="form-control"
                           placeholder="Ex.: Afiação de tesoura profissional" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="fDescricao">Descrição</label>
                    <textarea id="fDescricao" name="descricao" class="form-control"
                              placeholder="Detalhes, tipo de equipamento, observações…"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="fPreco">Preço</label>
                        <div class="input-prefix">
                            <span class="prefix">R$</span>
                            <input type="text" id="fPreco" name="preco" class="form-control"
                                   placeholder="0,00" autocomplete="off" inputmode="numeric">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="toggle-wrap">
                            <label class="toggle-switch">
                                <input type="checkbox" id="fAtivo" name="ativo" value="1" checked>
                                <span class="toggle-track"></span>
                            </label>
                            <span class="toggle-label" id="toggleLabel">Ativo</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-check"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="confirm-title">Excluir serviço?</div>
        <p class="confirm-msg">Você está prestes a excluir <strong id="confirmName"></strong>. Esta ação não pode ser desfeita.</p>
        <div class="confirm-actions">
            <button class="btn btn-ghost btn-sm" onclick="closeConfirm()">Cancelar</button>
            <button class="btn btn-danger btn-sm" id="confirmBtn">
                <i class="fas fa-trash-alt"></i> Excluir
            </button>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
// ── Modal principal ───────────────────────────────────────────────────────────
const drawerOverlay = document.getElementById('drawerOverlay');

function openModal(data) {
    document.getElementById('fId').value        = data ? data.id        : 0;
    document.getElementById('fNome').value      = data ? data.nome      : '';
    document.getElementById('fDescricao').value = data ? data.descricao : '';
    setPreco(data ? data.preco : '');

    const chk = document.getElementById('fAtivo');
    chk.checked = data ? data.ativo === 1 : true;
    document.getElementById('toggleLabel').textContent = chk.checked ? 'Ativo' : 'Inativo';
    document.getElementById('modalTitle').textContent  = data ? 'Editar Serviço' : 'Novo Serviço';

    drawerOverlay.classList.add('open');
    setTimeout(() => document.getElementById('fNome').focus(), 80);
}

function closeModal() {
    drawerOverlay.classList.remove('open');
}

drawerOverlay.addEventListener('click', e => { if (e.target === drawerOverlay) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeConfirm(); closeModal(); } });

document.getElementById('fAtivo').addEventListener('change', function() {
    document.getElementById('toggleLabel').textContent = this.checked ? 'Ativo' : 'Inativo';
});

// ── Máscara de preço (estilo ATM — digita centavos da direita) ────────────────
const fPreco = document.getElementById('fPreco');

function formatCurrency(cents) {
    if (!cents) return '';
    return (cents / 100).toFixed(2)
        .replace('.', ',')
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function setPreco(formatted) {
    // Recebe "1.234,56" ou "" e inicializa o campo sem acionar a máscara
    fPreco.value = formatted || '';
}

fPreco.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') return;

    if (e.key === 'Backspace') {
        e.preventDefault();
        const digits = this.value.replace(/\D/g, '').slice(0, -1);
        this.value = formatCurrency(parseInt(digits || '0', 10));
        return;
    }

    if (!/^\d$/.test(e.key)) { e.preventDefault(); return; }

    e.preventDefault();
    const digits = (this.value.replace(/\D/g, '') + e.key).replace(/^0+/, '') || '0';
    this.value = formatCurrency(parseInt(digits, 10));
});

fPreco.addEventListener('focus', function() {
    const len = this.value.length;
    this.setSelectionRange(len, len);
});

// ── Confirmação de exclusão ───────────────────────────────────────────────────
const confirmOverlay = document.getElementById('confirmOverlay');
let pendingDeleteForm = null;

function confirmDelete(form, nome) {
    pendingDeleteForm = form;
    document.getElementById('confirmName').textContent = nome;
    confirmOverlay.classList.add('open');
}

function closeConfirm() {
    confirmOverlay.classList.remove('open');
    pendingDeleteForm = null;
}

document.getElementById('confirmBtn').addEventListener('click', function() {
    if (pendingDeleteForm) pendingDeleteForm.submit();
});

confirmOverlay.addEventListener('click', e => { if (e.target === confirmOverlay) closeConfirm(); });

// ── Filtro em tempo real ──────────────────────────────────────────────────────
const searchInput  = document.getElementById('searchInput');
const resultCount  = document.getElementById('resultCount');
const allRows      = document.querySelectorAll('#dataTable tbody tr');
const totalVisible = allRows.length;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        let count = 0;

        allRows.forEach(row => {
            const nome = row.querySelector('.nome')?.textContent.toLowerCase() || '';
            const desc = row.querySelector('.desc')?.textContent.toLowerCase() || '';
            const show = !q || nome.includes(q) || desc.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) count++;
        });

        if (resultCount) {
            resultCount.textContent = q
                ? count + ' de ' + totalVisible + ' serviço' + (totalVisible !== 1 ? 's' : '')
                : totalVisible + ' serviço' + (totalVisible !== 1 ? 's' : '');
        }
    });
}
</script>
JS;

require_once 'includes/footer.php';
