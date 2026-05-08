<?php
require_once '../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: os.php'); exit; }

// ── Busca dados ───────────────────────────────────────────────────────────────
$os         = null;
$itens      = [];
$pagamentos = [];
$dbError    = '';

try {
    $stmt = $pdo->prepare("
        SELECT o.*,
               c.nome          AS cliente_nome,
               c.nome_fantasia,
               c.telefone,
               c.telefone2,
               c.rua,
               c.numero        AS end_numero,
               c.complemento,
               c.bairro,
               c.cidade,
               c.uf,
               c.cep
        FROM ordens_servico o
        LEFT JOIN afiacao_clientes c ON c.id = o.cliente_id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $os = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = 'OS: ' . $e->getMessage();
}

if (!$os && !$dbError) { header('Location: os.php'); exit; }

if ($os) {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, s.nome AS servico_nome
            FROM itens_os i
            LEFT JOIN servicos s ON s.id = i.servico_id
            WHERE i.os_id = ?
            ORDER BY i.id
        ");
        $stmt->execute([$id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $itens = [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM pagamentos WHERE os_id = ? ORDER BY criado_em");
        $stmt->execute([$id]);
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pagamentos = [];
    }
}

$totalPago = array_sum(array_column($pagamentos, 'valor'));
$saldo     = $os ? (float)$os['valor_total'] - $totalPago : 0;
$saldoCor  = $saldo > 0.005 ? '#DC2626' : '#059669';

$formaLabel = [
    'dinheiro'      => 'Dinheiro',
    'pix'           => 'PIX',
    'cartao_debito' => 'Débito',
    'cartao_credito'=> 'Crédito',
    'transferencia' => 'Transferência',
    'cheque'        => 'Cheque',
];
$statusLabel = [
    'aguardando'   => 'Aguardando',
    'em_andamento' => 'Em andamento',
    'pronto'       => 'Pronto',
    'entregue'     => 'Entregue',
    'cancelado'    => 'Cancelado',
];
$pagLabel = ['pendente' => 'Pendente', 'parcial' => 'Parcial', 'pago' => 'Pago'];

$endCliente = '';
if ($os && !empty($os['rua'])) {
    $endCliente = trim(
        $os['rua']
        . (!empty($os['end_numero']) ? ', ' . $os['end_numero'] : '')
        . (!empty($os['complemento']) ? ' — ' . $os['complemento'] : '')
        . (!empty($os['bairro'])      ? ' — ' . $os['bairro']     : '')
        . (!empty($os['cidade'])      ? ', '  . $os['cidade']     : '')
        . (!empty($os['uf'])          ? '/'   . $os['uf']         : '')
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OS <?= $os ? '#' . htmlspecialchars($os['numero']) : '' ?> — <?= COMPANY_NAME ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 11pt;
    color: #1a1a1a;
    background: #f0f0f0;
    padding: 20px;
}
.page {
    background: #fff;
    max-width: 800px;
    margin: 0 auto;
    padding: 28px 32px;
    border-radius: 4px;
    box-shadow: 0 2px 12px rgba(0,0,0,.12);
}
.print-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #C9A84C;
    padding-bottom: 14px;
    margin-bottom: 18px;
}
.company-block h1 { font-size: 17pt; font-weight: 800; color: #1a1a1a; }
.company-block h1 span { color: #C9A84C; }
.company-block p { font-size: 8.5pt; color: #555; margin-top: 2px; line-height: 1.5; }
.os-block { text-align: right; }
.os-num { font-size: 18pt; font-weight: 800; color: #C9A84C; font-family: 'Courier New', monospace; }
.os-date { font-size: 8.5pt; color: #777; margin-top: 3px; }
.os-badges { margin-top: 5px; display: flex; gap: 5px; justify-content: flex-end; }
.badge { display: inline-block; font-size: 7.5pt; font-weight: 700; padding: 2px 7px; border-radius: 20px; text-transform: uppercase; letter-spacing: .03em; }
.badge-warning  { background:#FEF3C7; color:#92400E; }
.badge-info     { background:#DBEAFE; color:#1E40AF; }
.badge-success  { background:#D1FAE5; color:#065F46; }
.badge-neutral  { background:#F3F4F6; color:#374151; }
.badge-danger   { background:#FEE2E2; color:#991B1B; }
.section { margin-bottom: 16px; }
.section-title { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #888; border-bottom: 1px solid #e5e5e5; padding-bottom: 4px; margin-bottom: 9px; }
.info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px 14px; }
.info-item label { display: block; font-size: 7.5pt; color: #888; margin-bottom: 1px; }
.info-item span { font-size: 9.5pt; color: #1a1a1a; font-weight: 500; }
.info-item.full { grid-column: 1 / -1; }
.items-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
.items-table th { background:#f7f7f7; padding:6px 8px; text-align:left; font-size:7.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#666; border:1px solid #e0e0e0; }
.items-table td { padding:6px 8px; border:1px solid #e0e0e0; vertical-align:top; }
.items-table tr:nth-child(even) td { background:#fafafa; }
.num { text-align: right; }
.totals-block { display: flex; justify-content: flex-end; margin-top: 6px; }
.totals-table { width: 240px; font-size: 9.5pt; }
.totals-table td { padding: 3px 6px; }
.totals-table td:last-child { text-align: right; font-weight: 600; }
.totals-table tr.total-row td { border-top: 2px solid #1a1a1a; font-size: 11pt; font-weight: 800; padding-top: 5px; }
.pag-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
.pag-table th { background:#f7f7f7; padding:5px 8px; text-align:left; font-size:7.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#666; border:1px solid #e0e0e0; }
.pag-table td { padding:5px 8px; border:1px solid #e0e0e0; }
.obs-box { background:#fafafa; border:1px solid #e5e5e5; border-radius:4px; padding:8px 10px; font-size:9.5pt; color:#333; min-height:36px; white-space:pre-wrap; }
.sign-row { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-top: 28px; }
.sign-line { border-top: 1px solid #1a1a1a; padding-top: 5px; font-size: 8pt; color: #555; text-align: center; }
.print-footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e5e5e5; font-size: 7.5pt; color: #aaa; text-align: center; }
.toolbar { max-width: 800px; margin: 0 auto 14px; display: flex; gap: 8px; }
.btn-print { background:#C9A84C; color:#1a1a1a; border:none; padding:8px 18px; border-radius:6px; font-weight:700; font-size:10pt; cursor:pointer; }
.btn-close { background:#e5e5e5; color:#333; border:none; padding:8px 18px; border-radius:6px; font-weight:600; font-size:10pt; cursor:pointer; text-decoration:none; display:inline-block; }
.error-box { background:#FEE2E2; border:1px solid #DC2626; border-radius:6px; padding:14px 18px; color:#991B1B; font-size:9.5pt; }
@media print {
    body { background:#fff; padding:0; }
    .page { box-shadow:none; padding:0; }
    .toolbar { display:none; }
    @page { margin:1.5cm; }
}
</style>
</head>
<body>

<div class="toolbar">
    <button class="btn-print" onclick="window.print()">&#128438; Imprimir</button>
    <a class="btn-close" href="os.php">&#8592; Voltar</a>
</div>

<div class="page">

<?php if ($dbError): ?>
    <div class="error-box">
        <strong>Erro ao carregar OS:</strong><br>
        <?= htmlspecialchars($dbError) ?>
        <br><br>
        <a href="../database/criar_tabelas.php">Verificar/criar tabelas</a>
    </div>
<?php elseif (!$os): ?>
    <div class="error-box">OS não encontrada.</div>
<?php else: ?>

    <!-- Cabeçalho -->
    <div class="print-header">
        <div class="company-block">
            <h1>Afiação <span>Almeida</span></h1>
            <p>
                <?= htmlspecialchars(COMPANY_PHONE) ?><br>
                <?= htmlspecialchars(COMPANY_ADDRESS) ?><br>
                <?= htmlspecialchars(COMPANY_CITY) ?><br>
                <?= htmlspecialchars(COMPANY_EMAIL) ?>
            </p>
        </div>
        <div class="os-block">
            <div class="os-num">OS #<?= htmlspecialchars($os['numero']) ?></div>
            <div class="os-date">
                Entrada: <?= date('d/m/Y H:i', strtotime($os['data_entrada'])) ?><br>
                <?php if ($os['prazo']): ?>
                    Prazo: <?= date('d/m/Y', strtotime($os['prazo'])) ?>
                <?php endif; ?>
            </div>
            <div class="os-badges">
                <?php
                if ($os['status'] === 'aguardando')        $sb = 'badge-warning';
                elseif ($os['status'] === 'em_andamento')  $sb = 'badge-info';
                elseif ($os['status'] === 'pronto')        $sb = 'badge-success';
                elseif ($os['status'] === 'cancelado')     $sb = 'badge-danger';
                else                                        $sb = 'badge-neutral';

                if ($os['status_pagamento'] === 'pago')    $pb = 'badge-success';
                elseif ($os['status_pagamento'] === 'parcial') $pb = 'badge-warning';
                else                                        $pb = 'badge-neutral';
                ?>
                <span class="badge <?= $sb ?>"><?= $statusLabel[$os['status']] ?? $os['status'] ?></span>
                <span class="badge <?= $pb ?>"><?= $pagLabel[$os['status_pagamento']] ?? 'Pendente' ?></span>
            </div>
        </div>
    </div>

    <!-- Cliente -->
    <div class="section">
        <div class="section-title">Dados do cliente</div>
        <div class="info-grid">
            <div class="info-item" style="grid-column:1/3">
                <label>Nome</label>
                <span><?= htmlspecialchars($os['cliente_nome'] ?? '—') ?></span>
                <?php if (!empty($os['nome_fantasia'])): ?>
                    <span style="color:#888;font-size:8.5pt"> (<?= htmlspecialchars($os['nome_fantasia']) ?>)</span>
                <?php endif; ?>
            </div>
            <div class="info-item">
                <label>Telefone</label>
                <span><?= htmlspecialchars($os['telefone'] ?? '—') ?></span>
            </div>
            <?php if (!empty($os['telefone2'])): ?>
            <div class="info-item">
                <label>Telefone 2</label>
                <span><?= htmlspecialchars($os['telefone2']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($endCliente): ?>
            <div class="info-item full">
                <label>Endereço</label>
                <span><?= htmlspecialchars($endCliente) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <label>Finalização</label>
                <span><?= $os['tipo_finalizacao'] === 'entrega' ? 'Entrega' : 'Retirada' ?></span>
            </div>
        </div>
    </div>

    <!-- Itens -->
    <div class="section">
        <div class="section-title">Itens / Equipamentos</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Equipamento</th>
                    <th>Serviço</th>
                    <th class="num">Qtd</th>
                    <th class="num">Valor unit.</th>
                    <th class="num">Subtotal</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itens)): ?>
                <tr><td colspan="7" style="text-align:center;color:#aaa;font-style:italic">Nenhum item registrado</td></tr>
                <?php else: ?>
                <?php foreach ($itens as $i => $item):
                    $sub = (int)$item['quantidade'] * (float)$item['valor_unitario']; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['equipamento']) ?></td>
                    <td><?= htmlspecialchars($item['servico_nome'] ?? '—') ?></td>
                    <td class="num"><?= (int)$item['quantidade'] ?></td>
                    <td class="num">R$ <?= number_format((float)$item['valor_unitario'], 2, ',', '.') ?></td>
                    <td class="num">R$ <?= number_format($sub, 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($item['observacao'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-block">
            <table class="totals-table">
                <?php if ((float)$os['taxa_entrega'] > 0): ?>
                <tr>
                    <td>Subtotal itens</td>
                    <td>R$ <?= number_format((float)$os['valor_total'] - (float)$os['taxa_entrega'], 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Taxa de entrega</td>
                    <td>R$ <?= number_format((float)$os['taxa_entrega'], 2, ',', '.') ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>Total</td>
                    <td>R$ <?= number_format((float)$os['valor_total'], 2, ',', '.') ?></td>
                </tr>
                <?php if (!empty($pagamentos)): ?>
                <tr>
                    <td style="padding-top:4px">Pago</td>
                    <td>R$ <?= number_format($totalPago, 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td style="color:<?= $saldoCor ?>;font-weight:700"><?= $saldo > 0.005 ? 'Saldo devedor' : 'Troco/crédito' ?></td>
                    <td style="color:<?= $saldoCor ?>;font-weight:700">R$ <?= number_format(abs($saldo), 2, ',', '.') ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Pagamentos -->
    <?php if (!empty($pagamentos)): ?>
    <div class="section">
        <div class="section-title">Histórico de pagamentos</div>
        <table class="pag-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Forma</th>
                    <th style="text-align:right">Valor</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagamentos as $pag): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($pag['criado_em'])) ?></td>
                    <td><?= htmlspecialchars($formaLabel[$pag['forma']] ?? ucfirst($pag['forma'])) ?></td>
                    <td style="text-align:right">R$ <?= number_format((float)$pag['valor'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($pag['observacao'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Observações -->
    <?php if (!empty($os['observacoes'])): ?>
    <div class="section">
        <div class="section-title">Observações</div>
        <div class="obs-box"><?= htmlspecialchars($os['observacoes']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Assinatura -->
    <div class="sign-row">
        <div class="sign-line">Assinatura do cliente / Responsável</div>
        <div class="sign-line">Assinatura da empresa / Técnico</div>
    </div>

    <!-- Rodapé -->
    <div class="print-footer">
        Documento gerado em <?= date('d/m/Y \à\s H:i') ?> &mdash;
        <?= COMPANY_NAME ?> &mdash; <?= COMPANY_PHONE ?>
    </div>

<?php endif; ?>
</div>

</body>
</html>
