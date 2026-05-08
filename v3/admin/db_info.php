<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

// Caminho real do banco
$dbPath = DB_PATH;
$dbReal = realpath($dbPath) ?: $dbPath . ' (arquivo não encontrado!)';
$dbSize = file_exists($dbPath) ? round(filesize($dbPath) / 1024, 1) . ' KB' : 'N/A';

// Tabelas existentes
$tabelas = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Dados de afiacao_clientes
$clientes = [];
if (in_array('afiacao_clientes', $tabelas)) {
    $clientes = $pdo->query("SELECT id, nome, status FROM afiacao_clientes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>DB Info</title>
<style>
body { font-family: monospace; background:#141414; color:#f0f0f0; padding:2rem; max-width:800px; margin:auto; }
h2   { color:#C9A84C; margin-bottom:.5rem; }
.box { background:#1e1e1e; border:1px solid #333; border-radius:8px; padding:1.2rem; margin-bottom:1.5rem; }
label{ color:#888; font-size:.8rem; display:block; margin-bottom:.25rem; }
code { color:#34D399; word-break:break-all; font-size:.9rem; }
table{ width:100%; border-collapse:collapse; }
th   { text-align:left; color:#888; font-size:.75rem; border-bottom:1px solid #333; padding:.4rem .6rem; }
td   { padding:.4rem .6rem; font-size:.88rem; border-bottom:1px solid #1a1a1a; }
.pill{ display:inline-block; background:#2a2a2a; border-radius:4px; padding:2px 8px; font-size:.78rem; color:#C9A84C; }
a { color:#C9A84C; }
</style>
</head>
<body>

<h2>🔍 Diagnóstico do Banco SQLite</h2>

<div class="box">
    <label>Caminho configurado (DB_PATH)</label>
    <code><?= htmlspecialchars($dbPath) ?></code>
    <br><br>
    <label>Caminho real no servidor (realpath)</label>
    <code><?= htmlspecialchars($dbReal) ?></code>
    <br><br>
    <label>Tamanho do arquivo</label>
    <code><?= $dbSize ?></code>
</div>

<div class="box">
    <label>Tabelas existentes no banco (<?= count($tabelas) ?>)</label>
    <br>
    <?php foreach ($tabelas as $t): ?>
        <span class="pill"><?= htmlspecialchars($t) ?></span>
    <?php endforeach; ?>
</div>

<div class="box">
    <label>Conteúdo de afiacao_clientes (<?= count($clientes) ?> registros)</label>
    <?php if (empty($clientes)): ?>
        <p style="color:#F87171">Nenhum cliente encontrado nesta tabela.</p>
    <?php else: ?>
        <table>
            <tr><th>ID</th><th>Nome</th><th>Status</th></tr>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['nome']) ?></td>
                    <td><?= htmlspecialchars($c['status'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<a href="clientes.php">← Voltar</a>

</body>
</html>
