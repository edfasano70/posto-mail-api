<?php
// Configuración de la app "posto" (API de correo vía Gmail SMTP).
// Las credenciales de Gmail y la API key NO van en el código: se incluyen
// desde /var/www/posto-mail-creds.php (fuera del webroot) en cada archivo
// que las necesite.

// Errores visibles SOLO en desarrollo. Cambiar a false en producción.
define('APP_DEBUG', false);

// Versión de la aplicación.
define('APP_VERSION', '1.0.0');

// Archivo externo de credenciales. Debe exponer:
//   $smtp_user = 'cuenta@gmail.com';
//   $smtp_pass = 'app_password_de_16_caracteres';
//   $api_key   = 'clave_secreta_para_consumir_la_api';
// Ver plantilla: php/mail-creds.example.php
if (!file_exists('/var/www/posto-mail-creds.php')) {
    if (APP_DEBUG) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Credenciales no configuradas: falta /var/www/posto-mail-creds.php'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('Error de configuración.');
}
include '/var/www/posto-mail-creds.php';
