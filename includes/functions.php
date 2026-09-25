<?php
require_once __DIR__ . '/settings.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_price($amount) {
    $currency = get_setting('currency', 'MXN');
    return '$' . number_format((float)$amount, 0) . ' ' . $currency;
}

function slugify($text) {
    $text = preg_replace('/[áàäâ]/u', 'a', $text);
    $text = preg_replace('/[éèëê]/u', 'e', $text);
    $text = preg_replace('/[íìïî]/u', 'i', $text);
    $text = preg_replace('/[óòöô]/u', 'o', $text);
    $text = preg_replace('/[úùüû]/u', 'u', $text);
    $text = preg_replace('/[ñ]/u', 'n', $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Base absoluta (esquema + host) para armar URLs completas en correos y redirecciones
 * de pasarela. Si el admin configuró "site_url" en Configuración, se usa tal cual
 * (recomendado en producción: evita depender del header Host, que un cliente podría
 * falsificar para inyectar un dominio ajeno en links de correo/Stripe). Sin configurar,
 * cae a esquema+host de la petición actual (cómodo en desarrollo/XAMPP).
 */
function site_base_url() {
    $configured = trim(get_setting('site_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function base_url($path = '') {
    static $base_path = null;
    if ($base_path === null) {
        $project_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc_root = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $base_path = '';
        if ($doc_root && strpos($project_root, $doc_root) === 0) {
            $base_path = substr($project_root, strlen($doc_root));
        }
        $base_path = rtrim($base_path, '/');
    }
    return $base_path . '/' . ltrim($path, '/');
}

/**
 * URL para mostrar un archivo subido (imagen de producto/servicio, logo, favicon...)
 * cuyo valor se guarda en BD como ruta relativa "pura" (ej. "uploads/products/x.jpg",
 * igual que ya hace media_library.file_path), sin el prefijo de entorno de base_url()
 * horneado adentro. Así la ruta guardada sigue funcionando igual si el proyecto vive
 * en la raíz del document root (hosting) o en una subcarpeta (XAMPP). Si el valor ya
 * es una URL absoluta (http/https), se regresa tal cual.
 */
function upload_url($path) {
    if ($path === null || $path === '') { return ''; }
    if (preg_match('#^https?://#i', $path)) { return $path; }
    return base_url($path);
}

function flash_set($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function generate_order_code() {
    return 'MPS-' . strtoupper(bin2hex(random_bytes(4)));
}

function star_rating_html($rating) {
    $rating = (float)$rating;
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = str_repeat('★', (int)$full);
    if ($half) { $html .= '½'; }
    $html .= str_repeat('☆', (int)$empty);
    return $html;
}

/**
 * Correo de "tu compra está lista": arma los botones de descarga reales (uno por
 * archivo comprado) y los pasa como variables a send_templated_email(), que busca
 * la plantilla activa con código COMPRA_LISTA en el admin; si no hay ninguna
 * publicada, usa el HTML por defecto de email_default_order_ready_html() como
 * respaldo, para que el correo automático siempre funcione.
 *
 * $order debe ser la fila completa de `orders` (id, download_token, created_at, etc.)
 * y $items las filas de `order_items` de ese pedido.
 */
function send_order_confirmation_email($order, $items) {
    $files_stmt = get_db()->prepare('SELECT * FROM product_files WHERE product_id = :product_id ORDER BY sort_order ASC');
    foreach ($items as &$item) {
        $files_stmt->execute(['product_id' => $item['product_id']]);
        $item['files'] = $files_stmt->fetchAll();
    }
    unset($item);

    $data = order_ready_email_variable_data($order, $items);

    return send_templated_email(
        'COMPRA_LISTA',
        $order['customer_email'],
        $data,
        '🎉 ¡Tu compra está lista!',
        email_default_order_ready_html($data)
    );
}

/**
 * Datos ({{variable}} => valor) para el correo de "compra lista", combinando el
 * catálogo de variables del sistema de plantillas con los datos reales del pedido.
 * $items debe traer ya la clave 'files' por cada item (ver send_order_confirmation_email()).
 */
function order_ready_email_variable_data($order, $items) {
    $store_name = get_setting('store_name', 'Monse Party Shop');
    $primary_color = get_setting('primary_color', '#FF6F91');
    $expiration_days = (int)get_setting('download_expiration', 30);
    $created_at = $order['created_at'] ?? 'now';

    $name_parts = preg_split('/\s+/', trim($order['customer_name']));
    $first_name = $name_parts[0] ?? $order['customer_name'];
    $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';

    $product_names = [];
    $buttons_html = '';
    foreach ($items as $item) {
        $product_names[] = $item['product_name'];

        if (empty($item['files'])) {
            $buttons_html .= '<p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#8a8195;margin:0 0 14px;">'
                . e($item['product_name']) . ' — archivo en preparación.</p>';
            continue;
        }

        $buttons_html .= '<p style="font-family:Arial,Helvetica,sans-serif;font-weight:700;margin:18px 0 8px;color:#2c2438;">'
            . e($item['product_name']) . '</p><p style="text-align:center;margin:0 0 10px;">';
        foreach ($item['files'] as $file) {
            $download_url = site_base_url() . base_url(
                'descargar.php?token=' . urlencode($order['download_token']) . '&item=' . (int)$item['id'] . '&file=' . (int)$file['id']
            );
            $label = $file['label'] ? mb_strtoupper($file['label']) : 'MI PLANTILLA';
            $buttons_html .= '<a href="' . e($download_url) . '" style="background:' . e($primary_color)
                . ';color:#ffffff;padding:12px 26px;border-radius:999px;text-decoration:none;font-weight:bold;'
                . 'display:inline-block;margin:4px;font-family:Arial,Helvetica,sans-serif;font-size:14px;">DESCARGAR '
                . e($label) . '</a>';
        }
        $buttons_html .= '</p>';
    }

    return [
        'nombre' => e($first_name),
        'apellido' => e($last_name),
        'nombre_completo' => e($order['customer_name']),
        'correo' => e($order['customer_email']),
        'telefono' => '',
        'codigo_confirmacion' => $order['order_code'],
        'estado_confirmacion' => 'Pagado',
        'fecha_confirmacion' => date('d/m/Y', strtotime($created_at)),
        'url_confirmacion' => site_base_url() . base_url('gracias.php?codigo=' . urlencode($order['order_code'])),
        'nombre_empresa' => $store_name,
        'correo_soporte' => get_setting('email', 'hola@monsepartyshop.com'),
        'telefono_soporte' => get_setting('phone', ''),
        'fecha_actual' => date('d/m/Y'),
        'total_pedido' => format_price($order['total']),
        'productos_pedido' => implode('<br>', array_map('e', $product_names)),
        'fecha_vencimiento_descarga' => date('d/m/Y', strtotime($created_at . ' +' . $expiration_days . ' days')),
        'botones_descarga' => $buttons_html,
        'url_rastreo_pedido' => site_base_url() . base_url('rastrear-pedido.php'),
    ];
}

/** HTML por defecto del correo de "compra lista", usado si no hay plantilla COMPRA_LISTA activa en el admin. */
function email_default_order_ready_html($data) {
    $store_name = get_setting('store_name', 'Monse Party Shop');
    $primary_color = get_setting('primary_color', '#FF6F91');
    $logo = get_setting('logo', '');
    $logo_url = $logo ? site_base_url() . base_url($logo) : '';
    $logo_html = $logo_url
        ? '<img src="' . e($logo_url) . '" alt="' . e($store_name) . '" style="max-height:44px;">'
        : '<span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:800;color:#ffffff;">' . e($store_name) . '</span>';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f6f3f7;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3f7;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">'
        . '<tr><td align="center" style="background:' . e($primary_color) . ';padding:26px 20px;">' . $logo_html . '</td></tr>'
        . '<tr><td style="padding:32px 30px 10px;">'
        . '<h1 style="margin:0 0 14px;font-size:22px;color:#2c2438;">¡Hola, ' . e($data['nombre_completo']) . '! 🎉</h1>'
        . '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#4b4453;">Gracias por comprar en <strong>' . e($store_name)
        . '</strong>. Tu pago ha sido confirmado y tus plantillas ya están listas para descargar.</p>'
        . '<p style="margin:0 0 20px;font-size:13px;color:#8a8195;">Número de pedido: <strong>' . e($data['codigo_confirmacion']) . '</strong></p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 30px 10px;">' . $data['botones_descarga'] . '</td></tr>'
        . '<tr><td style="padding:10px 30px 30px;">'
        . '<p style="margin:0;font-size:13px;color:#8a8195;line-height:1.6;">Podrás descargar tus plantillas hasta el <strong>'
        . e($data['fecha_vencimiento_descarga']) . '</strong>. Si tienes cualquier duda, escríbenos a <a href="mailto:'
        . e($data['correo_soporte']) . '" style="color:' . e($primary_color) . ';">' . e($data['correo_soporte']) . '</a>.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 30px 30px;">'
        . '<p style="margin:0;font-size:13px;color:#8a8195;line-height:1.6;">¿Necesitas volver a descargar tus plantillas más adelante? Guarda este correo o entra en cualquier momento a <a href="'
        . e($data['url_rastreo_pedido']) . '" style="color:' . e($primary_color) . ';font-weight:bold;">Rastrear mi pedido</a> con tu número de pedido (<strong>'
        . e($data['codigo_confirmacion']) . '</strong>) y el correo con el que compraste.</p>'
        . '</td></tr>'
        . '<tr><td align="center" style="padding:18px 20px;background:#f6f3f7;font-size:12px;color:#a79fb0;">¡Esperamos que disfrutes creando algo especial! 💕<br>'
        . e($store_name) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Correo de "instrucciones para completar tu pago" (OXXO/SPEI pendientes de
 * confirmar). Igual que send_order_confirmation_email(), pasa por
 * send_templated_email(): usa la plantilla activa con código PAGO_PENDIENTE si
 * existe, o el HTML por defecto de email_default_payment_pending_html() si no.
 */
function send_payment_pending_email($order, $payment_method) {
    $data = payment_pending_email_variable_data($order, $payment_method);

    return send_templated_email(
        'PAGO_PENDIENTE',
        $order['customer_email'],
        $data,
        '🧾 Instrucciones para completar tu pago',
        email_default_payment_pending_html($data)
    );
}

/**
 * Genera una Checkout Session de Stripe nueva y vigente para un pedido ya
 * existente que sigue 'pending' con pasarela Stripe (la sesión original pudo
 * haber expirado, ya que Stripe las vence a las 24h), y actualiza
 * orders.stripe_session_id con la nueva sesión para que el webhook y
 * gracias.php la reconozcan cuando el cliente pague. Devuelve la URL de pago,
 * o null si Stripe no está configurado o la llamada falla.
 */
function stripe_generate_pay_link_for_order($order) {
    $items_stmt = get_db()->prepare('SELECT * FROM order_items WHERE order_id = :id');
    $items_stmt->execute(['id' => $order['id']]);
    $order_items = $items_stmt->fetchAll();

    $line_items = [];
    foreach ($order_items as $item) {
        $line_items[] = [
            'name' => $item['product_name'],
            'unit_amount' => (int)round($item['price'] * 100),
            'quantity' => (int)$item['quantity'],
        ];
    }
    $tax = (float)($order['tax'] ?? 0);
    if ($tax > 0) {
        $line_items[] = ['name' => 'Impuestos', 'unit_amount' => (int)round($tax * 100), 'quantity' => 1];
    }
    if (!$line_items) {
        return null;
    }

    $stripe_method_types = [];
    if (get_setting('stripe_card_enabled', '1') === '1') { $stripe_method_types[] = 'card'; }
    if (get_setting('stripe_oxxo_enabled', '0') === '1') { $stripe_method_types[] = 'oxxo'; }
    if (get_setting('stripe_spei_enabled', '0') === '1') { $stripe_method_types[] = 'customer_balance'; }
    if (!$stripe_method_types) { $stripe_method_types = ['card']; }

    $base_url = site_base_url();
    $success_url = $base_url . base_url('gracias.php?codigo=' . urlencode($order['order_code']) . '&session_id={CHECKOUT_SESSION_ID}');
    $cancel_url = $base_url . base_url('checkout.php');

    $metadata = [
        'pedido' => $order['order_code'],
        'pedido_id' => (string)$order['id'],
        'cliente' => $order['customer_name'],
        'productos' => implode(', ', array_column($order_items, 'product_name')),
    ];

    $result = stripe_create_checkout_session(
        $order['customer_email'], $line_items, $success_url, $cancel_url, $order['order_code'], $stripe_method_types,
        $metadata, stripe_payment_description($order['order_code'], $order['customer_name'])
    );
    if (!$result['ok']) {
        return null;
    }

    get_db()->prepare('UPDATE orders SET stripe_session_id = :sid WHERE id = :id')
        ->execute(['sid' => $result['data']['id'], 'id' => $order['id']]);

    return $result['data']['url'];
}

/** Datos ({{variable}} => valor) para el correo de "instrucciones para completar tu pago". */
function payment_pending_email_variable_data($order, $payment_method) {
    $store_name = get_setting('store_name', 'Monse Party Shop');

    $name_parts = preg_split('/\s+/', trim($order['customer_name']));
    $first_name = $name_parts[0] ?? $order['customer_name'];
    $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';

    $method_labels = ['oxxo_cash' => 'OXXO Pay', 'spei' => 'Transferencia SPEI'];
    $method_label = $method_labels[$payment_method] ?? 'Tarjeta';

    // Para pedidos con pasarela Stripe, el CTA de pago debe ser un enlace real donde
    // el cliente pueda completar el cobro (a diferencia de OXXO/SPEI, que se pagan
    // fuera del sitio con solo la referencia). Si Stripe no responde, se cae al
    // enlace de siempre hacia gracias.php.
    $confirmation_url = site_base_url() . base_url('gracias.php?codigo=' . urlencode($order['order_code']));
    if (($order['payment_gateway'] ?? '') === 'stripe') {
        $pay_link = stripe_generate_pay_link_for_order($order);
        if ($pay_link) {
            $confirmation_url = $pay_link;
        }
    }

    return [
        'nombre' => e($first_name),
        'apellido' => e($last_name),
        'nombre_completo' => e($order['customer_name']),
        'correo' => e($order['customer_email']),
        'telefono' => '',
        'codigo_confirmacion' => $order['order_code'],
        'estado_confirmacion' => 'Pendiente de pago',
        'fecha_confirmacion' => date('d/m/Y'),
        'url_confirmacion' => $confirmation_url,
        'nombre_empresa' => $store_name,
        'correo_soporte' => get_setting('email', 'hola@monsepartyshop.com'),
        'telefono_soporte' => get_setting('phone', ''),
        'fecha_actual' => date('d/m/Y'),
        'total_pedido' => format_price($order['total']),
        'metodo_pago' => $method_label,
        'referencia_pago' => e((string)($order['payment_reference'] ?? '')),
        'fecha_vencimiento_pago' => !empty($order['payment_expires_at']) ? date('d/m/Y H:i', strtotime($order['payment_expires_at'])) : '',
        'url_rastreo_pedido' => site_base_url() . base_url('rastrear-pedido.php'),
    ];
}

/** HTML por defecto del correo de pago pendiente, usado si no hay plantilla PAGO_PENDIENTE activa en el admin. */
function email_default_payment_pending_html($data) {
    $store_name = get_setting('store_name', 'Monse Party Shop');
    $primary_color = get_setting('primary_color', '#FF6F91');
    $logo = get_setting('logo', '');
    $logo_url = $logo ? site_base_url() . base_url($logo) : '';
    $logo_html = $logo_url
        ? '<img src="' . e($logo_url) . '" alt="' . e($store_name) . '" style="max-height:44px;">'
        : '<span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:800;color:#ffffff;">' . e($store_name) . '</span>';

    $reference_block = '';
    if ($data['referencia_pago'] !== '') {
        $reference_block = '<p style="text-align:center;margin:0 0 8px;font-size:22px;font-weight:800;color:' . e($primary_color) . ';letter-spacing:1px;">' . e($data['referencia_pago']) . '</p>';
    }
    $expires_block = $data['fecha_vencimiento_pago'] !== ''
        ? '<p style="margin:0;font-size:13px;color:#8a8195;text-align:center;">Vence: <strong>' . e($data['fecha_vencimiento_pago']) . '</strong></p>'
        : '';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f6f3f7;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3f7;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">'
        . '<tr><td align="center" style="background:' . e($primary_color) . ';padding:26px 20px;">' . $logo_html . '</td></tr>'
        . '<tr><td style="padding:32px 30px 10px;">'
        . '<h1 style="margin:0 0 14px;font-size:22px;color:#2c2438;">¡Ya casi, ' . e($data['nombre_completo']) . '!</h1>'
        . '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#4b4453;">Gracias por tu pedido en <strong>' . e($store_name)
        . '</strong>. Solo falta completar tu pago con <strong>' . e($data['metodo_pago']) . '</strong> para que tus plantillas estén disponibles.</p>'
        . '<p style="margin:0 0 20px;font-size:13px;color:#8a8195;">Número de pedido: <strong>' . e($data['codigo_confirmacion']) . '</strong> &nbsp;·&nbsp; Monto: <strong>' . e($data['total_pedido']) . '</strong></p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 30px 20px;">' . $reference_block . $expires_block . '</td></tr>'
        . '<tr><td style="padding:0 30px 30px;">'
        . '<p style="margin:0 0 10px;font-size:13px;color:#8a8195;line-height:1.6;">En cuanto confirmemos tu pago te avisaremos por correo y tus plantillas estarán listas para descargar en <a href="'
        . e($data['url_confirmacion']) . '" style="color:' . e($primary_color) . ';">esta página</a>.</p>'
        . '<p style="margin:0;font-size:13px;color:#8a8195;line-height:1.6;">¿Perdiste este correo? Puedes volver a buscar tu pedido en <a href="'
        . e($data['url_rastreo_pedido']) . '" style="color:' . e($primary_color) . ';font-weight:bold;">Rastrear mi pedido</a>.</p>'
        . '</td></tr>'
        . '<tr><td align="center" style="padding:18px 20px;background:#f6f3f7;font-size:12px;color:#a79fb0;">¡Gracias por tu compra! 💕<br>'
        . e($store_name) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Recordatorio de pago pendiente, disparado manualmente por el admin desde
 * admin/order_detail.php (no es automático como send_payment_pending_email()).
 * Pensado para pedidos que llevan tiempo en 'pending': repite las instrucciones
 * de pago con un botón para que el cliente vaya a completarlo.
 */
function send_payment_reminder_email($order) {
    $data = payment_pending_email_variable_data($order, $order['payment_method']);

    return send_templated_email(
        'RECORDATORIO_PAGO',
        $order['customer_email'],
        $data,
        '⏰ Tu pedido sigue esperando el pago',
        email_default_payment_reminder_html($data)
    );
}

/** HTML por defecto del recordatorio de pago, usado si no hay plantilla RECORDATORIO_PAGO activa en el admin. */
function email_default_payment_reminder_html($data) {
    $store_name = get_setting('store_name', 'Monse Party Shop');
    $primary_color = get_setting('primary_color', '#FF6F91');
    $logo = get_setting('logo', '');
    $logo_url = $logo ? site_base_url() . base_url($logo) : '';
    $logo_html = $logo_url
        ? '<img src="' . e($logo_url) . '" alt="' . e($store_name) . '" style="max-height:44px;">'
        : '<span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:800;color:#ffffff;">' . e($store_name) . '</span>';

    $reference_block = '';
    if ($data['referencia_pago'] !== '') {
        $reference_block = '<p style="text-align:center;margin:0 0 8px;font-size:22px;font-weight:800;color:' . e($primary_color) . ';letter-spacing:1px;">' . e($data['referencia_pago']) . '</p>';
    }
    $expires_block = $data['fecha_vencimiento_pago'] !== ''
        ? '<p style="margin:0;font-size:13px;color:#8a8195;text-align:center;">Vence: <strong>' . e($data['fecha_vencimiento_pago']) . '</strong></p>'
        : '';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f6f3f7;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3f7;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">'
        . '<tr><td align="center" style="background:' . e($primary_color) . ';padding:26px 20px;">' . $logo_html . '</td></tr>'
        . '<tr><td style="padding:32px 30px 10px;">'
        . '<h1 style="margin:0 0 14px;font-size:22px;color:#2c2438;">¡' . e($data['nombre']) . ', tu pedido sigue esperando el pago!</h1>'
        . '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#4b4453;">Notamos que tu pedido en <strong>' . e($store_name)
        . '</strong> todavía no se ha pagado. Completa tu pago con <strong>' . e($data['metodo_pago']) . '</strong> para que tus plantillas queden disponibles.</p>'
        . '<p style="margin:0 0 20px;font-size:13px;color:#8a8195;">Número de pedido: <strong>' . e($data['codigo_confirmacion']) . '</strong> &nbsp;·&nbsp; Monto: <strong>' . e($data['total_pedido']) . '</strong></p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 30px 20px;">' . $reference_block . $expires_block . '</td></tr>'
        . '<tr><td style="padding:0 30px 30px;">'
        . '<p style="text-align:center;margin:0 0 20px;"><a href="' . e($data['url_confirmacion']) . '" style="background:' . e($primary_color)
        . ';color:#ffffff;padding:14px 32px;border-radius:999px;text-decoration:none;font-weight:bold;display:inline-block;font-size:15px;">PAGAR MI PEDIDO</a></p>'
        . '<p style="margin:0;font-size:13px;color:#8a8195;line-height:1.6;">¿Perdiste este correo? Puedes volver a buscar tu pedido en <a href="'
        . e($data['url_rastreo_pedido']) . '" style="color:' . e($primary_color) . ';font-weight:bold;">Rastrear mi pedido</a>.</p>'
        . '</td></tr>'
        . '<tr><td align="center" style="padding:18px 20px;background:#f6f3f7;font-size:12px;color:#a79fb0;">¡Gracias por tu compra! 💕<br>'
        . e($store_name) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Variables para el correo de recuperación de contraseña del admin. $token es el
 * crudo (nunca se guarda así en BD, solo su hash) y solo vive en esta URL de un solo uso.
 */
function admin_password_reset_email_variable_data($admin, $token) {
    return [
        'nombre' => e($admin['name'] ?: $admin['username']),
        'url_reset' => site_base_url() . admin_url('reset_password.php?token=' . urlencode($token)),
        'expira_en' => '1 hora',
        'nombre_empresa' => e(get_setting('store_name', 'Monse Party Shop')),
    ];
}

/** Envía el correo de recuperación de contraseña del admin (disparado desde admin/forgot_password.php). */
function send_admin_password_reset_email($admin, $token) {
    $data = admin_password_reset_email_variable_data($admin, $token);

    return send_templated_email(
        'RECUPERAR_PASSWORD_ADMIN',
        $admin['email'],
        $data,
        '🔒 Recupera tu contraseña de administrador',
        email_default_admin_password_reset_html($data)
    );
}

/** HTML por defecto del correo de recuperación, usado si no hay plantilla RECUPERAR_PASSWORD_ADMIN activa. */
function email_default_admin_password_reset_html($data) {
    $store_name = get_setting('store_name', 'Monse Party Shop');
    $primary_color = get_setting('primary_color', '#FF6F91');
    $logo = get_setting('logo', '');
    $logo_url = $logo ? site_base_url() . base_url($logo) : '';
    $logo_html = $logo_url
        ? '<img src="' . e($logo_url) . '" alt="' . e($store_name) . '" style="max-height:44px;">'
        : '<span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:800;color:#ffffff;">' . e($store_name) . '</span>';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f6f3f7;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3f7;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">'
        . '<tr><td align="center" style="background:' . e($primary_color) . ';padding:26px 20px;">' . $logo_html . '</td></tr>'
        . '<tr><td style="padding:32px 30px 10px;">'
        . '<h1 style="margin:0 0 14px;font-size:22px;color:#2c2438;">Hola, ' . $data['nombre'] . '</h1>'
        . '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#4b4453;">Recibimos una solicitud para restablecer la contraseña del panel administrativo de <strong>' . $data['nombre_empresa'] . '</strong>. Si fuiste tú, usa el siguiente botón:</p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 30px 20px;">'
        . '<p style="text-align:center;margin:0 0 20px;"><a href="' . e($data['url_reset']) . '" style="background:' . e($primary_color)
        . ';color:#ffffff;padding:14px 32px;border-radius:999px;text-decoration:none;font-weight:bold;display:inline-block;font-size:15px;">RESTABLECER CONTRASEÑA</a></p>'
        . '<p style="margin:0;font-size:13px;color:#8a8195;line-height:1.6;">Este enlace expira en <strong>' . $data['expira_en'] . '</strong> y solo se puede usar una vez. Si tú no solicitaste esto, puedes ignorar este correo — tu contraseña actual seguirá funcionando.</p>'
        . '</td></tr>'
        . '<tr><td align="center" style="padding:18px 20px;background:#f6f3f7;font-size:12px;color:#a79fb0;">' . $data['nombre_empresa'] . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Catálogos del formulario de contacto (contacto.php). Centralizados aquí para
 * que el formulario público y el panel admin (admin/contact_messages*.php)
 * siempre muestren las mismas opciones/etiquetas a partir del mismo valor
 * guardado en `contact_messages.reason` / `.preferred_contact`.
 */
function contact_reason_options() {
    return [
        'informacion' => 'Solicitar información',
        'cotizacion' => 'Solicitar una cotización',
        'soporte' => 'Soporte con mi pedido',
        'facturacion' => 'Facturación',
        'alianza' => 'Alianza o negocio',
        'otro' => 'Otro',
    ];
}

function contact_preferred_contact_options() {
    return [
        'correo' => 'Correo electrónico',
        'llamada' => 'Llamada telefónica',
        'whatsapp' => 'WhatsApp',
    ];
}
