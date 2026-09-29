<?php
require_once __DIR__ . '/../../includes/init.php';

define('ADMIN_MAX_ATTEMPTS', 5);
define('ADMIN_LOCKOUT_MINUTES', 15);

/**
 * Catálogo de roles del panel. 'sections' son las secciones que cada rol puede abrir
 * ('*' = todas); 'profile' (Mi cuenta) siempre está permitida para cualquiera.
 * Un rol nuevo solo se agrega aquí (y en el ENUM de admin_users.role).
 */
function admin_roles() {
    return [
        'super_admin' => [
            'label' => 'Súper administrador',
            'description' => 'Acceso total, incluida la gestión de usuarios del panel.',
            'sections' => ['*'],
        ],
        'admin' => [
            'label' => 'Administrador',
            'description' => 'Todo el panel excepto la gestión de usuarios.',
            'sections' => ['dashboard', 'products', 'categories', 'services', 'orders', 'messages', 'settings', 'emails', 'media'],
        ],
        'editor' => [
            'label' => 'Editor',
            'description' => 'Solo catálogo: productos, categorías, servicios y biblioteca de archivos. Sin pedidos, mensajes ni configuración.',
            'sections' => ['products', 'categories', 'services', 'media'],
        ],
    ];
}

function admin_role_label($role) {
    $roles = admin_roles();
    return $roles[$role]['label'] ?? $role;
}

/**
 * Sección a la que pertenece un script del admin, por prefijo de nombre de archivo.
 * Un script que no encaja en ninguna queda como 'other', que solo abren los roles con '*'
 * (así una página nueva olvidada aquí queda cerrada por defecto en vez de abierta).
 */
function admin_section_for_script($script) {
    $map = [
        'index.php' => 'dashboard',
        'profile.php' => 'profile',
        'logout.php' => 'profile',
        'settings.php' => 'settings',
        'test_email.php' => 'settings',
    ];
    if (isset($map[$script])) { return $map[$script]; }
    $prefixes = [
        'product' => 'products',
        'categor' => 'categories',
        'service' => 'services',
        'order' => 'orders',
        'contact_message' => 'messages',
        'email_' => 'emails',
        'media_' => 'media',
        'user' => 'users',
    ];
    foreach ($prefixes as $prefix => $section) {
        if (strpos($script, $prefix) === 0) { return $section; }
    }
    return 'other';
}

/** ¿El admin en sesión puede abrir esta sección? */
function admin_can($section) {
    if ($section === 'profile') { return true; }
    $admin = current_admin();
    if (!$admin) { return false; }
    $roles = admin_roles();
    $allowed = $roles[$admin['role']]['sections'] ?? [];
    return in_array('*', $allowed, true) || in_array($section, $allowed, true);
}

/** Primera página a la que el rol actual tiene acceso (a donde se manda tras login o un acceso denegado). */
function admin_home_url() {
    $pages = [
        'dashboard' => 'index.php', 'products' => 'products.php', 'categories' => 'categories.php',
        'services' => 'services.php', 'orders' => 'orders.php', 'media' => 'media_library.php',
    ];
    foreach ($pages as $section => $page) {
        if (admin_can($section)) { return admin_url($page); }
    }
    return admin_url('profile.php');
}

/**
 * Fila del admin en sesión, releída de la BD una vez por request: así un cambio de rol,
 * una desactivación o un borrado hecho por otro súper admin surte efecto de inmediato,
 * sin esperar a que la persona cierre sesión.
 */
function current_admin() {
    static $admin = false;
    if ($admin === false) {
        $admin = null;
        if (!empty($_SESSION['admin_id'])) {
            $stmt = get_db()->prepare("SELECT * FROM admin_users WHERE id = :id AND status = 'active' LIMIT 1");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $admin = $stmt->fetch() ?: null;
        }
    }
    return $admin;
}

/**
 * Súper administradores activos sin contar a $exclude_id. Se usa para no permitir que
 * una baja/desactivación/cambio de rol deje al panel sin nadie que pueda gestionar usuarios.
 */
function admin_other_active_super_admins($exclude_id) {
    $stmt = get_db()->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active' AND id != :id");
    $stmt->execute(['id' => $exclude_id]);
    return (int)$stmt->fetchColumn();
}

function admin_attempt_login($identifier, $password) {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE email = :id OR phone = :id LIMIT 1');
    $stmt->execute(['id' => $identifier]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return false;
    }

    if ($admin['status'] !== 'active') {
        return false;
    }

    if (!empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()) {
        return false;
    }

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $reset = $db->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id');
        $reset->execute(['id' => $admin['id']]);

        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        return true;
    }

    $attempts = (int)$admin['failed_attempts'] + 1;
    if ($attempts >= ADMIN_MAX_ATTEMPTS) {
        $locked_until = date('Y-m-d H:i:s', time() + (ADMIN_LOCKOUT_MINUTES * 60));
        $update = $db->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = :locked WHERE id = :id');
        $update->execute(['locked' => $locked_until, 'id' => $admin['id']]);
    } else {
        $update = $db->prepare('UPDATE admin_users SET failed_attempts = :attempts WHERE id = :id');
        $update->execute(['attempts' => $attempts, 'id' => $admin['id']]);
    }
    return false;
}

function admin_is_locked($identifier) {
    $stmt = get_db()->prepare('SELECT locked_until FROM admin_users WHERE email = :id OR phone = :id LIMIT 1');
    $stmt->execute(['id' => $identifier]);
    $row = $stmt->fetch();
    return $row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time();
}

function admin_logged_in() {
    return !empty($_SESSION['admin_id']);
}

/**
 * Exige sesión de admin activa y que su rol tenga acceso a la sección del script actual.
 * Si la cuenta fue desactivada/borrada, cierra la sesión; si el rol no alcanza, manda a
 * la primera página que sí puede abrir.
 */
function require_admin_login() {
    if (!admin_logged_in()) {
        redirect(admin_url('login.php'));
    }
    if (!current_admin()) {
        admin_logout();
        flash_set('Tu cuenta ya no tiene acceso al panel.', 'error');
        redirect(admin_url('login.php'));
    }
    $section = admin_section_for_script(basename($_SERVER['SCRIPT_NAME']));
    if (!admin_can($section)) {
        if ($section !== 'dashboard') {
            flash_set('No tienes permiso para entrar a esa sección.', 'error');
        }
        redirect(admin_home_url());
    }
}

function admin_logout() {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
}

function admin_url($path = '') {
    return base_url('admin/' . ltrim($path, '/'));
}

function admin_find_by_identifier($identifier) {
    $stmt = get_db()->prepare("SELECT * FROM admin_users WHERE (email = :id OR phone = :id) AND status = 'active' LIMIT 1");
    $stmt->execute(['id' => $identifier]);
    return $stmt->fetch() ?: null;
}

/**
 * Crea una solicitud de recuperación de contraseña: invalida las anteriores no usadas
 * de ese admin (evita que un link viejo siga siendo válido junto con el nuevo), genera
 * un token aleatorio de 32 bytes y guarda solo su hash SHA-256. Devuelve el token crudo
 * (solo existe en este momento, para armar la URL del correo).
 */
function admin_create_password_reset($admin_id, $ip = null) {
    $db = get_db();
    $db->prepare('DELETE FROM admin_password_resets WHERE admin_user_id = :id AND used_at IS NULL')->execute(['id' => $admin_id]);

    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare(
        'INSERT INTO admin_password_resets (admin_user_id, token_hash, ip_address, expires_at)
         VALUES (:admin_id, :token_hash, :ip, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
    );
    $stmt->execute([
        'admin_id' => $admin_id,
        'token_hash' => hash('sha256', $token),
        'ip' => $ip,
    ]);
    return $token;
}

/** Busca una solicitud de recuperación vigente (no usada, no vencida) por su token crudo. */
function admin_find_valid_reset($token) {
    if (!$token) { return null; }
    $stmt = get_db()->prepare(
        "SELECT r.*, u.username, u.name, u.email FROM admin_password_resets r
         JOIN admin_users u ON u.id = r.admin_user_id
         WHERE r.token_hash = :hash AND r.used_at IS NULL AND r.expires_at > NOW() AND u.status = 'active' LIMIT 1"
    );
    $stmt->execute(['hash' => hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function admin_consume_reset($reset_id) {
    get_db()->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE id = :id')->execute(['id' => $reset_id]);
}

/** Cambia la contraseña y limpia cualquier bloqueo: probar identidad por correo ya es suficiente. */
function admin_reset_password($admin_id, $new_password) {
    $stmt = get_db()->prepare(
        'UPDATE admin_users SET password_hash = :hash, failed_attempts = 0, locked_until = NULL WHERE id = :id'
    );
    $stmt->execute(['hash' => password_hash($new_password, PASSWORD_DEFAULT), 'id' => $admin_id]);
}

/** Límite anti-abuso: máx. 3 solicitudes de recuperación por IP cada 10 minutos. */
function admin_password_reset_rate_limited($ip) {
    if (!$ip) { return false; }
    $stmt = get_db()->prepare(
        "SELECT COUNT(*) FROM admin_password_resets WHERE ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
    $stmt->execute(['ip' => $ip]);
    return (int)$stmt->fetchColumn() >= 3;
}
