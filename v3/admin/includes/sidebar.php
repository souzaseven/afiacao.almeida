<?php
$admin    = $_SESSION['admin_nome'] ?? 'Admin';
$words    = explode(' ', trim($admin));
$initials = strtoupper(($words[0][0] ?? 'A') . ($words[1][0] ?? ''));

// Badge: OS com prazo vencido
$_badge_venc = 0;
try {
    $_badge_venc = (int) $pdo->query(
        "SELECT COUNT(*) FROM ordens_servico WHERE prazo < CURDATE() AND status NOT IN ('entregue','cancelado')"
    )->fetchColumn();
} catch (PDOException $e) {}

// Badge: progresso primeiros passos (mostra enquanto incompleto)
$_badge_setup = '';
try {
    $srv = (int)$pdo->query("SELECT COUNT(*) FROM servicos WHERE ativo=1")->fetchColumn();
    $cli = (int)$pdo->query("SELECT COUNT(*) FROM afiacao_clientes")->fetchColumn();
    $os  = (int)$pdo->query("SELECT COUNT(*) FROM ordens_servico")->fetchColumn();
    $_done_setup  = ($srv > 0 ? 1 : 0) + ($cli > 0 ? 1 : 0) + ($os > 0 ? 1 : 0);
    if ($_done_setup < 3) $_badge_setup = $_done_setup . '/3';
} catch (PDOException $e) {}

$nav = [
    'PAINEL' => [
        ['href' => 'index.php',           'icon' => 'fas fa-gauge',          'label' => 'Dashboard',         'page' => 'index'],
    ],
    'GESTÃO' => [
        ['href' => 'os.php',              'icon' => 'fas fa-file-invoice',   'label' => 'Ordens de Serviço', 'page' => 'os'],
        ['href' => 'clientes.php',        'icon' => 'fas fa-users',          'label' => 'Clientes',          'page' => 'clientes'],
        ['href' => 'producao.php',        'icon' => 'fas fa-scissors',       'label' => 'Produção',          'page' => 'producao',        'badge' => $_badge_venc],
        ['href' => 'pagamentos.php',      'icon' => 'fas fa-credit-card',    'label' => 'Pagamentos',        'page' => 'pagamentos'],
    ],
    'SISTEMA' => [
        ['href' => 'servicos.php',        'icon' => 'fas fa-wrench',         'label' => 'Serviços',          'page' => 'servicos'],
        ['href' => 'relatorios.php',      'icon' => 'fas fa-chart-bar',      'label' => 'Relatórios',        'page' => 'relatorios'],
        ['href' => 'primeiros_passos.php','icon' => 'fas fa-rocket',         'label' => 'Primeiros Passos',  'page' => 'primeiros_passos', 'badge_gold' => $_badge_setup],
    ],
];
?>
<aside class="sidebar" id="sidebar">

    <div class="sidebar-logo">
        <div class="logo-icon">A</div>
        <span>Almeida</span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($nav as $section => $items): ?>
            <div class="nav-section-label"><?= $section ?></div>
            <?php foreach ($items as $item): ?>
                <a href="<?= $item['href'] ?>"
                   class="nav-item<?= ($currentPage ?? '') === $item['page'] ? ' active' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i>
                    <?= $item['label'] ?>
                    <?php if (!empty($item['badge'])): ?>
                        <span style="margin-left:auto;background:#F87171;color:#fff;font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:100px;line-height:1.4;flex-shrink:0">
                            <?= (int)$item['badge'] ?>
                        </span>
                    <?php elseif (!empty($item['badge_gold'])): ?>
                        <span style="margin-left:auto;background:rgba(201,168,76,.2);color:#C9A84C;font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:100px;line-height:1.4;flex-shrink:0">
                            <?= htmlspecialchars($item['badge_gold']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($admin) ?></span>
                <span class="user-role">Administrador</span>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>

</aside>
