<?php
$store_name = get_setting('store_name', 'Monse Party Shop');
$instagram = get_setting('instagram', '');
$facebook = get_setting('facebook', '');
$tiktok = get_setting('tiktok', '');
$whatsapp = get_setting('whatsapp', '');
?>
<footer class="site-footer">
    <div class="footer-grid">
        <div class="footer-col">
            <div class="footer-logo"><?= e($store_name) ?></div>
            <p>Plantillas digitales para celebrar momentos especiales.</p>
            <div class="footer-social">
                <?php if ($instagram): ?><a href="<?= e($instagram) ?>" target="_blank" rel="noopener">IG</a><?php endif; ?>
                <?php if ($facebook): ?><a href="<?= e($facebook) ?>" target="_blank" rel="noopener">FB</a><?php endif; ?>
                <?php if ($tiktok): ?><a href="<?= e($tiktok) ?>" target="_blank" rel="noopener">TT</a><?php endif; ?>
                <?php if ($whatsapp): ?><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">WA</a><?php endif; ?>
            </div>
        </div>
        <div class="footer-col">
            <h4>Enlaces</h4>
            <ul>
                <li><a href="<?= base_url('index.php') ?>">Inicio</a></li>
                <li><a href="<?= base_url('productos.php') ?>">Plantillas</a></li>
                <li><a href="<?= base_url('index.php#categorias') ?>">Categorías</a></li>
                <li><a href="<?= base_url('como-funciona.php') ?>">Cómo funciona</a></li>
                <li><a href="<?= base_url('contacto.php') ?>">Contacto</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Legal</h4>
            <ul>
                <li><a href="<?= base_url('rastrear-pedido.php') ?>">Rastrear mi pedido</a></li>
                <li><a href="<?= base_url('terminos.php') ?>">Términos y condiciones</a></li>
                <li><a href="<?= base_url('privacidad.php') ?>">Política de privacidad</a></li>
                <li><a href="<?= base_url('politica-descargas.php') ?>">Política de descargas</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> <?= e($store_name) ?>. Todos los derechos reservados.
    </div>
</footer>
<script src="<?= asset_url('assets/js/main.js') ?>"></script>
</body>
</html>
