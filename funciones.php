<?php
require_once __DIR__ . '/auth.php';

function e(string|int|float|null $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dinero(float $v): string { return '$ ' . number_format($v, 0, ',', '.'); }

// Costos aplicados a pagos Bold: 4x1000 (0,40%) + comisión Bold (1,49%).
function boldCuatroxMil(float $valor): float { return round($valor * 0.004, 2); }
function boldComision(float $valor): float { return round($valor * 0.0149, 2); }
function boldDescuentos(float $valor): float { return boldCuatroxMil($valor) + boldComision($valor); }
function boldNeto(float $valor): float { return round($valor - boldDescuentos($valor), 2); }
function esBold(?string $metodo): bool { return strtolower(trim((string)$metodo)) === 'transferencia' || strtolower(trim((string)$metodo)) === 'bold'; }
function ventaDescuento4x1000(float $valor, ?string $metodo): float { return esBold($metodo) ? boldCuatroxMil($valor) : 0.0; }
function ventaComision(float $valor, ?string $metodo): float { return esBold($metodo) ? boldComision($valor) : 0.0; }
function ventaNeta(float $valor, ?string $metodo): float { return round($valor - ventaDescuento4x1000($valor,$metodo) - ventaComision($valor,$metodo), 2); }
function nombreMetodoPago(?string $metodo): string { return esBold($metodo) ? 'Bold' : ucfirst((string)$metodo); }

function flash(string $tipo, string $mensaje): void { $_SESSION['flash']=['tipo'=>$tipo,'mensaje'=>$mensaje]; }
function obtenerFlash(): ?array { $x=$_SESSION['flash']??null; unset($_SESSION['flash']); return $x; }
function redir(string $url): never { header('Location: '.$url); exit; }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function validarCsrf(): void { if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')) throw new RuntimeException('Solicitud no válida. Recarga la página e inténtalo de nuevo.'); }

function cajaAbierta(mysqli $conn): ?array {
    $q=$conn->query("SELECT c.*,u.nombre usuario_nombre FROM cajas c LEFT JOIN usuarios u ON u.id=c.usuario_id WHERE c.estado='abierta' ORDER BY c.id DESC LIMIT 1");
    return $q ? $q->fetch_assoc() : null;
}

function actualizarTotalesPedido(mysqli $conn, int $pedidoId): float {
    $st=$conn->prepare("SELECT COALESCE(SUM(subtotal),0) subtotal FROM detalle_pedidos WHERE pedido_id=?");
    $st->bind_param('i',$pedidoId); $st->execute();
    $subtotal=(float)$st->get_result()->fetch_assoc()['subtotal'];
    // Cada cambio de productos/cantidades/precios elimina cualquier ajuste anterior.
    // Los descuentos solo se aplican desde el formulario de ajuste manual y con confirmación.
    $descuento=0.0;
    $total=$subtotal;
    $st=$conn->prepare("UPDATE pedidos SET subtotal=?,descuento=0,total=? WHERE id=?");
    $st->bind_param('ddi',$subtotal,$total,$pedidoId); $st->execute();
    return $total;
}

function procesarGastosRecurrentes(mysqli $conn): void {
    try {
        $hoy=date('Y-m-d');
        $st=$conn->prepare("SELECT * FROM gastos WHERE tipo='fijo' AND frecuencia<>'unico' AND estado='activo' AND proxima_fecha IS NOT NULL AND proxima_fecha<=?");
        $st->bind_param('s',$hoy); $st->execute(); $rs=$st->get_result();
        while($g=$rs->fetch_assoc()) {
            $next=$g['proxima_fecha'];
            switch($g['frecuencia']) {
                case 'diario': $next=date('Y-m-d',strtotime($next.' +1 day')); break;
                case 'semanal': $next=date('Y-m-d',strtotime($next.' +7 day')); break;
                case 'quincenal': $next=date('Y-m-d',strtotime($next.' +15 day')); break;
                case 'mensual': $next=date('Y-m-d',strtotime($next.' +1 month')); break;
            }
            try {
                $uid=$_SESSION['usuario']['id']??null; $caja=cajaAbierta($conn); $cajaId=$caja?(int)$caja['id']:null;
                $tipo='fijo'; $freq='unico';
                $ins=$conn->prepare("INSERT INTO gastos(usuario_id,tipo,concepto,monto,frecuencia,fecha_inicio,proxima_fecha,estado,gasto_origen_id,caja_id) VALUES(?,?,?,?,?,?,?,'pagado',?,?)");
                $ins->bind_param('issdsssii',$uid,$tipo,$g['concepto'],$g['monto'],$freq,$g['proxima_fecha'],$g['proxima_fecha'],$g['id'],$cajaId); $ins->execute(); $gid=$ins->insert_id;
                $concepto='Gasto fijo automático: '.$g['concepto'];
                $c=$conn->prepare("INSERT INTO movimientos_caja(usuario_id,caja_id,gasto_id,tipo,monto,concepto) VALUES(?,?,?,'salida',?,?)");
                $c->bind_param('iiids',$uid,$cajaId,$gid,$g['monto'],$concepto); $c->execute();
                $up=$conn->prepare("UPDATE gastos SET proxima_fecha=? WHERE id=?"); $up->bind_param('si',$next,$g['id']); $up->execute();
                registrarHistorial($conn,'gastos','automatico','Gasto recurrente generado',['gasto_origen'=>$g['id'],'gasto'=>$gid,'monto'=>$g['monto']]);
            } catch(Throwable $e) {}
        }
    } catch(Throwable $e) {}
}
