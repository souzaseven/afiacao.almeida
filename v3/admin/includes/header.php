<?php
if (!isset($pageTitle))   $pageTitle   = 'Admin';
if (!isset($currentPage)) $currentPage = '';
if (!isset($breadcrumb))  $breadcrumb  = '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> — Afiação Almeida</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <?= $extraHead ?? '' ?>
</head>
<body>

<div class="admin-wrapper">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">

        <header class="topbar">
            <div class="topbar-left">
                <h1 class="topbar-title"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if ($breadcrumb): ?>
                    <p class="topbar-breadcrumb"><?= htmlspecialchars($breadcrumb) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <?= $topbarActions ?? '' ?>
                <a href="../index.html" target="_blank" class="topbar-btn" title="Ver site">
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>" target="_blank" class="topbar-btn" title="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
        </header>

        <main class="page-content">

            <?php
            $_fls = $_SESSION['flash_success'] ?? '';
            $_fle = $_SESSION['flash_error']   ?? '';
            unset($_SESSION['flash_success'], $_SESSION['flash_error']);
            if ($_fls || $_fle): ?>
            <script>window._flash=<?= json_encode(['s'=>$_fls,'e'=>$_fle],JSON_UNESCAPED_UNICODE) ?>;</script>
            <?php endif; ?>
