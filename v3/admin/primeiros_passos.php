<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$pageTitle   = 'Primeiros Passos';
$currentPage = 'primeiros_passos';
$breadcrumb  = 'Guia de configuração inicial do sistema';

// ── Verificar estado real do banco ────────────────────────────────────────────
$ok_tabelas   = false;
$n_servicos   = 0;
$n_clientes   = 0;
$n_os         = 0;
$n_pagamentos = 0;

try {
    $pdo->query("SELECT 1 FROM ordens_servico LIMIT 1");
    $ok_tabelas = true;
} catch (PDOException $e) {}

if ($ok_tabelas) {
    try { $n_servicos   = (int)$pdo->query("SELECT COUNT(*) FROM servicos WHERE ativo=1")->fetchColumn(); } catch(PDOException $e){}
    try { $n_clientes   = (int)$pdo->query("SELECT COUNT(*) FROM afiacao_clientes")->fetchColumn(); } catch(PDOException $e){}
    try { $n_os         = (int)$pdo->query("SELECT COUNT(*) FROM ordens_servico")->fetchColumn(); } catch(PDOException $e){}
    try { $n_pagamentos = (int)$pdo->query("SELECT COUNT(*) FROM pagamentos")->fetchColumn(); } catch(PDOException $e){}
}

$steps = [
    [
        'id'      => 'tabelas',
        'num'     => 1,
        'icon'    => 'fas fa-database',
        'title'   => 'Banco de dados',
        'desc'    => 'Cria todas as tabelas necessárias no MySQL: clientes, ordens de serviço, itens, pagamentos e entregas. Deve ser executado uma vez antes de usar o sistema.',
        'done'    => $ok_tabelas,
        'done_txt'=> 'Tabelas encontradas no banco.',
        'todo_txt'=> 'As tabelas ainda não foram criadas. Execute o script de inicialização.',
        'action'  => ['url' => '../database/init_mysql.php', 'label' => 'Executar inicialização', 'icon' => 'fas fa-play', 'target' => '_blank'],
    ],
    [
        'id'      => 'servicos',
        'num'     => 2,
        'icon'    => 'fas fa-wrench',
        'title'   => 'Cadastrar serviços',
        'desc'    => 'Defina os tipos de serviço que sua empresa oferece: afiação de tesoura, afiação de navalha, manutenção, polimento etc. Eles aparecem na hora de criar uma OS.',
        'done'    => $n_servicos > 0,
        'done_txt'=> $n_servicos . ' serviço(s) ativo(s) cadastrado(s).',
        'todo_txt'=> 'Nenhum serviço cadastrado ainda. Adicione pelo menos um para poder criar OS.',
        'action'  => ['url' => 'servicos.php', 'label' => 'Ir para Serviços', 'icon' => 'fas fa-wrench'],
    ],
    [
        'id'      => 'clientes',
        'num'     => 3,
        'icon'    => 'fas fa-users',
        'title'   => 'Cadastrar clientes',
        'desc'    => 'Registre seus clientes com nome, telefone, endereço e preferências de entrega. Você também pode importar uma lista via CSV se já tiver os dados em planilha.',
        'done'    => $n_clientes > 0,
        'done_txt'=> $n_clientes . ' cliente(s) cadastrado(s).',
        'todo_txt'=> 'Nenhum cliente cadastrado. Comece adicionando seus principais clientes.',
        'action'  => ['url' => 'clientes.php', 'label' => 'Ir para Clientes', 'icon' => 'fas fa-users'],
        'extra'   => ['url' => 'clientes.php?exportar=0', 'label' => 'Importar via CSV', 'icon' => 'fas fa-upload'],
    ],
    [
        'id'      => 'os',
        'num'     => 4,
        'icon'    => 'fas fa-file-invoice',
        'title'   => 'Criar primeira OS',
        'desc'    => 'Crie sua primeira Ordem de Serviço: selecione o cliente, adicione os equipamentos/itens e defina o prazo. O sistema gera o número automaticamente.',
        'done'    => $n_os > 0,
        'done_txt'=> $n_os . ' ordem(ns) de serviço criada(s).',
        'todo_txt'=> 'Nenhuma OS criada ainda. Clique em "Nova OS" para começar.',
        'action'  => ['url' => 'os.php', 'label' => 'Ir para Ordens de Serviço', 'icon' => 'fas fa-file-invoice'],
    ],
    [
        'id'      => 'pagamento',
        'num'     => 5,
        'icon'    => 'fas fa-credit-card',
        'title'   => 'Registrar um pagamento',
        'desc'    => 'Ao concluir um serviço, registre o pagamento informando valor e forma (dinheiro, PIX, cartão). O sistema atualiza o status da OS automaticamente.',
        'done'    => $n_pagamentos > 0,
        'done_txt'=> $n_pagamentos . ' pagamento(s) registrado(s).',
        'todo_txt'=> 'Nenhum pagamento registrado ainda.',
        'action'  => ['url' => 'pagamentos.php', 'label' => 'Ir para Pagamentos', 'icon' => 'fas fa-credit-card'],
    ],
    [
        'id'      => 'impressao',
        'num'     => 6,
        'icon'    => 'fas fa-print',
        'title'   => 'Testar impressão de OS',
        'desc'    => 'Cada OS pode ser impressa como comprovante para o cliente, com dados da empresa, itens, total e linha de assinatura. Teste para garantir que está tudo certo.',
        'done'    => $n_os > 0,
        'done_txt'=> 'Impressão disponível — abra qualquer OS e clique no ícone de impressora.',
        'todo_txt'=> 'Crie uma OS primeiro para poder testar a impressão.',
        'action'  => ['url' => 'os.php', 'label' => 'Ver Ordens de Serviço', 'icon' => 'fas fa-external-link-alt'],
    ],
];

$totalDone  = count(array_filter($steps, fn($s) => $s['done']));
$totalSteps = count($steps);
$pct        = (int) round($totalDone / $totalSteps * 100);
$allDone    = $totalDone === $totalSteps;

include 'includes/header.php';
?>

<style>
/* ── Cabeçalho de progresso ──────────────────────────────── */
.setup-hero {
    background: var(--surface-1);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.setup-hero-icon {
    width: 54px; height: 54px;
    background: rgba(201,168,76,.12);
    border: 1px solid rgba(201,168,76,.25);
    border-radius: var(--radius-lg);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; color: var(--gold); flex-shrink: 0;
}
.setup-hero-text { flex: 1; min-width: 200px; }
.setup-hero-text h2 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: .2rem; }
.setup-hero-text p  { font-size: .85rem; color: var(--text-muted); }
.setup-progress-wrap { flex: 2; min-width: 200px; }
.progress-label {
    display: flex; justify-content: space-between;
    font-size: .78rem; color: var(--text-secondary); margin-bottom: .4rem;
}
.progress-label strong { color: var(--gold); }
.progress-bar-bg {
    background: var(--surface-2);
    border-radius: 99px;
    height: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.progress-bar-fill {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, var(--gold), #e8c86a);
    transition: width .5s ease;
}

/* ── Lista de passos ─────────────────────────────────────── */
.steps-list {
    display: flex;
    flex-direction: column;
    gap: .75rem;
}
.step-card {
    background: var(--surface-1);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.1rem 1.25rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    transition: border-color var(--transition), box-shadow var(--transition);
}
.step-card:hover { border-color: rgba(201,168,76,.3); }
.step-card.done  { border-left: 3px solid var(--success); }
.step-card.todo  { border-left: 3px solid var(--border); }

/* Indicador numérico */
.step-num {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem; font-weight: 800;
    flex-shrink: 0; margin-top: .1rem;
}
.step-card.done .step-num {
    background: rgba(52,211,153,.15);
    color: var(--success);
    border: 1px solid rgba(52,211,153,.3);
}
.step-card.todo .step-num {
    background: var(--surface-2);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

/* Ícone central */
.step-icon {
    width: 38px; height: 38px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0; margin-top: .1rem;
}
.step-card.done .step-icon { background: rgba(52,211,153,.1);  color: var(--success); }
.step-card.todo .step-icon { background: var(--surface-2); color: var(--gold); }

/* Conteúdo */
.step-body { flex: 1; min-width: 0; }
.step-title {
    font-size: .95rem; font-weight: 700;
    color: var(--text-primary); margin-bottom: .25rem;
    display: flex; align-items: center; gap: .5rem;
}
.step-title .step-badge {
    font-size: .68rem; font-weight: 700;
    padding: .15rem .5rem; border-radius: 99px;
    text-transform: uppercase; letter-spacing: .04em;
}
.done .step-badge { background: rgba(52,211,153,.15); color: var(--success); }
.todo .step-badge { background: rgba(255,255,255,.06); color: var(--text-muted); }
.step-desc    { font-size: .84rem; color: var(--text-secondary); line-height: 1.55; margin-bottom: .65rem; }
.step-status  {
    font-size: .8rem; margin-bottom: .7rem;
    display: flex; align-items: center; gap: .4rem;
}
.step-card.done .step-status { color: var(--success); }
.step-card.todo .step-status { color: var(--text-muted); }
.step-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

/* Parabéns */
.all-done-banner {
    background: linear-gradient(135deg, rgba(52,211,153,.08), rgba(201,168,76,.06));
    border: 1px solid rgba(52,211,153,.25);
    border-radius: var(--radius-xl);
    padding: 1.75rem;
    text-align: center;
    margin-bottom: 1.5rem;
}
.all-done-banner .trophy { font-size: 2.5rem; margin-bottom: .75rem; }
.all-done-banner h2 { color: var(--success); font-size: 1.2rem; margin-bottom: .4rem; }
.all-done-banner p  { color: var(--text-secondary); font-size: .88rem; }
</style>

<!-- Cabeçalho de progresso -->
<?php if ($allDone): ?>
<div class="all-done-banner">
    <div class="trophy">🏆</div>
    <h2>Sistema configurado com sucesso!</h2>
    <p>Todos os passos foram concluídos. O sistema está pronto para uso.<br>
       Acesse o <a href="index.php" style="color:var(--gold)">Dashboard</a> para acompanhar suas OS em tempo real.</p>
</div>
<?php endif; ?>

<div class="setup-hero">
    <div class="setup-hero-icon">
        <i class="fas fa-rocket"></i>
    </div>
    <div class="setup-hero-text">
        <h2>Configuração inicial</h2>
        <p>Siga os passos abaixo para colocar o sistema em funcionamento.<br>
           Os itens já concluídos são detectados automaticamente.</p>
    </div>
    <div class="setup-progress-wrap">
        <div class="progress-label">
            <span><?= $totalDone ?> de <?= $totalSteps ?> passos concluídos</span>
            <strong><?= $pct ?>%</strong>
        </div>
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
        </div>
    </div>
</div>

<!-- Lista de passos -->
<div class="steps-list">
<?php foreach ($steps as $step):
    $isDone = $step['done'];
    $cls    = $isDone ? 'done' : 'todo';
?>
    <div class="step-card <?= $cls ?>" id="step-<?= $step['id'] ?>">

        <div class="step-num">
            <?php if ($isDone): ?>
                <i class="fas fa-check"></i>
            <?php else: ?>
                <?= $step['num'] ?>
            <?php endif; ?>
        </div>

        <div class="step-icon">
            <i class="<?= $step['icon'] ?>"></i>
        </div>

        <div class="step-body">
            <div class="step-title">
                <?= htmlspecialchars($step['title']) ?>
                <span class="step-badge"><?= $isDone ? 'Concluído' : 'Pendente' ?></span>
            </div>
            <p class="step-desc"><?= htmlspecialchars($step['desc']) ?></p>
            <div class="step-status">
                <i class="fas <?= $isDone ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                <?= $isDone ? htmlspecialchars($step['done_txt']) : htmlspecialchars($step['todo_txt']) ?>
            </div>
            <div class="step-actions">
                <?php $a = $step['action']; ?>
                <a href="<?= htmlspecialchars($a['url']) ?>"
                   <?= isset($a['target']) ? 'target="'.$a['target'].'"' : '' ?>
                   class="btn <?= $isDone ? 'btn-ghost' : 'btn-primary' ?> btn-sm">
                    <i class="<?= $a['icon'] ?>"></i>
                    <?= htmlspecialchars($a['label']) ?>
                </a>
                <?php if (!empty($step['extra'])): $e = $step['extra']; ?>
                    <a href="<?= htmlspecialchars($e['url']) ?>" class="btn btn-ghost btn-sm"
                       onclick="F('btnImportar')?.click();return false;">
                        <i class="<?= $e['icon'] ?>"></i>
                        <?= htmlspecialchars($e['label']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isDone): ?>
        <div style="flex-shrink:0;color:var(--success);font-size:1.1rem;margin-top:.2rem">
            <i class="fas fa-check-circle"></i>
        </div>
        <?php endif; ?>

    </div>
<?php endforeach; ?>
</div>

<!-- Dica final -->
<div style="margin-top:1.5rem;background:var(--surface-1);border:1px solid var(--border);
    border-radius:var(--radius-lg);padding:1rem 1.25rem;display:flex;gap:.8rem;align-items:flex-start">
    <i class="fas fa-lightbulb" style="color:var(--gold);font-size:1.1rem;margin-top:.1rem;flex-shrink:0"></i>
    <div>
        <div style="font-size:.85rem;font-weight:700;color:var(--text-primary);margin-bottom:.3rem">Dica</div>
        <div style="font-size:.83rem;color:var(--text-secondary);line-height:1.55">
            Você pode voltar a esta página a qualquer momento pelo menu lateral em
            <strong style="color:var(--gold)">Sistema → Primeiros Passos</strong>.
            O progresso é detectado automaticamente com base nos dados reais do banco.
            Esta página também aparece como atalho quando há passos ainda pendentes.
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
function F(id) { return document.getElementById(id); }

// Realça o próximo passo pendente com animação
document.querySelectorAll('.step-card.todo').forEach(function(card, i) {
    if (i === 0) {
        card.style.boxShadow = '0 0 0 2px rgba(201,168,76,.3)';
        card.style.borderColor = 'rgba(201,168,76,.5)';
    }
});
</script>
JS;

include 'includes/footer.php';
?>
