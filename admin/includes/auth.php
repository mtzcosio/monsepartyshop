<?php
require_once __DIR__ . '/../../includes/init.php';

define('ADMIN_MAX_ATTEMPTS', 5);
define('ADMIN_LOCKOUT_MINUTES', 15);

function admin_attempt_login($identifier, $password) {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE email = :id OR phone = :id LIMIT 1');
    $stmt->execute(['id' => $identifier]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return false;
    }

    if (!empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()) {
        return false;
    }

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $reset = $db->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = :id');
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

function require_admin_login() {
    if (!admin_logged_in()) {
        redirect(admin_url('login.php'));
    }
}

function admin_logout() {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
}

function admin_url($path = '') {
    return base_url('admin/' . ltrim($path, '/'));
}

function admin_find_by_identifier($identifier) {
    $stmt = get_db()->prepare('SELECT * FROM admin_users WHERE email = :id OR phone = :id LIMIT 1');
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
         WHERE r.token_hash = :hash AND r.used_at IS NULL AND r.expires_at > NOW() LIMIT 1"
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
