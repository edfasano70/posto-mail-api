# posto-mail-api

API mínima en PHP para enviar correo por SMTP de Gmail. Sin framework, sin
Composer, sin base de datos y sin build: solo un endpoint HTTP y un cliente SMTP
propio.

## Características

- Endpoint público `GET /posto/api/send.php`.
- Autenticación por API key (`key`).
- Envío vía `smtp.gmail.com:465` (SMTPS, AUTH LOGIN) con una App Password de
  Gmail. Requiere la extensión `openssl` de PHP.
- Destinatario, asunto, cuerpo, `Reply-To` y `CC` opcionales.
- Asunto codificado en RFC 2047 (UTF-8), dot-stuffing del cuerpo y rechazo de
  CR/LF para evitar header injection.
- Respuestas JSON y códigos HTTP claros.

## Requisitos

- Apache + PHP 8.x con `openssl`.
- Cuenta de Gmail con verificación en dos pasos y una
  [App Password](https://myaccount.google.com/apppasswords).

## Instalación

1. Clonar/copiar la carpeta en el webroot, por ejemplo `/var/www/html/posto`.
2. Crear el archivo de credenciales **fuera del webroot**:

   ```bash
   cp php/mail-creds.example.php /var/www/posto-mail-creds.php
   ```

3. Editarlo con los datos reales:

   ```php
   <?php
   $smtp_user = 'TU_CUENTA@gmail.com';
   $smtp_pass = 'APP_PASSWORD_16_CARACTERES';
   $api_key   = 'CLAVE_SECRETA_ALEATORIA';
   ```

4. Verificar que exista `openssl`:

   ```bash
   php -m | grep -i openssl
   ```

5. Probar un envío (ver más abajo).

La guía completa de instalación, despliegue y solución de problemas está en
[`GUIA_INSTALACION.txt`](GUIA_INSTALACION.txt).

## Uso de la API

```
GET /posto/api/send.php
```

| Parámetro    | Requerido | Descripción                          |
| ------------ | --------- | ------------------------------------ |
| `key`        | sí        | API key definida en las credenciales |
| `email_dest` | sí        | Destinatario                         |
| `subject`    | sí        | Asunto                               |
| `body`       | sí        | Cuerpo en texto plano                |
| `reply_to`   | no        | Dirección `Reply-To`                 |
| `cc`         | no        | Dirección en copia (`CC`)            |

Los parámetros se pueden enviar como JSON en un solo parámetro `json` (alias
`data`) o como parámetros GET individuales.

```bash
curl -G "https://SERVIDOR/posto/api/send.php" \
  --data-urlencode "key=CLAVE_API" \
  --data-urlencode 'json={"email_dest":"destino@ejemplo.com","subject":"Hola","body":"Mensaje de prueba","reply_to":"remite@ejemplo.com","cc":"copia@ejemplo.com"}'
```

También:

```bash
curl -G "https://SERVIDOR/posto/api/send.php" \
  --data-urlencode "key=CLAVE_API" \
  --data-urlencode "email_dest=destino@ejemplo.com" \
  --data-urlencode "subject=Hola" \
  --data-urlencode "body=Mensaje de prueba"
```

### Respuestas

| HTTP | Cuerpo                                              | Significado                |
| ---- | --------------------------------------------------- | -------------------------- |
| 200  | `{"success":true}`                                  | Correo enviado             |
| 400  | `{"success":false,"error":"..."}`                   | Parámetros inválidos       |
| 401  | `{"success":false,"error":"API key inválida."}`     | API key incorrecta         |
| 500  | `{"success":false,"error":"..."}`                   | Error de configuración     |
| 502  | `{"success":false,"error":"..."}`                   | Falló el envío SMTP        |

## Formulario de prueba

`test.php` es un formulario web para enviar un correo de prueba pidiendo la API
key. Úsalo solo en desarrollo o restringí su acceso en producción.

## Estructura

```
api/send.php              Endpoint HTTP
php/smtp.php              Cliente SMTP (Gmail)
php/config.php            APP_DEBUG, APP_VERSION y carga de credenciales
php/display_errors.php    Control de errores según APP_DEBUG
php/mail-creds.example.php  Plantilla de credenciales
test.php                  Formulario de prueba
GUIA_INSTALACION.txt      Guía operativa completa
```

## Seguridad

- Las credenciales reales nunca se versionan: viven en
  `/var/www/posto-mail-creds.php` (fuera del webroot) y `.gitignore` excluye
  `*creds*.php`.
- Usar una API key larga y aleatoria. La key viaja en la URL: puede quedar en
  logs de Apache o proxies.
- En producción dejar `APP_DEBUG` en `false` y no exponer `test.php`.

## Despliegue

Copia directa de la carpeta al webroot del servidor (sin CI), creando el archivo
de credenciales de producción por separado. Ver
[`GUIA_INSTALACION.txt`](GUIA_INSTALACION.txt) sección 9.
