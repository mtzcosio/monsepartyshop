<?php
/**
 * Cliente mínimo para Google reCAPTCHA v2 (casilla "No soy un robot"),
 * sin SDK: solo verifica el token del widget contra el endpoint oficial con
 * cURL directo, igual que includes/stripe_client.php y conekta_client.php.
 * Protege contacto.php contra envíos automatizados por bots.
 */
define('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');

/** true solo si el admin activó reCAPTCHA y configuró ambas llaves. */
function recaptcha_is_enabled() {
    return get_setting('recaptcha_enabled', '0') === '1'
        && get_setting('recaptcha_site_key', '') !== ''
        && get_setting('recaptcha_secret_key', '') !== '';
}

/**
 * Verifica el token que manda el widget (campo oculto `g-recaptcha-response`,
 * inyectado automáticamente por el script de Google dentro del formulario).
 */
function recaptcha_verify($token, $remote_ip = '') {
    $secret_key = get_setting('recaptcha_secret_key', '');
    if (!$secret_key) {
        return ['ok' => false, 'error' => 'reCAPTCHA no está configurado (falta la llave secreta).'];
    }
    if ($token === '') {
        return ['ok' => false, 'error' => 'Confirma que no eres un robot.'];
    }

    $fields = ['secret' => $secret_key, 'response' => $token];
    if ($remote_ip !== '') {
        $fields['remoteip'] = $remote_ip;
    }

    $ch = curl_init(RECAPTCHA_VERIFY_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'No se pudo verificar el reCAPTCHA: ' . $curl_error];
    }

    $data = json_decode($response, true);
    if (empty($data['success'])) {
        return ['ok' => false, 'error' => 'No pudimos confirmar que no eres un robot. Intenta de nuevo.'];
    }
    return ['ok' => true, 'error' => null];
}
