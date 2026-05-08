<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle    = 'Dashboard';
$currentPage  = 'index';
$breadcrumb   = 'Visão geral — ' . date('d/m/Y H:i');
$topbarActions = '<a href="os.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nova OS</a>';

// ── KPIs ─────────────────────────────────────────────────────────────────────
try {
    $clientes     = (int) $pdo->query("SELECT COUNT(*) FROM afiacao_clientes WHERE status = 'ativo'")->fetchColumn();
    $total_os     = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico")->fetchColumn();
    $os_andamento = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status IN ('aguardando','em_andamento')")->fetchColumn();
    $os_prontas   = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'pronto'")->fetchColumn();
    $os_atrasadas = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE prazo < CURDATE() AND status NOT IN ('entregue','cancelado')")->fetchColumn();
    $os_hoje      = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE DATE(data_entrada) = CURDATE()")->fetchColumn();
    $fat_dia      = (float) $pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM ordens_servico WHERE status IN ('pronto','entregue') AND DATE(data_entrada) = CURDATE()")->fetchColumn();
    $fat_mes      = (float) $pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM ordens_servico WHERE status IN ('pronto','entregue') AND DATE_FORMAT(data_entrada,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn();
    $pag_pendente = (int) $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status_pagamento IN ('pendente','parcial')")->fetchColumn();
    $a_receber    = (float) $pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM ordens_servico WHERE status_pagamento IN ('pendente','parcial')")->fetchColumn();
    // Comparativo mês anterior
    $fat_mes_ant  = (float) $pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM ordens_servico WHERE status IN ('pronto','entregue') AND DATE_FORMAT(data_entrada,'%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m')")->fetchColumn();
    $os_mes_ant   = (int)   $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE DATE_FORMAT(data_entrada,'%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m')")->fetchColumn();
} catch (PDOException $e) {
    $clientes = $total_os = $os_andamento = $os_prontas = $os_atrasadas = $os_hoje = 0;
    $fat_dia = $fat_mes = $pag_pendente = $a_receber = $fat_mes_ant = $os_mes_ant = 0;
}
function tendencia(float $atual, float $anterior): string {
    if ($anterior <= 0) return '';
    $pct = round(($atual - $anterior) / $anterior * 100);
    if ($pct === 0) return '<span style="color:var(--text-muted);font-size:.7rem">= mês anterior</span>';
    $up  = $pct > 0;
    $cor = $up ? 'var(--success)' : 'var(--danger)';
    return '<span style="color:'.$cor.';font-size:.7rem;font-weight:600">'.($up?'↑':'↓').' '.abs($pct).'% vs mês ant.</span>';
}

// ── Dados para gráficos ───────────────────────────────────────────────────────
$chartMes = [];
try {
    $rows = $pdo->query("
        SELECT DATE_FORMAT(data_entrada,'%b/%y') AS label,
               DATE_FORMAT(data_entrada,'%Y-%m') AS mes_key,
               COUNT(*) AS qtd,
               COALESCE(SUM(CASE WHEN status NOT IN ('cancelado') THEN valor_total ELSE 0 END),0) AS receita
        FROM ordens_servico
        WHERE data_entrada >= DATE_SUB(NOW(), INTERVAL 5 MONTH)
        GROUP BY mes_key
        ORDER BY mes_key ASC
    ")->fetchAll();
    foreach ($rows as $r) {
        $chartMes[] = ['label' => $r->label, 'qtd' => (int)$r->qtd, 'receita' => (float)$r->receita];
    }
} catch (PDOException $e) {}

$chartSrv = [];
try {
    $rows = $pdo->query("
        SELECT s.nome, COUNT(i.id) AS qtd
        FROM itens_os i
        JOIN servicos s ON s.id = i.servico_id
        GROUP BY s.id ORDER BY qtd DESC LIMIT 7
    ")->fetchAll();
    foreach ($rows as $r) {
        $chartSrv[] = ['nome' => $r->nome, 'qtd' => (int)$r->qtd];
    }
} catch (PDOException $e) {}

// ── Últimas OS ────────────────────────────────────────────────────────────────
$ultimas_os = [];
try {
    $stmt = $pdo->query("
        SELECT os.id, os.numero, c.nome AS cliente,
               os.status, os.status_pagamento,
               os.data_entrada, os.valor_total
        FROM ordens_servico os
        JOIN afiacao_clientes c ON c.id = os.cliente_id
        ORDER BY os.data_entrada DESC
        LIMIT 8
    ");
    $ultimas_os = $stmt->fetchAll();
} catch (PDOException $e) {}

// ── Top 5 clientes ────────────────────────────────────────────────────────────
$top_clientes = [];
try {
    $stmt = $pdo->query("
        SELECT c.nome, c.tipo, COUNT(os.id) AS total_os,
               COALESCE(SUM(os.valor_total), 0) AS total_valor
        FROM afiacao_clientes c
        LEFT JOIN ordens_servico os ON os.cliente_id = c.id
        GROUP BY c.id
        ORDER BY total_valor DESC
        LIMIT 5
    ");
    $top_clientes = $stmt->fetchAll();
} catch (PDOException $e) {}

// ── Entregas do dia ───────────────────────────────────────────────────────────
$entregas_hoje = [];
try {
    $stmt = $pdo->query("
        SELECT e.tipo, e.horario, e.status,
               c.nome AS cliente, os.numero
        FROM entregas e
        JOIN ordens_servico os ON os.id = e.os_id
        JOIN afiacao_clientes c ON c.id  = e.cliente_id
        WHERE DATE(e.data_entrega) = CURDATE()
        ORDER BY e.horario ASC
        LIMIT 6
    ");
    $entregas_hoje = $stmt->fetchAll();
} catch (PDOException $e) {}

// ── Helpers de badge ──────────────────────────────────────────────────────────
function badgeOS(string $s): string {
    return match ($s) {
        'aguardando'              => '<span class="badge badge-warning">Aguardando</span>',
        'em_afiacao','em_producao'=> '<span class="badge badge-info">Em produção</span>',
        'pronto'                  => '<span class="badge badge-success">Pronto</span>',
        'atrasado'                => '<span class="badge badge-danger">Atrasado</span>',
        'entregue'                => '<span class="badge badge-neutral">Entregue</span>',
        default                   => '<span class="badge badge-neutral">'.htmlspecialchars($s).'</span>',
    };
}
function badgePag(string $s): string {
    return match ($s) {
        'pago'    => '<span class="badge badge-success">Pago</span>',
        'parcial' => '<span class="badge badge-warning">Parcial</span>',
        'pendente'=> '<span class="badge badge-danger">Pendente</span>',
        default   => '<span class="badge badge-neutral">'.htmlspecialchars($s).'</span>',
    };
}

include 'includes/header.php';
?>

<?php if ($os_atrasadas > 0): ?>
<div class="alert alert-danger mb-md">
    <i class="fas fa-exclamation-triangle"></i>
    Há <strong><?= $os_atrasadas ?> ordem(ns) atrasada(s)</strong>. Verifique e entre em contato com os clientes.
    <a href="os.php?filtro=atrasado" style="margin-left:auto;color:var(--danger);font-weight:600;font-size:.8rem">
        Ver agora →
    </a>
</div>
<?php endif; ?>

<!-- ── KPIs ──────────────────────────────────────────────────────────────── -->
<div class="kpi-grid mb-xl">

    <div class="kpi-card">
        <div class="kpi-icon"><i class="fas fa-file-alt"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">OS hoje</span>
            <span class="kpi-value"><?= $os_hoje ?></span>
            <span class="kpi-sub">Total geral: <?= $total_os ?></span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon green"><i class="fas fa-coins"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">Receita do mês</span>
            <span class="kpi-value" style="font-size:1.25rem">R$&nbsp;<?= number_format($fat_mes, 2, ',', '.') ?></span>
            <span class="kpi-sub"><?= tendencia($fat_mes, $fat_mes_ant) ?: 'Hoje: R$ '.number_format($fat_dia,2,',','.') ?></span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon blue"><i class="fas fa-users"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">Clientes ativos</span>
            <span class="kpi-value"><?= $clientes ?></span>
            <span class="kpi-sub">Cadastrados no sistema</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">Em andamento</span>
            <span class="kpi-value"><?= $os_andamento ?></span>
            <span class="kpi-sub">A receber: R$ <?= number_format($a_receber, 2, ',', '.') ?></span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">OS atrasadas</span>
            <span class="kpi-value" <?= $os_atrasadas > 0 ? 'style="color:var(--danger)"' : '' ?>><?= $os_atrasadas ?></span>
            <span class="kpi-sub">Requer atenção</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">OS prontas</span>
            <span class="kpi-value"><?= $os_prontas ?></span>
            <span class="kpi-sub">Aguardando retirada</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon rose"><i class="fas fa-credit-card"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">Pag. pendentes</span>
            <span class="kpi-value"><?= $pag_pendente ?></span>
            <span class="kpi-sub">Parcial ou em aberto</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon blue"><i class="fas fa-calendar-check"></i></div>
        <div class="kpi-info">
            <span class="kpi-label">OS recebidas hoje</span>
            <span class="kpi-value"><?= $os_hoje ?></span>
            <span class="kpi-sub">Novas OS do dia</span>
        </div>
    </div>

</div><!-- /.kpi-grid -->

<!-- ── Ações rápidas ──────────────────────────────────────────────────────── -->
<div class="flex-center gap-sm mb-xl">
    <a href="os.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nova OS</a>
    <a href="os.php" class="btn btn-ghost btn-sm"><i class="fas fa-search"></i> Buscar OS</a>
    <a href="pagamentos.php" class="btn btn-ghost btn-sm"><i class="fas fa-money-bill-wave"></i> Registrar pagamento</a>
</div>

<!-- ── Gráficos ───────────────────────────────────────────────────────────── -->
<div class="charts-grid mb-md">

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-chart-bar"></i> Receita e OS por mês</span>
        </div>
        <div class="card-body">
            <div class="chart-wrapper"><canvas id="chartMes"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-chart-pie"></i> Serviços por tipo</span>
        </div>
        <div class="card-body">
            <div class="chart-wrapper"><canvas id="chartServicos"></canvas></div>
        </div>
    </div>

</div><!-- /.charts-grid -->

<!-- ── Top clientes + Entregas ───────────────────────────────────────────── -->
<div class="dashboard-grid-2 mb-md">

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-trophy"></i> Top 5 clientes</span>
            <a href="clientes.php" class="card-link">Ver todos →</a>
        </div>
        <div class="ranking-list">
            <?php if (empty($top_clientes)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Nenhum cliente encontrado.</p>
                </div>
            <?php else: ?>
                <?php foreach ($top_clientes as $i => $c): ?>
                    <div class="ranking-item">
                        <span class="rank-pos"><?= $i + 1 ?></span>
                        <div class="rank-info">
                            <div class="rank-name"><?= htmlspecialchars($c->nome) ?></div>
                            <div class="rank-meta"><?= (int) $c->total_os ?> OS · <?= htmlspecialchars($c->tipo ?? 'PF') ?></div>
                        </div>
                        <span class="rank-value">R$ <?= number_format((float)$c->total_valor, 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-bicycle"></i> Entregas de hoje</span>
            <a href="os.php" class="card-link">Ver agenda →</a>
        </div>
        <div class="delivery-list">
            <?php if (empty($entregas_hoje)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Nenhuma entrega agendada para hoje.</p>
                </div>
            <?php else: ?>
                <?php foreach ($entregas_hoje as $e): ?>
                    <div class="delivery-item">
                        <div class="delivery-icon <?= $e->tipo === 'retirada' ? 'pickup' : 'delivery' ?>">
                            <i class="fas fa-<?= $e->tipo === 'retirada' ? 'arrow-up' : 'arrow-down' ?>"></i>
                        </div>
                        <div class="delivery-info">
                            <div class="delivery-name"><?= htmlspecialchars($e->cliente) ?></div>
                            <div class="delivery-meta">
                                <i class="fas fa-clock"></i>
                                <?= htmlspecialchars($e->horario ?? '') ?>
                                — <?= $e->tipo === 'retirada' ? 'Retirada' : 'Entrega' ?>
                                · OS <?= htmlspecialchars($e->numero) ?>
                            </div>
                        </div>
                        <?php
                        $badgeE = match ($e->status ?? '') {
                            'agendado'  => '<span class="badge badge-warning">Agendado</span>',
                            'em_rota'   => '<span class="badge badge-info">Em rota</span>',
                            'concluido' => '<span class="badge badge-success">Concluído</span>',
                            default     => '<span class="badge badge-neutral">'.htmlspecialchars($e->status ?? '').'</span>',
                        };
                        echo $badgeE;
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.dashboard-grid-2 -->

<!-- ── OS Recentes ────────────────────────────────────────────────────────── -->
<div class="card mb-md">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-file-invoice"></i> Ordens de serviço recentes</span>
        <a href="os.php" class="card-link">Ver todas →</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>OS</th>
                    <th>Cliente</th>
                    <th>Entrada</th>
                    <th>Valor</th>
                    <th>Pagamento</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimas_os)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            Nenhuma ordem encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ultimas_os as $os): ?>
                        <tr>
                            <td>
                                <span class="text-gold font-bold text-sm"><?= htmlspecialchars($os->numero) ?></span>
                            </td>
                            <td style="color:var(--text-primary);font-weight:500">
                                <?= htmlspecialchars($os->cliente) ?>
                            </td>
                            <td class="text-muted text-sm">
                                <?= date('d/m/Y H:i', strtotime($os->data_entrada)) ?>
                            </td>
                            <td style="font-weight:600;color:var(--success)">
                                R$&nbsp;<?= number_format((float)$os->valor_total, 2, ',', '.') ?>
                            </td>
                            <td><?= badgePag($os->status_pagamento ?? 'pendente') ?></td>
                            <td><?= badgeOS($os->status) ?></td>
                            <td>
                                <div class="flex-center gap-sm">
                                    <a href="os.php?id=<?= (int)$os->id ?>" class="btn-action" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="os.php?edit=<?= (int)$os->id ?>" class="btn-action" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=OS+<?= urlencode($os->numero) ?>"
                                       class="btn-action wa" target="_blank" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div><!-- /.card -->

<?php
$chartJson = json_encode(['mes' => $chartMes, 'srv' => $chartSrv], JSON_UNESCAPED_UNICODE);
$extraScripts = '<script>var CHART_DATA=' . $chartJson . ';</script>';
$extraScripts .= <<<'JS'
<script>
(function () {
    var gold    = '#C9A84C';
    var blue    = '#60A5FA';
    var grid    = 'rgba(255,255,255,0.05)';
    var tick    = '#666';
    var fontFam = 'Poppins, sans-serif';
    var baseScale = {
        ticks: { color: tick, font: { size: 11, family: fontFam } },
        grid:  { color: grid }
    };
    var mes = CHART_DATA.mes || [];
    var srv = CHART_DATA.srv || [];

    // Gráfico de barras + linha — dados reais dos últimos 6 meses
    var ctxMes = document.getElementById('chartMes');
    if (ctxMes) {
        new Chart(ctxMes, {
            type: 'bar',
            data: {
                labels: mes.map(function(m){ return m.label; }),
                datasets: [
                    {
                        label: 'Qtd. OS',
                        data: mes.map(function(m){ return m.qtd; }),
                        backgroundColor: 'rgba(201,168,76,.65)',
                        borderColor: gold, borderWidth: 1,
                        borderRadius: 5, order: 2
                    },
                    {
                        label: 'Receita (R$)',
                        data: mes.map(function(m){ return m.receita; }),
                        type: 'line',
                        borderColor: blue,
                        backgroundColor: 'rgba(96,165,250,.08)',
                        tension: 0.4, fill: true,
                        pointBackgroundColor: blue, pointRadius: 4,
                        order: 1, yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: tick, font: { size: 11, family: fontFam }, boxWidth: 10, padding: 16 } } },
                scales: {
                    x: baseScale,
                    y: Object.assign({}, baseScale),
                    y1: {
                        type: 'linear', position: 'right',
                        ticks: { color: blue, font: { size: 11 },
                            callback: function(v){ return 'R$' + v.toLocaleString('pt-BR'); } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Gráfico donut — serviços mais usados (dados reais)
    var ctxServ = document.getElementById('chartServicos');
    if (ctxServ) {
        var cores = ['#C9A84C','#60A5FA','#34D399','#FBBF24','#B76E79','#A78BFA','#FB923C'];
        var labels = srv.length ? srv.map(function(s){ return s.nome; }) : ['Sem dados'];
        var data   = srv.length ? srv.map(function(s){ return s.qtd;  }) : [1];
        new Chart(ctxServ, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: cores, borderWidth: 0, hoverOffset: 6 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom',
                        labels: { color: tick, font: { size: 11, family: fontFam }, padding: 14, boxWidth: 10 } }
                }
            }
        });
    }
})();
</script>
JS;

include 'includes/footer.php';
?>
