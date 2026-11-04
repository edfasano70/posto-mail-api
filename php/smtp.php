<?php
// Cliente SMTP mínimo en PHP puro (sin librerías externas).
// Envía correo a través de smtp.gmail.com usando SMTPS (puerto 465) con AUTH LOGIN.
// Requiere la extensión openssl.

/**
 * Lee una respuesta SMTP del socket hasta la línea final (código seguido de espacio).
 * Devuelve [código, texto].
 */
function smtp_read_response($fp) {
    $code = null;
    $text = '';
    while (($line = fgets($fp, 512)) !== false) {
        $text .= $line;
        // Respuesta final: 3 dígitos + espacio (no guion).
        if (preg_match('/^(\d{3}) /', $line, $m)) {
            $code = (int) $m[1];
            break;
        }
    }
    return [$code, trim($text)];
}

/**
 * Envía un correo vía SMTP de Gmail.
 *
 * @param string $to         Destinatario (email_dest).
 * @param string $subject    Asunto.
 * @param string $body       Cuerpo del mensaje (texto plano).
 * @param string $reply_to   Opcional: Reply-To.
 * @param string $cc         Opcional: dirección en copia (CC).
 * @param string $user       Cuenta Gmail remitente.
 * @param string $pass       Contraseña de aplicación (App Password).
 * @return array{ok: bool, error: string}
 */
function smtp_send_gmail($to, $subject, $body, $reply_to, $cc, $user, $pass) {
    $host = 'smtp.gmail.com';
    $port = 465;

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client("tls://{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        return ['ok' => false, 'error' => "No se pudo conectar a SMTP ({$errstr})"];
    }
    stream_set_timeout($fp, 30);

    list($code, $text) = smtp_read_response($fp);
    if ($code !== 220) {
        fclose($fp);
        return ['ok' => false, 'error' => "Saludo SMTP inesperado ({$code}): {$text}"];
    }

    // EHLO
    fwrite($fp, "EHLO localhost\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 250) {
        fclose($fp);
        return ['ok' => false, 'error' => "EHLO falló ({$code}): {$text}"];
    }

    // AUTH LOGIN
    fwrite($fp, "AUTH LOGIN\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 334) {
        fclose($fp);
        return ['ok' => false, 'error' => "AUTH LOGIN no aceptado ({$code}): {$text}"];
    }
    fwrite($fp, base64_encode($user) . "\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 334) {
        fclose($fp);
        return ['ok' => false, 'error' => "Usuario rechazado ({$code}): {$text}"];
    }
    fwrite($fp, base64_encode($pass) . "\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 235) {
        fclose($fp);
        return ['ok' => false, 'error' => "Autenticación fallida ({$code}): {$text}"];
    }

    // Remitente y destinatario
    fwrite($fp, "MAIL FROM:<{$user}>\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 250) {
        fclose($fp);
        return ['ok' => false, 'error' => "MAIL FROM rechazado ({$code}): {$text}"];
    }
    fwrite($fp, "RCPT TO:<{$to}>\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 250) {
        fclose($fp);
        return ['ok' => false, 'error' => "RCPT TO rechazado ({$code}): {$text}"];
    }
    if ($cc !== '') {
        fwrite($fp, "RCPT TO:<{$cc}>\r\n");
        list($code, $text) = smtp_read_response($fp);
        if ($code !== 250) {
            fclose($fp);
            return ['ok' => false, 'error' => "RCPT TO (CC) rechazado ({$code}): {$text}"];
        }
    }

    // DATA
    fwrite($fp, "DATA\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 354) {
        fclose($fp);
        return ['ok' => false, 'error' => "DATA no aceptado ({$code}): {$text}"];
    }

    $headers = "From: {$user}\r\n";
    $headers .= "To: {$to}\r\n";
    if ($cc !== '') {
        $headers .= "Cc: {$cc}\r\n";
    }
    if ($reply_to !== '') {
        $headers .= "Reply-To: {$reply_to}\r\n";
    }
    // Asunto en UTF-8 (RFC 2047) para caracteres no ASCII.
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    // Punto de terminación SMTP: líneas que empiezan con "." se duplican.
    $safe_body = preg_replace('/^\./m', '..', $body);
    $safe_body = str_replace("\r\n", "\n", $safe_body);
    $safe_body = str_replace("\n", "\r\n", $safe_body);

    fwrite($fp, $headers . "\r\n" . $safe_body . "\r\n.\r\n");
    list($code, $text) = smtp_read_response($fp);
    if ($code !== 250) {
        fclose($fp);
        return ['ok' => false, 'error' => "Envío rechazado ({$code}): {$text}"];
    }

    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return ['ok' => true, 'error' => ''];
}