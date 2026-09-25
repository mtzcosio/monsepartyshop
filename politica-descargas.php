<?php
$page_title = 'Política de descargas';
require_once __DIR__ . '/includes/header.php';
$expiration_days = (int)get_setting('download_expiration', 30);
$max_downloads = (int)get_setting('max_downloads', 5);
?>
<section class="section">
    <div class="container" style="max-width:800px;">
        <h1 class="section-title">Política de descargas</h1>
        <div class="card-box">
            <p>¡Tu plantilla está lista para ti apenas confirmamos tu pago! Aquí te contamos cómo funcionan tus descargas.</p>
            <h3>Tiempo disponible</h3>
            <p>Podrás descargar tus plantillas durante <?= $expiration_days ?> días después de tu compra. Después de ese periodo, el enlace dejará de funcionar.</p>
            <h3>Número de descargas</h3>
            <p>Cada plantilla puede descargarse hasta <?= $max_downloads ?> veces, para que puedas guardar una copia de respaldo sin problema.</p>
            <h3>¿Perdiste tu enlace?</h3>
            <p>No te preocupes, escríbenos desde nuestra página de <a href="<?= base_url('contacto.php') ?>">contacto</a> y con gusto te ayudamos a recuperarlo.</p>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
