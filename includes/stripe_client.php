<?php
/**
 * Cliente mínimo para la API de Stripe (https://stripe.com/docs/api),
 * sin dependencias externas (usa cURL directo).
 */
define('STRIPE_API_BASE', 'https://api.stripe.com/v1');

function stripe_request($method, $path, $form_fields = null) {
    $secret_key = get_setting('stripe_secret_key', '');
    if (!$secret_key) {
        return ['ok' => false, 'error' => 'Stripe no está configurado (falta la llave secreta).', 'data' => null];
    }

    $ch = curl_init(STRIPE_API_BASE . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($form_fields !== null) {
        // Stripe espera application/x-www-form-urlencoded con notación de arreglos tipo PHP.
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form_fields));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'No se pudo conectar con Stripe: ' . $curl_error, 'data' => null];
    }

    $data = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        return ['ok' => true, 'error' => null, 'data' => $data];
    }

    $error_message = $data['error']['message'] ?? 'Stripe rechazó la solicitud.';
    return ['ok' => false, 'error' => $error_message, 'data' => $data];
}

/**
 * Texto identificador del pago que Stripe muestra directo en la lista de "Pagos"
 * del Dashboard (a diferencia de metadata, que solo se ve al entrar al detalle).
 * Se calcula igual aquí (al crear la sesión) que en admin/order_detail.php (para
 * mostrarlo y poder cotejarlo visualmente contra Stripe), así que un cambio aquí
 * debe reflejarse también allá.
 */
function stripe_payment_description($order_code, $customer_name) {
    return 'Pedido ' . $order_code . ' - ' . $customer_name;
}

/**
 * Crea una sesión de Stripe Checkout (página de pago alojada por Stripe).
 * $line_items: [['name' => ..., 'unit_amount' => centavos, 'quantity' => ...], ...]
 * $payment_method_types: ej. ['card'], ['card','oxxo'], ['card','oxxo','customer_balance'].
 * Apple Pay y Google Pay aparecen automáticamente dentro de "card" en la página de Stripe,
 * no requieren un tipo aparte.
 * $metadata: pares clave-valor libres (ej. ['pedido' => 'MPS-...', 'cliente' => 'Ana Pérez'])
 * para poder identificar el pedido desde el Dashboard de Stripe sin cruzarlo con la BD.
 * Se manda tanto en la sesión como en payment_intent_data.metadata, porque el metadata de
 * la sesión NO se copia solo al PaymentIntent (que es lo que se ve en la lista de "Pagos").
 * $description: texto libre (ver stripe_payment_description()) que sí aparece directo en
 * esa lista de "Pagos", vía payment_intent_data.description.
 */
function stripe_create_checkout_session($customer_email, $line_items, $success_url, $cancel_url, $client_reference_id, $payment_method_types = ['card'], $metadata = [], $description = null) {
    $currency = strtolower(get_setting('currency', 'MXN'));
    $fields = [
        'mode' => 'payment',
        'client_reference_id' => $client_reference_id,
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'payment_method_types' => $payment_method_types,
    ];

    foreach ($metadata as $key => $value) {
        $value = mb_substr((string)$value, 0, 500);
        $fields['metadata'][$key] = $value;
        $fields['payment_intent_data']['metadata'][$key] = $value;
    }

    if ($description !== null && $description !== '') {
        $fields['payment_intent_data']['description'] = mb_substr($description, 0, 1000);
    }

    if (in_array('customer_balance', $payment_method_types, true)) {
        // "customer_balance" (SPEI) exige un objeto Customer real en Stripe;
        // no basta con customer_email. Lo creamos aquí y lo asociamos a la sesión.
        $customer_result = stripe_request('POST', '/customers', ['email' => $customer_email]);
        if ($customer_result['ok'] && !empty($customer_result['data']['id'])) {
            $fields['customer'] = $customer_result['data']['id'];
        } else {
            $fields['customer_email'] = $customer_email;
        }
        $fields['payment_method_options']['customer_balance']['funding_type'] = 'bank_transfer';
        $fields['payment_method_options']['customer_balance']['bank_transfer']['type'] = 'mx_bank_transfer';
    } else {
        $fields['customer_email'] = $customer_email;
    }

    if (in_array('oxxo', $payment_method_types, true)) {
        $fields['payment_method_options']['oxxo']['expires_after_days'] = 3;
    }

    foreach ($line_items as $i => $item) {
        $fields['line_items'][$i]['price_data']['currency'] = $currency;
        $fields['line_items'][$i]['price_data']['product_data']['name'] = $item['name'];
        $fields['line_items'][$i]['price_data']['unit_amount'] = $item['unit_amount'];
        $fields['line_items'][$i]['quantity'] = $item['quantity'];
    }
    return stripe_request('POST', '/checkout/sessions', $fields);
}

function stripe_get_checkout_session($session_id) {
    return stripe_request('GET', '/checkout/sessions/' . urlencode($session_id));
}

/**
 * Verifica la firma de un webhook de Stripe (header Stripe-Signature) usando
 * el secreto del endpoint, sin depender del SDK oficial.
 */
function stripe_verify_webhook_signature($payload, $signature_header, $webhook_secret) {
    if (!$webhook_secret || !$signature_header) {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signature_header) as $pair) {
        $kv = explode('=', $pair, 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }
    if (empty($parts['t']) || empty($parts['v1'])) {
        return false;
    }

    $signed_payload = $parts['t'] . '.' . $payload;
    $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);

    // Tolerancia de 5 minutos para evitar ataques de repetición.
    if (abs(time() - (int)$parts['t']) > 300) {
        return false;
    }

    return hash_equals($expected_signature, $parts['v1']);
}
