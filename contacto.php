<?php
$page_title = 'Contacto';
require_once __DIR__ . '/includes/init.php';

/*
 * Sello de tiempo para detectar envíos "demasiado rápidos" (indicio de bot):
 * se fija en sesión al cargar la página (GET) y se compara en el POST, sin
 * confiar en ningún valor que venga del propio formulario/cliente.
 */
if (empty($_SESSION['contact_form_ts'])) {
    $_SESSION['contact_form_ts'] = time();
}

$reason_options = contact_reason_options();
$preferred_options = contact_preferred_contact_options();

$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$errors = [];
$success = false;
$success_message = '';

$posted = [
    'full_name' => '', 'email' => '', 'phone' => '', 'company' => '',
    'reason' => '', 'message' => '', 'preferred_contact' => '',
];

// Prellenar el formulario cuando se llega desde "Solicitar cotización" en servicio.php.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_GET['servicio'])) {
    $service_stmt = get_db()->prepare("SELECT name FROM services WHERE slug = :slug AND status = 'active' LIMIT 1");
    $service_stmt->execute(['slug' => $_GET['servicio']]);
    $service_name = $service_stmt->fetchColumn();
    if ($service_name) {
        $posted['reason'] = 'cotizacion';
        $posted['message'] = 'Me interesa el servicio: ' . $service_name . '. ';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted['full_name'] = trim($_POST['full_name'] ?? '');
    $posted['email'] = trim($_POST['email'] ?? '');
    $posted['phone'] = trim($_POST['phone'] ?? '');
    $posted['company'] = trim($_POST['company'] ?? '');
    $posted['reason'] = trim($_POST['reason'] ?? '');
    $posted['message'] = trim($_POST['message'] ?? '');
    $posted['preferred_contact'] = trim($_POST['preferred_contact'] ?? '');
    $privacy_accepted = isset($_POST['privacy_accept']);
    // Campo trampa anti-spam: invisible para personas (ver .hp-field en el CSS),
    // los bots que autocompletan formularios suelen rellenarlo igual.
    $honeypot = trim($_POST['website'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors['_form'] = 'Tu sesión expiró. Recarga la página e intenta de nuevo.';
    }
    if ($posted['full_name'] === '') {
        $errors['full_name'] = 'Ingresa tu nombre completo.';
    }
    if (!filter_var($posted['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ingresa un correo electrónico válido.';
    }
    if ($posted['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $posted['phone'])) {
        $errors['phone'] = 'Ingresa un teléfono válido (solo números, espacios y + - ( )).';
    }
    if (!isset($reason_options[$posted['reason']])) {
        $errors['reason'] = 'Selecciona un motivo de contacto.';
    }
    if (mb_strlen($posted['message']) < 10) {
        $errors['message'] = 'Cuéntanos un poco más sobre tu solicitud (mínimo 10 caracteres).';
    }
    if ($posted['preferred_contact'] !== '' && !isset($preferred_options[$posted['preferred_contact']])) {
        $errors['preferred_contact'] = 'Selecciona un medio de contacto válido.';
    }
    if (!$privacy_accepted) {
        $errors['privacy_accept'] = 'Debes aceptar el aviso de privacidad para continuar.';
    }

    /*
     * Protección contra spam sin depender de un servicio externo de CAPTCHA:
     * 1) honeypot -> si el campo trampa viene lleno, se descarta en silencio
     *    (se simula éxito para no delatar la trampa).
     * 2) envío demasiado rápido tras cargar la página -> típico de un bot.
     * 3) límite de mensajes por IP en una ventana corta -> evita flood.
     */
    $is_honeypot_spam = ($honeypot !== '');
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $too_fast = (time() - (int)$_SESSION['contact_form_ts']) < 3;
    if (!$errors && !$is_honeypot_spam && $too_fast) {
        $errors['_form'] = 'Tu mensaje se envió demasiado rápido. Intenta de nuevo.';
    }

    if (!$errors && !$is_honeypot_spam && recaptcha_is_enabled()) {
        $captcha_result = recaptcha_verify($_POST['g-recaptcha-response'] ?? '', $client_ip);
        if (!$captcha_result['ok']) {
            $errors['recaptcha'] = $captcha_result['error'];
        }
    }

    if (!$errors && !$is_honeypot_spam) {
        $rate_stmt = get_db()->prepare(
            'SELECT COUNT(*) FROM contact_messages WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $rate_stmt->execute(['ip' => $client_ip]);
        if ((int)$rate_stmt->fetchColumn() >= 3) {
            $errors['_form'] = 'Has enviado varios mensajes en poco tiempo. Espera unos minutos e intenta de nuevo.';
        }
    }

    if ($is_honeypot_spam) {
        $success = true;
        $success_message = '¡Gracias por escribirnos! Te responderemos muy pronto. 💕';
    } elseif (!$errors) {
        try {
            $stmt = get_db()->prepare(
                'INSERT INTO contact_messages (full_name, email, phone, company, reason, message, preferred_contact, ip_address)
                 VALUES (:full_name, :email, :phone, :company, :reason, :message, :preferred_contact, :ip_address)'
            );
            $stmt->execute([
                'full_name' => $posted['full_name'],
                'email' => $posted['email'],
                'phone' => $posted['phone'],
                'company' => $posted['company'],
                'reason' => $posted['reason'],
                'message' => $posted['message'],
                'preferred_contact' => $posted['preferred_contact'],
                'ip_address' => $client_ip,
            ]);
            $success = true;
            $success_message = '¡Gracias por escribirnos! Te responderemos muy pronto. 💕';
            unset($_SESSION['contact_form_ts']);
        } catch (Exception $e) {
            $errors['_form'] = 'Ocurrió un problema al enviar tu mensaje. Intenta de nuevo en unos minutos.';
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $success ? $success_message : ($errors['_form'] ?? 'Revisa los campos marcados e intenta de nuevo.'),
            'errors' => $errors,
        ]);
        exit;
    }
}

// Cada campo se puede ocultar del portal del cliente desde Configuración → Contacto
// (checkbox "Mostrar en la tienda") sin borrar el valor guardado; por eso se resuelve
// a '' cuando está desactivado, en vez de solo leer el valor crudo del setting.
$store_email = get_setting('show_contact_email', '1') === '1' ? get_setting('email', '') : '';
$store_phone = get_setting('show_contact_phone', '1') === '1' ? get_setting('phone', '') : '';
$store_whatsapp = get_setting('show_contact_whatsapp', '1') === '1' ? get_setting('whatsapp', '') : '';
$store_hours = get_setting('show_contact_business_hours', '1') === '1' ? get_setting('business_hours', '') : '';
$store_address = get_setting('show_contact_address', '1') === '1' ? get_setting('address', '') : '';
$store_instagram = get_setting('show_contact_instagram', '1') === '1' ? get_setting('instagram', '') : '';
$store_facebook = get_setting('show_contact_facebook', '1') === '1' ? get_setting('facebook', '') : '';
$store_tiktok = get_setting('show_contact_tiktok', '1') === '1' ? get_setting('tiktok', '') : '';
$recaptcha_enabled = recaptcha_is_enabled();
$recaptcha_site_key = get_setting('recaptcha_site_key', '');

require_once __DIR__ . '/includes/header.php';
?>

<section class="section contact-section">
    <div class="container">
        <div class="contact-header text-center">
            <h1 class="section-title">Contáctanos</h1>
            <p class="section-subtitle">Escríbenos y te respondemos lo antes posible.</p>
        </div>

        <div class="contact-grid">
            <!-- Columna izquierda: información de contacto (administrable desde el panel) -->
            <div class="contact-info">
                <div class="card-box">
                    <h3 class="mt-0">Información de contacto</h3>
                    <ul class="contact-info-list">
                        <?php if ($store_email): ?>
                        <li>
                            <span class="contact-info-icon" aria-hidden="true">📧</span>
                            <div>
                                <strong>Correo</strong>
                                <a href="mailto:<?= e($store_email) ?>"><?= e($store_email) ?></a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($store_phone): ?>
                        <li>
                            <span class="contact-info-icon" aria-hidden="true">📞</span>
                            <div>
                                <strong>Teléfono</strong>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $store_phone)) ?>"><?= e($store_phone) ?></a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($store_whatsapp): ?>
                        <li>
                            <span class="contact-info-icon" aria-hidden="true">💬</span>
                            <div>
                                <strong>WhatsApp</strong>
                                <a href="https://wa.me/<?= e($store_whatsapp) ?>" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($store_hours): ?>
                        <li>
                            <span class="contact-info-icon" aria-hidden="true">🕒</span>
                            <div>
                                <strong>Horario de atención</strong>
                                <span><?= nl2br(e($store_hours)) ?></span>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($store_address): ?>
                        <li>
                            <span class="contact-info-icon" aria-hidden="true">📍</span>
                            <div>
                                <strong>Dirección</strong>
                                <span><?= nl2br(e($store_address)) ?></span>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if (!$store_email && !$store_phone && !$store_whatsapp && !$store_hours && !$store_address): ?>
                        <li><span>Aún no se ha configurado la información de contacto en el panel administrativo.</span></li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($store_instagram || $store_facebook || $store_tiktok): ?>
                    <div class="contact-social">
                        <strong>Síguenos</strong>
                        <div class="contact-social-links">
                            <?php if ($store_instagram): ?><a href="<?= e($store_instagram) ?>" target="_blank" rel="noopener" aria-label="Instagram">IG</a><?php endif; ?>
                            <?php if ($store_facebook): ?><a href="<?= e($store_facebook) ?>" target="_blank" rel="noopener" aria-label="Facebook">FB</a><?php endif; ?>
                            <?php if ($store_tiktok): ?><a href="<?= e($store_tiktok) ?>" target="_blank" rel="noopener" aria-label="TikTok">TT</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Columna derecha: formulario de contacto -->
            <div class="contact-form-wrap">
                <div class="card-box">
                    <div id="contactAlert" class="alert" role="alert" hidden></div>

                    <?php if ($success && !$is_ajax): ?>
                        <div class="alert alert-success" role="alert"><?= e($success_message) ?></div>
                    <?php else: ?>
                        <?php if (!empty($errors['_form'])): ?>
                            <div class="alert alert-error" role="alert"><?= e($errors['_form']) ?></div>
                        <?php endif; ?>

                        <form method="post" id="contactForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                            <!-- Campo trampa para bots: no lo llenes, se oculta con CSS (.hp-field). -->
                            <div class="hp-field" aria-hidden="true">
                                <label for="website">No llenar este campo</label>
                                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <p class="contact-form-section-title">Tus datos</p>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="full_name">Nombre completo <span class="required">*</span></label>
                                    <input type="text" id="full_name" name="full_name" class="form-control<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" value="<?= e($posted['full_name']) ?>" required aria-describedby="err_full_name">
                                    <span class="field-error" id="err_full_name"><?= e($errors['full_name'] ?? '') ?></span>
                                </div>
                                <div class="form-group">
                                    <label for="email">Correo electrónico <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" value="<?= e($posted['email']) ?>" required aria-describedby="err_email">
                                    <span class="field-error" id="err_email"><?= e($errors['email'] ?? '') ?></span>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Teléfono</label>
                                    <input type="tel" id="phone" name="phone" class="form-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" value="<?= e($posted['phone']) ?>" placeholder="55 1234 5678" aria-describedby="err_phone">
                                    <span class="field-error" id="err_phone"><?= e($errors['phone'] ?? '') ?></span>
                                </div>
                                <div class="form-group">
                                    <label for="company">Empresa</label>
                                    <input type="text" id="company" name="company" class="form-control" value="<?= e($posted['company']) ?>">
                                </div>
                            </div>

                            <p class="contact-form-section-title">Tu mensaje</p>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="reason">Motivo de contacto <span class="required">*</span></label>
                                    <select id="reason" name="reason" class="form-control<?= isset($errors['reason']) ? ' is-invalid' : '' ?>" required aria-describedby="err_reason">
                                        <option value="">Selecciona una opción</option>
                                        <?php foreach ($reason_options as $value => $label): ?>
                                            <option value="<?= e($value) ?>" <?= $posted['reason'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="field-error" id="err_reason"><?= e($errors['reason'] ?? '') ?></span>
                                </div>
                                <div class="form-group">
                                    <label>Medio de contacto</label>
                                    <div class="radio-pill-group">
                                        <?php foreach ($preferred_options as $value => $label): ?>
                                            <label class="radio-pill">
                                                <input type="radio" name="preferred_contact" value="<?= e($value) ?>" <?= $posted['preferred_contact'] === $value ? 'checked' : '' ?>>
                                                <?= e($label) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="message">Mensaje <span class="required">*</span></label>
                                <textarea id="message" name="message" class="form-control<?= isset($errors['message']) ? ' is-invalid' : '' ?>" rows="4" required aria-describedby="err_message"><?= e($posted['message']) ?></textarea>
                                <span class="field-error" id="err_message"><?= e($errors['message'] ?? '') ?></span>
                            </div>

                            <?php if ($recaptcha_enabled): ?>
                            <div class="form-group">
                                <div class="g-recaptcha" data-sitekey="<?= e($recaptcha_site_key) ?>"></div>
                                <span class="field-error" id="err_recaptcha"><?= e($errors['recaptcha'] ?? '') ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="privacy_accept" id="privacy_accept" value="1" required aria-describedby="err_privacy_accept">
                                    He leído y acepto el <a href="<?= base_url('privacidad.php') ?>" target="_blank">Aviso de Privacidad</a>.
                                </label>
                                <span class="field-error" id="err_privacy_accept"><?= e($errors['privacy_accept'] ?? '') ?></span>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block" id="contactSubmitBtn">
                                <span class="btn-text">ENVIAR MENSAJE</span>
                                <span class="btn-spinner" hidden></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($recaptcha_enabled): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<script>
(function () {
    var form = document.getElementById('contactForm');
    if (!form) { return; }

    var alertBox = document.getElementById('contactAlert');
    var submitBtn = document.getElementById('contactSubmitBtn');
    var btnText = submitBtn.querySelector('.btn-text');
    var btnSpinner = submitBtn.querySelector('.btn-spinner');
    var recaptchaEnabled = <?= $recaptcha_enabled ? 'true' : 'false' ?>;

    var PHONE_RE = /^[0-9+\-\s()]{7,20}$/;
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var FIELD_NAMES = ['full_name', 'email', 'phone', 'reason', 'message', 'privacy_accept', 'recaptcha'];

    function setFieldError(name, msg) {
        var el = document.getElementById('err_' + name);
        var input = form.elements[name];
        if (el) { el.textContent = msg || ''; }
        if (input && input.classList) { input.classList.toggle('is-invalid', !!msg); }
    }

    function clearErrors() {
        FIELD_NAMES.forEach(function (n) { setFieldError(n, ''); });
    }

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.hidden = false;
    }

    function validateClient() {
        var errs = {};
        var fullName = form.full_name.value.trim();
        var email = form.email.value.trim();
        var phone = form.phone.value.trim();
        var reason = form.reason.value;
        var message = form.message.value.trim();
        var privacyOk = form.privacy_accept.checked;

        if (!fullName) { errs.full_name = 'Ingresa tu nombre completo.'; }
        if (!EMAIL_RE.test(email)) { errs.email = 'Ingresa un correo electrónico válido.'; }
        if (phone && !PHONE_RE.test(phone)) { errs.phone = 'Ingresa un teléfono válido (solo números, espacios y + - ( )).'; }
        if (!reason) { errs.reason = 'Selecciona un motivo de contacto.'; }
        if (message.length < 10) { errs.message = 'Cuéntanos un poco más sobre tu solicitud (mínimo 10 caracteres).'; }
        if (!privacyOk) { errs.privacy_accept = 'Debes aceptar el aviso de privacidad para continuar.'; }
        if (recaptchaEnabled && typeof grecaptcha !== 'undefined' && grecaptcha.getResponse().length === 0) {
            errs.recaptcha = 'Confirma que no eres un robot.';
        }
        return errs;
    }

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        btnText.hidden = isLoading;
        btnSpinner.hidden = !isLoading;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
        alertBox.hidden = true;

        var errs = validateClient();
        var errorKeys = Object.keys(errs);
        if (errorKeys.length) {
            errorKeys.forEach(function (k) { setFieldError(k, errs[k]); });
            showAlert('error', 'Revisa los campos marcados e intenta de nuevo.');
            var firstInput = form.elements[errorKeys[0]];
            if (firstInput) { (firstInput.length ? firstInput[0] : firstInput).focus(); }
            return;
        }

        setLoading(true);
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                setLoading(false);
                if (data.success) {
                    form.hidden = true;
                    showAlert('success', data.message);
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function (k) {
                            if (k !== '_form') { setFieldError(k, data.errors[k]); }
                        });
                    }
                    showAlert('error', data.message || 'Revisa los campos marcados e intenta de nuevo.');
                    // El token de reCAPTCHA es de un solo uso: si el envío falló (por lo
                    // que sea), hay que resetear el widget para que pueda volver a resolverlo.
                    if (recaptchaEnabled && typeof grecaptcha !== 'undefined') { grecaptcha.reset(); }
                }
            })
            .catch(function () {
                setLoading(false);
                showAlert('error', 'No se pudo enviar tu mensaje. Verifica tu conexión e intenta de nuevo.');
                if (recaptchaEnabled && typeof grecaptcha !== 'undefined') { grecaptcha.reset(); }
            });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
