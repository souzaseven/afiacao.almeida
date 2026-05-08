<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function startAdminSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startAdminSession();
    if (!isset($_SESSION['admin_id'], $_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        return false;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $loginUrl = str_contains($_SERVER['PHP_SELF'], '/admin/') ? 'login.php' : 'admin/login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

function doLogin(string $email, string $password): bool {
    // Login temporário: usuário 'anderson' e senha 'teste'
    if (($email === 'anderson' || $email === 'anderson@afiacao.com') && $password === 'teste') {
        startAdminSession();
        session_regenerate_id(true);
        $_SESSION['admin_id']        = 1;
        $_SESSION['admin_nome']      = 'Anderson';
        $_SESSION['admin_email']     = 'anderson@afiacao.com';
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['last_activity']   = time();
        return true;
    }
    // Caso queira liberar cadastro, basta aceitar qualquer usuário/senha:
    // startAdminSession();
    // $_SESSION['admin_id'] = 1;
    // $_SESSION['admin_logged_in'] = true;
    // $_SESSION['last_activity'] = time();
    // return true;
    return false;
}

function doLogout(): void {
    startAdminSession();
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

function getCurrentAdmin(): array {
    startAdminSession();
    return [
        'id'    => $_SESSION['admin_id']    ?? null,
        'nome'  => $_SESSION['admin_nome']  ?? '',
        'email' => $_SESSION['admin_email'] ?? '',
    ];
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
