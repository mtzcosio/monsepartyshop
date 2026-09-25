<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/pagination.php';
$store_name = get_setting('store_name', 'Monse Party Shop');
$current = basename($_SERVER['SCRIPT_NAME']);

$admin_display_name = $_SESSION['admin_name'] ?: 'Admin';
$admin_initials_parts = preg_split('/\s+/', trim($admin_display_name));
$admin_initials = strtoupper(substr($admin_initials_parts[0], 0, 1) . substr($admin_initials_parts[count($admin_initials_parts) - 1], 0, 1));

$notif_orders = get_db()->query(
    "SELECT id, order_code, customer_name, total, created_at FROM orders WHERE status = 'pending' ORDER BY created_at DESC LIMIT 6"
)->fetchAll();
$notif_count = (int)get_db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

$notif_messages = get_db()->query(
    "SELECT id, full_name, email, reason, created_at FROM contact_messages WHERE status = 'new' ORDER BY created_at DESC LIMIT 6"
)->fetchAll();
$notif_messages_count = (int)get_db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? e($page_title) . ' | Admin' : 'Admin' ?> - <?= e($store_name) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-overlay" id="adminOverlay"></div>
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <span class="sidebar-brand-mark">🎉</span>
            <span class="sidebar-brand-text"><?= e($store_name) ?><span class="sidebar-brand-sub">Panel administrativo</span></span>
            <button type="button" class="sidebar-close" id="adminSidebarClose" aria-label="Cerrar menú">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="sidebar-section-label">Menú</div>
        <nav class="sidebar-nav">
            <a href="<?= admin_url('index.php') ?>" class="nav-item <?= $current === 'index.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                Panel
            </a>
            <a href="<?= admin_url('products.php') ?>" class="nav-item <?= ($current === 'products.php' || $current === 'product_form.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
                Productos
            </a>
            <a href="<?= admin_url('categories.php') ?>" class="nav-item <?= ($current === 'categories.php' || $current === 'category_form.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41L11 3.83A2 2 0 0 0 9.59 3.2L4 3a1 1 0 0 0-1 1l.2 5.59a2 2 0 0 0 .58 1.41l9.59 9.59a2 2 0 0 0 2.83 0l4.39-4.39a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.1"/></svg>
                Categorías
            </a>
            <a href="<?= admin_url('services.php') ?>" class="nav-item <?= ($current === 'services.php' || $current === 'service_form.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
                Servicios
            </a>
            <a href="<?= admin_url('orders.php') ?>" class="nav-item <?= ($current === 'orders.php' || $current === 'order_detail.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 13H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                Pedidos
                <?php if ($notif_count > 0): ?><span class="notif-dot" style="position:static;margin-left:auto;border:none;"><?= $notif_count ?></span><?php endif; ?>
            </a>
            <a href="<?= admin_url('contact_messages.php') ?>" class="nav-item <?= ($current === 'contact_messages.php' || $current === 'contact_message_detail.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Mensajes
                <?php if ($notif_messages_count > 0): ?><span class="notif-dot" style="position:static;margin-left:auto;border:none;"><?= $notif_messages_count ?></span><?php endif; ?>
            </a>
            <a href="<?= admin_url('settings.php') ?>" class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Configuración
            </a>
            <a href="<?= admin_url('email_templates.php') ?>" class="nav-item <?= in_array($current, ['email_templates.php', 'email_template_form.php', 'email_template_view.php', 'email_variables.php'], true) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Plantillas de correo
            </a>
            <a href="<?= admin_url('media_library.php') ?>" class="nav-item <?= $current === 'media_library.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                Biblioteca de archivos
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= base_url('index.php') ?>" target="_blank" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
                Ver tienda
            </a>
        </div>
    </aside>

    <div class="admin-main-col">
        <header class="admin-topnav">
            <button type="button" class="topnav-toggle" id="adminNavToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="adminSidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/></svg>
            </button>

            <form class="topnav-search" action="<?= admin_url('products.php') ?>" method="get">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="nombre" placeholder="Buscar productos..." value="<?= e($_GET['nombre'] ?? '') ?>">
            </form>

            <div class="topnav-spacer"></div>

            <div class="topnav-actions">
                <div class="dropdown-wrap" id="notifDropdown">
                    <button type="button" class="icon-btn" id="notifToggle" aria-label="Notificaciones">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($notif_count > 0): ?><span class="notif-dot"><?= $notif_count > 9 ? '9+' : $notif_count ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-panel">
                        <div class="dropdown-head">
                            Pedidos pendientes
                            <?php if ($notif_count > 0): ?><span class="count-tag"><?= $notif_count ?></span><?php endif; ?>
                        </div>
                        <div class="notif-list">
                            <?php if ($notif_orders): ?>
                                <?php foreach ($notif_orders as $notif): ?>
                                    <a class="notif-item" href="<?= admin_url('order_detail.php?id=' . (int)$notif['id']) ?>">
                                        <strong><?= e($notif['order_code']) ?> · <?= e($notif['customer_name']) ?></strong>
                                        <span><?= format_price($notif['total']) ?> · <?= e(date('d/m/Y H:i', strtotime($notif['created_at']))) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notif-empty">No hay pedidos pendientes 🎉</div>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-foot"><a href="<?= admin_url('orders.php?estado=pending') ?>">Ver todos los pedidos pendientes</a></div>
                    </div>
                </div>

                <div class="dropdown-wrap" id="notifMessagesDropdown">
                    <button type="button" class="icon-btn" id="notifMessagesToggle" aria-label="Mensajes de contacto nuevos">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <?php if ($notif_messages_count > 0): ?><span class="notif-dot"><?= $notif_messages_count > 9 ? '9+' : $notif_messages_count ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-panel">
                        <div class="dropdown-head">
                            Mensajes nuevos
                            <?php if ($notif_messages_count > 0): ?><span class="count-tag"><?= $notif_messages_count ?></span><?php endif; ?>
                        </div>
                        <div class="notif-list">
                            <?php if ($notif_messages): ?>
                                <?php foreach ($notif_messages as $notif_msg): ?>
                                    <a class="notif-item" href="<?= admin_url('contact_message_detail.php?id=' . (int)$notif_msg['id']) ?>">
                                        <strong><?= e($notif_msg['full_name']) ?></strong>
                                        <span><?= e($notif_msg['email']) ?> · <?= e(date('d/m/Y H:i', strtotime($notif_msg['created_at']))) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notif-empty">No hay mensajes nuevos 📭</div>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-foot"><a href="<?= admin_url('contact_messages.php?estado=new') ?>">Ver todos los mensajes nuevos</a></div>
                    </div>
                </div>

                <div class="dropdown-wrap" id="profileDropdown">
                    <button type="button" class="profile-btn" id="profileToggle">
                        <span class="avatar"><?= e($admin_initials) ?></span>
                        <span class="profile-name"><?= e($admin_display_name) ?></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="dropdown-panel">
                        <div class="profile-menu">
                            <a href="<?= admin_url('profile.php') ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Mi cuenta
                            </a>
                            <a href="<?= base_url('index.php') ?>" target="_blank">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
                                Ver tienda
                            </a>
                            <a href="<?= admin_url('logout.php') ?>" class="danger">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                                Cerrar sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <?php $flash = flash_get(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
