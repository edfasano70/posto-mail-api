# AGENTS.md

App PHP pura: API de envío de correo vía SMTP de Gmail. Las convenciones de
abajo se heredan de las apps hermanas en `/var/www/html` (`openhouse`, `acla`).

## Estado actual (API de correo)

- `api/send.php` — endpoint público: `GET /posto/api/send.php?key=...&json={...}`.
  Parámetros (`json` como objeto o como GET individuales): `email_dest`,
  `subject`, `body`, `reply_to` (opcional). Responde JSON
  `{"success":bool}` o `{"success":false,"error":...}`.
- `php/smtp.php` — cliente SMTP puro (sin librerías): `smtp_send_gmail()`
  contra `smtp.gmail.com:465` (SMTPS, AUTH LOGIN). Requiere ext-openssl.
- Credenciales Gmail + API key viven en `/var/www/posto-mail-creds.php`
  (fuera del webroot; plantilla `php/mail-creds.example.php`): expone
  `$smtp_user`, `$smtp_pass` (App Password), `$api_key`.
- PHP `mail()`/MTA local NO se usa: todo sale por SMTP de Gmail.

## Stack y entorno

- PHP sin framework + MySQL/MariaDB, servido por Apache directamente desde el
  webroot. Sin composer, sin build, sin tests.
- Servidor local de desarrollo: `192.168.1.150`, docroot `/var/www/html/posto`.
  Producción: VPS `maticsson.ddns.net` (despliegue por copia directa; ver
  `../openhouse/GUIA_DESPLIEGUE.txt`).
- MySQL (extensión **mysqli**, no PDO).

## Convenciones de código (patrón de las apps hermanas)

- **Credenciales NUNCA en el código.** Cada archivo que use BD hace:
  `include '/var/www/acla-creds.php';` (fuera del webroot) que expone
  `$servername`, `$username`, `$password`. No definir credenciales locales.
- Cabecera estándar de un endpoint:
  `include 'php/config.php'; include 'php/display_errors.php'; include '/var/www/acla-creds.php'; include 'php/session.php';`
- Sessión: `secure_session_start()` con **nombre de sesión por app** (evitar
  colisión entre apps del mismo dominio). Token CSRF en `$_SESSION['csrf']`.
- Errores visibles solo con `define('APP_DEBUG', true)` en `php/config.php`;
  `php/display_errors.php` respeta esa bandera. Nunca mostrar errores sin debug.
- Conexión: `new mysqli($servername, $username, $password, $dbname)`.
- BD de autenticación separada (`* -app` con tabla `users`) + whitelist de BDs
  operativas en `config.php`; validar el valor del usuario contra la whitelist.
- Autenticación usa `session_regenerate_id(true)` tras login y cookies
  `HttpOnly`/`SameSite=Lax`.
- Comentarios en español, alineados con los hermanos.

## Gotchas verificados

- Los `include` relativos (`include 'php/config.php'`) solo funcionan para
  archivos en la raíz de la app. Para archivos en subcarpetas (p. ej. `api/`)
  usar rutas absolutas con `__DIR__`: `include __DIR__ . '/../php/config.php';`.
- Endpoint en subcarpeta debe incluir también el cliente SMTP:
  `include __DIR__ . '/../php/smtp.php';` antes de llamar `smtp_send_gmail()`.
