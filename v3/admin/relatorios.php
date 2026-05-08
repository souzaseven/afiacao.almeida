<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle   = 'Relatórios';
$currentPage = 'relatorios';
$breadcrumb  = 'Análise e desempenho';

// ── Período ───────────────────────────────────────────────────────────────────
$periodo = $_GET['periodo'] ?? 'mes';
$hoje    = date('Y-m-d');

switch ($periodo) {
    case 'hoje':
        $de = $ate = $hoje;
        break;
    case 'semana':
        $de  = date('Y-m-d', strtotime('monday this week'));
        $ate = $hoje;
        break;
    case 'ano':
        $de  = date('Y-01-01');
        $ate = $hoje;
        break;
    case 'personalizado':
        $de  = $_GET['de']  ?? date('Y-m-01');
        $ate = $_GET['ate'] ?? $hoje;
        // sanitizar datas
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  $de  = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) $ate = $hoje;
        if ($ate < $de) [$de, $ate] = [$ate, $de];
        break;
    default:
        $periodo = 'mes';
        $de  = date('Y-m-01');
        $ate = $hoje;
}

$dias_diff  = max(1, (int)((strtotime($ate) - strtotime($de)) / 86400) + 1);
$agrupar    = $dias_diff > 60 ? 'mes' : 'dia';

// ── Queries ───────────────────────────────────────────────────────────────────
try {
    // 1. KPIs do período
    $kpis = $pdo->prepare("
        SELECT
            COUNT(*)                                                                    AS total_os,
            COALESCE(SUM(valor_total), 0)                                               AS faturamento,
            COALESCE(SUM(CASE WHEN status_pagamento = 'pago'               THEN valor_total ELSE 0 END), 0) AS recebido,
            COALESCE(SUM(CASE WHEN status_pagamento IN ('pendente','parcial') THEN valor_total ELSE 0 END), 0) AS pendente,
            COALESCE(AVG(valor_total), 0)                                               AS ticket_medio,
            COUNT(CASE WHEN status = 'entregue' THEN 1 END)                             AS os_entregues,
            COUNT(CASE WHEN status = 'cancelado' THEN 1 END)                            AS os_canceladas
        FROM ordens_servico
        WHERE DATE(data_entrada) BETWEEN ? AND ?
    ");
    $kpis->execute([$de, $ate]);
    $kpis = $kpis->fetch(PDO::FETCH_ASSOC);

    // 2. Faturamento por período (dia ou mês)
    if ($agrupar === 'mes') {
        $sqlPeriodo = "
            SELECT DATE_FORMAT(data_entrada,'%Y-%m') AS periodo,
                   DATE_FORMAT(data_entrada,'%m/%Y')  AS periodo_fmt,
                   COUNT(*)                            AS qtd_os,
                   COALESCE(SUM(valor_total), 0)       AS total,
                   COALESCE(SUM(CASE WHEN status_pagamento='pago' THEN valor_total ELSE 0 END),0) AS recebido
            FROM ordens_servico
            WHERE DATE(data_entrada) BETWEEN ? AND ? AND status != 'cancelado'
            GROUP BY DATE_FORMAT(data_entrada,'%Y-%m')
            ORDER BY periodo ASC
        ";
    } else {
        $sqlPeriodo = "
            SELECT DATE(data_entrada)                  AS periodo,
                   DATE_FORMAT(data_entrada,'%d/%m')   AS periodo_fmt,
                   COUNT(*)                            AS qtd_os,
                   COALESCE(SUM(valor_total), 0)       AS total,
                   COALESCE(SUM(CASE WHEN status_pagamento='pago' THEN valor_total ELSE 0 END),0) AS recebido
            FROM ordens_servico
            WHERE DATE(data_entrada) BETWEEN ? AND ? AND status != 'cancelado'
            GROUP BY DATE(data_entrada)
            ORDER BY periodo ASC
        ";
    }
    $stmtPer = $pdo->prepare($sqlPeriodo);
    $stmtPer->execute([$de, $ate]);
    $porPeriodo = $stmtPer->fetchAll(PDO::FETCH_ASSOC);

    // 3. Faturamento últimos 6 meses (independe do filtro)
    $stmtMes = $pdo->prepare("
        SELECT DATE_FORMAT(data_entrada,'%Y-%m') AS mes,
               DATE_FORMAT(data_entrada,'%b/%Y') AS mes_fmt,
               COUNT(*)                          AS qtd,
               COALESCE(SUM(valor_total), 0)     AS total
        FROM ordens_servico
        WHERE data_entrada >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          AND status != 'cancelado'
        GROUP BY DATE_FORMAT(data_entrada,'%Y-%m')
        ORDER BY mes ASC
    ");
    $stmtMes->execute();
    $ultMeses = $stmtMes->fetchAll(PDO::FETCH_ASSOC);

    // 4. OS por status (geral)
    $stmtStatus = $pdo->query("
        SELECT status, COUNT(*) AS qtd
        FROM ordens_servico
        GROUP BY status
        ORDER BY qtd DESC
    ");
    $porStatus = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
    $totalStatusGeral = array_sum(array_column($porStatus, 'qtd'));

    // 5. Top 10 clientes no período
    $stmtCli = $pdo->prepare("
        SELECT c.nome, c.nome_fantasia,
               COUNT(o.id)            AS qtd_os,
               COALESCE(SUM(o.valor_total), 0) AS total,
               COALESCE(SUM(CASE WHEN o.status_pagamento='pago' THEN o.valor_total ELSE 0 END),0) AS recebido
        FROM ordens_servico o
        JOIN afiacao_clientes c ON c.id = o.cliente_id
        WHERE DATE(o.data_entrada) BETWEEN ? AND ? AND o.status != 'cancelado'
        GROUP BY o.cliente_id
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmtCli->execute([$de, $ate]);
    $topClientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);

    // 6. Top serviços no período
    $stmtSrv = $pdo->prepare("
        SELECT s.nome,
               COUNT(i.id)                            AS qtd_uso,
               COALESCE(SUM(i.quantidade), 0)         AS total_pcs,
               COALESCE(SUM(i.quantidade * i.valor_unitario), 0) AS receita
        FROM itens_os i
        JOIN servicos s        ON s.id = i.servico_id
        JOIN ordens_servico o  ON o.id = i.os_id
        WHERE DATE(o.data_entrada) BETWEEN ? AND ? AND o.status != 'cancelado'
        GROUP BY i.servico_id
        ORDER BY receita DESC
        LIMIT 10
    ");
    $stmtSrv->execute([$de, $ate]);
    $topServicos = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);

    // 7. Pagamentos breakdown no período
    $stmtPag = $pdo->prepare("
        SELECT status_pagamento,
               COUNT(*)                      AS qtd,
               COALESCE(SUM(valor_total), 0) AS total
        FROM ordens_servico
        WHERE DATE(data_entrada) BETWEEN ? AND ? AND status != 'cancelado'
        GROUP BY status_pagamento
    ");
    $stmtPag->execute([$de, $ate]);
    $pagBreakdown = [];
    foreach ($stmtPag->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $pagBreakdown[$r['status_pagamento']] = $r;
    }

} catch (PDOException $e) {
    $kpis = $porPeriodo = $ultMeses = $porStatus = $topClientes = $topServicos = [];
    $pagBreakdown = []; $totalStatusGeral = 0;
    $_SESSION['flash_error'] = 'Erro ao carregar relatórios: ' . $e->getMessage();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function R(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
function pct(float $v, float $total): int {
    return $total > 0 ? (int)min(100, round($v / $total * 100)) : 0;
}

$maxPeriodo  = $porPeriodo  ? max(array_column($porPeriodo,  'total'))  : 1;
$maxClientes = $topClientes ? max(array_column($topClientes, 'total'))  : 1;
$maxServicos = $topServicos ? max(array_column($topServicos, 'receita')): 1;
$maxMes      = $ultMeses    ? max(array_column($ultMeses,    'total'))  : 1;

$topbarActions = '<button onclick="window.print()" class="btn btn-ghost btn-sm">
    <i class="fas fa-print"></i> Imprimir
</button>';

$extraHead = '<style>
/* ══ Filtro período ═══════════════════════════════════════════ */
.period-bar {
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: .75rem 1rem;
    display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;
    margin-bottom: 1.5rem;
}
.ptab {
    padding: .32rem .85rem; border-radius: var(--radius-md); font-size: .8rem;
    font-weight: 600; color: var(--text-muted); text-decoration: none;
    transition: all var(--transition); border: 1px solid transparent;
}
.ptab:hover { background: var(--surface-2); color: var(--text-primary); }
.ptab.active { background: rgba(201,168,76,.15); color: var(--gold); border-color: rgba(201,168,76,.3); }
.period-sep { width: 1px; height: 20px; background: var(--border); margin: 0 .25rem; }
.date-range { display: flex; gap: .5rem; align-items: center; }
.date-range input {
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-md); color: var(--text-primary);
    font-size: .8rem; padding: .3rem .6rem; font-family: inherit;
}
.date-range input:focus { outline: none; border-color: var(--gold); }
.date-range .btn { padding: .3rem .7rem; font-size: .8rem; }

/* ══ KPIs ═════════════════════════════════════════════════════ */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi  { background: var(--surface-1); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1rem 1.2rem; }
.kpi-val { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); line-height: 1; }
.kpi-val.sm { font-size: 1.05rem; }
.kpi-lbl { font-size: .73rem; color: var(--text-muted); margin-top: .3rem; }

/* ══ Seções ═══════════════════════════════════════════════════ */
.rel-section {
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 1.25rem;
}
.rel-section-head {
    display: flex; align-items: center; gap: .6rem;
    padding: .85rem 1.1rem; border-bottom: 1px solid var(--border);
    font-size: .82rem; font-weight: 700; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .05em;
}
.rel-section-head i { color: var(--gold); font-size: .9rem; }
.rel-body { padding: 1rem 1.1rem; }

/* ══ Tabela de dados ══════════════════════════════════════════ */
.rel-table { width: 100%; border-collapse: collapse; }
.rel-table th {
    padding: .55rem .8rem; font-size: .7rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em;
    text-align: left; border-bottom: 1px solid var(--border);
}
.rel-table td {
    padding: .65rem .8rem; font-size: .845rem; color: var(--text-primary);
    border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle;
}
.rel-table tr:last-child td { border-bottom: none; }
.rel-table tr:hover td { background: rgba(255,255,255,.02); }
.rel-table .num { font-weight: 700; color: var(--gold); }
.rel-table .muted { color: var(--text-muted); font-size: .8rem; }
.rel-table .right { text-align: right; }

/* ══ Barra visual ════════════════════════════════════════════ */
.bar-wrap { display: flex; align-items: center; gap: .6rem; }
.bar-track { flex: 1; height: 6px; background: var(--surface-2); border-radius: 100px; overflow: hidden; min-width: 60px; }
.bar-fill  { height: 100%; border-radius: 100px; background: var(--gold); transition: width .3s; }
.bar-fill.green  { background: var(--success); }
.bar-fill.blue   { background: var(--info); }
.bar-fill.rose   { background: var(--rose); }
.bar-val { font-size: .78rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; min-width: 80px; text-align: right; }

/* ══ Comparativo meses ════════════════════════════════════════ */
.mes-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px,1fr)); gap: .75rem; }
.mes-item { text-align: center; }
.mes-bar-wrap { height: 80px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: .4rem; }
.mes-bar { width: 28px; border-radius: 4px 4px 0 0; background: var(--gold); opacity: .7; min-height: 4px; transition: opacity .2s; }
.mes-bar.atual { opacity: 1; background: var(--gold); }
.mes-lbl { font-size: .7rem; color: var(--text-muted); }
.mes-val { font-size: .75rem; font-weight: 700; color: var(--text-primary); }

/* ══ Status pills ════════════════════════════════════════════ */
.status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: .75rem; }
.status-item {
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: .8rem 1rem;
}
.status-item-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .5rem; }
.status-item-lbl { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.status-item-qtd { font-size: .75rem; color: var(--text-muted); }
.status-item-val { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: .4rem; }

/* ══ Pag breakdown ═══════════════════════════════════════════ */
.pag-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: .75rem; }
@media(max-width:600px){ .pag-grid{ grid-template-columns:1fr; } }
.pag-card {
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: .9rem 1rem; text-align: center;
}
.pag-card-val { font-size: 1.25rem; font-weight: 700; line-height: 1; margin-bottom: .25rem; }
.pag-card-lbl { font-size: .73rem; color: var(--text-muted); }
.pag-card-qtd { font-size: .75rem; color: var(--text-secondary); margin-top: .2rem; }

/* ══ Empty ═══════════════════════════════════════════════════ */
.rel-empty { text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: .85rem; }
.rel-empty i { font-size: 1.5rem; opacity: .25; display: block; margin-bottom: .5rem; }

/* ══ Abas de seção ═══════════════════════════════════════════ */
.sec-tabs {
    display: flex; gap: .25rem; flex-wrap: wrap;
    background: var(--surface-1); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: .5rem;
    margin-bottom: 1.25rem;
}
.sec-tab {
    display: flex; align-items: center; gap: .45rem;
    padding: .45rem 1rem; border-radius: var(--radius-md);
    font-size: .82rem; font-weight: 600; color: var(--text-muted);
    cursor: pointer; border: none; background: none; font-family: inherit;
    transition: all var(--transition); white-space: nowrap;
}
.sec-tab:hover { background: var(--surface-2); color: var(--text-primary); }
.sec-tab.active {
    background: var(--gold); color: #141414;
    box-shadow: 0 2px 8px rgba(201,168,76,.35);
}
.sec-tab i { font-size: .8rem; }
.rel-section { display: none; }
.rel-section.visible { display: block; }

/* ══ Print ═══════════════════════════════════════════════════ */
@media print {
    .admin-sidebar, .topbar-actions, .period-bar, .sec-tabs, .nav-toggle { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .rel-section { display: block !important; break-inside: avoid; }
}
</style>';

require_once 'includes/header.php';

$labelPeriodo = match($periodo) {
    'hoje'         => 'Hoje',
    'semana'       => 'Esta semana',
    'ano'          => 'Este ano',
    'personalizado'=> date('d/m/Y', strtotime($de)) . ' – ' . date('d/m/Y', strtotime($ate)),
    default        => 'Este mês',
};
?>

<!-- Filtro de período -->
<div class="period-bar">
    <a href="?periodo=hoje"   class="ptab<?= $periodo==='hoje'   ?' active':''?>">Hoje</a>
    <a href="?periodo=semana" class="ptab<?= $periodo==='semana' ?' active':''?>">Esta semana</a>
    <a href="?periodo=mes"    class="ptab<?= $periodo==='mes'    ?' active':''?>">Este mês</a>
    <a href="?periodo=ano"    class="ptab<?= $periodo==='ano'    ?' active':''?>">Este ano</a>

    <div class="period-sep"></div>

    <form method="GET" class="date-range">
        <input type="hidden" name="periodo" value="personalizado">
        <input type="date" name="de"  value="<?= htmlspecialchars($de) ?>"  max="<?= $hoje ?>">
        <span style="color:var(--text-muted);font-size:.8rem">até</span>
        <input type="date" name="ate" value="<?= htmlspecialchars($ate) ?>" max="<?= $hoje ?>">
        <button type="submit" class="btn btn-ghost btn-sm">Filtrar</button>
    </form>
</div>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi">
        <div class="kpi-val sm"><?= R((float)$kpis['faturamento']) ?></div>
        <div class="kpi-lbl">Faturamento bruto</div>
    </div>
    <div class="kpi">
        <div class="kpi-val sm" style="color:var(--success)"><?= R((float)$kpis['recebido']) ?></div>
        <div class="kpi-lbl">Recebido</div>
    </div>
    <div class="kpi">
        <div class="kpi-val sm" style="color:var(--danger)"><?= R((float)$kpis['pendente']) ?></div>
        <div class="kpi-lbl">A receber</div>
    </div>
    <div class="kpi">
        <div class="kpi-val"><?= (int)$kpis['total_os'] ?></div>
        <div class="kpi-lbl">OS no período</div>
    </div>
    <div class="kpi">
        <div class="kpi-val sm"><?= R((float)$kpis['ticket_medio']) ?></div>
        <div class="kpi-lbl">Ticket médio</div>
    </div>
    <div class="kpi">
        <div class="kpi-val" style="color:var(--text-muted)"><?= (int)$kpis['os_entregues'] ?></div>
        <div class="kpi-lbl">OS entregues</div>
    </div>
</div>

<!-- Abas de seção -->
<div class="sec-tabs">
    <button class="sec-tab active" data-target="sec-faturamento">
        <i class="fas fa-chart-bar"></i> Faturamento
    </button>
    <button class="sec-tab" data-target="sec-comparativo">
        <i class="fas fa-calendar-alt"></i> Últimos 6 meses
    </button>
    <button class="sec-tab" data-target="sec-status">
        <i class="fas fa-tasks"></i> OS por status
    </button>
    <button class="sec-tab" data-target="sec-clientes">
        <i class="fas fa-users"></i> Top clientes
    </button>
    <button class="sec-tab" data-target="sec-servicos">
        <i class="fas fa-wrench"></i> Top serviços
    </button>
    <button class="sec-tab" data-target="sec-pagamentos">
        <i class="fas fa-hand-holding-usd"></i> Pagamentos
    </button>
</div>

<!-- ── 1. Faturamento por período ────────────────────────────────────────────── -->
<div class="rel-section visible" id="sec-faturamento">
    <div class="rel-section-head">
        <i class="fas fa-chart-bar"></i>
        Faturamento — <?= htmlspecialchars($labelPeriodo) ?>
        <span style="color:var(--text-muted);font-weight:400;font-size:.75rem;margin-left:auto">
            agrupado por <?= $agrupar === 'mes' ? 'mês' : 'dia' ?>
        </span>
    </div>
    <?php if (empty($porPeriodo)): ?>
        <div class="rel-empty"><i class="fas fa-chart-bar"></i>Sem dados no período.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="rel-table" style="min-width:500px">
        <thead>
            <tr>
                <th><?= $agrupar === 'mes' ? 'Mês' : 'Data' ?></th>
                <th class="right">OS</th>
                <th>Faturado</th>
                <th>Recebido</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($porPeriodo as $r): ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($r['periodo_fmt']) ?></td>
                <td class="right muted"><?= (int)$r['qtd_os'] ?></td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= pct((float)$r['total'], $maxPeriodo) ?>%"></div>
                        </div>
                        <span class="bar-val"><?= R((float)$r['total']) ?></span>
                    </div>
                </td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-track">
                            <div class="bar-fill green" style="width:<?= pct((float)$r['recebido'], $maxPeriodo) ?>%"></div>
                        </div>
                        <span class="bar-val" style="color:var(--success)"><?= R((float)$r['recebido']) ?></span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── 2. Comparativo últimos 6 meses ────────────────────────────────────────── -->
<?php if (!empty($ultMeses)): ?>
<div class="rel-section" id="sec-comparativo">
    <div class="rel-section-head">
        <i class="fas fa-calendar-alt"></i>
        Comparativo — últimos 6 meses
    </div>
    <div class="rel-body">
        <div class="mes-grid">
            <?php
            $mesAtual = date('Y-m');
            foreach ($ultMeses as $m):
                $h = $maxMes > 0 ? max(4, (int)(($m['total'] / $maxMes) * 76)) : 4;
            ?>
            <div class="mes-item">
                <div class="mes-bar-wrap">
                    <div class="mes-bar <?= $m['mes'] === $mesAtual ? 'atual' : '' ?>" style="height:<?= $h ?>px"></div>
                </div>
                <div class="mes-val"><?= R((float)$m['total']) ?></div>
                <div class="mes-lbl"><?= htmlspecialchars($m['mes_fmt']) ?></div>
                <div class="mes-lbl"><?= (int)$m['qtd'] ?> OS</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── 3. OS por status (geral) ──────────────────────────────────────────────── -->
<div class="rel-section" id="sec-status">
    <div class="rel-section-head">
        <i class="fas fa-tasks"></i>
        OS por status — geral (todas as OS)
    </div>
    <div class="rel-body">
        <?php if (empty($porStatus)): ?>
            <div class="rel-empty"><i class="fas fa-tasks"></i>Sem dados.</div>
        <?php else: ?>
        <div class="status-grid">
            <?php
            $statusCfg = [
                'aguardando'   => ['Aguardando',   '--warning'],
                'em_andamento' => ['Em andamento', '--info'],
                'pronto'       => ['Pronto',        '--success'],
                'entregue'     => ['Entregue',      '--text-muted'],
                'cancelado'    => ['Cancelado',     '--danger'],
            ];
            foreach ($porStatus as $r):
                $cfg = $statusCfg[$r['status']] ?? [ucfirst($r['status']), '--text-muted'];
                $p   = pct((int)$r['qtd'], $totalStatusGeral);
            ?>
            <div class="status-item">
                <div class="status-item-head">
                    <span class="status-item-lbl" style="color:var(<?= $cfg[1] ?>)"><?= $cfg[0] ?></span>
                    <span class="status-item-qtd"><?= $p ?>%</span>
                </div>
                <div class="status-item-val"><?= (int)$r['qtd'] ?> OS</div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:<?= $p ?>%;background:var(<?= $cfg[1] ?>)"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── 4. Top clientes ────────────────────────────────────────────────────────── -->
<div class="rel-section" id="sec-clientes">
    <div class="rel-section-head">
        <i class="fas fa-users"></i>
        Top clientes — <?= htmlspecialchars($labelPeriodo) ?>
    </div>
    <?php if (empty($topClientes)): ?>
        <div class="rel-empty"><i class="fas fa-users"></i>Sem dados no período.</div>
    <?php else: ?>
    <table class="rel-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th class="right">OS</th>
                <th>Faturado</th>
                <th>Recebido</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topClientes as $i => $c): ?>
            <tr>
                <td class="muted"><?= $i + 1 ?></td>
                <td>
                    <span style="font-weight:600"><?= htmlspecialchars($c['nome']) ?></span>
                    <?php if ($c['nome_fantasia']): ?>
                        <span class="muted"> — <?= htmlspecialchars($c['nome_fantasia']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="right muted"><?= (int)$c['qtd_os'] ?></td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= pct((float)$c['total'], $maxClientes) ?>%"></div>
                        </div>
                        <span class="bar-val"><?= R((float)$c['total']) ?></span>
                    </div>
                </td>
                <td style="color:var(--success);font-weight:600;font-size:.82rem"><?= R((float)$c['recebido']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── 5. Top serviços ────────────────────────────────────────────────────────── -->
<div class="rel-section" id="sec-servicos">
    <div class="rel-section-head">
        <i class="fas fa-wrench"></i>
        Top serviços — <?= htmlspecialchars($labelPeriodo) ?>
    </div>
    <?php if (empty($topServicos)): ?>
        <div class="rel-empty"><i class="fas fa-wrench"></i>Sem dados no período.</div>
    <?php else: ?>
    <table class="rel-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Serviço</th>
                <th class="right">Usos</th>
                <th class="right">Peças</th>
                <th>Receita</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topServicos as $i => $s): ?>
            <tr>
                <td class="muted"><?= $i + 1 ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($s['nome']) ?></td>
                <td class="right muted"><?= (int)$s['qtd_uso'] ?></td>
                <td class="right muted"><?= (int)$s['total_pcs'] ?></td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-track">
                            <div class="bar-fill rose" style="width:<?= pct((float)$s['receita'], $maxServicos) ?>%"></div>
                        </div>
                        <span class="bar-val" style="color:var(--rose)"><?= R((float)$s['receita']) ?></span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── 6. Pagamentos ──────────────────────────────────────────────────────────── -->
<div class="rel-section" id="sec-pagamentos">
    <div class="rel-section-head">
        <i class="fas fa-hand-holding-usd"></i>
        Pagamentos — <?= htmlspecialchars($labelPeriodo) ?>
    </div>
    <div class="rel-body">
        <div class="pag-grid">
            <?php
            $pagCfg = [
                'pago'    => ['Pago',     '--success', $pagBreakdown['pago']    ?? ['qtd'=>0,'total'=>0]],
                'parcial' => ['Parcial',  '--warning', $pagBreakdown['parcial'] ?? ['qtd'=>0,'total'=>0]],
                'pendente'=> ['Pendente', '--danger',  $pagBreakdown['pendente']?? ['qtd'=>0,'total'=>0]],
            ];
            foreach ($pagCfg as [$lbl, $cor, $dados]): ?>
            <div class="pag-card">
                <div class="pag-card-val" style="color:var(<?= $cor ?>)"><?= R((float)$dados['total']) ?></div>
                <div class="pag-card-lbl"><?= $lbl ?></div>
                <div class="pag-card-qtd"><?= (int)$dados['qtd'] ?> OS</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
(function() {
    var tabs     = document.querySelectorAll('.sec-tab');
    var sections = document.querySelectorAll('.rel-section');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = this.dataset.target;

            // Ativa a aba clicada
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');

            // Mostra só a seção correspondente
            sections.forEach(function(sec) {
                sec.classList.toggle('visible', sec.id === target);
            });

            // Persiste a aba ativa na URL sem recarregar
            var url = new URL(window.location);
            url.searchParams.set('aba', target);
            history.replaceState(null, '', url);
        });
    });

    // Restaura aba ativa ao recarregar (após mudar período)
    var abaAtiva = new URLSearchParams(window.location.search).get('aba');
    if (abaAtiva) {
        var tabAlvo = document.querySelector('[data-target="' + abaAtiva + '"]');
        if (tabAlvo) tabAlvo.click();
    }
})();
</script>
JS;

require_once 'includes/footer.php';
