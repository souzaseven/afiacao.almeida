<?php
require_once '../includes/auth.php';

// Já logado → vai direto pro dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha o usuário e a senha.';
    } elseif (doLogin($email, $senha)) {
        header('Location: index.php');
        exit;
    } else {
        $erro = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Login — Afiação Almeida</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg);
            padding: 1rem;
        }

        .login-wrap {
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--rose) 100%);
            color: var(--bg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            font-family: var(--font-display);
            box-shadow: var(--shadow-gold);
            margin-bottom: .75rem;
        }

        .login-brand {
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gold);
            display: block;
        }

        .login-sub {
            font-size: .82rem;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
        }

        .login-card {
            background: var(--surface-1);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2rem;
        }

        .login-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: .35rem;
            letter-spacing: .03em;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .9rem;
            pointer-events: none;
        }

        .input-wrap .form-control {
            padding-left: 36px;
        }

        .btn-login {
            width: 100%;
            padding: 11px;
            font-size: .95rem;
            margin-top: .5rem;
            justify-content: center;
        }

        .erro-msg {
            display: flex;
            align-items: center;
            gap: .5rem;
            background: var(--danger-dim);
            border: 1px solid rgba(248,113,113,.25);
            color: var(--danger);
            padding: .7rem 1rem;
            border-radius: var(--radius-md);
            font-size: .85rem;
            font-weight: 500;
            margin-bottom: 1.2rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .78rem;
            color: var(--text-muted);
        }

        .toggle-senha {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: .9rem;
            padding: 0;
            transition: color var(--transition);
        }
        .toggle-senha:hover { color: var(--gold); }
    </style>
</head>
<body>

<div class="login-wrap">

    <div class="login-logo">
        <div class="login-logo-icon">A</div>
        <span class="login-brand">Afiação Almeida</span>
        <span class="login-sub">Painel Administrativo</span>
    </div>

    <div class="login-card">

        <p class="login-title">Acesse sua conta</p>

        <?php if ($erro): ?>
            <div class="erro-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>

            <div class="form-group">
                <label class="form-label" for="email">Usuário</label>
                <div class="input-wrap">
                    <i class="fas fa-user"></i>
                    <input
                        type="text"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="anderson"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="senha">Senha</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        class="form-control"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="toggle-senha" id="toggleSenha" title="Mostrar/ocultar senha">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>

        </form>

    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> Afiação Almeida — Acesso restrito
    </div>

</div>

<script>
const toggle = document.getElementById('toggleSenha');
const senha  = document.getElementById('senha');
const icon   = document.getElementById('toggleIcon');
if (toggle) {
    toggle.addEventListener('click', () => {
        const hidden = senha.type === 'password';
        senha.type   = hidden ? 'text' : 'password';
        icon.className = hidden ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
}
</script>

</body>
</html>
