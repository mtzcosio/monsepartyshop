<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/media_picker.php';

$fields = [
    'store_name', 'store_description', 'site_url', 'email', 'phone', 'whatsapp',
    'business_hours', 'address',
    'instagram', 'facebook', 'tiktok', 'tax_rate', 'currency',
    'download_expiration', 'max_downloads',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption',
    'conekta_public_key', 'stripe_publishable_key', 'recaptcha_site_key',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            set_setting($field, trim($_POST[$field]));
        }
    }
    set_setting('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
    if (!empty($_POST['smtp_password'])) {
        set_setting('smtp_password', $_POST['smtp_password']);
    }
    set_setting('conekta_enabled', isset($_POST['conekta_enabled']) ? '1' : '0');
    set_setting('conekta_card_enabled', isset($_POST['conekta_card_enabled']) ? '1' : '0');
    set_setting('conekta_oxxo_enabled', isset($_POST['conekta_oxxo_enabled']) ? '1' : '0');
    set_setting('conekta_spei_enabled', isset($_POST['conekta_spei_enabled']) ? '1' : '0');
    if (!empty($_POST['conekta_private_key'])) {
        set_setting('conekta_private_key', trim($_POST['conekta_private_key']));
    }
    set_setting('stripe_enabled', isset($_POST['stripe_enabled']) ? '1' : '0');
    set_setting('stripe_card_enabled', isset($_POST['stripe_card_enabled']) ? '1' : '0');
    set_setting('stripe_oxxo_enabled', isset($_POST['stripe_oxxo_enabled']) ? '1' : '0');
    set_setting('stripe_spei_enabled', isset($_POST['stripe_spei_enabled']) ? '1' : '0');
    if (!empty($_POST['stripe_secret_key'])) {
        set_setting('stripe_secret_key', trim($_POST['stripe_secret_key']));
    }
    if (!empty($_POST['stripe_webhook_secret'])) {
        set_setting('stripe_webhook_secret', trim($_POST['stripe_webhook_secret']));
    }
    set_setting('recaptcha_enabled', isset($_POST['recaptcha_enabled']) ? '1' : '0');
    if (!empty($_POST['recaptcha_secret_key'])) {
        set_setting('recaptcha_secret_key', trim($_POST['recaptcha_secret_key']));
    }
    // Visibilidad por campo de la información de contacto en el portal del cliente (contacto.php).
    foreach (['email', 'phone', 'whatsapp', 'business_hours', 'address', 'instagram', 'facebook', 'tiktok'] as $contact_field) {
        set_setting('show_contact_' . $contact_field, isset($_POST['show_contact_' . $contact_field]) ? '1' : '0');
    }
    foreach (['primary_color', 'secondary_color', 'accent_color'] as $color_field) {
        if (!empty($_POST[$color_field])) {
            set_setting($color_field, $_POST[$color_field]);
        }
    }

    foreach (['logo' => 'uploads/products', 'favicon' => 'uploads/products'] as $file_field => $dir) {
        if (!empty($_FILES[$file_field]['name'])) {
            $ext = strtolower(pathinfo($_FILES[$file_field]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'], true)) {
                $filename = $file_field . '_' . uniqid() . '.' . $ext;
                $dest = __DIR__ . '/../' . $dir . '/' . $filename;
                if (move_uploaded_file($_FILES[$file_field]['tmp_name'], $dest)) {
                    set_setting($file_field, base_url($dir . '/' . $filename));
                }
            }
        } elseif (!empty($_POST[$file_field . '_media_url'])) {
            // Elegido desde la Biblioteca de archivos en vez de subir un archivo nuevo.
            set_setting($file_field, trim($_POST[$file_field . '_media_url']));
        }
    }

    flash_set('Configuración guardada.');
    redirect(admin_url('settings.php'));
}

$page_title = 'Configuración';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Configuración de la marca</h1>
        <p class="page-subtitle">Identidad, colores, contacto, ventas, correo y pagos — todo en un solo lugar.</p>
    </div>
</div>

<form method="post" enctype="multipart/form-data" class="card-box settings-tabs">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="settings-tabs-nav">
        <button type="button" class="tab-btn active" data-tab="tab-identidad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            Identidad
        </button>
        <button type="button" class="tab-btn" data-tab="tab-colores">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.32 0z"/></svg>
            Colores
        </button>
        <button type="button" class="tab-btn" data-tab="tab-contacto">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Contacto
        </button>
        <button type="button" class="tab-btn" data-tab="tab-ventas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            Ventas
        </button>
        <button type="button" class="tab-btn" data-tab="tab-correo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Correo
        </button>
        <button type="button" class="tab-btn" data-tab="tab-pagos">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Pagos
        </button>
        <button type="button" class="tab-btn" data-tab="tab-plantillas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Plantillas de correo
        </button>
        <button type="button" class="tab-btn" data-tab="tab-biblioteca">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            Biblioteca de archivos
        </button>
    </div>

    <div id="tab-identidad" class="tab-panel active">
        <div class="form-grid">
            <div class="form-group">
                <label for="store_name">Nombre de la tienda</label>
                <input type="text" id="store_name" name="store_name" class="form-control" value="<?= e(get_setting('store_name')) ?>">
            </div>
            <div class="form-group">
                <label for="store_description">Descripción / propuesta de valor</label>
                <input type="text" id="store_description" name="store_description" class="form-control" value="<?= e(get_setting('store_description')) ?>">
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="site_url">URL del sitio (producción)</label>
                <input type="text" id="site_url" name="site_url" class="form-control" value="<?= e(get_setting('site_url')) ?>" placeholder="https://www.tudominio.com">
                <p class="settings-hint" style="margin-bottom:0;">
                    Déjalo vacío para usar automáticamente el dominio de cada visita (cómodo en desarrollo/XAMPP).
                    En producción, configúralo con tu dominio real: así los enlaces de correo, de rastreo de pedido y de pago
                    no dependen del encabezado <code>Host</code> que envía el navegador del cliente (que podría manipularse).
                </p>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="logo">Logo</label>
                <?php if (get_setting('logo')): ?><img src="<?= e(get_setting('logo')) ?>" style="max-height:50px;margin-bottom:8px;"><?php endif; ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*" style="flex:1;min-width:180px;">
                    <?php render_media_picker_field('logo', 'Elegir de la biblioteca'); ?>
                </div>
            </div>
            <div class="form-group">
                <label for="favicon">Favicon</label>
                <?php if (get_setting('favicon')): ?><img src="<?= e(get_setting('favicon')) ?>" style="max-height:32px;margin-bottom:8px;"><?php endif; ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="file" id="favicon" name="favicon" class="form-control" accept="image/*" style="flex:1;min-width:180px;">
                    <?php render_media_picker_field('favicon', 'Elegir de la biblioteca'); ?>
                </div>
            </div>
        </div>
    </div>

    <div id="tab-colores" class="tab-panel">
        <div class="form-grid">
            <div class="form-group">
                <label for="primary_color">Color primario</label>
                <div class="color-field">
                    <input type="color" class="color-swatch-input" aria-label="Selector visual del color primario" value="<?= e(get_setting('primary_color', '#FF6F91')) ?>">
                    <input type="text" id="primary_color" name="primary_color" class="color-hex-input" value="<?= e(strtoupper(get_setting('primary_color', '#FF6F91'))) ?>" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label for="secondary_color">Color secundario</label>
                <div class="color-field">
                    <input type="color" class="color-swatch-input" aria-label="Selector visual del color secundario" value="<?= e(get_setting('secondary_color', '#FFC75F')) ?>">
                    <input type="text" id="secondary_color" name="secondary_color" class="color-hex-input" value="<?= e(strtoupper(get_setting('secondary_color', '#FFC75F'))) ?>" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label for="accent_color">Color de acento</label>
                <div class="color-field">
                    <input type="color" class="color-swatch-input" aria-label="Selector visual del color de acento" value="<?= e(get_setting('accent_color', '#845EC2')) ?>">
                    <input type="text" id="accent_color" name="accent_color" class="color-hex-input" value="<?= e(strtoupper(get_setting('accent_color', '#845EC2'))) ?>" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false" autocomplete="off">
                </div>
            </div>
        </div>
        <p class="settings-hint">Nota: los colores se guardan y quedan disponibles para aplicarlos como variables CSS de la tienda.</p>
    </div>

    <div id="tab-contacto" class="tab-panel">
        <p class="settings-hint" style="margin-top:0;">Desmarca "Mostrar en la tienda" en cualquier campo para ocultarlo de la página pública de Contacto, aunque lo dejes lleno aquí.</p>
        <div class="form-grid">
            <div class="form-group">
                <label for="email" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>Correo de contacto</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_email" <?= get_setting('show_contact_email', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e(get_setting('email')) ?>">
            </div>
            <div class="form-group">
                <label for="phone" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>Teléfono</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_phone" <?= get_setting('show_contact_phone', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e(get_setting('phone')) ?>">
            </div>
            <div class="form-group">
                <label for="whatsapp" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>WhatsApp (con código de país, sin +)</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_whatsapp" <?= get_setting('show_contact_whatsapp', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <input type="text" id="whatsapp" name="whatsapp" class="form-control" value="<?= e(get_setting('whatsapp')) ?>" placeholder="5215512345678">
            </div>
            <div class="form-group">
                <label for="business_hours" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>Horario de atención</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_business_hours" <?= get_setting('show_contact_business_hours', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <textarea id="business_hours" name="business_hours" class="form-control" rows="2" placeholder="Lunes a viernes, 9:00 - 18:00"><?= e(get_setting('business_hours')) ?></textarea>
            </div>
            <div class="form-group">
                <label for="address" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>Dirección (opcional)</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_address" <?= get_setting('show_contact_address', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <textarea id="address" name="address" class="form-control" rows="2" placeholder="Solo si atiendes en un punto físico"><?= e(get_setting('address')) ?></textarea>
            </div>
            <div class="form-group">
                <label for="instagram" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>Instagram (URL)</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_instagram" <?= get_setting('show_contact_instagram', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <input type="text" id="instagram" name="instagram" class="form-control" value="<?= e(get_setting('instagram')) ?>">
            </div>
            <div class="form-group">
                <label for="facebook" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>Facebook (URL)</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_facebook" <?= get_setting('show_contact_facebook', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <input type="text" id="facebook" name="facebook" class="form-control" value="<?= e(get_setting('facebook')) ?>">
            </div>
            <div class="form-group">
                <label for="tiktok" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span>TikTok (URL)</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:400;font-size:13px;white-space:nowrap;"><input type="checkbox" name="show_contact_tiktok" <?= get_setting('show_contact_tiktok', '1') === '1' ? 'checked' : '' ?>> Mostrar en la tienda</span>
                </label>
                <input type="text" id="tiktok" name="tiktok" class="form-control" value="<?= e(get_setting('tiktok')) ?>">
            </div>
        </div>

        <div class="settings-subcard" style="margin-top:22px;">
            <h4>Protección contra spam (reCAPTCHA)</h4>
            <p class="settings-hint">
                Agrega la casilla "No soy un robot" de <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">Google reCAPTCHA v2</a> al formulario de contacto, para bloquear envíos automatizados por bots.
                Registra tu dominio ahí (elige "reCAPTCHA v2" → "Casilla de verificación") y pega las dos llaves que te den. Mientras esté deshabilitado, el formulario sigue protegido con las validaciones automáticas (campo trampa, límite de envíos), solo sin la casilla visible.
            </p>
            <div class="form-group">
                <label style="font-weight:400;"><input type="checkbox" name="recaptcha_enabled" <?= get_setting('recaptcha_enabled', '0') === '1' ? 'checked' : '' ?>> Mostrar la casilla reCAPTCHA en el formulario de contacto</label>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="recaptcha_site_key">Clave del sitio (site key)</label>
                    <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" class="form-control" value="<?= e(get_setting('recaptcha_site_key')) ?>">
                </div>
                <div class="form-group">
                    <label for="recaptcha_secret_key">Clave secreta (secret key)</label>
                    <input type="password" id="recaptcha_secret_key" name="recaptcha_secret_key" class="form-control" placeholder="<?= get_setting('recaptcha_secret_key') ? '••••••••  (dejar en blanco para mantenerla)' : '' ?>" autocomplete="new-password">
                </div>
            </div>
        </div>
    </div>

    <div id="tab-ventas" class="tab-panel">
        <div class="form-grid">
            <div class="form-group">
                <label for="currency">Moneda</label>
                <input type="text" id="currency" name="currency" class="form-control" value="<?= e(get_setting('currency', 'MXN')) ?>">
            </div>
            <div class="form-group">
                <label for="tax_rate">Impuesto (%)</label>
                <input type="number" step="0.01" id="tax_rate" name="tax_rate" class="form-control" value="<?= e(get_setting('tax_rate', 0)) ?>">
            </div>
            <div class="form-group">
                <label for="download_expiration">Días de vigencia de descarga</label>
                <input type="number" id="download_expiration" name="download_expiration" class="form-control" value="<?= e(get_setting('download_expiration', 30)) ?>">
            </div>
            <div class="form-group">
                <label for="max_downloads">Máximo de descargas por producto</label>
                <input type="number" id="max_downloads" name="max_downloads" class="form-control" value="<?= e(get_setting('max_downloads', 5)) ?>">
            </div>
        </div>
    </div>

    <div id="tab-correo" class="tab-panel">
        <p class="settings-hint" style="margin-top:0;">
            Configura estos datos para que los correos (confirmación de compra, etc.) se envíen de forma confiable.
            Si lo dejas deshabilitado, se usará la función <code>mail()</code> del servidor, que en muchos hostings no llega correctamente.
        </p>
        <div class="form-group">
            <label style="font-weight:400;"><input type="checkbox" name="smtp_enabled" <?= get_setting('smtp_enabled', '0') === '1' ? 'checked' : '' ?>> Habilitar envío por SMTP</label>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="smtp_host">Servidor SMTP (host)</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="<?= e(get_setting('smtp_host')) ?>" placeholder="smtp.hostinger.com">
            </div>
            <div class="form-group">
                <label for="smtp_port">Puerto</label>
                <input type="number" id="smtp_port" name="smtp_port" class="form-control" value="<?= e(get_setting('smtp_port', '587')) ?>" placeholder="587">
            </div>
            <div class="form-group">
                <label for="smtp_encryption">Cifrado</label>
                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                    <?php $enc = get_setting('smtp_encryption', 'tls'); ?>
                    <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS (recomendado, puerto 587)</option>
                    <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL (puerto 465)</option>
                    <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>Ninguno</option>
                </select>
            </div>
            <div class="form-group">
                <label for="smtp_username">Usuario SMTP</label>
                <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="<?= e(get_setting('smtp_username')) ?>" placeholder="hola@monsepartyshop.com">
            </div>
            <div class="form-group">
                <label for="smtp_password">Contraseña SMTP</label>
                <input type="password" id="smtp_password" name="smtp_password" class="form-control" placeholder="<?= get_setting('smtp_password') ? '••••••••  (dejar en blanco para mantenerla)' : '' ?>" autocomplete="new-password">
            </div>
        </div>

        <div class="settings-subcard" style="margin-top:22px;">
            <h4>Probar envío de correo</h4>
            <p class="settings-hint" style="margin-bottom:14px;">Guarda primero la configuración de arriba, luego envía un correo de prueba para confirmar que todo funciona.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0;">
                    <label for="test_email">Enviar correo de prueba a</label>
                    <input type="email" id="test_email" name="test_email" class="form-control" placeholder="tu-correo@ejemplo.com" form="test-email-form" required>
                </div>
                <button type="submit" form="test-email-form" class="btn btn-secondary btn-sm">ENVIAR PRUEBA</button>
            </div>
        </div>
    </div>

    <div id="tab-pagos" class="tab-panel">
        <div class="settings-subcard">
            <h4>Conekta</h4>
            <p class="settings-hint">
                Conecta tu cuenta de <a href="https://www.conekta.com" target="_blank" rel="noopener">Conekta</a> para cobrar con tarjeta, OXXO Pay y SPEI.
                Obtén tus llaves en el panel de Conekta → Desarrollo → Llaves API. Mientras esté deshabilitado, el checkout queda en modo de prueba (pedidos marcados como pagados automáticamente, sin cobrar de verdad).
            </p>
            <div class="form-group">
                <label style="font-weight:400;"><input type="checkbox" name="conekta_enabled" <?= get_setting('conekta_enabled', '0') === '1' ? 'checked' : '' ?>> Habilitar cobros reales con Conekta</label>
            </div>
            <div class="form-group">
                <label style="font-weight:400;display:block;margin-bottom:6px;">Métodos de pago dentro de Conekta:</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="conekta_card_enabled" <?= get_setting('conekta_card_enabled', '1') === '1' ? 'checked' : '' ?>> Tarjeta</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="conekta_oxxo_enabled" <?= get_setting('conekta_oxxo_enabled', '1') === '1' ? 'checked' : '' ?>> OXXO Pay</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="conekta_spei_enabled" <?= get_setting('conekta_spei_enabled', '1') === '1' ? 'checked' : '' ?>> SPEI</label>
                <p class="settings-hint" style="margin-top:6px;margin-bottom:0;">Desmarca los que no quieras ofrecer al cliente. Debes dejar al menos uno activo para que Conekta aparezca como opción de pago.</p>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="conekta_public_key">Llave pública</label>
                    <input type="text" id="conekta_public_key" name="conekta_public_key" class="form-control" value="<?= e(get_setting('conekta_public_key')) ?>" placeholder="key_... o pk_...">
                </div>
                <div class="form-group">
                    <label for="conekta_private_key">Llave privada</label>
                    <input type="password" id="conekta_private_key" name="conekta_private_key" class="form-control" placeholder="<?= get_setting('conekta_private_key') ? '••••••••  (dejar en blanco para mantenerla)' : 'sk_...' ?>" autocomplete="new-password">
                </div>
            </div>
            <p class="settings-hint" style="margin-bottom:0;">
                URL del webhook para configurar en Conekta (Desarrollo → Webhooks):<br>
                <code><?= e(site_base_url() . base_url('webhook-conekta.php')) ?></code>
            </p>
        </div>

        <div class="settings-subcard">
            <h4>Stripe</h4>
            <p class="settings-hint">
                Conecta tu cuenta de <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">Stripe</a> para cobrar con tarjeta mediante Stripe Checkout (página de pago alojada por Stripe).
                Obtén tus llaves en el panel de Stripe → Desarrolladores → Claves de API. Puedes tener Stripe y Conekta activos a la vez: el cliente elegirá con cuál pagar.
            </p>
            <div class="form-group">
                <label style="font-weight:400;"><input type="checkbox" name="stripe_enabled" <?= get_setting('stripe_enabled', '0') === '1' ? 'checked' : '' ?>> Habilitar cobros reales con Stripe</label>
            </div>
            <div class="form-group">
                <label style="font-weight:400;display:block;margin-bottom:6px;">Métodos de pago dentro de Stripe:</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="stripe_card_enabled" <?= get_setting('stripe_card_enabled', '1') === '1' ? 'checked' : '' ?>> Tarjeta (incluye Apple Pay y Google Pay)</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="stripe_oxxo_enabled" <?= get_setting('stripe_oxxo_enabled', '0') === '1' ? 'checked' : '' ?>> OXXO</label>
                <label style="display:inline-block;margin-right:16px;font-weight:400;"><input type="checkbox" name="stripe_spei_enabled" <?= get_setting('stripe_spei_enabled', '0') === '1' ? 'checked' : '' ?>> SPEI</label>
                <p class="settings-hint" style="margin-top:6px;margin-bottom:0;">Solo actives OXXO/SPEI si ya están habilitados en tu cuenta de Stripe (Panel de Stripe → Configuración → Métodos de pago), o el pago fallará. Debes dejar al menos uno activo para que Stripe aparezca como opción de pago.</p>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="stripe_publishable_key">Llave publicable</label>
                    <input type="text" id="stripe_publishable_key" name="stripe_publishable_key" class="form-control" value="<?= e(get_setting('stripe_publishable_key')) ?>" placeholder="pk_test_... o pk_live_...">
                </div>
                <div class="form-group">
                    <label for="stripe_secret_key">Llave secreta</label>
                    <input type="password" id="stripe_secret_key" name="stripe_secret_key" class="form-control" placeholder="<?= get_setting('stripe_secret_key') ? '••••••••  (dejar en blanco para mantenerla)' : 'sk_test_... o sk_live_...' ?>" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="stripe_webhook_secret">Secreto del webhook</label>
                    <input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" class="form-control" placeholder="<?= get_setting('stripe_webhook_secret') ? '••••••••  (dejar en blanco para mantenerlo)' : 'whsec_...' ?>" autocomplete="new-password">
                </div>
            </div>
            <p class="settings-hint" style="margin-bottom:0;">
                URL del webhook para configurar en Stripe (Desarrolladores → Webhooks → Agregar endpoint, evento <code>checkout.session.completed</code>):<br>
                <code><?= e(site_base_url() . base_url('webhook-stripe.php')) ?></code><br>
                Stripe te dará un "secreto de firma" (<code>whsec_...</code>) al crear el endpoint — pégalo arriba.
            </p>
        </div>
    </div>

    <div id="tab-plantillas" class="tab-panel">
        <div class="settings-subcard" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
            <div style="display:flex;align-items:center;gap:16px;min-width:220px;">
                <div class="stat-icon purple" style="flex-shrink:0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div>
                    <h4 style="margin:0;">Plantillas de correo</h4>
                    <p class="settings-hint" style="margin:2px 0 0;">Crea y administra plantillas reutilizables con variables dinámicas para confirmaciones, pagos, recordatorios y notificaciones.</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= admin_url('email_variables.php') ?>" class="btn btn-secondary btn-sm" style="white-space:nowrap;">Variables</a>
                <a href="<?= admin_url('email_templates.php') ?>" class="btn btn-primary btn-sm" style="white-space:nowrap;">Gestionar plantillas →</a>
            </div>
        </div>

        <?php
        require_once __DIR__ . '/includes/email_variables.php';
        $tpl_counts_stmt = get_db()->query("SELECT status, COUNT(*) c FROM email_templates GROUP BY status");
        $tpl_counts = ['active' => 0, 'draft' => 0, 'inactive' => 0];
        foreach ($tpl_counts_stmt->fetchAll() as $row) { $tpl_counts[$row['status']] = (int)$row['c']; }
        $tpl_total = array_sum($tpl_counts);
        ?>
        <div class="stat-grid" style="margin-top:20px;margin-bottom:0;">
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= $tpl_total ?></div>
                    <div class="stat-label">Plantillas totales</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= $tpl_counts['active'] ?></div>
                    <div class="stat-label">Activas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= $tpl_counts['draft'] ?></div>
                    <div class="stat-label">Borradores</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= $tpl_counts['inactive'] ?></div>
                    <div class="stat-label">Inactivas</div>
                </div>
            </div>
        </div>
    </div>

    <div id="tab-biblioteca" class="tab-panel">
        <div class="settings-subcard" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
            <div style="display:flex;align-items:center;gap:16px;min-width:220px;">
                <div class="stat-icon purple" style="flex-shrink:0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                </div>
                <div>
                    <h4 style="margin:0;">Biblioteca de archivos</h4>
                    <p class="settings-hint" style="margin:2px 0 0;">Repositorio centralizado de imágenes y archivos, reutilizables en productos, plantillas y configuración sin volver a subirlos.</p>
                </div>
            </div>
            <a href="<?= admin_url('media_library.php') ?>" class="btn btn-primary btn-sm" style="white-space:nowrap;">Gestionar biblioteca →</a>
        </div>

        <?php
        require_once __DIR__ . '/includes/media_library.php';
        $media_total = (int)get_db()->query('SELECT COUNT(*) FROM media_library')->fetchColumn();
        $media_size = (int)get_db()->query('SELECT COALESCE(SUM(file_size),0) FROM media_library')->fetchColumn();
        $media_categories = count(media_category_names());
        ?>
        <div class="stat-grid" style="margin-top:20px;margin-bottom:0;">
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= $media_total ?></div>
                    <div class="stat-label">Archivos guardados</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= media_human_filesize($media_size) ?></div>
                    <div class="stat-label">Espacio utilizado</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-body">
                    <div class="stat-value"><?= $media_categories ?></div>
                    <div class="stat-label">Categorías</div>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-actions" style="justify-content:flex-start;border-top:1px solid var(--admin-border);margin-top:28px;padding-top:22px;">
        <button type="submit" class="btn btn-primary">GUARDAR CONFIGURACIÓN</button>
    </div>
</form>

<form method="post" id="test-email-form" action="<?= admin_url('test_email.php') ?>" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
