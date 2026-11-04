# AGENTS.md

App PHP pura: API de envío de correo vía SMTP de Gmail. Sin framework, sin
Composer, sin build, sin tests y **sin base de datos**. No copiar las secciones
de BD/sesión de las apps hermanas (`openhouse`, `acla`): este repo no las usa.

## Fuentes de verdad

- `api/send.php` — endpoint público `GET /posto/api/send.php`.
  Parámetros: `key`, `email_dest`, `subject`, `body`, `reply_to` (opcional) y
  `cc` (opcional). Pueden venir como JSON en `json` (alias `data`) o como GET
  individuales. Responde JSON `{"success":bool}` con 400/401/500/502.
- `php/smtp.php` — cliente SMTP propio contra `smtp.gmail.com:465` (SMTPS,
  AUTH LOGIN). Firma actual:
  `smtp_send_gmail($to, $subject, $body, $reply_to, $cc, $user, $pass)`.
  Requiere ext-openssl.
- `php/config.php` — `APP_DEBUG`/`APP_VERSION` y carga de credenciales.
- `test.php` — formulario de prueba (envía directo por SMTP, pide la API key).
- `GUIA_INSTALACION.txt` — instalación, despliegue, curl y troubleshooting.
  Ante dudas de operación, consultar ahí antes de improvisar.

## Credenciales y configuración

- `$smtp_user`, `$smtp_pass` (App Password de Gmail) y `$api_key` viven en
  `/var/www/posto-mail-creds.php`, **fuera del webroot**. Plantilla:
  `php/mail-creds.example.php`. `.gitignore` excluye `*creds*.php`.
- Sin ese archivo, `config.php` corta la ejecución ("Error de configuración." o
  500). Al depurar, confirmar primero que exista y sea legible por Apache.
- Nunca hardcodear credenciales ni copiar el archivo real al repo.

## Gotchas verificados

- En subcarpetas usar `__DIR__` (`api/send.php` incluye `__DIR__ . '/../php/...'`);
  los `include` relativos solo funcionan en la raíz de la app.
- Todo envío sale por SMTP de Gmail; `mail()`/MTA local no se usan.
- No se permite CR/LF en `to`, `subject`, `reply_to` ni `cc`
  (anti header-injection): validar igual cualquier parámetro nuevo.
- `smtp_send_gmail` arma las cabeceras `To`/`Cc`/`Reply-To`, hace dot-stuffing
  del body y codifica el asunto en RFC 2047.
- **`test.php` replica la firma de `smtp_send_gmail()`** (y ya soporta CC):
  mantener ambos sincronizados al cambiarla, o verificar por curl.

## Entorno y despliegue

- Desarrollo en el servidor local `192.168.1.150`, docroot
  `/var/www/html/posto`; el working copy local es un espejo de esa carpeta.
- Producción: VPS `maticsson.ddns.net`, copia directa (sin CI). Crear el
  `posto-mail-creds.php` de producción aparte y dejar `APP_DEBUG` en `false`.
- Verificar con `php -l` y un envío real (curl de la guía o `test.php`). No hay
  suite de tests.
- En el dev server el vhost de `192.168.1.150` (saludable) redirige a HTTPS y
  tapa `/posto`; para probar por HTTP usar un Host que caiga en el vhost
  default, p. ej. `curl -H 'Host: posto.test' http://192.168.1.150/posto/api/send.php`.
- Git local sin remoto; commits marcados `v1.0.0` / `v1.1.0` (sin tags).
