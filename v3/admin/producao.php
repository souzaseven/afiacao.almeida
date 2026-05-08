<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle   = 'Produção';
$currentPage = 'producao';
$breadcrumb  = 'Fila de produção';

// ── POST: alterar status ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $_POST['novo_status'] ?? '';
    if ($id > 0 && in_array($st, ['aguardando','em_andamento','pronto','entregue','cancelado'])) {
        try {
            $pdo->prepare("UPDATE ordens_servico SET status=? WHERE id=?")->execute([$st, $id]);
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Erro ao atualizar status.';
        }
    }
    header('Location: producao.php');
    exit;
}

// ── Carregar OS em produção ───────────────────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT o.id, o.numero, o.status, o.prazo, o.valor_total,
               o.tipo_finalizacao, o.status_pagamento, o.criado_em,
               c.nome AS cliente_nome, c.nome_fantasia, c.telefone,
               COUNT(i.id) AS total_itens,
               GROUP_CONCAT(i.equipamento ORDER BY i.id SEPARATOR ', ') AS equipamentos
        FROM ordens_servico o
        LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
        LEFT JOIN itens_os i ON i.os_id = o.id
        WHERE o.status NOT IN ('entregue','cancelado')
        GROUP BY o.id
        ORDER BY FIELD(o.status,'aguardando','em_andamento','pronto'), o.prazo ASC, o.id ASC
    ");
    $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todas = [];
    $_SESSION['flash_error'] = 'Erro ao carregar ordens: ' . $e->getMessage();
}

$hoje  = date('Y-m-d');
$grupos = ['aguardando' => [], 'em_andamento' => [], 'pronto' => []];
foreach ($todas as $os) {
    if (isset($grupos[$os['status']])) $grupos[$os['status']][] = $os;
}

$colunas = [
    'aguardando'   => [
        'label'          => 'Aguardando',
        'cor'            => '--warning',
        'icon'           => 'fa-clock',
        'proximo'        => 'em_andamento',
        'proximo_label'  => 'Iniciar',
        'proximo_icon'   => 'fa-play',
        'anterior'       => null,
    ],
    'em_andamento' => [
        'label'          => 'Em andamento',
        'cor'            => '--info',
        'icon'           => 'fa-wrench',
        'proximo'        => 'pronto',
        'proximo_label'  => 'Concluir',
        'proximo_icon'   => 'fa-check',
        'anterior'       => 'aguardando',
        'anterior_label' => 'Pausar',
        'anterior_icon'  => 'fa-pause',
    ],
    'pronto'       => [
        'label'          => 'Pronto',
        'cor'            => '--success',
        'icon'           => 'fa-check-circle',
        'proximo'        => 'entregue',
        'proximo_label'  => 'Entregar',
        'proximo_icon'   => 'fa-truck',
        'anterior'       => 'em_andamento',
        'anterior_label' => 'Reabrir',
        'anterior_icon'  => 'fa-undo',
    ],
];

$topbarActions = '<a href="os.php" class="btn btn-ghost btn-sm">
    <i class="fas fa-file-invoice"></i> Ver todas as OS
</a>';

$extraHead = '<style>
/* ══ Kanban ═══════════════════════════════════════════════════ */
.kanban {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 900px) {
    .kanban { grid-template-columns: 1fr; }
}

.kanban-col {
    background: var(--surface-1);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.kanban-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--border);
}
.kanban-head-left {
    display: flex;
    align-items: center;
    gap: .55rem;
    font-size: .82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.kanban-count {
    font-size: .72rem;
    font-weight: 700;
    padding: .18rem .55rem;
    border-radius: 100px;
}
.kanban-body {
    padding: .75rem;
    display: flex;
    flex-direction: column;
    gap: .65rem;
    min-height: 80px;
}

/* ══ Card ════════════════════════════════════════════════════ */
.os-card {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: .85rem .9rem;
    transition: border-color var(--transition), box-shadow var(--transition);
}
.os-card:hover {
    border-color: rgba(201,168,76,.3);
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
}
.os-card.vencida {
    border-color: rgba(248,113,113,.35);
}
.card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: .55rem;
    gap: .5rem;
}
.card-num {
    font-size: .8rem;
    font-weight: 700;
    color: var(--gold);
    font-family: monospace;
}
.card-badges {
    display: flex;
    gap: .3rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.card-cliente {
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: .15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.card-fantasia {
    font-size: .75rem;
    color: var(--text-muted);
    margin-bottom: .45rem;
}
.card-equip {
    font-size: .78rem;
    color: var(--text-secondary);
    margin-bottom: .55rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.card-equip i {
    color: var(--text-muted);
    margin-right: .3rem;
    font-size: .72rem;
}
.card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    flex-wrap: wrap;
}
.card-prazo {
    font-size: .75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .3rem;
}
.card-prazo.vencida { color: var(--danger); font-weight: 600; }
.card-prazo.hoje    { color: var(--warning); font-weight: 600; }
.card-actions {
    display: flex;
    gap: .35rem;
}
.btn-card {
    height: 28px;
    padding: 0 .6rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--surface-1);
    color: var(--text-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .72rem;
    font-weight: 600;
    transition: all var(--transition);
    white-space: nowrap;
    font-family: inherit;
}
.btn-card:hover          { border-color: var(--gold); color: var(--gold); background: rgba(201,168,76,.08); }
.btn-card.btn-avancar    { border-color: rgba(52,211,153,.3); color: var(--success); background: var(--success-dim); }
.btn-card.btn-avancar:hover { background: rgba(52,211,153,.2); }
.btn-card.btn-entregar   { border-color: rgba(96,165,250,.3); color: var(--info); background: var(--info-dim); }
.btn-card.btn-entregar:hover { background: rgba(96,165,250,.2); }

/* ══ Empty col ════════════════════════════════════════════════ */
.col-empty {
    text-align: center;
    padding: 1.5rem 1rem;
    color: var(--text-muted);
    font-size: .82rem;
}
.col-empty i { display: block; font-size: 1.4rem; opacity: .25; margin-bottom: .5rem; }

/* ══ Totalizador ══════════════════════════════════════════════ */
.prod-summary {
    display: flex;
    gap: .75rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.prod-kpi {
    background: var(--surface-1);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: .7rem 1.1rem;
    display: flex;
    align-items: center;
    gap: .65rem;
    flex: 1;
    min-width: 130px;
}
.prod-kpi-icon {
    width: 34px; height: 34px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; flex-shrink: 0;
}
.prod-kpi-val { font-size: 1.3rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
.prod-kpi-lbl { font-size: .72rem; color: var(--text-muted); margin-top: .15rem; }
</style>';

require_once 'includes/header.php';
?>

<!-- Totalizador -->
<div class="prod-summary">
    <div class="prod-kpi">
        <div class="prod-kpi-icon" style="background:var(--warning-dim);color:var(--warning)">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <div class="prod-kpi-val"><?= count($grupos['aguardando']) ?></div>
            <div class="prod-kpi-lbl">Aguardando</div>
        </div>
    </div>
    <div class="prod-kpi">
        <div class="prod-kpi-icon" style="background:var(--info-dim);color:var(--info)">
            <i class="fas fa-wrench"></i>
        </div>
        <div>
            <div class="prod-kpi-val"><?= count($grupos['em_andamento']) ?></div>
            <div class="prod-kpi-lbl">Em andamento</div>
        </div>
    </div>
    <div class="prod-kpi">
        <div class="prod-kpi-icon" style="background:var(--success-dim);color:var(--success)">
            <i class="fas fa-check-circle"></i>
        </div>
        <div>
            <div class="prod-kpi-val"><?= count($grupos['pronto']) ?></div>
            <div class="prod-kpi-lbl">Prontos</div>
        </div>
    </div>
    <?php
    $vencidas = array_filter($todas, fn($o) => $o['prazo'] && $o['prazo'] < $hoje);
    $nVenc = count($vencidas);
    ?>
    <?php if ($nVenc > 0): ?>
    <div class="prod-kpi">
        <div class="prod-kpi-icon" style="background:var(--danger-dim);color:var(--danger)">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <div class="prod-kpi-val" style="color:var(--danger)"><?= $nVenc ?></div>
            <div class="prod-kpi-lbl">Vencida<?= $nVenc !== 1 ? 's' : '' ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Kanban -->
<div class="kanban">
    <?php foreach ($colunas as $status => $col): ?>
    <div class="kanban-col">
        <div class="kanban-head">
            <div class="kanban-head-left" style="color:var(<?= $col['cor'] ?>)">
                <i class="fas <?= $col['icon'] ?>"></i>
                <?= $col['label'] ?>
            </div>
            <span class="kanban-count"
                  style="background:color-mix(in srgb,var(<?= $col['cor'] ?>) 15%,transparent);color:var(<?= $col['cor'] ?>)">
                <?= count($grupos[$status]) ?>
            </span>
        </div>

        <div class="kanban-body">
            <?php if (empty($grupos[$status])): ?>
                <div class="col-empty">
                    <i class="fas <?= $col['icon'] ?>"></i>
                    Nenhuma OS aqui
                </div>
            <?php else: ?>
                <?php foreach ($grupos[$status] as $os):
                    $vencida = $os['prazo'] && $os['prazo'] < $hoje;
                    $eHoje   = $os['prazo'] === $hoje;
                    $nomeExib = $os['cliente_nome'] ?? 'Sem cliente';
                ?>
                <div class="os-card <?= $vencida ? 'vencida' : '' ?>">
                    <div class="card-top">
                        <span class="card-num">#<?= htmlspecialchars($os['numero']) ?></span>
                        <div class="card-badges">
                            <?php if ($os['tipo_finalizacao'] === 'entrega'): ?>
                                <span class="badge badge-info" style="font-size:.65rem">
                                    <i class="fas fa-truck"></i> Entrega
                                </span>
                            <?php endif; ?>
                            <?php if ($os['status_pagamento'] === 'pago'): ?>
                                <span class="badge badge-success" style="font-size:.65rem">Pago</span>
                            <?php elseif ($os['status_pagamento'] === 'parcial'): ?>
                                <span class="badge badge-warning" style="font-size:.65rem">Parcial</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-cliente"><?= htmlspecialchars($nomeExib) ?></div>
                    <?php if (!empty($os['nome_fantasia'])): ?>
                        <div class="card-fantasia"><?= htmlspecialchars($os['nome_fantasia']) ?></div>
                    <?php endif; ?>

                    <?php if ($os['equipamentos']): ?>
                        <div class="card-equip">
                            <i class="fas fa-tools"></i><?= htmlspecialchars($os['equipamentos']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-meta">
                        <div class="card-prazo <?= $vencida ? 'vencida' : ($eHoje ? 'hoje' : '') ?>">
                            <?php if ($os['prazo']): ?>
                                <i class="fas fa-<?= $vencida ? 'exclamation-triangle' : ($eHoje ? 'exclamation-circle' : 'calendar') ?>"></i>
                                <?= $vencida ? 'Venceu ' : ($eHoje ? 'Hoje — ' : '') ?><?= date('d/m', strtotime($os['prazo'])) ?>
                            <?php else: ?>
                                <i class="fas fa-calendar-times"></i> Sem prazo
                            <?php endif; ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-box"></i> <?= (int)$os['total_itens'] ?> item<?= (int)$os['total_itens'] !== 1 ? 's' : '' ?>
                        </div>

                        <div class="card-actions">
                            <?php if ($col['anterior'] ?? false): ?>
                                <form method="POST" style="display:contents">
                                    <input type="hidden" name="id" value="<?= $os['id'] ?>">
                                    <input type="hidden" name="novo_status" value="<?= $col['anterior'] ?>">
                                    <button type="submit" class="btn-card" title="<?= $col['anterior_label'] ?>">
                                        <i class="fas <?= $col['anterior_icon'] ?>"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display:contents">
                                <input type="hidden" name="id" value="<?= $os['id'] ?>">
                                <input type="hidden" name="novo_status" value="<?= $col['proximo'] ?>">
                                <button type="submit"
                                    class="btn-card <?= $col['proximo'] === 'entregue' ? 'btn-entregar' : 'btn-avancar' ?>"
                                    title="<?= $col['proximo_label'] ?>">
                                    <i class="fas <?= $col['proximo_icon'] ?>"></i>
                                    <?= $col['proximo_label'] ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$extraScripts = <<<'JS'
<script>
// Auto-refresh a cada 60 s para manter a fila atualizada
setTimeout(function() { location.reload(); }, 60000);
</script>
JS;

require_once 'includes/footer.php';
