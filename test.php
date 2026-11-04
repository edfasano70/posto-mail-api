<?php
// Formulario de prueba de la API de correo (posto).
// Envía directamente por SMTP de Gmail usando las mismas funciones de la API.

include __DIR__ . '/php/config.php';
include __DIR__ . '/php/display_errors.php';
include __DIR__ . '/php/smtp.php';

$msg = '';
$msgClass = '';
$emailDest = isset($_POST['email_dest']) ? trim((string) $_POST['email_dest']) : '';
$subject   = isset($_POST['subject'])    ? trim((string) $_POST['subject'])    : '';
$body      = isset($_POST['body'])       ? (string) $_POST['body']             : '';
$replyTo   = isset($_POST['reply_to'])   ? trim((string) $_POST['reply_to'])   : '';
$cc        = isset($_POST['cc'])         ? trim((string) $_POST['cc'])         : '';
$key       = isset($_POST['key'])        ? trim((string) $_POST['key'])        : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configuredKey = isset($api_key) ? (string) $api_key : '';
    if ($configuredKey === '' || !hash_equals($configuredKey, $key)) {
        $msg = 'API key inválida.';
        $msgClass = 'danger';
    } elseif (preg_match('/[\r\n]/', $emailDest . $subject . $replyTo . $cc)) {
        $msg = 'Parámetros inválidos.';
        $msgClass = 'danger';
    } elseif ($emailDest === '' || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
        $msg = 'email_dest no es un email válido.';
        $msgClass = 'danger';
    } elseif ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $msg = 'reply_to no es un email válido.';
        $msgClass = 'danger';
    } elseif ($cc !== '' && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
        $msg = 'cc no es un email válido.';
        $msgClass = 'danger';
    } elseif ($subject === '' || $body === '') {
        $msg = 'Completá subject y body.';
        $msgClass = 'danger';
    } else {
        $result = smtp_send_gmail($emailDest, $subject, $body, $replyTo, $cc, $smtp_user, $smtp_pass);
        if ($result['ok']) {
            $msg = 'Correo enviado con éxito.';
            $msgClass = 'success';
        } else {
            $msg = 'Error de envío: ' . $result['error'];
            $msgClass = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba de envío - API posto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
    <div class="container" style="max-width: 720px;">
        <h2 class="mb-3">Prueba de envío de correo</h2>

        <?php if ($msg !== '') : ?>
            <div class="alert alert-<?php echo $msgClass; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="test.php">
                    <div class="mb-3">
                        <label class="form-label">email_dest</label>
                        <input type="email" class="form-control" name="email_dest" required
                               value="<?php echo htmlspecialchars($emailDest); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">subject</label>
                        <input type="text" class="form-control" name="subject" required
                               value="<?php echo htmlspecialchars($subject); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">body</label>
                        <textarea class="form-control" name="body" rows="5" required><?php echo htmlspecialchars($body); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">reply_to (opcional)</label>
                        <input type="email" class="form-control" name="reply_to"
                               value="<?php echo htmlspecialchars($replyTo); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">cc (opcional)</label>
                        <input type="email" class="form-control" name="cc"
                               value="<?php echo htmlspecialchars($cc); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API key</label>
                        <input type="password" class="form-control" name="key" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
