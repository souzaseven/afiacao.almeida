
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Afiação Almeida</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Caminho corrigido para o CSS do admin -->
    <link href="/admin/assets/css/admin.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/admin/dashboard.php">Afiação Almeida</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/admin/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/clientes/index.php">Clientes</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/servicos/index.php">Serviços</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/relacionamentos/index.php">Relacionamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/configuracoes.php">Configurações</a></li>
      </ul>
      <span class="navbar-text me-3">Usuário: <?= htmlspecialchars($_SESSION['usuario'] ?? 'admin') ?></span>
      <a href="/admin/logout.php" class="btn btn-outline-light btn-sm">Sair</a>
    </div>
  </div>
</nav>
<div class="d-flex" style="min-height:100vh;">
  <aside class="bg-light border-end p-3" style="min-width:220px;">
    <ul class="nav flex-column">
      <li class="nav-item mb-1"><a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
      <li class="nav-item mb-1"><a class="nav-link" href="/admin/clientes/index.php"><i class="bi bi-people"></i> Clientes</a></li>
      <li class="nav-item mb-1"><a class="nav-link" href="/admin/servicos/index.php"><i class="bi bi-tools"></i> Serviços</a></li>
      <li class="nav-item mb-1"><a class="nav-link" href="/admin/relacionamentos/index.php"><i class="bi bi-link-45deg"></i> Relacionamentos</a></li>
      <li class="nav-item mb-1"><a class="nav-link" href="/admin/configuracoes.php"><i class="bi bi-gear"></i> Configurações</a></li>
    </ul>
  </aside>
  <main class="flex-fill p-4">
