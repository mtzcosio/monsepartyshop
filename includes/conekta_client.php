<?php
/**
 * Cliente mínimo para la API de Conekta (https://developers.conekta.com),
 * sin dependencias externas (usa cURL directo).
 */
define('CONEKTA_API_BASE', 'https://api.conekta.io');
define('CONEKTA_API_VERSION', '2.1.0');

function conekta_request($method, $path, $payload = null) {
    $private_key = get_setting('conekta_private_key', '');
    if (!$private_key) {
        return ['ok' => false, 'error' => 'Conekta no está configurado (falta la llave privada).', 'data' => null];
    }

    $ch = curl_init(CONEKTA_API_BASE . $path);
    $headers = [
        'Accept: application/vnd.conekta-v' . CONEKTA_API_VERSION . '+json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $private_key,
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'No se pudo conectar con Conekta: ' . $curl_error, 'data' => null];
    }

    $data = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        return ['ok' => true, 'error' => null, 'data' => $data];
    }

    $error_message = 'Conekta rechazó la solicitud.';
    if (!empty($data['details'][0]['message'])) {
        $error_message = $data['details'][0]['message'];
    } elseif (!empty($data['message'])) {
        $error_message = $data['message'];
    }
    return ['ok' => false, 'error' => $error_message, 'data' => $data];
}

/**
 * Crea una orden en Conekta con un cargo (tarjeta, OXXO o SPEI).
 * $charge_payload es el bloque "payment_method" según el método elegido.
 */
function conekta_create_order($customer_name, $customer_email, $line_items, $charge_payload) {
    $payload = [
        'currency' => get_setting('currency', 'MXN'),
        'customer_info' => [
            'name' => $customer_name,
            'email' => $customer_email,
        ],
        'line_items' => $line_items,
        'charges' => [
            ['payment_method' => $charge_payload],
        ],
    ];
    return conekta_request('POST', '/orders', $payload);
}

function conekta_get_order($conekta_order_id) {
    return conekta_request('GET', '/orders/' . urlencode($conekta_order_id));
}
