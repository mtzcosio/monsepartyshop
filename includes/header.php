<?php
require_once __DIR__ . '/init.php';
$store_name = get_setting('store_name', 'Monse Party Shop');
$logo = get_setting('logo', '');
$favicon = get_setting('favicon', '');
$page_title = isset($page_title) ? $page_title . ' | ' . $store_name : $store_name;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?></title>
<?php if ($favicon): ?>
<link rel="icon" href="<?= e(upload_url($favicon)) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
<style>
:root {
    --primary-color: <?= e(get_setting('primary_color', '#FF6F91')) ?>;
    --secondary-color: <?= e(get_setting('secondary_color', '#FFC75F')) ?>;
    --accent-color: <?= e(get_setting('accent_color', '#845EC2')) ?>;
}
</style>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="<?= base_url('index.php') ?>" class="logo">
            <?php if ($logo): ?>
                <img src="<?= e(upload_url($logo)) ?>" alt="<?= e($store_name) ?>">
            <?php else: ?>
                <?= e($store_name) ?>
            <?php endif; ?>
        </a>
        <nav class="main-nav" id="mainNav">
            <a href="<?= base_url('index.php') ?>">Inicio</a>
            <a href="<?= base_url('productos.php') ?>">Plantillas</a>
            <a href="<?= base_url('servicios.php') ?>">Servicios</a>
            <a href="<?= base_url('index.php#categorias') ?>">Categorías</a>
            <a href="<?= base_url('como-funciona.php') ?>">Cómo funciona</a>
            <a href="<?= base_url('contacto.php') ?>">Contacto</a>
        </nav>
        <div class="header-actions">
            <a href="<?= base_url('carrito.php') ?>" class="cart-link" title="Carrito">
                🛍️
                <?php $count = cart_count(); if ($count > 0): ?>
                    <span class="cart-badge"><?= (int)$count ?></span>
                <?php endif; ?>
            </a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="mainNav">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>
<?php $flash = flash_get(); if ($flash): ?>
<div class="container" style="padding-top:20px;">
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= e($flash['message']) ?></div>
</div>
<?php endif; ?>
