<?php
/**
 * Catálogo de variables dinámicas disponibles para las plantillas de correo,
 * organizado por categoría. Es la única fuente de verdad: el panel de
 * variables del editor, la validación de plantillas y los datos de ejemplo
 * de la vista previa / correo de prueba se generan a partir de este catálogo.
 *
 * El catálogo tiene dos capas:
 *  - Integradas: fijas en este archivo (email_builtin_variable_catalog()).
 *  - Personalizadas: creadas por el admin desde "Gestionar variables",
 *    guardadas en la tabla email_custom_variables y combinadas aquí.
 */
function email_builtin_variable_catalog() {
    return [
        'Datos del destinatario' => [
            'nombre' => 'Primer nombre del destinatario.',
            'apellido' => 'Apellido del destinatario.',
            'nombre_completo' => 'Nombre completo del destinatario.',
            'correo' => 'Correo electrónico del destinatario.',
            'telefono' => 'Teléfono de contacto del destinatario.',
        ],
        'Información del evento' => [
            'nombre_evento' => 'Nombre o título del evento.',
            'fecha_evento' => 'Fecha en que se realizará el evento.',
            'hora_evento' => 'Hora en que se realizará el evento.',
            'ubicacion_evento' => 'Nombre del lugar del evento.',
            'direccion_evento' => 'Dirección completa del evento.',
            'url_evento' => 'Enlace a la página del evento.',
        ],
        'Información de confirmación' => [
            'codigo_confirmacion' => 'Código único de la confirmación o pedido.',
            'estado_confirmacion' => 'Estado actual de la confirmación (ej. Confirmado).',
            'fecha_confirmacion' => 'Fecha en que se registró la confirmación.',
            'url_confirmacion' => 'Enlace para ver el detalle de la confirmación.',
        ],
        'Información del sistema' => [
            'nombre_empresa' => 'Nombre de la tienda o empresa.',
            'correo_soporte' => 'Correo de contacto para soporte.',
            'telefono_soporte' => 'Teléfono de contacto para soporte.',
            'fecha_actual' => 'Fecha en la que se envía el correo.',
        ],
        'Información del pedido' => [
            'total_pedido' => 'Total pagado del pedido, ya formateado con la moneda de la tienda.',
            'productos_pedido' => 'Lista de los productos comprados en el pedido (uno por línea).',
            'fecha_vencimiento_descarga' => 'Fecha límite para descargar los archivos del pedido.',
            'botones_descarga' => 'Bloque HTML con un botón de descarga por cada archivo comprado. Insértalo donde quieras que aparezcan los botones.',
            'url_rastreo_pedido' => 'Enlace a la página donde el cliente puede volver a encontrar su pedido con su número y correo.',
        ],
        'Información de pago' => [
            'metodo_pago' => 'Nombre del método de pago pendiente (ej. "OXXO Pay" o "Transferencia SPEI").',
            'referencia_pago' => 'Referencia o CLABE que el cliente debe usar para completar su pago.',
            'fecha_vencimiento_pago' => 'Fecha y hora límite para completar el pago pendiente.',
        ],
        'Recuperación de contraseña (admin)' => [
            'url_reset' => 'Enlace de un solo uso para que el administrador cree su nueva contraseña.',
            'expira_en' => 'Cuánto tiempo sigue siendo válido el enlace de recuperación (ej. "1 hora").',
        ],
    ];
}

/** Todas las filas de variables personalizadas, ordenadas por categoría y nombre. */
function email_custom_variable_rows() {
    static $rows = null;
    if ($rows === null) {
        $rows = get_db()->query('SELECT * FROM email_custom_variables ORDER BY category ASC, name ASC')->fetchAll();
    }
    return $rows;
}

/**
 * Catálogo combinado (integradas + personalizadas), agrupado por categoría.
 * Si una variable personalizada usa el nombre de una categoría integrada
 * (sin distinguir mayúsculas/acentos/espacios), se agrega dentro de esa
 * misma sección en vez de crear una duplicada.
 */
function email_variable_catalog() {
    $catalog = email_builtin_variable_catalog();
    $category_keys = []; // clave normalizada => nombre de categoría tal como se muestra
    foreach (array_keys($catalog) as $cat) {
        $category_keys[email_normalize_category_key($cat)] = $cat;
    }

    foreach (email_custom_variable_rows() as $row) {
        $norm = email_normalize_category_key($row['category']);
        if (!isset($category_keys[$norm])) {
            $category_keys[$norm] = $row['category'];
            $catalog[$row['category']] = [];
        }
        $display_category = $category_keys[$norm];
        $catalog[$display_category][$row['name']] = $row['description'] !== '' ? $row['description'] : 'Variable personalizada.';
    }

    return $catalog;
}

function email_normalize_category_key($category) {
    $key = mb_strtolower(trim((string)$category), 'UTF-8');
    $key = preg_replace('/[áàäâ]/u', 'a', $key);
    $key = preg_replace('/[éèëê]/u', 'e', $key);
    $key = preg_replace('/[íìïî]/u', 'i', $key);
    $key = preg_replace('/[óòöô]/u', 'o', $key);
    $key = preg_replace('/[úùüû]/u', 'u', $key);
    $key = preg_replace('/[ñ]/u', 'n', $key);
    return preg_replace('/\s+/', ' ', $key);
}

/** Lista plana de nombres de variable válidos (sin llaves), para validar. */
function email_variable_names() {
    $names = [];
    foreach (email_variable_catalog() as $vars) {
        $names = array_merge($names, array_keys($vars));
    }
    return $names;
}

/** Nombres de las variables integradas (fijas), para impedir que una personalizada las reutilice. */
function email_builtin_variable_names() {
    $names = [];
    foreach (email_builtin_variable_catalog() as $vars) {
        $names = array_merge($names, array_keys($vars));
    }
    return $names;
}

/** Nombres de categoría existentes (integradas + personalizadas), para el autocompletado del formulario. */
function email_variable_category_names() {
    return array_keys(email_variable_catalog());
}

/** true si $name ya está en uso (integrada o personalizada), opcionalmente ignorando un id (al editar). */
function email_custom_variable_name_taken($name, $exclude_id = null) {
    $name = strtolower(trim($name));
    if (in_array($name, email_builtin_variable_names(), true)) {
        return true;
    }
    $sql = 'SELECT COUNT(*) FROM email_custom_variables WHERE name = :name';
    $params = ['name' => $name];
    if ($exclude_id) {
        $sql .= ' AND id != :id';
        $params['id'] = $exclude_id;
    }
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

/** Datos de ejemplo usados en la vista previa y en el correo de prueba. */
function email_variable_sample_data() {
    $data = [
        'nombre' => 'Juan',
        'apellido' => 'Pérez',
        'nombre_completo' => 'Juan Pérez',
        'correo' => 'juan.perez@ejemplo.com',
        'telefono' => '55 1234 5678',
        'nombre_evento' => 'Baby Shower de Julieta',
        'fecha_evento' => '15 de octubre de 2026',
        'hora_evento' => '17:00 hrs',
        'ubicacion_evento' => 'Salón Jardín Encanto',
        'direccion_evento' => 'Av. Siempre Viva 123, CDMX',
        'url_evento' => 'https://monsepartyshop.com/evento/demo',
        'codigo_confirmacion' => 'CONF-DEMO123',
        'estado_confirmacion' => 'Confirmado',
        'fecha_confirmacion' => date('d/m/Y'),
        'url_confirmacion' => 'https://monsepartyshop.com/confirmacion/demo',
        'nombre_empresa' => get_setting('store_name', 'Monse Party Shop'),
        'correo_soporte' => get_setting('email', 'hola@monsepartyshop.com'),
        'telefono_soporte' => get_setting('phone', ''),
        'fecha_actual' => date('d/m/Y'),
        'total_pedido' => format_price(178),
        'productos_pedido' => 'Cajita Osito Baby<br>Kit Cumpleaños Arcoíris',
        'fecha_vencimiento_descarga' => date('d/m/Y', strtotime('+30 days')),
        'botones_descarga' => '<p style="text-align:center;margin:0;"><a href="#" style="background:#FF6F91;color:#ffffff;padding:12px 28px;border-radius:999px;text-decoration:none;font-weight:bold;display:inline-block;">DESCARGAR MI PLANTILLA</a></p>',
        'url_rastreo_pedido' => base_url('rastrear-pedido.php'),
        'metodo_pago' => 'OXXO Pay',
        'referencia_pago' => '93000012345678',
        'fecha_vencimiento_pago' => date('d/m/Y H:i', strtotime('+3 days')),
        'url_reset' => base_url('admin/reset_password.php?token=demo'),
        'expira_en' => '1 hora',
    ];
    foreach (email_custom_variable_rows() as $row) {
        $data[$row['name']] = $row['sample_value'] !== '' ? $row['sample_value'] : '[' . $row['name'] . ']';
    }
    return $data;
}

/** Sustituye {{variable}} por su valor de $data; deja intacta cualquier variable sin dato. */
function email_render_variables($text, $data) {
    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($data) {
        $key = strtolower($m[1]);
        return array_key_exists($key, $data) ? $data[$key] : $m[0];
    }, (string)$text);
}

/** Devuelve los nombres de variable usados en $text que NO existen en el catálogo. */
function email_find_unknown_variables($text) {
    if (!preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', (string)$text, $matches)) {
        return [];
    }
    $known = email_variable_names();
    $unknown = [];
    foreach ($matches[1] as $name) {
        $name = strtolower($name);
        if (!in_array($name, $known, true) && !in_array($name, $unknown, true)) {
            $unknown[] = $name;
        }
    }
    return $unknown;
}

/** Tipos de plantilla disponibles para el selector y el filtro. */
function email_template_types() {
    return ['Confirmación', 'Pago', 'Recordatorio', 'Notificación', 'Seguridad', 'Personalizado'];
}

/**
 * Plantilla activa (status='active') por su código único, para usarla en envíos
 * automáticos del sitio (ej. "COMPRA_LISTA"). Devuelve null si no existe o está
 * en borrador/inactiva, para que el código que envía el correo pueda usar un
 * contenido por defecto como respaldo.
 */
function email_active_template_by_code($code) {
    static $cache = [];
    if (!array_key_exists($code, $cache)) {
        $stmt = get_db()->prepare("SELECT * FROM email_templates WHERE code = :code AND status = 'active' LIMIT 1");
        $stmt->execute(['code' => $code]);
        $cache[$code] = $stmt->fetch() ?: null;
    }
    return $cache[$code];
}

/**
 * Punto único de envío para cualquier correo automático del sitio basado en una
 * plantilla del admin: busca la plantilla activa por su código, sustituye las
 * variables en el asunto y el contenido, y la envía. Todos los correos
 * transaccionales (send_order_confirmation_email, send_payment_pending_email, etc.)
 * deben pasar por aquí en vez de armar el HTML/envío por su cuenta.
 *
 * @param string       $code             Código único de la plantilla (ej. 'COMPRA_LISTA').
 * @param string       $to               Correo del destinatario.
 * @param string|array $variables        Variables para sustituir {{...}}, como JSON (string, ej. '{"nombre":"Ana"}') o array asociativo ya decodificado.
 * @param string|null  $fallback_subject Asunto a usar si no hay ninguna plantilla activa con ese código.
 * @param string|null  $fallback_html    HTML a usar si no hay ninguna plantilla activa con ese código.
 * @return array ['success' => bool, 'message' => string]
 */
function send_templated_email($code, $to, $variables, $fallback_subject = null, $fallback_html = null) {
    if (is_string($variables)) {
        $data = json_decode($variables, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Las variables enviadas no son un JSON válido.'];
        }
    } else {
        $data = (array)$variables;
    }

    $template = email_active_template_by_code($code);
    if ($template) {
        $subject = email_render_variables($template['subject'], $data);
        $html = email_render_variables($template['content_html'], $data);
    } elseif ($fallback_html !== null) {
        $subject = (string)$fallback_subject;
        $html = $fallback_html;
    } else {
        return ['success' => false, 'message' => "No existe una plantilla activa con el código \"{$code}\" y no se definió un contenido de respaldo."];
    }

    return send_html_email($to, $subject, $html);
}
