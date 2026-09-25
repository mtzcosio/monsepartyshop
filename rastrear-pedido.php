<?php
$page_title = 'Rastrear mi pedido';
require_once __DIR__ . '/includes/init.php';

$errors = [];
$order_code_input = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Tu sesión expiró, por favor intenta de nuevo.';
    }

    $order_code_input = trim($_POST['order_code'] ?? '');
    $email_input = trim($_POST['email'] ?? '');

    if ($order_code_input === '') { $errors[] = 'Ingresa el número de tu pedido.'; }
    if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Ingresa el correo con el que hiciste la compra.'; }

    if (!$errors) {
        $stmt = get_db()->prepare(
            'SELECT order_code FROM orders WHERE order_code = :code AND LOWER(customer_email) = LOWER(:email) LIMIT 1'
        );
        $stmt->execute(['code' => $order_code_input, 'email' => $email_input]);
        $order = $stmt->fetch();

        if ($order) {
            redirect(base_url('gracias.php?codigo=' . urlencode($order['order_code'])));
        }
        $errors[] = 'No encontramos ningún pedido con ese número y correo. Verifica los datos e intenta de nuevo.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width:600px;">
        <h1 class="section-title text-center">Rastrear mi pedido</h1>
        <p class="section-subtitle">Ingresa tu número de pedido y el correo con el que compraste para volver a ver tus plantillas.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" class="card-box">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
                <label for="order_code">Número de pedido</label>
                <input type="text" id="order_code" name="order_code" class="form-control" placeholder="MPS-XXXXXXXX" value="<?= e($order_code_input) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email_input) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">BUSCAR MI PEDIDO</button>
            <p style="font-size:13px;opacity:0.7;margin:14px 0 0;">Encuentras tu número de pedido en el correo de confirmación de compra.</p>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
