<?php
/** Espera una variable $product en scope. */
$discount = 0;
if (!empty($product['old_price']) && $product['old_price'] > $product['price']) {
    $discount = round((1 - ($product['price'] / $product['old_price'])) * 100);
}
?>
<div class="product-card">
    <div class="product-image">
        <div class="product-badges">
            <?php if (!empty($product['is_new'])): ?><span class="badge badge-nuevo">Nuevo</span><?php endif; ?>
            <?php if (!empty($product['is_bestseller'])): ?><span class="badge badge-vendido">Más vendido</span><?php endif; ?>
            <?php if (!empty($product['is_kit'])): ?><span class="badge badge-kit">Kit</span><?php endif; ?>
            <?php if (!empty($product['is_offer'])): ?><span class="badge badge-oferta">Oferta</span><?php endif; ?>
        </div>
        <?php if (!empty($product['image'])): ?>
            <img src="<?= e(upload_url($product['image'])) ?>" alt="<?= e($product['name']) ?>">
        <?php else: ?>
            🎉
        <?php endif; ?>
    </div>
    <div class="product-info">
        <?php if (!empty($product['category_name'])): ?>
            <span class="product-category"><?= e($product['category_name']) ?></span>
        <?php endif; ?>
        <span class="product-name"><?= e($product['name']) ?></span>
        <div class="product-price-row">
            <span class="product-price"><?= format_price($product['price']) ?></span>
            <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                <span class="product-old-price"><?= format_price($product['old_price']) ?></span>
                <span class="product-discount">-<?= $discount ?>%</span>
            <?php endif; ?>
        </div>
        <a href="<?= base_url('producto.php?slug=' . urlencode($product['slug'])) ?>" class="btn btn-secondary btn-sm btn-block">VER PRODUCTO</a>
    </div>
</div>
