<?php
$page_title = 'Tu carrito';
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['update'])) {
        foreach ($_POST['qty'] as $product_id => $qty) {
            cart_update($product_id, $qty);
        }
        flash_set('Carrito actualizado.');
    } elseif (isset($_POST['remove'])) {
        cart_remove($_POST['remove']);
        flash_set('Producto eliminado del carrito.');
    }
    redirect(base_url('carrito.php'));
}

require_once __DIR__ . '/includes/header.php';
$items = cart_items();
$subtotal = cart_subtotal();
$tax_rate = (float)get_setting('tax_rate', 0);
$tax = $subtotal * ($tax_rate / 100);
$total = $subtotal + $tax;
?>

<section class="section">
    <div class="container">
        <h1 class="section-title">Tu carrito</h1>

        <?php if (!$items): ?>
            <div class="cart-empty">
                <p>Tu carrito está vacío. ¡Es momento de encontrar algo especial! 💕</p>
                <a href="<?= base_url('productos.php') ?>" class="btn btn-primary">EXPLORAR PLANTILLAS</a>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="overflow-x:auto;">
                <table class="cart-table">
                    <thead>
                        <tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): $p = $item['product']; ?>
                        <tr>
                            <td class="cart-item-name"><?= e($p['name']) ?></td>
                            <td><?= format_price($p['price']) ?></td>
                            <td><input type="number" min="1" class="qty-input" name="qty[<?= (int)$p['id'] ?>]" value="<?= (int)$item['qty'] ?>"></td>
                            <td><?= format_price($item['line_total']) ?></td>
                            <td><button type="submit" name="remove" value="<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Quitar</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div style="margin:20px 0;">
                    <button type="submit" name="update" class="btn btn-secondary btn-sm">Actualizar carrito</button>
                </div>
            </form>

            <div class="cart-summary">
                <div class="cart-summary-row"><span>Subtotal</span><span><?= format_price($subtotal) ?></span></div>
                <?php if ($tax_rate > 0): ?>
                <div class="cart-summary-row"><span>Impuestos (<?= e($tax_rate) ?>%)</span><span><?= format_price($tax) ?></span></div>
                <?php endif; ?>
                <div class="cart-summary-row" style="font-weight:700;font-size:18px;"><span>Total</span><span><?= format_price($total) ?></span></div>
                <a href="<?= base_url('checkout.php') ?>" class="btn btn-primary btn-block">CONTINUAR AL PAGO</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
