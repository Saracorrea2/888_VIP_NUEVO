<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/conexion.php';

function usuarioActual(): ?array { return $_SESSION['usuario'] ?? null; }

function requerirLogin(): void {
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function esAdmin(): bool {
    return ($_SESSION['usuario']['rol'] ?? '') === 'admin';
}

function registrarHistorial(mysqli $conn, string $modulo, string $accion, string $descripcion, ?array $datos=null): void {
    $uid = $_SESSION['usuario']['id'] ?? null;
    $json = $datos ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null;
    $st = $conn->prepare("INSERT INTO historial(usuario_id,modulo,accion,descripcion,datos) VALUES(?,?,?,?,?)");
    $st->bind_param('issss', $uid, $modulo, $accion, $descripcion, $json);
    $st->execute();
}
