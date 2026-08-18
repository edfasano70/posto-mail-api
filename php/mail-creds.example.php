<?php
// PLANTILLA de credenciales para la app "posto".
//
// Copiar este archivo al servidor como:
//   /var/www/posto-mail-creds.php   (FUERA del webroot, nunca en el repo)
//
// Crear la contraseña de aplicación de Gmail en:
//   https://myaccount.google.com/apppasswords
// (requiere 2FA activado en la cuenta). La cuenta de Gmail es el remitente.

$smtp_user = 'TU_CUENTA@gmail.com';
$smtp_pass = 'APP_PASSWORD_16_CARACTERES';
$api_key   = 'CLAVE_SECRETA_ALEATORIA';

// No incluir este archivo en el repositorio ni exponerlo por HTTP.