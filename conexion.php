<?php
declare(strict_types=1);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = '888_vip';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    // Colombia (UTC-5): evita que las ventas cerradas queden en el día siguiente.
    $conn->query("SET time_zone = '-05:00'");
} catch (Throwable $e) {
    http_response_code(500);
    die('No se pudo conectar con MySQL. Verifica que Apache y MySQL estén activos en XAMPP y que exista la base 888_vip.');
}
