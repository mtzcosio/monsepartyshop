<?php
require_once __DIR__ . '/includes/auth.php';

if (admin_logged_in()) {
    redirect(admin_url('index.php'));
}

$error = '';
$flash = flash_get();
if ($flash && $flash['type'] === 'error') { $error = $flash['message']; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Tu sesión expiró, intenta de nuevo.';
    } elseif (admin_is_locked($identifier)) {
        $error = 'Cuenta bloqueada temporalmente por demasiados intentos fallidos. Intenta de nuevo en unos minutos.';
    } elseif (admin_attempt_login($identifier, $_POST['password'] ?? '')) {
        redirect(admin_url('index.php'));
    } else {
        $error = 'Correo/teléfono o contraseña incorrectos.';
    }
}
$store_name = get_setting('store_name', 'Monse Party Shop');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso admin - <?= e($store_name) ?></title>
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
    <div class="login-mark">🎉</div>
    <h2 class="text-center" style="color:var(--text-color);"><?= e($store_name) ?></h2>
    <p class="text-center" style="opacity:0.6;font-size:13.5px;margin-bottom:28px;">Acceso al panel administrativo</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
            <label for="identifier">Correo o teléfono</label>
            <input type="text" id="identifier" name="identifier" class="form-control" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">INGRESAR</button>
    </form>
    <p class="text-center" style="margin-top:20px;font-size:13.5px;"><a href="<?= e(admin_url('forgot_password.php')) ?>">¿Olvidaste tu contraseña?</a></p>
</div>
</body>
</html>
