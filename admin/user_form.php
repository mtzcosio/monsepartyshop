<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$db = get_db();
$roles = admin_roles();
$id = (int)($_GET['id'] ?? 0);

// La cuenta propia se edita desde "Mi cuenta" (pide la contraseña actual y no deja
// cambiarse el rol ni desactivarse a uno mismo).
if ($id && $id === (int)current_admin()['id']) {
    redirect(admin_url('profile.php'));
}

$user = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'editor', 'status' => 'active', 'username' => ''];

if ($id) {
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash_set('Ese usuario no existe.', 'error');
        redirect(admin_url('users.php'));
    }
    $user = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, intenta de nuevo.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '') { $errors[] = 'El nombre es obligatorio.'; }
    if (!isset($roles[$role])) { $errors[] = 'Elige un rol válido.'; }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo no es válido.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = 'El teléfono no es válido.';
    }
    if ($email === '' && $phone === '') {
        $errors[] = 'Captura al menos un correo o un teléfono: es con lo que la persona iniciará sesión.';
    }
    if ($email !== '') {
        $dupe = $db->prepare('SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1');
        $dupe->execute(['email' => $email, 'id' => $id]);
        if ($dupe->fetch()) { $errors[] = 'Ese correo ya lo usa otra cuenta.'; }
    }
    if ($phone !== '') {
        $dupe = $db->prepare('SELECT id FROM admin_users WHERE phone = :phone AND id != :id LIMIT 1');
        $dupe->execute(['phone' => $phone, 'id' => $id]);
        if ($dupe->fetch()) { $errors[] = 'Ese teléfono ya lo usa otra cuenta.'; }
    }

    // Contraseña: obligatoria al crear; al editar, vacía = no se cambia.
    if (!$id || $password !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
    }

    // Quitarle el rol o desactivar al último súper admin activo dejaría el panel sin quien gestione usuarios.
    if ($id && $user['role'] === 'super_admin' && $user['status'] === 'active'
        && ($role !== 'super_admin' || $status !== 'active')
        && admin_other_active_super_admins($id) === 0) {
        $errors[] = 'Debe quedar al menos un súper administrador activo.';
    }

    if (!$errors) {
        $data = [
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'role' => $role,
            'status' => $status,
        ];

        if ($id) {
            $sql = 'UPDATE admin_users SET name = :name, email = :email, phone = :phone, role = :role, status = :status';
            if ($password !== '') {
                // Una contraseña nueva puesta por un súper admin también quita cualquier bloqueo.
                $sql .= ', password_hash = :hash, failed_attempts = 0, locked_until = NULL';
                $data['hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $data['id'] = $id;
            $db->prepare($sql . ' WHERE id = :id')->execute($data);
            flash_set('Usuario actualizado.');
        } else {
            // El login es por correo/teléfono; username solo necesita ser único (columna NOT NULL UNIQUE).
            $base = slugify($email !== '' ? strstr($email, '@', true) : $name) ?: 'usuario';
            $username = $base;
            $check = $db->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u');
            for ($n = 2; ; $n++) {
                $check->execute(['u' => $username]);
                if (!(int)$check->fetchColumn()) { break; }
                $username = $base . '-' . $n;
            }
            $data['username'] = $username;
            $data['hash'] = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare(
                'INSERT INTO admin_users (username, name, email, phone, role, status, password_hash)
                 VALUES (:username, :name, :email, :phone, :role, :status, :hash)'
            )->execute($data);
            flash_set('Usuario creado. Ya puede entrar al panel con su correo o teléfono y la contraseña que le asignaste.');
        }
        redirect(admin_url('users.php'));
    }

    $user = array_merge($user, ['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $role, 'status' => $status]);
}

$page_title = $id ? 'Editar usuario' : 'Nuevo usuario';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0"><?= $id ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        <p class="page-subtitle"><?= $id ? e($user['name']) : 'Crea una cuenta con acceso al panel y elige su rol.' ?></p>
    </div>
    <a href="<?= admin_url('users.php') ?>" class="btn btn-secondary btn-sm">← Volver a usuarios</a>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>

<form method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="card-box" style="max-width:720px;margin-bottom:24px;">
        <h3 style="margin-top:0;">Datos de la cuenta</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Nombre</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="status">Estado</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo (no puede entrar)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" placeholder="correo@ejemplo.com">
            </div>
            <div class="form-group">
                <label for="phone">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+52 55 1234 5678">
            </div>
        </div>
        <p class="settings-hint" style="margin-bottom:0;">La persona inicia sesión con su correo o su teléfono (al menos uno es obligatorio). Con el correo también puede recuperar su contraseña si la olvida.</p>
    </div>

    <div class="card-box" style="max-width:720px;margin-bottom:24px;">
        <h3 style="margin-top:0;">Rol</h3>
        <?php foreach ($roles as $key => $role): ?>
            <label style="display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid rgba(75,68,83,0.12);border-radius:var(--radius-sm);margin-bottom:10px;cursor:pointer;">
                <input type="radio" name="role" value="<?= e($key) ?>" <?= $user['role'] === $key ? 'checked' : '' ?> style="margin-top:4px;">
                <span>
                    <strong><?= e($role['label']) ?></strong><br>
                    <span style="font-size:13px;color:var(--admin-text-muted);"><?= e($role['description']) ?></span>
                </span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="card-box" style="max-width:720px;margin-bottom:24px;">
        <h3 style="margin-top:0;"><?= $id ? 'Cambiar contraseña' : 'Contraseña' ?></h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="password"><?= $id ? 'Nueva contraseña' : 'Contraseña' ?></label>
                <input type="password" id="password" name="password" class="form-control" minlength="8" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmar contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" minlength="8" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
            </div>
        </div>
        <p class="settings-hint" style="margin-bottom:0;">
            <?= $id ? 'Déjalo vacío para no cambiarla. Si pones una nueva, también se quita cualquier bloqueo por intentos fallidos.' : 'Mínimo 8 caracteres. Compártela con la persona por un medio seguro; podrá cambiarla después en "Mi cuenta".' ?>
        </p>
    </div>

    <button type="submit" class="btn btn-primary"><?= $id ? 'GUARDAR CAMBIOS' : 'CREAR USUARIO' ?></button>
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
