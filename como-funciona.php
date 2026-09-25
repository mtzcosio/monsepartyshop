<?php
$page_title = 'Cómo funciona';
require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <h1 class="section-title text-center">¿Cómo funciona?</h1>
        <p class="section-subtitle">Crear algo especial nunca fue tan fácil.</p>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">01</div>
                <div class="step-title">ELIGE</div>
                <p>Encuentra la plantilla perfecta para tu evento.</p>
            </div>
            <div class="step-card">
                <div class="step-number">02</div>
                <div class="step-title">COMPRA</div>
                <p>Realiza tu pago de forma segura.</p>
            </div>
            <div class="step-card">
                <div class="step-number">03</div>
                <div class="step-title">DESCARGA</div>
                <p>Recibe tu acceso inmediatamente después de confirmar tu pago.</p>
            </div>
            <div class="step-card">
                <div class="step-number">04</div>
                <div class="step-title">CREA</div>
                <p>Imprime, arma y disfruta tu creación.</p>
            </div>
        </div>
        <div class="text-center" style="margin-top:48px;">
            <a href="<?= base_url('productos.php') ?>" class="btn btn-primary">EXPLORAR PLANTILLAS</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
