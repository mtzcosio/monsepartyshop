<?php
/** Espera una variable $service en scope. */
?>
<div class="product-card">
    <div class="product-image">
        <?php if (!empty($service['image'])): ?>
            <img src="<?= e(upload_url($service['image'])) ?>" alt="<?= e($service['name']) ?>">
        <?php else: ?>
            🎉
        <?php endif; ?>
    </div>
    <div class="product-info">
        <span class="product-name"><?= e($service['name']) ?></span>
        <?php if ($service['short_description']): ?>
            <p style="font-size:13.5px;opacity:0.8;margin:0 0 10px;"><?= e($service['short_description']) ?></p>
        <?php endif; ?>
        <div class="product-price-row">
            <span class="product-price"><?= $service['price_from'] !== null ? 'Desde ' . format_price($service['price_from']) : 'Precio a cotizar' ?></span>
        </div>
        <a href="<?= base_url('servicio.php?slug=' . urlencode($service['slug'])) ?>" class="btn btn-secondary btn-sm btn-block">VER SERVICIO</a>
    </div>
</div>
