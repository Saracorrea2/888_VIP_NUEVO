<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/conexion.php';

if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$setupRequired = (int) $conn->query('SELECT COUNT(*) FROM usuarios')->fetch_row()[0] === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($setupRequired) {
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $clave = $_POST['clave'] ?? '';
        $confirmacion = $_POST['confirmacion'] ?? '';

        if ($nombre === '' || strlen($nombre) > 100) {
            $error = 'Escribe un nombre de hasta 100 caracteres.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $usuario)) {
            $error = 'El usuario debe tener entre 3 y 50 caracteres: letras, números, punto, guion o guion bajo.';
        } elseif (strlen($clave) < 12) {
            $error = 'La contraseña debe tener al menos 12 caracteres.';
        } elseif ($clave !== $confirmacion) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $st = $conn->prepare("INSERT INTO usuarios (nombre, usuario, clave, rol, activo) VALUES (?, ?, ?, 'admin', 1)");
            $st->bind_param('sss', $nombre, $usuario, $hash);
            $st->execute();
            $_SESSION['usuario'] = [
                'id' => $st->insert_id,
                'nombre' => $nombre,
                'usuario' => $usuario,
                'rol' => 'admin',
            ];
            header('Location: index.php');
            exit;
        }
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $clave = $_POST['clave'] ?? '';
        $st = $conn->prepare("SELECT id, nombre, usuario, clave, rol FROM usuarios WHERE usuario = ? AND activo = 1 LIMIT 1");
        $st->bind_param('s', $usuario);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $ok = $r && (
            hash_equals((string) $r['clave'], hash('sha256', $clave))
            || password_verify($clave, $r['clave'])
        );

        if ($ok) {
            $_SESSION['usuario'] = [
                'id' => $r['id'],
                'nombre' => $r['nombre'],
                'usuario' => $r['usuario'],
                'rol' => $r['rol'],
            ];
            header('Location: index.php');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>888 VIP POS</title>
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body class="login-body">
    <form class="login-card" method="post">
        <div class="login-mark">888</div>
        <h1>888 VIP POS</h1>
        <?php if ($setupRequired): ?>
            <p>Crea la cuenta administradora para iniciar.</p>
            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <label>Nombre<input name="nombre" maxlength="100" autocomplete="name" required></label>
            <label>Usuario<input name="usuario" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username" required></label>
            <label>Contraseña (mínimo 12 caracteres)<input type="password" name="clave" minlength="12" autocomplete="new-password" required></label>
            <label>Confirma la contraseña<input type="password" name="confirmacion" minlength="12" autocomplete="new-password" required></label>
            <button class="btn primary full">Crear administrador</button>
        <?php else: ?>
            <p>Ventas, mesas, inventario y caja</p>
            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <label>Usuario<input name="usuario" autocomplete="username" required></label>
            <label>Contraseña<input type="password" name="clave" autocomplete="current-password" required></label>
            <button class="btn primary full">Ingresar</button>
        <?php endif; ?>
    </form>
</body>
</html>
