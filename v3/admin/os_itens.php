<?php
require_once '../includes/auth.php';
requireLogin();
require_once 'includes/db.php';

header('Content-Type: application/json');

$os_id = (int)($_GET['os_id'] ?? 0);
if ($os_id <= 0) { echo '[]'; exit; }

$stmt = $pdo->prepare(
    "SELECT equipamento, servico_id, quantidade, valor_unitario, observacao
     FROM itens_os WHERE os_id = ? ORDER BY id ASC"
);
$stmt->execute([$os_id]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
