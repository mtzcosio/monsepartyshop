<?php
require_once __DIR__ . '/includes/auth.php';

if (admin_logged_in()) {
    redirect(admin_url('index.php'));
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = admin_find_valid_reset($token);
$error = '';
$done = false;

if (!$reset) {
    $error = 'Este enlace ya no es válido o ya venció.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Tu sesión expiró, intenta de nuevo.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $password_confirm = (string)($_POST['password_confirm'] ?? '');
        if (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            admin_reset_password($reset['admin_user_id'], $password);
            admin_consume_reset($reset['id']);
            flash_set('Contraseña actualizada. Ya puedes iniciar sesión.');
            redirect(admin_url('login.php'));
        }
    }
}
$store_name = get_setting('store_name', 'Monse Party Shop');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restablecer contraseña - <?= e($store_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
<style>
    body {
        display: flex; align-items: center; justify-content: center; min-height: 100vh;
        background: #F4F5FA;
        background-image: radial-gradient(circle at 15% 15%, rgba(255,111,145,0.14), transparent 45%),
                           radial-gradient(circle at 85% 85%, rgba(132,94,194,0.12), transparent 45%);
        padding: 20px;
    }
    .login-card {
        width: 100%; max-width: 380px;
        background: #fff;
        border-radius: 20px;
        border: 1px solid rgba(75,68,83,0.08);
        box-shadow: 0 20px 48px rgba(75,68,83,0.14);
        padding: 40px 36px;
    }
    .login-mark {
        width: 52px; height: 52px; border-radius: 15px; margin: 0 auto 18px;
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        display: flex; align-items: center; justify-content: center; font-size: 24px;
    }
</style>
</head>
<body>
<div class="login-card">
    <div class="login-mark">🔒</div>
    <h2 class="text-center" style="color:var(--text-color);"><?= e($store_name) ?></h2>
    <p class="text-center" style="opacity:0.6;font-size:13.5px;margin-bottom:28px;">Restablecer contraseña</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($reset): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-group">
                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" autofocus>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmar contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary btn-block">GUARDAR CONTRASEÑA</button>
        </form>
    <?php else: ?>
        <p class="text-center" style="margin-top:20px;"><a href="<?= e(admin_url('forgot_password.php')) ?>">Solicitar un nuevo enlace</a></p>
    <?php endif; ?>
</div>
</body>
</html>
