<?php
$page_title = 'Finalizar compra';
require_once __DIR__ . '/includes/init.php';

$items = cart_items();
if (!$items) {
    redirect(base_url('carrito.php'));
}

$subtotal = cart_subtotal();
$tax_rate = (float)get_setting('tax_rate', 0);
$tax = $subtotal * ($tax_rate / 100);
$total = $subtotal + $tax;
$errors = [];

$conekta_enabled = get_setting('conekta_enabled', '0') === '1';
$conekta_card_enabled = get_setting('conekta_card_enabled', '1') === '1';
$conekta_oxxo_enabled = get_setting('conekta_oxxo_enabled', '1') === '1';
$conekta_spei_enabled = get_setting('conekta_spei_enabled', '1') === '1';

$stripe_enabled = get_setting('stripe_enabled', '0') === '1';
$stripe_card_enabled = get_setting('stripe_card_enabled', '1') === '1';
$stripe_oxxo_enabled = get_setting('stripe_oxxo_enabled', '0') === '1';
$stripe_spei_enabled = get_setting('stripe_spei_enabled', '0') === '1';

$stripe_method_types = [];
$stripe_label_parts = [];
if ($stripe_card_enabled) { $stripe_method_types[] = 'card'; $stripe_label_parts[] = 'tarjeta, Apple Pay, Google Pay'; }
if ($stripe_oxxo_enabled) { $stripe_method_types[] = 'oxxo'; $stripe_label_parts[] = 'OXXO'; }
if ($stripe_spei_enabled) { $stripe_method_types[] = 'customer_balance'; $stripe_label_parts[] = 'SPEI'; }
$stripe_label = '💳 Stripe (' . implode(', ', $stripe_label_parts) . ')';

$payment_options = [];
if ($stripe_enabled && $stripe_method_types) {
    $payment_options[] = ['value' => 'stripe_card', 'label' => $stripe_label];
}
if ($conekta_enabled) {
    if ($conekta_card_enabled) { $payment_options[] = ['value' => 'card', 'label' => '💳 Tarjeta (Conekta)']; }
    if ($conekta_oxxo_enabled) { $payment_options[] = ['value' => 'oxxo_cash', 'label' => '🏪 OXXO Pay']; }
    if ($conekta_spei_enabled) { $payment_options[] = ['value' => 'spei', 'label' => '🏦 Transferencia SPEI']; }
}
$real_gateway_active = !empty($payment_options);
$site_base_url = site_base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, por favor intenta de nuevo.';
    }
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');

    if ($name === '') { $errors[] = 'Por favor ingresa tu nombre.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Por favor ingresa un correo válido.'; }

    $allowed_methods = array_column($payment_options, 'value');
    $payment_method = $_POST['payment_method'] ?? ($allowed_methods[0] ?? 'card');
    if ($real_gateway_active && !in_array($payment_method, $allowed_methods, true)) {
        $payment_method = $allowed_methods[0];
    }
    if (!$real_gateway_active) {
        $payment_method = 'card';
    }

    // Datos que varían según la pasarela usada.
    $order_status = 'paid';
    $payment_gateway = 'demo';
    $conekta_order_id = null;
    $stripe_session_id = null;
    $payment_reference = null;
    $payment_expires_at = null;
    $redirect_after = null; // si se define, se redirige aquí en vez de a gracias.php
    $skip_confirmation_email = false;

    if (!$errors && $payment_method === 'stripe_card') {
        $payment_gateway = 'stripe';
        $order_code = generate_order_code();
        $success_url = $site_base_url . base_url('gracias.php?codigo=' . urlencode($order_code) . '&session_id={CHECKOUT_SESSION_ID}');
        $cancel_url = $site_base_url . base_url('checkout.php');

        $line_items = [];
        foreach ($items as $item) {
            $line_items[] = [
                'name' => $item['product']['name'],
                'unit_amount' => (int)round($item['product']['price'] * 100),
                'quantity' => (int)$item['qty'],
            ];
        }
        if ($tax > 0) {
            $line_items[] = ['name' => 'Impuestos', 'unit_amount' => (int)round($tax * 100), 'quantity' => 1];
        }

        $metadata = [
            'pedido' => $order_code,
            'cliente' => $name,
            'productos' => implode(', ', array_map(function ($i) { return $i['product']['name']; }, $items)),
        ];

        $result = stripe_create_checkout_session(
            $email, $line_items, $success_url, $cancel_url, $order_code, $stripe_method_types,
            $metadata, stripe_payment_description($order_code, $name)
        );
        if (!$result['ok']) {
            $errors[] = 'No se pudo iniciar el pago con Stripe: ' . $result['error'];
        } else {
            $order_status = 'pending';
            $stripe_session_id = $result['data']['id'];
            $redirect_after = $result['data']['url'];
            $skip_confirmation_email = true;
        }
    } elseif (!$errors && $payment_method !== 'stripe_card' && $conekta_enabled && $real_gateway_active) {
        $payment_gateway = 'conekta';
        $order_code = generate_order_code();
        $line_items = [];
        foreach ($items as $item) {
            $line_items[] = [
                'name' => mb_substr($item['product']['name'], 0, 250),
                'unit_price' => (int)round($item['product']['price'] * 100),
                'quantity' => (int)$item['qty'],
            ];
        }
        if ($tax > 0) {
            $line_items[] = ['name' => 'Impuestos', 'unit_price' => (int)round($tax * 100), 'quantity' => 1];
        }

        if ($payment_method === 'card') {
            $token_id = trim($_POST['conekta_token_id'] ?? '');
            if ($token_id === '') {
                $errors[] = 'No se pudo procesar tu tarjeta. Verifica los datos e intenta de nuevo.';
                $charge_payload = null;
            } else {
                $charge_payload = ['type' => 'card', 'token_id' => $token_id];
            }
        } else {
            $charge_payload = ['type' => $payment_method, 'expires_at' => time() + (3 * 86400)];
        }

        if (!$errors) {
            $result = conekta_create_order($name, $email, $line_items, $charge_payload);
            if (!$result['ok']) {
                $errors[] = 'No se pudo procesar el pago: ' . $result['error'];
            } else {
                $conekta_order = $result['data'];
                $conekta_order_id = $conekta_order['id'] ?? null;
                $charge = $conekta_order['charges']['data'][0] ?? [];
                $charge_status = $charge['status'] ?? 'pending_payment';
                $order_status = $charge_status === 'paid' ? 'paid' : 'pending';

                if ($payment_method === 'oxxo_cash' && !empty($charge['payment_method']['reference'])) {
                    $payment_reference = $charge['payment_method']['reference'];
                } elseif ($payment_method === 'spei' && !empty($charge['payment_method']['clabe'])) {
                    $payment_reference = $charge['payment_method']['clabe'];
                }
                if (!empty($charge['payment_method']['expires_at'])) {
                    $payment_expires_at = date('Y-m-d H:i:s', (int)$charge['payment_method']['expires_at']);
                }
            }
        }
    } else {
        $payment_method = 'card';
        $order_code = generate_order_code();
    }

    if (!$errors) {
        $db = get_db();
        $db->beginTransaction();
        try {
            $download_token = bin2hex(random_bytes(24));

            $stmt = $db->prepare(
                'INSERT INTO orders (order_code, customer_name, customer_email, subtotal, tax, total, status,
                 download_token, payment_method, payment_gateway, conekta_order_id, stripe_session_id,
                 payment_reference, payment_expires_at)
                 VALUES (:code, :name, :email, :subtotal, :tax, :total, :status,
                 :token, :payment_method, :payment_gateway, :conekta_order_id, :stripe_session_id,
                 :payment_reference, :payment_expires_at)'
            );
            $stmt->execute([
                'code' => $order_code,
                'name' => $name,
                'email' => $email,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => $order_status,
                'token' => $download_token,
                'payment_method' => $payment_method === 'stripe_card' ? 'card' : $payment_method,
                'payment_gateway' => $payment_gateway,
                'conekta_order_id' => $conekta_order_id,
                'stripe_session_id' => $stripe_session_id,
                'payment_reference' => $payment_reference,
                'payment_expires_at' => $payment_expires_at,
            ]);
            $order_id = $db->lastInsertId();

            $item_stmt = $db->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, quantity)
                 VALUES (:order_id, :product_id, :product_name, :price, :quantity)'
            );
            foreach ($items as $item) {
                $item_stmt->execute([
                    'order_id' => $order_id,
                    'product_id' => $item['product']['id'],
                    'product_name' => $item['product']['name'],
                    'price' => $item['product']['price'],
                    'quantity' => $item['qty'],
                ]);
            }

            $db->commit();
            cart_clear();

            if (!$skip_confirmation_email) {
                // Se relee de la BD (en vez de reusar las variables locales) para que el correo
                // tenga el id, download_token y created_at reales, necesarios para armar los
                // enlaces de descarga por archivo.
                $order_stmt = $db->prepare('SELECT * FROM orders WHERE id = :id');
                $order_stmt->execute(['id' => $order_id]);
                $order_row = $order_stmt->fetch();

                if ($order_status === 'paid') {
                    $order_items_stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
                    $order_items_stmt->execute(['order_id' => $order_id]);
                    send_order_confirmation_email($order_row, $order_items_stmt->fetchAll());
                } else {
                    send_payment_pending_email($order_row, $payment_method);
                }
            }

            redirect($redirect_after ?: base_url('gracias.php?codigo=' . urlencode($order_code)));
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Ocurrió un problema al procesar tu pedido. Intenta de nuevo.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width:700px;">
        <h1 class="section-title">Finalizar compra</h1>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <div class="card-box">
            <h3>Resumen de tu pedido</h3>
            <?php foreach ($items as $item): ?>
                <div class="cart-summary-row">
                    <span><?= e($item['product']['name']) ?> x<?= (int)$item['qty'] ?></span>
                    <span><?= format_price($item['line_total']) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="cart-summary-row" style="font-weight:700;font-size:18px;border-top:1px solid rgba(75,68,83,0.1);padding-top:12px;margin-top:12px;">
                <span>Total a pagar</span><span><?= format_price($total) ?></span>
            </div>
        </div>

        <form method="post" class="card-box" style="margin-top:24px;" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
                <label for="customer_name">Nombre completo</label>
                <input type="text" id="customer_name" name="customer_name" class="form-control" value="<?= e($_POST['customer_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="customer_email">Correo electrónico</label>
                <input type="email" id="customer_email" name="customer_email" class="form-control" value="<?= e($_POST['customer_email'] ?? '') ?>" required>
            </div>

            <?php if ($real_gateway_active): ?>
                <input type="hidden" name="conekta_token_id" id="conekta_token_id" value="">
                <div class="form-group">
                    <label>Método de pago</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php foreach ($payment_options as $i => $opt): ?>
                            <label class="payment-option"><input type="radio" name="payment_method" value="<?= e($opt['value']) ?>" <?= $i === 0 ? 'checked' : '' ?>> <?= e($opt['label']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($conekta_enabled && $conekta_card_enabled): ?>
                <div id="cardFields">
                    <div class="form-group">
                        <label for="card-number">Número de tarjeta</label>
                        <input type="text" id="card-number" class="form-control" placeholder="4242 4242 4242 4242" inputmode="numeric" autocomplete="cc-number">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="card-name">Nombre en la tarjeta</label>
                            <input type="text" id="card-name" class="form-control" autocomplete="cc-name">
                        </div>
                        <div class="form-group">
                            <label for="card-cvc">CVC</label>
                            <input type="text" id="card-cvc" class="form-control" placeholder="123" inputmode="numeric" autocomplete="cc-csc">
                        </div>
                        <div class="form-group">
                            <label for="card-exp-month">Mes de expiración</label>
                            <input type="text" id="card-exp-month" class="form-control" placeholder="MM" inputmode="numeric" autocomplete="cc-exp-month">
                        </div>
                        <div class="form-group">
                            <label for="card-exp-year">Año de expiración</label>
                            <input type="text" id="card-exp-year" class="form-control" placeholder="AAAA" inputmode="numeric" autocomplete="cc-exp-year">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($conekta_enabled && ($conekta_oxxo_enabled || $conekta_spei_enabled)): ?>
                <div id="cashNotice" class="digital-notice" style="display:none;">
                    Al confirmar, te mostraremos los datos para completar tu pago. Tu pedido se activará automáticamente en cuanto se confirme.
                </div>
                <?php endif; ?>
                <?php if ($stripe_enabled): ?>
                <div id="stripeNotice" class="digital-notice" style="display:none;">
                    Al confirmar, te llevaremos a la página segura de Stripe para completar tu pago con tarjeta.
                </div>
                <?php endif; ?>
                <div id="checkoutError" class="alert alert-error" style="display:none;"></div>

                <button type="submit" class="btn btn-primary btn-block" id="checkoutSubmitBtn">PAGAR <?= format_price($total) ?></button>

                <?php if ($conekta_enabled && $conekta_card_enabled): ?>
                <script src="https://cdn.conekta.io/js/latest/conekta.js"></script>
                <script>Conekta.setPublicKey('<?= e(get_setting('conekta_public_key')) ?>');</script>
                <?php endif; ?>
                <script>
                (function () {
                    var form = document.getElementById('checkoutForm');
                    var submitBtn = document.getElementById('checkoutSubmitBtn');
                    var errorBox = document.getElementById('checkoutError');
                    var cardFields = document.getElementById('cardFields');
                    var cashNotice = document.getElementById('cashNotice');
                    var stripeNotice = document.getElementById('stripeNotice');
                    var radios = document.querySelectorAll('input[name="payment_method"]');
                    var tokenInput = document.getElementById('conekta_token_id');

                    function toggleFields() {
                        var checked = form.querySelector('input[name="payment_method"]:checked');
                        var method = checked ? checked.value : 'card';
                        if (cardFields) { cardFields.style.display = method === 'card' ? 'block' : 'none'; }
                        if (cashNotice) { cashNotice.style.display = (method === 'oxxo_cash' || method === 'spei') ? 'block' : 'none'; }
                        if (stripeNotice) { stripeNotice.style.display = method === 'stripe_card' ? 'block' : 'none'; }
                    }
                    radios.forEach(function (r) { r.addEventListener('change', toggleFields); });
                    toggleFields();

                    function showError(msg) {
                        errorBox.textContent = msg;
                        errorBox.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'PAGAR <?= e(format_price($total)) ?>';
                    }

                    function conektaSuccess(token) {
                        tokenInput.value = token.id;
                        form.submit();
                    }
                    function conektaError(response) {
                        showError(response.message_to_purchaser || response.message || 'No se pudo procesar tu tarjeta.');
                    }

                    form.addEventListener('submit', function (e) {
                        var checked = form.querySelector('input[name="payment_method"]:checked');
                        var method = checked ? checked.value : 'card';
                        if (method === 'card' && typeof Conekta !== 'undefined' && !tokenInput.value) {
                            e.preventDefault();
                            errorBox.style.display = 'none';
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'Procesando...';
                            Conekta.Token.create({
                                card: {
                                    number: document.getElementById('card-number').value.replace(/\s+/g, ''),
                                    name: document.getElementById('card-name').value,
                                    cvc: document.getElementById('card-cvc').value,
                                    exp_month: document.getElementById('card-exp-month').value,
                                    exp_year: document.getElementById('card-exp-year').value,
                                }
                            }, conektaSuccess, conektaError);
                        } else if (method === 'stripe_card') {
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'Redirigiendo a Stripe...';
                        }
                    });
                })();
                </script>
            <?php else: ?>
                <p style="font-size:13px;opacity:0.7;">Este es un checkout de demostración: tu pedido se marcará como pagado automáticamente para que puedas probar la descarga.</p>
                <button type="submit" class="btn btn-primary btn-block">PAGAR <?= format_price($total) ?></button>
            <?php endif; ?>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
