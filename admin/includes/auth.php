<?php
require_once __DIR__ . '/../../includes/init.php';

define('ADMIN_MAX_ATTEMPTS', 5);
define('ADMIN_LOCKOUT_MINUTES', 15);

function admin_attempt_login($username, $password) {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $username]);
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

function admin_is_locked($username) {
    $stmt = get_db()->prepare('SELECT locked_until FROM admin_users WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $username]);
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
