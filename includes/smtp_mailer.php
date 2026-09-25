<?php
/**
 * Cliente SMTP mínimo, sin dependencias externas.
 * Habla el protocolo SMTP directo por socket (EHLO, STARTTLS, AUTH LOGIN, DATA).
 */
function smtp_send($params) {
    $host = $params['host'];
    $port = (int)$params['port'];
    $encryption = $params['encryption'];

    $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return ['success' => false, 'message' => "No se pudo conectar al servidor SMTP: {$errstr}"];
    }
    stream_set_timeout($socket, 15);

    $read_response = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') { break; }
        }
        return $data;
    };

    $send_command = function ($cmd) use ($socket, $read_response) {
        fwrite($socket, $cmd . "\r\n");
        return $read_response();
    };

    $response = $read_response();
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'message' => "El servidor no respondió correctamente: {$response}"];
    }

    $response = $send_command('EHLO ' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost'));
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'message' => "EHLO falló: {$response}"];
    }

    if ($encryption === 'tls') {
        $response = $send_command('STARTTLS');
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => "STARTTLS falló: {$response}"];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'message' => 'No se pudo iniciar el cifrado TLS.'];
        }
        $response = $send_command('EHLO ' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost'));
    }

    if (!empty($params['username'])) {
        $response = $send_command('AUTH LOGIN');
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'message' => "AUTH LOGIN no soportado: {$response}"];
        }
        $response = $send_command(base64_encode($params['username']));
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'message' => "Usuario rechazado: {$response}"];
        }
        $response = $send_command(base64_encode($params['password']));
        if (substr($response, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'message' => "Autenticación fallida (revisa usuario/contraseña): {$response}"];
        }
    }

    $response = $send_command('MAIL FROM:<' . $params['from_email'] . '>');
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'message' => "El servidor rechazó el remitente: {$response}"];
    }

    $response = $send_command('RCPT TO:<' . $params['to'] . '>');
    if (substr($response, 0, 3) !== '250' && substr($response, 0, 3) !== '251') {
        fclose($socket);
        return ['success' => false, 'message' => "El servidor rechazó el destinatario: {$response}"];
    }

    $response = $send_command('DATA');
    if (substr($response, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'message' => "DATA falló: {$response}"];
    }

    $encoded_subject = '=?UTF-8?B?' . base64_encode($params['subject']) . '?=';
    $body_escaped = preg_replace('/^\./m', '..', $params['body']);
    $content_type = $params['content_type'] ?? 'text/plain';

    $message = "From: {$params['from_name']} <{$params['from_email']}>\r\n";
    $message .= "To: <{$params['to']}>\r\n";
    $message .= "Subject: {$encoded_subject}\r\n";
    $message .= 'Date: ' . date('r') . "\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: {$content_type}; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n";
    $message .= "\r\n";
    $message .= $body_escaped . "\r\n.";

    $response = $send_command($message);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'message' => "El servidor rechazó el mensaje: {$response}"];
    }

    $send_command('QUIT');
    fclose($socket);
    return ['success' => true, 'message' => 'Correo enviado correctamente por SMTP.'];
}

function send_email($to, $subject, $body) {
    $store_name = get_setting('store_name', 'Monse Party Shop');

    if (get_setting('smtp_enabled', '0') === '1') {
        $smtp_username = get_setting('smtp_username', '');
        // El remitente debe coincidir con la cuenta autenticada por SMTP;
        // si no, muchos proveedores aceptan el envío (250 OK) pero lo
        // descartan silenciosamente por protección antisuplantación.
        $from_email = $smtp_username ?: get_setting('email', 'no-reply@example.com');

        return smtp_send([
            'host' => get_setting('smtp_host', ''),
            'port' => get_setting('smtp_port', '587'),
            'encryption' => get_setting('smtp_encryption', 'tls'),
            'username' => $smtp_username,
            'password' => get_setting('smtp_password', ''),
            'from_email' => $from_email,
            'from_name' => $store_name,
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    $from_email = get_setting('email', 'no-reply@example.com');

    $headers = "From: {$store_name} <{$from_email}>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($to, $subject, $body, $headers);
    return [
        'success' => $ok,
        'message' => $ok
            ? 'Correo enviado con la función mail() de PHP (SMTP está deshabilitado).'
            : 'No se pudo enviar el correo con mail() de PHP. Considera habilitar SMTP.',
    ];
}

/**
 * Igual que send_email() pero envía el cuerpo como HTML
 * (usado por las plantillas de correo, que producen HTML).
 */
function send_html_email($to, $subject, $html_body) {
    $store_name = get_setting('store_name', 'Monse Party Shop');

    if (get_setting('smtp_enabled', '0') === '1') {
        $smtp_username = get_setting('smtp_username', '');
        $from_email = $smtp_username ?: get_setting('email', 'no-reply@example.com');

        return smtp_send([
            'host' => get_setting('smtp_host', ''),
            'port' => get_setting('smtp_port', '587'),
            'encryption' => get_setting('smtp_encryption', 'tls'),
            'username' => $smtp_username,
            'password' => get_setting('smtp_password', ''),
            'from_email' => $from_email,
            'from_name' => $store_name,
            'to' => $to,
            'subject' => $subject,
            'body' => $html_body,
            'content_type' => 'text/html',
        ]);
    }

    $from_email = get_setting('email', 'no-reply@example.com');

    $headers = "From: {$store_name} <{$from_email}>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = @mail($to, $subject, $html_body, $headers);
    return [
        'success' => $ok,
        'message' => $ok
            ? 'Correo enviado con la función mail() de PHP (SMTP está deshabilitado).'
            : 'No se pudo enviar el correo con mail() de PHP. Considera habilitar SMTP.',
    ];
}
