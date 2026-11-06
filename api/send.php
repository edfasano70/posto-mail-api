<?php
// API de envío de correo vía Gmail SMTP.
//
// Uso (GET):
//   /posto/api/send.php?key=CLAVE&json={"email_dest":"a@b.com","subject":"Hola","body":"Mensaje","reply_to":"r@b.com","cc":"c@b.com"}
//
// También acepta los parámetros como valores GET individuales:
//   /posto/api/send.php?key=CLAVE&email_dest=a@b.com&subject=Hola&body=Mensaje&reply_to=r@b.com&cc=c@b.com
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
//   cc         (opcional)  dirección en copia (CC)

include __DIR__ . '/../php/config.php';
include __DIR__ . '/../php/display_errors.php';
include __DIR__ . '/../php/smtp.php';

header('Content-Type: application/json; charset=UTF-8');

function respond_error($httpCode, $message) {
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Rate limit simple por IP usando un archivo en el directorio temporal.
 * No bloquea si el archivo no se puede abrir/escribir.
 */
function rate_limit_exceeded($ip) {
    $max = (int) RATE_LIMIT_MAX;
    $window = (int) RATE_LIMIT_WINDOW;
    if ($max <= 0 || $window <= 0) {
        return false;
    }

    $file = sys_get_temp_dir() . '/posto-rate-' . hash('sha256', $ip) . '.json';
    $fp = @fopen($file, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) {
            fclose($fp);
        }
        return false;
    }

    $data = json_decode(stream_get_contents($fp), true);
    $now = time();
    if (!is_array($data) || !isset($data['start'], $data['count']) || ($now - (int) $data['start']) >= $window) {
        $data = ['start' => $now, 'count' => 0];
    }

    $exceeded = ((int) $data['count']) >= $max;
    if (!$exceeded) {
        $data['count'] = (int) $data['count'] + 1;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $exceeded;
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
$cc       = isset($fields['cc'])          ? (string) $fields['cc']          : (isset($_GET['cc']) ? (string) $_GET['cc'] : '');

// ---- Validaciones ----
$configuredKey = isset($api_key) ? (string) $api_key : '';
if ($configuredKey === '' || !hash_equals($configuredKey, $apiKey)) {
    respond_error(401, 'API key inválida.');
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'desconocida';
if (rate_limit_exceeded($ip)) {
    header('Retry-After: ' . (int) RATE_LIMIT_WINDOW);
    respond_error(429, 'Demasiadas solicitudes. Intentá más tarde.');
}

// Evitar inyección de cabeceras: sin CR/LF en asunto, reply-to, destinatario ni cc.
if (preg_match('/[\r\n]/', $subject) || preg_match('/[\r\n]/', $replyTo) || preg_match('/[\r\n]/', $to) || preg_match('/[\r\n]/', $cc)) {
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
if ($cc !== '' && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
    respond_error(400, 'cc no es un email válido.');
}
if ($subject === '') {
    respond_error(400, 'Falta el parámetro subject.');
}
if (strlen($subject) > MAX_SUBJECT_BYTES) {
    respond_error(400, 'subject demasiado largo.');
}
if ($body === '') {
    respond_error(400, 'Falta el parámetro body.');
}
if (strlen($body) > MAX_BODY_BYTES) {
    respond_error(413, 'body demasiado grande.');
}

// ---- Envío ----
$result = smtp_send_gmail($to, $subject, $body, $replyTo, $cc, $smtp_user, $smtp_pass);

if ($result['ok']) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
}
