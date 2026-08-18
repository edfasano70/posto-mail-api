<?php
// API de envío de correo vía Gmail SMTP.
//
// Uso (GET):
//   /posto/api/send.php?key=CLAVE&json={"email_dest":"a@b.com","subject":"Hola","body":"Mensaje","reply_to":"r@b.com"}
//
// También acepta los parámetros como valores GET individuales:
//   /posto/api/send.php?key=CLAVE&email_dest=a@b.com&subject=Hola&body=Mensaje&reply_to=r@b.com
//
// Respuesta (JSON):
//   {"success":true}
//   {"success":false,"error":"motivo"}
//
// Parámetros:
//   key        (requerido) API key definida en /var/www/posto-mail-creds.php
//   email_dest (requerido) destinatario
//   subject    (requerido) asunto
//   body       (requerido) cuerpo en texto plano
//   reply_to   (opcional)  dirección Reply-To

include __DIR__ . '/../php/config.php';
include __DIR__ . '/../php/display_errors.php';
include __DIR__ . '/../php/smtp.php';

header('Content-Type: application/json; charset=UTF-8');

function respond_error($httpCode, $message) {
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Parámetros ----
// El payload puede venir como JSON en un solo parámetro GET (`json` o `data`)
// o como parámetros individuales.
$rawJson = isset($_GET['json']) ? $_GET['json'] : (isset($_GET['data']) ? $_GET['data'] : '');
$fields = [];
if ($rawJson !== '') {
    $fields = json_decode($rawJson, true);
    if (!is_array($fields)) {
        respond_error(400, 'El parámetro json no contiene un JSON válido.');
    }
}

$apiKey   = isset($fields['key'])        ? (string) $fields['key']        : (isset($_GET['key']) ? (string) $_GET['key'] : '');
$to       = isset($fields['email_dest']) ? (string) $fields['email_dest'] : (isset($_GET['email_dest']) ? (string) $_GET['email_dest'] : '');
$subject  = isset($fields['subject'])    ? (string) $fields['subject']    : (isset($_GET['subject']) ? (string) $_GET['subject'] : '');
$body     = isset($fields['body'])       ? (string) $fields['body']       : (isset($_GET['body']) ? (string) $_GET['body'] : '');
$replyTo  = isset($fields['reply_to'])   ? (string) $fields['reply_to']   : (isset($_GET['reply_to']) ? (string) $_GET['reply_to'] : '');

// ---- Validaciones ----
$configuredKey = isset($api_key) ? (string) $api_key : '';
if ($configuredKey === '' || !hash_equals($configuredKey, $apiKey)) {
    respond_error(401, 'API key inválida.');
}

// Evitar inyección de cabeceras: sin CR/LF en asunto, reply-to ni destinatario.
if (preg_match('/[\r\n]/', $subject) || preg_match('/[\r\n]/', $replyTo) || preg_match('/[\r\n]/', $to)) {
    respond_error(400, 'Parámetros inválidos.');
}

if ($to === '') {
    respond_error(400, 'Falta el parámetro email_dest.');
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    respond_error(400, 'email_dest no es un email válido.');
}
if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
    respond_error(400, 'reply_to no es un email válido.');
}
if ($subject === '') {
    respond_error(400, 'Falta el parámetro subject.');
}
if ($body === '') {
    respond_error(400, 'Falta el parámetro body.');
}

// ---- Envío ----
$result = smtp_send_gmail($to, $subject, $body, $replyTo, $smtp_user, $smtp_pass);

if ($result['ok']) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
}
