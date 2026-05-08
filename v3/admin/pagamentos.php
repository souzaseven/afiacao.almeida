<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle   = 'Pagamentos';
$currentPage = 'pagamentos';
$breadcrumb  = 'Recebimentos e pendências';

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Registrar pagamento
    if ($action === 'pagar') {
        $os_id = (int)($_POST['os_id'] ?? 0);
        $forma = in_array($_POST['forma'] ?? '', ['dinheiro','pix','cartao_debito','cartao_credito','transferencia'])
                 ? $_POST['forma'] : 'dinheiro';
        $novo_status = ($_POST['status_pagamento'] ?? '') === 'pago' ? 'pago' : 'parcial';
        $obs = trim($_POST['obs'] ?? '');

        $valor = (float) str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
        if ($os_id > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE ordens_servico SET status_pagamento=? WHERE id=?")
                    ->execute([$novo_status, $os_id]);
                if ($valor > 0) {
                    $pdo->prepare("INSERT INTO pagamentos (os_id, valor, forma, observacao) VALUES (?,?,?,?)")
                        ->execute([$os_id, $valor, $forma, trim($_POST['obs'] ?? '')]);
                }
                $pdo->commit();
                $label = match($forma) {
                    'pix'            => 'PIX',
                    'cartao_debito'  => 'Cartão débito',
                    'cartao_credito' => 'Cartão crédito',
                    'transferencia'  => 'Transferência',
                    default          => 'Dinheiro',
                };
                $_SESSION['flash_success'] = "OS #{$_POST['os_num']} marcada como "
                    . ($novo_status === 'pago' ? 'paga' : 'parcialmente paga')
                    . " via $label.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Erro ao registrar pagamento: ' . $e->getMessage();
            }
        }
        header('Location: pagamentos.php');
        exit;
    }

    // Reverter para pendente
    if ($action === 'reverter') {
        $os_id = (int)($_POST['os_id'] ?? 0);
        if ($os_id > 0) {
            try {
                $pdo->prepare("UPDATE ordens_servico SET status_pagamento='pendente' WHERE id=?")
                    ->execute([$os_id]);
                $_SESSION['flash_success'] = 'Pagamento revertido para pendente.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Erro ao reverter: ' . $e->getMessage();
            }
        }
        header('Location: pagamentos.php');
        exit;
    }
}

// ── Filtro ────────────────────────────────────────────────────────────────────
$filtro = $_GET['filtro'] ?? '';
if (!in_array($filtro, ['pendente', 'pago', 'todos'])) $filtro = 'pendente';

// ── Dados ─────────────────────────────────────────────────────────────────────
try {
    if ($filtro === 'pago') {
        $stmt = $pdo->prepare("
            SELECT o.id, o.numero, o.status, o.status_pagamento,
                   o.valor_total, o.tipo_finalizacao, o.prazo, o.data_entrada,
                   c.nome AS cliente_nome, c.nome_fantasia, c.telefone
            FROM ordens_servico o
            LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
            WHERE o.status_pagamento = 'pago' AND o.status != 'cancelado'
            ORDER BY o.data_entrada DESC LIMIT 300
        ");
    } elseif ($filtro === 'todos') {
        $stmt = $pdo->prepare("
            SELECT o.id, o.numero, o.status, o.status_pagamento,
                   o.valor_total, o.tipo_finalizacao, o.prazo, o.data_entrada,
                   c.nome AS cliente_nome, c.nome_fantasia, c.telefone
            FROM ordens_servico o
            LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
            WHERE o.status != 'cancelado'
            ORDER BY o.status_pagamento ASC, o.data_entrada DESC LIMIT 300
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT o.id, o.numero, o.status, o.status_pagamento,
                   o.valor_total, o.tipo_finalizacao, o.prazo, o.data_entrada,
                   c.nome AS cliente_nome, c.nome_fantasia, c.telefone
            FROM ordens_servico o
            LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
            WHERE o.status_pagamento IN ('pendente', 'parcial') AND o.status != 'cancelado'
            ORDER BY o.status_pagamento ASC, o.data_entrada DESC LIMIT 300
        ");
    }
    $stmt->execute();
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Histórico de pagamentos registrados
    $historico = $pdo->query("
        SELECT p.id, p.valor, p.forma, p.observacao, p.criado_em,
               o.numero AS os_numero, c.nome AS cliente_nome
        FROM pagamentos p
        JOIN ordens_servico o ON o.id = p.os_id
        LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
        ORDER BY p.criado_em DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    // KPIs
    $kpis = $pdo->query("
        SELECT
            SUM(CASE WHEN status_pagamento = 'pendente' AND status != 'cancelado' THEN valor_total ELSE 0 END) AS a_receber_pendente,
            SUM(CASE WHEN status_pagamento = 'parcial'  AND status != 'cancelado' THEN valor_total ELSE 0 END) AS a_receber_parcial,
            COUNT(CASE WHEN status_pagamento IN ('pendente','parcial') AND status != 'cancelado' THEN 1 END)    AS qtd_pendente,
            SUM(CASE WHEN status_pagamento = 'pago' AND DATE_FORMAT(data_entrada,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m') THEN valor_total ELSE 0 END) AS recebido_mes,
            COUNT(CASE WHEN status_pagamento = 'pago' AND DATE_FORMAT(data_entrada,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m') THEN 1 END) AS qtd_pago_mes
        FROM ordens_servico
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $lista = [];
    $kpis  = [];
    $_SESSION['flash_error'] = 'Erro ao carregar pagamentos: ' . $e->getMessage();
}

function fmtR(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}
function badgePag(string $s): string {
    return match($s) {
        'pago'    => '<span class="badge badge-success">Pago</span>',
        'parcial' => '<span class="badge badge-warning">Parcial</span>',
        default   => '<span class="badge badge-neutral">Pendente</span>',
    };
}
function badgeOS(string $s): string {
    return match($s) {
        'aguardando'   => '<span class="badge badge-warning">Aguardando</span>',
        'em_andamento' => '<span class="badge badge-info">Em andamento</span>',
        'pronto'       => '<span class="badge badge-success">Pronto</span>',
        'entregue'     => '<span class="badge badge-neutral">Entregue</span>',
        'cancelado'    => '<span class="badge badge-danger">Cancelado</span>',
        default        => '<span class="badge badge-neutral">' . htmlspecialchars($s) . '</span>',
    };
}

$topbarActions = '';

$extraHead = '<style>
/* ══ KPIs ═══════════════════════════════════════════════════ */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi  { background: var(--surface-1); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1rem 1.2rem; }
.kpi-val { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
.kpi-val.sm { font-size: 1.1rem; }
.kpi-lbl { font-size: .74rem; color: var(--text-muted); margin-top: .3rem; }

/* ══ Tabela ══════════════════════════════════════════════════ */
.tbl-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.tbl-toolbar {
    display: flex; gap: .75rem; align-items: center;
    padding: .9rem 1.1rem; border-bottom: 1px solid var(--border); flex-wrap: wrap;
}
.filter-tabs { display: flex; gap: .25rem; }
.ftab {
    padding: .32rem .8rem; border-radius: var(--radius-md); font-size: .78rem;
    font-weight: 600; color: var(--text-muted); text-decoration: none;
    transition: all var(--transition);
}
.ftab:hover { background: var(--surface-2); color: var(--text-primary); }
.ftab.active { background: rgba(201,168,76,.15); color: var(--gold); }
.tbl-count { font-size: .78rem; color: var(--text-muted); margin-left: auto; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    padding: .65rem 1rem; font-size: .71rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em;
    text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap;
}
.data-table td {
    padding: .8rem 1rem; font-size: .875rem; color: var(--text-primary);
    border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(255,255,255,.02); }
.num-os { font-weight: 700; color: var(--gold); font-family: monospace; }
.cli-nome { font-weight: 600; }
.cli-fan  { font-size: .75rem; color: var(--text-muted); }
.val-total { font-weight: 700; font-size: .95rem; }
.actions-cell { display: flex; gap: .4rem; align-items: center; justify-content: flex-end; }
.btn-icon {
    width: 30px; height: 30px; border-radius: var(--radius-md);
    border: 1px solid var(--border); background: var(--surface-2); color: var(--text-muted);
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    font-size: .78rem; transition: all var(--transition);
}
.btn-icon:hover { border-color: var(--gold); color: var(--gold); background: rgba(201,168,76,.08); }
.btn-icon.danger:hover { border-color: var(--danger); color: var(--danger); background: rgba(248,113,113,.08); }
.empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
.empty-state i { font-size: 1.8rem; margin-bottom: .6rem; opacity: .35; display: block; }

/* ══ Modal de pagamento ══════════════════════════════════════ */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.7); z-index: 1000;
    align-items: center; justify-content: center; padding: 1rem;
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-xl); width: 100%; max-width: 420px;
    transform: scale(.95) translateY(10px); opacity: 0;
    transition: transform .22s ease, opacity .22s ease;
}
.modal-overlay.open .modal { transform: scale(1) translateY(0); opacity: 1; }
.modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.1rem 1.3rem; border-bottom: 1px solid var(--border);
}
.modal-title { font-size: .95rem; font-weight: 600; color: var(--text-primary); }
.modal-title i { color: var(--gold); margin-right: .4rem; }
.btn-close-modal {
    background: none; border: none; color: var(--text-muted); cursor: pointer;
    font-size: 1rem; padding: .25rem; transition: color var(--transition);
}
.btn-close-modal:hover { color: var(--text-primary); }
.modal-body { padding: 1.3rem; }
.modal-foot {
    display: flex; gap: .6rem; justify-content: flex-end;
    padding: .9rem 1.3rem; border-top: 1px solid var(--border);
}

/* ══ Formas de pagamento ═════════════════════════════════════ */
.formas-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem;
    margin-bottom: 1rem;
}
.forma-opt { display: none; }
.forma-label {
    display: flex; flex-direction: column; align-items: center; gap: .3rem;
    padding: .6rem .4rem; border: 1px solid var(--border); border-radius: var(--radius-md);
    cursor: pointer; font-size: .72rem; font-weight: 600; color: var(--text-muted);
    text-align: center; transition: all var(--transition);
}
.forma-label i { font-size: 1.1rem; }
.forma-opt:checked + .forma-label {
    border-color: var(--gold); color: var(--gold);
    background: rgba(201,168,76,.1);
}
.forma-label:hover { border-color: rgba(201,168,76,.4); color: var(--text-primary); }

/* ══ Status select ═══════════════════════════════════════════ */
.form-group { margin-bottom: 1rem; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: .78rem; font-weight: 600; color: var(--text-secondary); margin-bottom: .3rem; }
.form-control, .form-select {
    width: 100%; background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-md); color: var(--text-primary); font-size: .875rem;
    padding: .5rem .8rem; font-family: inherit; transition: border-color var(--transition);
}
.form-control:focus, .form-select:focus {
    outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,.12);
}

/* ══ Resumo da OS no modal ═══════════════════════════════════ */
.os-resumo {
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: .75rem 1rem; margin-bottom: 1.1rem;
    display: flex; justify-content: space-between; align-items: center; gap: .5rem;
}
.os-resumo-num { font-weight: 700; color: var(--gold); font-family: monospace; font-size: .88rem; }
.os-resumo-cli { font-size: .82rem; color: var(--text-secondary); }
.os-resumo-val { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); white-space: nowrap; }
</style>';

require_once 'includes/header.php';
?>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi">
        <div class="kpi-val" style="color:var(--danger)"><?= fmtR((float)($kpis['a_receber_pendente'] ?? 0)) ?></div>
        <div class="kpi-lbl">A receber (pendente)</div>
    </div>
    <div class="kpi">
        <div class="kpi-val sm" style="color:var(--warning)"><?= fmtR((float)($kpis['a_receber_parcial'] ?? 0)) ?></div>
        <div class="kpi-lbl">A receber (parcial)</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--text-muted)"><?= (int)($kpis['qtd_pendente'] ?? 0) ?></div>
        <div class="kpi-lbl">OS em aberto</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--success)"><?= fmtR((float)($kpis['recebido_mes'] ?? 0)) ?></div>
        <div class="kpi-lbl">Recebido este mês</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--text-muted)"><?= (int)($kpis['qtd_pago_mes'] ?? 0) ?></div>
        <div class="kpi-lbl">OS pagas este mês</div>
    </div>
</div>

<!-- Tabela -->
<div class="tbl-card">
    <div class="tbl-toolbar">
        <div class="filter-tabs">
            <a href="?filtro=pendente" class="ftab<?= $filtro === 'pendente' ? ' active' : '' ?>">Pendentes</a>
            <a href="?filtro=pago"     class="ftab<?= $filtro === 'pago'     ? ' active' : '' ?>">Pagos</a>
            <a href="?filtro=todos"    class="ftab<?= $filtro === 'todos'    ? ' active' : '' ?>">Todos</a>
        </div>
        <span class="tbl-count"><?= count($lista) ?> registro<?= count($lista) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($lista)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p><?= $filtro === 'pendente' ? 'Nenhum pagamento pendente.' : 'Nenhum registro encontrado.' ?></p>
        </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>OS</th>
                <th>Cliente</th>
                <th>Status OS</th>
                <th>Pagamento</th>
                <th>Total</th>
                <th>Data entrada</th>
                <th style="text-align:right">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $os): ?>
            <tr>
                <td><span class="num-os">#<?= htmlspecialchars($os['numero']) ?></span></td>
                <td>
                    <div class="cli-nome"><?= htmlspecialchars($os['cliente_nome'] ?? '—') ?></div>
                    <?php if (!empty($os['nome_fantasia'])): ?>
                        <div class="cli-fan"><?= htmlspecialchars($os['nome_fantasia']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= badgeOS($os['status']) ?></td>
                <td><?= badgePag($os['status_pagamento']) ?></td>
                <td><span class="val-total"><?= fmtR((float)$os['valor_total']) ?></span></td>
                <td style="color:var(--text-muted);font-size:.82rem">
                    <?= date('d/m/Y', strtotime($os['data_entrada'])) ?>
                </td>
                <td>
                    <div class="actions-cell">
                        <?php if ($os['status_pagamento'] !== 'pago'): ?>
                            <button
                                class="btn-icon"
                                title="Registrar pagamento"
                                style="color:var(--success);border-color:rgba(52,211,153,.3)"
                                onclick="openPagar(<?= htmlspecialchars(json_encode([
                                    'id'     => $os['id'],
                                    'numero' => $os['numero'],
                                    'cliente'=> $os['cliente_nome'] ?? '—',
                                    'total'  => number_format((float)$os['valor_total'], 2, ',', '.'),
                                    'status' => $os['status_pagamento'],
                                ]), ENT_QUOTES) ?>)">
                                <i class="fas fa-check-double"></i>
                            </button>
                        <?php else: ?>
                            <form method="POST" style="display:contents"
                                  onsubmit="return confirm('Reverter pagamento para pendente?')">
                                <input type="hidden" name="action"  value="reverter">
                                <input type="hidden" name="os_id"   value="<?= $os['id'] ?>">
                                <button type="submit" class="btn-icon danger" title="Reverter para pendente">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Histórico de pagamentos registrados -->
<?php if (!empty($historico)): ?>
<div class="tbl-card" style="margin-top:1.25rem">
    <div class="tbl-toolbar">
        <span style="font-size:.8rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">
            <i class="fas fa-history" style="color:var(--gold);margin-right:.4rem"></i>
            Últimos pagamentos registrados
        </span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>OS</th>
                <th>Cliente</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Data</th>
                <th>Obs.</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $formaLabel = [
                'dinheiro'       => '<i class="fas fa-money-bill-wave"></i> Dinheiro',
                'pix'            => '<i class="fas fa-bolt"></i> PIX',
                'cartao_debito'  => '<i class="fas fa-credit-card"></i> Débito',
                'cartao_credito' => '<i class="fas fa-credit-card"></i> Crédito',
                'transferencia'  => '<i class="fas fa-exchange-alt"></i> Transfer.',
            ];
            foreach ($historico as $h): ?>
            <tr>
                <td><span class="num-os">#<?= htmlspecialchars($h['os_numero']) ?></span></td>
                <td style="font-weight:600"><?= htmlspecialchars($h['cliente_nome'] ?? '—') ?></td>
                <td style="font-size:.8rem;color:var(--text-secondary)"><?= $formaLabel[$h['forma']] ?? htmlspecialchars($h['forma']) ?></td>
                <td><span class="val-total" style="color:var(--success)"><?= fmtR((float)$h['valor']) ?></span></td>
                <td style="color:var(--text-muted);font-size:.8rem"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></td>
                <td style="color:var(--text-muted);font-size:.8rem"><?= htmlspecialchars($h['observacao'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal de pagamento -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-title"><i class="fas fa-hand-holding-usd"></i> Registrar pagamento</span>
            <button class="btn-close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="formPagar">
            <input type="hidden" name="action" value="pagar">
            <input type="hidden" name="os_id"  id="fOsId">
            <input type="hidden" name="os_num" id="fOsNum">

            <div class="modal-body">
                <!-- Resumo -->
                <div class="os-resumo">
                    <div>
                        <div class="os-resumo-num" id="rNum"></div>
                        <div class="os-resumo-cli" id="rCli"></div>
                    </div>
                    <div class="os-resumo-val" id="rVal"></div>
                </div>

                <!-- Forma de pagamento -->
                <div class="form-group">
                    <label class="form-label">Forma de pagamento</label>
                    <div class="formas-grid">
                        <label>
                            <input type="radio" name="forma" value="dinheiro" class="forma-opt" checked>
                            <span class="forma-label"><i class="fas fa-money-bill-wave"></i>Dinheiro</span>
                        </label>
                        <label>
                            <input type="radio" name="forma" value="pix" class="forma-opt">
                            <span class="forma-label"><i class="fas fa-bolt"></i>PIX</span>
                        </label>
                        <label>
                            <input type="radio" name="forma" value="cartao_debito" class="forma-opt">
                            <span class="forma-label"><i class="fas fa-credit-card"></i>Débito</span>
                        </label>
                        <label>
                            <input type="radio" name="forma" value="cartao_credito" class="forma-opt">
                            <span class="forma-label"><i class="fas fa-credit-card"></i>Crédito</span>
                        </label>
                        <label>
                            <input type="radio" name="forma" value="transferencia" class="forma-opt">
                            <span class="forma-label"><i class="fas fa-exchange-alt"></i>Transfer.</span>
                        </label>
                    </div>
                </div>

                <!-- Valor recebido -->
                <div class="form-group">
                    <label class="form-label" for="fValor">Valor recebido</label>
                    <input type="text" name="valor" id="fValor" class="form-control"
                           placeholder="0,00" autocomplete="off" inputmode="numeric">
                </div>

                <!-- Status final -->
                <div class="form-group">
                    <label class="form-label" for="fStatusPag">Situação após o pagamento</label>
                    <select name="status_pagamento" id="fStatusPag" class="form-select">
                        <option value="pago">Pago integralmente</option>
                        <option value="parcial">Parcialmente pago</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fObs">Observação (opcional)</label>
                    <input type="text" name="obs" id="fObs" class="form-control"
                           placeholder="Ex.: troco R$ 10,00, parcelado 2x...">
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-check"></i> Confirmar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
var overlay = document.getElementById('modalOverlay');

function formatCents(cents) {
    return (cents / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Máscara ATM no campo valor
var fValor = document.getElementById('fValor');
fValor.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') return;
    if (e.key === 'Backspace') {
        e.preventDefault();
        var d = this.value.replace(/\D/g,'').slice(0,-1);
        this.value = d ? formatCents(parseInt(d,10)) : '';
        return;
    }
    if (!/^\d$/.test(e.key)) { e.preventDefault(); return; }
    e.preventDefault();
    var d = (this.value.replace(/\D/g,'') + e.key).replace(/^0+/,'') || '0';
    this.value = formatCents(parseInt(d,10));
});
fValor.addEventListener('focus', function() { this.setSelectionRange(this.value.length, this.value.length); });

function openPagar(data) {
    document.getElementById('fOsId').value      = data.id;
    document.getElementById('fOsNum').value     = data.numero;
    document.getElementById('rNum').textContent = '#' + data.numero;
    document.getElementById('rCli').textContent = data.cliente;
    document.getElementById('rVal').textContent = 'R$ ' + data.total;
    document.getElementById('fValor').value     = data.total;
    document.getElementById('fStatusPag').value = data.status === 'parcial' ? 'parcial' : 'pago';
    overlay.classList.add('open');
    setTimeout(function() { document.getElementById('fValor').focus(); }, 80);
}

function closeModal() { overlay.classList.remove('open'); }

overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });
</script>
JS;

require_once 'includes/footer.php';
