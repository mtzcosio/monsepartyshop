<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$db = get_db();
$admin = $db->prepare('SELECT * FROM admin_users WHERE id = :id');
$admin->execute(['id' => $_SESSION['admin_id']]);
$admin = $admin->fetch();

$profile_errors = [];
$password_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $profile_errors[] = 'Tu sesión expiró, intenta de nuevo.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') { $profile_errors[] = 'El nombre es obligatorio.'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors[] = 'El correo no es válido.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $profile_errors[] = 'El teléfono no es válido.';
        }
        if ($email === '' && $phone === '') {
            $profile_errors[] = 'Debes dejar al menos un correo o un teléfono: es lo que usas para iniciar sesión.';
        }
        if ($email !== '') {
            $dupe = $db->prepare('SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1');
            $dupe->execute(['email' => $email, 'id' => $admin['id']]);
            if ($dupe->fetch()) { $profile_errors[] = 'Ese correo ya lo usa otra cuenta de administrador.'; }
        }
        if ($phone !== '') {
            $dupe = $db->prepare('SELECT id FROM admin_users WHERE phone = :phone AND id != :id LIMIT 1');
            $dupe->execute(['phone' => $phone, 'id' => $admin['id']]);
            if ($dupe->fetch()) { $profile_errors[] = 'Ese teléfono ya lo usa otra cuenta de administrador.'; }
        }

        if (!$profile_errors) {
            $update = $db->prepare('UPDATE admin_users SET name = :name, email = :email, phone = :phone WHERE id = :id');
            $update->execute([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'id' => $admin['id'],
            ]);
            $_SESSION['admin_name'] = $name;
            flash_set('Datos de la cuenta actualizados.');
            redirect(admin_url('profile.php'));
        }
        $admin['name'] = $name;
        $admin['email'] = $email;
        $admin['phone'] = $phone;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $password_errors[] = 'Tu sesión expiró, intenta de nuevo.';
    } else {
        $current_password = (string)($_POST['current_password'] ?? '');
        $new_password = (string)($_POST['new_password'] ?? '');
        $new_password_confirm = (string)($_POST['new_password_confirm'] ?? '');

        if (!password_verify($current_password, $admin['password_hash'])) {
            $password_errors[] = 'Tu contraseña actual no es correcta.';
        } elseif (strlen($new_password) < 8) {
            $password_errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($new_password !== $new_password_confirm) {
            $password_errors[] = 'Las contraseñas nuevas no coinciden.';
        }

        if (!$password_errors) {
            admin_reset_password($admin['id'], $new_password);
            flash_set('Contraseña actualizada correctamente.');
            redirect(admin_url('profile.php'));
        }
    }
}

$page_title = 'Mi cuenta';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Mi cuenta</h1>
        <p class="page-subtitle">Datos de tu usuario administrador y contraseña de acceso al panel.</p>
    </div>
</div>

<div class="card-box" style="max-width:560px;margin-bottom:24px;">
    <h3 style="margin-top:0;">Datos de la cuenta</h3>
    <?php foreach ($profile_errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="form" value="profile">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
            <label>Usuario</label>
            <input type="text" class="form-control" value="<?= e($admin['username']) ?>" disabled>
        </div>
        <div class="form-group">
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= e($admin['name']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($admin['email']) ?>" placeholder="tucorreo@ejemplo.com">
        </div>
        <div class="form-group">
            <label for="phone">Teléfono</label>
            <input type="text" id="phone" name="phone" class="form-control" value="<?= e($admin['phone'] ?? '') ?>" placeholder="+52 55 1234 5678">
            <p class="settings-hint" style="margin-bottom:0;">El correo y/o el teléfono son con lo que inicias sesión en el panel (ya no con un usuario de texto) y con lo que se envían las instrucciones si olvidas tu contraseña. Debes dejar al menos uno de los dos.</p>
        </div>
        <button type="submit" class="btn btn-primary">GUARDAR DATOS</button>
    </form>
</div>

<div class="card-box" style="max-width:560px;">
    <h3 style="margin-top:0;">Cambiar contraseña</h3>
    <?php foreach ($password_errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="form" value="password">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
            <label for="current_password">Contraseña actual</label>
            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="new_password">Nueva contraseña</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="new_password_confirm">Confirmar nueva contraseña</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">CAMBIAR CONTRASEÑA</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
