<?php
// Configuración de la app "posto" (API de correo vía Gmail SMTP).
// Las credenciales de Gmail y la API key NO van en el código: se incluyen
// desde /var/www/posto-mail-creds.php (fuera del webroot) en cada archivo
// que las necesite.

// Errores visibles SOLO en desarrollo. Cambiar a false en producción.
define('APP_DEBUG', false);

// Versión de la aplicación.
define('APP_VERSION', '1.3.0');

// Límites de la API (bytes y ventana de rate limit por IP).
define('MAX_SUBJECT_BYTES', 1000);
define('MAX_BODY_BYTES', 100000);
define('RATE_LIMIT_MAX', 60);    // peticiones por IP dentro de la ventana
define('RATE_LIMIT_WINDOW', 60); // segundos

// Archivo externo de credenciales. Debe exponer:
//   $smtp_user = 'cuenta@gmail.com';
//   $smtp_pass = 'app_password_de_16_caracteres';
//   $api_key   = 'clave_secreta_para_consumir_la_api';
// Ver plantilla: php/mail-creds.example.php
if (!file_exists('/var/www/posto-mail-creds.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    $error = APP_DEBUG
        ? 'Credenciales no configuradas: falta /var/www/posto-mail-creds.php'
        : 'Error de configuración.';
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}
include_once '/var/www/posto-mail-creds.php';
