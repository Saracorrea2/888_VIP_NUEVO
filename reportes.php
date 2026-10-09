<?php
$titulo='Reportes';require_once 'header.php';
$ventaVer=(int)($_GET['venta']??0);
$metodoFiltro=trim($_GET['metodo']??'');
$detalleVenta=null;$ventaSeleccionada=null;$ventaSubtotal=0;$ventaDescuento=0;

$metodosPago=[
 'efectivo'=>['nombre'=>'Efectivo','icono'=>'💵'],
 'nequi'=>['nombre'=>'Nequi','icono'=>'📱'],
 'daviplata'=>['nombre'=>'Daviplata','icono'=>'📲'],
 'tarjeta'=>['nombre'=>'Tarjeta','icono'=>'💳'],
 'transferencia'=>['nombre'=>'Bold','icono'=>'🏦'],
 'otro'=>['nombre'=>'Otro','icono'=>'✨']
];
$mesaNombrePorVenta=[];$qhMesa=$conn->query("SELECT datos FROM historial WHERE modulo='ventas' AND accion='cobrar' ORDER BY id ASC");if($qhMesa){while($hh=$qhMesa->fetch_assoc()){ $dd=json_decode((string)$hh['datos'],true);if(!is_array($dd))continue;$pp=(int)($dd['pedido']??0);if($pp>0&&!empty($dd['mesa_nombre']))$mesaNombrePorVenta[$pp]=(string)$dd['mesa_nombre'];}}

/* Anulación de una venta cerrada SIN modificar la estructura de la BD.
   La venta no se elimina: pasa a estado anulado, se devuelve el stock,
   se registra la reversión de caja y queda una auditoría completa. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'anular_venta') {
    try {
        validarCsrf();
        $pid = (int)($_POST['pedido_id'] ?? 0);
        $motivo = trim((string)($_POST['motivo_anulacion'] ?? ''));
        if ($pid <= 0) throw new RuntimeException('Venta no válida.');
        if ($motivo === '') throw new RuntimeException('Escribe el motivo de la anulación.');

        $conn->begin_transaction();
        $st = $conn->prepare("SELECT * FROM pedidos WHERE id=? FOR UPDATE");
        $st->bind_param('i', $pid);
        $st->execute();
        $venta = $st->get_result()->fetch_assoc();
        if (!$venta) throw new RuntimeException('La venta no existe.');
        if ($venta['estado'] !== 'cerrado') throw new RuntimeException('Solo se pueden anular ventas cerradas.');

        $st = $conn->prepare("SELECT d.id,d.producto_id,d.cantidad,d.precio,d.subtotal,p.nombre,p.stock FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id WHERE d.pedido_id=? FOR UPDATE");
        $st->bind_param('i', $pid);
        $st->execute();
        $detalles = $st->get_result();
        $devueltos = [];
        $uid = (int)($_SESSION['usuario']['id'] ?? 0);

        while ($d = $detalles->fetch_assoc()) {
            $cantidad = (float)$d['cantidad'];
            $antes = (float)$d['stock'];
            $nuevo = $antes + $cantidad;
            $up = $conn->prepare("UPDATE productos SET stock=? WHERE id=?");
            $up->bind_param('di', $nuevo, $d['producto_id']);
            $up->execute();

            $tipo = 'entrada';
            $origen = 'ajuste';
            $mot = 'Anulación venta #'.$pid;
            $obs = 'Devolución automática de stock por anulación. Motivo: '.$motivo;
            $mi = $conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)");
            $mi->bind_param('iissdddss', $d['producto_id'], $uid, $tipo, $origen, $cantidad, $antes, $nuevo, $mot, $obs);
            $mi->execute();

            $devueltos[] = [
                'producto' => $d['producto_id'],
                'nombre' => $d['nombre'],
                'cantidad' => $cantidad,
                'stock_anterior' => $antes,
                'stock_nuevo' => $nuevo
            ];
        }

        /* Reversión contable del movimiento que creó la venta.
           No se borra el movimiento original: se agrega su salida compensatoria. */
        $monto = ventaNeta((float)$venta['total'],$venta['metodo_pago']);
        $cajaId = !empty($venta['caja_id']) ? (int)$venta['caja_id'] : null;
        $concepto = 'Anulación venta #'.$pid.' - '.$motivo;
        $mc = $conn->prepare("INSERT INTO movimientos_caja(usuario_id,caja_id,pedido_id,tipo,monto,concepto) VALUES(?,?,?,'salida',?,?)");
        $mc->bind_param('iiids', $uid, $cajaId, $pid, $monto, $concepto);
        $mc->execute();

        $up = $conn->prepare("UPDATE pedidos SET estado='anulado' WHERE id=?");
        $up->bind_param('i', $pid);
        $up->execute();

        if (!empty($venta['mesa_id'])) {
            $mesaId = (int)$venta['mesa_id'];
            $up = $conn->prepare("UPDATE mesas SET estado='libre',nombre=CONCAT('Mesa ',id) WHERE id=?");
            $up->bind_param('i', $mesaId);
            $up->execute();
        }

        registrarHistorial($conn, 'ventas', 'anular', 'Venta #'.$pid.' anulada y stock devuelto', [
            'pedido' => $pid,
            'mesa' => $venta['mesa_id'],
            'caja' => $venta['caja_id'],
            'total_anulado' => (float)$venta['total'],
            'neto_revertido' => $monto,
            'metodo_pago' => $venta['metodo_pago'],
            'motivo' => $motivo,
            'productos_devuelto_stock' => $devueltos
        ]);

        $conn->commit();
        flash('success', 'Venta #'.$pid.' anulada. El stock fue devuelto y la reversión quedó registrada en caja e historial.');
        redir('reportes.php?venta='.$pid);
    } catch (Throwable $e) {
        if ($conn->in_transaction) $conn->rollback();
        flash('error', $e->getMessage());
        redir('reportes.php'.(!empty($_POST['pedido_id']) ? '?venta='.(int)$_POST['pedido_id'] : ''));
    }
}

/* Abrir una venta concreta. Si se llegó desde un medio de pago, conservamos ese filtro. */
if($ventaVer){
 $st=$conn->prepare("SELECT pe.id,pe.estado,pe.creado_en,pe.cerrado_en,pe.subtotal,pe.descuento,pe.total,pe.metodo_pago,pe.caja_id,pe.usuario_id,u.nombre usuario_nombre,m.nombre mesa FROM pedidos pe LEFT JOIN mesas m ON m.id=pe.mesa_id LEFT JOIN usuarios u ON u.id=pe.usuario_id WHERE pe.id=? AND pe.estado IN ('cerrado','anulado')");
 $st->bind_param('i',$ventaVer);$st->execute();$ventaSeleccionada=$st->get_result()->fetch_assoc();
 if($ventaSeleccionada){
  if(isset($mesaNombrePorVenta[(int)$ventaSeleccionada['id']])) $ventaSeleccionada['mesa']=$mesaNombrePorVenta[(int)$ventaSeleccionada['id']];
  $ventaSubtotal=(float)$ventaSeleccionada['subtotal'];
  $ventaDescuento=(float)$ventaSeleccionada['descuento'];
  $st=$conn->prepare("SELECT d.id,d.cantidad,d.precio,d.subtotal,p.nombre FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id WHERE d.pedido_id=? ORDER BY d.id");
  $st->bind_param('i',$ventaVer);$st->execute();$detalleVenta=$st->get_result();
  if($metodoFiltro==='' && !empty($ventaSeleccionada['metodo_pago'])) $metodoFiltro=$ventaSeleccionada['metodo_pago'];
 }
}



$ventasPorMetodo=[];
foreach($metodosPago as $m=>$info)$ventasPorMetodo[$m]=['ventas'=>0,'cantidad'=>0];
$qPago=$conn->query("SELECT COALESCE(metodo_pago,'otro') metodo_pago,total FROM pedidos WHERE estado='cerrado' ORDER BY id");
while($r=$qPago->fetch_assoc()){
 $m=strtolower(trim((string)$r['metodo_pago']));
 if(!isset($ventasPorMetodo[$m]))$m='otro';
 $ventasPorMetodo[$m]['ventas']+=ventaNeta((float)$r['total'],$m);
 $ventasPorMetodo[$m]['cantidad']++;
}
$totalVentasMedios=0;$totalCantidadMedios=0;
foreach($ventasPorMetodo as $i){$totalVentasMedios+=(float)$i['ventas'];$totalCantidadMedios+=(int)$i['cantidad'];}

/* Ventas del medio seleccionado. El filtro se conserva al abrir una venta. */
$ventas=null;
$ventasMetodo=null;
if($metodoFiltro!=='' && isset($metodosPago[$metodoFiltro])){
 $stFiltro=$conn->prepare("SELECT pe.id,pe.creado_en,pe.cerrado_en,pe.subtotal,pe.descuento,pe.total,pe.metodo_pago,pe.caja_id,u.nombre usuario_nombre,m.nombre mesa FROM pedidos pe LEFT JOIN mesas m ON m.id=pe.mesa_id LEFT JOIN usuarios u ON u.id=pe.usuario_id WHERE pe.estado='cerrado' AND pe.metodo_pago=? ORDER BY DATE(COALESCE(pe.cerrado_en,pe.creado_en)) DESC, COALESCE(pe.cerrado_en,pe.creado_en) DESC, pe.id DESC");
 $stFiltro->bind_param('s',$metodoFiltro);$stFiltro->execute();$ventasMetodo=$stFiltro->get_result();
}

$ventas=$conn->query("SELECT pe.id,pe.creado_en,pe.cerrado_en,pe.subtotal,pe.descuento,pe.total,pe.metodo_pago,pe.caja_id,u.nombre usuario_nombre,m.nombre mesa FROM pedidos pe LEFT JOIN mesas m ON m.id=pe.mesa_id LEFT JOIN usuarios u ON u.id=pe.usuario_id WHERE pe.estado IN ('cerrado','anulado') ORDER BY DATE(COALESCE(pe.cerrado_en,pe.creado_en)) DESC, COALESCE(pe.cerrado_en,pe.creado_en) DESC, pe.id DESC");
if(!$ventas){ $ventas=$conn->query("SELECT pe.id,pe.creado_en,pe.cerrado_en,pe.subtotal,pe.descuento,pe.total,pe.metodo_pago,pe.caja_id,u.nombre usuario_nombre,m.nombre mesa FROM pedidos pe LEFT JOIN mesas m ON m.id=pe.mesa_id LEFT JOIN usuarios u ON u.id=pe.usuario_id WHERE pe.estado='cerrado' ORDER BY DATE(COALESCE(pe.cerrado_en,pe.creado_en)) DESC, COALESCE(pe.cerrado_en,pe.creado_en) DESC, pe.id DESC"); }

$productosSalieron=$conn->query("SELECT p.nombre,COALESCE(SUM(d.cantidad),0) cantidad,COALESCE(SUM(d.subtotal),0) total FROM detalle_pedidos d JOIN pedidos pe ON pe.id=d.pedido_id JOIN productos p ON p.id=d.producto_id WHERE pe.estado='cerrado' GROUP BY d.producto_id,p.nombre ORDER BY cantidad DESC,p.nombre ASC");
$cierres=$conn->query("SELECT c.*,u.nombre usuario_nombre,COALESCE((SELECT SUM(mc.monto) FROM movimientos_caja mc LEFT JOIN pedidos pp ON pp.id=mc.pedido_id WHERE mc.caja_id=c.id AND mc.tipo='entrada' AND pp.metodo_pago='efectivo'),0) efectivo,COALESCE((SELECT SUM(mc.monto) FROM movimientos_caja mc WHERE mc.caja_id=c.id AND mc.tipo='salida'),0) salidas FROM cajas c LEFT JOIN usuarios u ON u.id=c.usuario_id ORDER BY c.id DESC LIMIT 30");
$cierreRows=[]; while($cr=$cierres->fetch_assoc()){ $cr['ventas_total']=0.0; $stCr=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE caja_id=? AND estado='cerrado'"); $stCr->bind_param('i',$cr['id']); $stCr->execute(); $rrCr=$stCr->get_result(); while($vrCr=$rrCr->fetch_assoc()) $cr['ventas_total'] += ventaNeta((float)$vrCr['total'],$vrCr['metodo_pago']); $cierreRows[]=$cr; }
?>
<style>
/* Reportes: tablas limpias, sin líneas horizontales entre registros */
.reportes-clean table { border-collapse: separate; border-spacing: 0; }
.reportes-clean table th,
.reportes-clean table td { border-bottom: 0 !important; }
.reportes-clean table tbody tr:hover { background: #fafafa; }
.reportes-clean table tbody tr td { padding-top: 13px; padding-bottom: 13px; }
</style>
<div class="reportes-clean">
<div class="toolbar"><div><span class="eyebrow">RESUMEN</span><h2>Reportes</h2><p>Consulta ventas por medio de pago y revisa el detalle completo de cada venta.</p></div></div>

<?php if($ventaSeleccionada): ?>
<section class="panel">
 <div class="section-head">
  <div><h3>Venta #<?=e($ventaSeleccionada['id'])?></h3><small>Información completa de la venta realizada.</small></div>
  <div class="actions"><a class="btn small" href="facturas.php?venta=<?=e($ventaSeleccionada['id'])?>" target="_blank">Ver / imprimir factura</a><a class="btn small" href="reportes.php<?=isset($metodosPago[$metodoFiltro])?'?metodo='.urlencode($metodoFiltro):''?>">Volver</a></div>
 </div>
 <div class="stats">
  <div class="stat"><small>Fecha y hora</small><b><?=e($ventaSeleccionada['cerrado_en']??$ventaSeleccionada['creado_en'])?></b></div>
  <div class="stat"><small>Mesa</small><b><?=e($ventaSeleccionada['mesa']??'Venta general')?></b></div>
  <div class="stat"><small>Usuario</small><b><?=e($ventaSeleccionada['usuario_nombre']??'Sistema')?></b></div>
  <div class="stat"><small>Método de pago</small><b><?=e($metodosPago[$ventaSeleccionada['metodo_pago']]['nombre']??($ventaSeleccionada['metodo_pago']??'—'))?></b></div>
  <div class="stat"><small>Caja</small><b>#<?=e($ventaSeleccionada['caja_id']??'—')?></b></div>
  <div class="stat"><small>Venta neta</small><b><?=dinero(ventaNeta((float)$ventaSeleccionada['total'],$ventaSeleccionada['metodo_pago']))?></b></div><?php if(esBold($ventaSeleccionada['metodo_pago'])): ?><div class="stat"><small>4×1000</small><b>-<?=dinero(ventaDescuento4x1000((float)$ventaSeleccionada['total'],$ventaSeleccionada['metodo_pago']))?></b></div><div class="stat"><small>Bold 1,49%</small><b>-<?=dinero(ventaComision((float)$ventaSeleccionada['total'],$ventaSeleccionada['metodo_pago']))?></b></div><div class="stat"><small>Neto recibido</small><b><?=dinero(ventaNeta((float)$ventaSeleccionada['total'],$ventaSeleccionada['metodo_pago']))?></b></div><?php endif; ?>
  <div class="stat"><small>Estado</small><b><?=e(strtoupper($ventaSeleccionada['estado']))?></b></div>
 </div>
 <?php if(($ventaSeleccionada['estado']??'')==='cerrado'): ?>
 <div class="alert" style="margin:16px 0;">
  <strong>Anular esta venta</strong> — la venta no se elimina; se devuelve el stock y se registra una reversión en caja. La anulación del POS no realiza automáticamente un reembolso en el banco/datáfono.
  <form method="post" style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
   <input type="hidden" name="csrf" value="<?=e(csrf())?>">
   <input type="hidden" name="accion" value="anular_venta">
   <input type="hidden" name="pedido_id" value="<?=e($ventaSeleccionada['id'])?>">
   <label style="min-width:280px;flex:1;">Motivo de anulación<input type="text" name="motivo_anulacion" required maxlength="255" placeholder="Ej.: error en la cuenta, cliente canceló..."></label>
   <button class="btn danger" onclick="return confirm('¿Confirmas anular esta venta? Se devolverá el stock y se registrará una salida compensatoria en caja.')">Anular venta</button>
  </form>
 </div>
 <?php else: ?>
 <div class="alert" style="margin:16px 0;"><strong>Venta anulada.</strong> Esta operación se conserva para auditoría y no vuelve a descontar stock.</div>
 <?php endif; ?>
 <div class="table-wrap"><table><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio vendido</th><th>Subtotal</th></tr></thead><tbody><?php while($d=$detalleVenta->fetch_assoc()):?><tr><td><strong><?=e($d['nombre'])?></strong></td><td><?=e($d['cantidad'])?></td><td><?=dinero((float)$d['precio'])?></td><td><?=dinero((float)$d['subtotal'])?></td></tr><?php endwhile;?></tbody><tfoot><tr><th colspan="3">Subtotal</th><th><?=dinero($ventaSubtotal)?></th></tr><tr><th colspan="3">Ajuste / descuento</th><th><?=dinero($ventaDescuento)?></th></tr><tr><th colspan="3">TOTAL FACTURA</th><th><?=dinero((float)$ventaSeleccionada['total'])?></th></tr><tr><th colspan="3">VENTA NETA</th><th><?=dinero(ventaNeta((float)$ventaSeleccionada['total'],$ventaSeleccionada['metodo_pago']))?></th></tr></tfoot></table></div>
</section>
<?php endif; ?>


<section class="panel table-wrap"><div class="section-head"><div><h3>Ventas por medio de pago</h3><small>Selecciona un medio para ver todas sus ventas. Desde ahí puedes abrir cada venta y consultar exactamente qué se vendió.</small></div></div><table><thead><tr><th>Medio de pago</th><th>Cantidad de ventas</th><th>Venta neta</th><th></th></tr></thead><tbody><?php foreach($metodosPago as $m=>$info):?><tr><td><strong><?=e($info['icono'].' '.$info['nombre'])?></strong></td><td><?=e($ventasPorMetodo[$m]['cantidad'])?></td><td><strong><?=dinero((float)$ventasPorMetodo[$m]['ventas'])?></strong></td><td><a class="btn small primary" href="reportes.php?metodo=<?=urlencode($m)?>">Ver ventas</a></td></tr><?php endforeach;?></tbody><tfoot><tr><th>TOTAL</th><th><?=e($totalCantidadMedios)?></th><th><?=dinero($totalVentasMedios)?></th><th></th></tr></tfoot></table></section>

<?php if($ventasMetodo!==null): ?>
<section class="panel table-wrap"><div class="section-head"><div><h3><?=e($metodosPago[$metodoFiltro]['icono'].' '.$metodosPago[$metodoFiltro]['nombre'])?> — ventas realizadas</h3><small>Estas son las ventas cerradas registradas con este medio. Puedes abrir cualquiera para ver el detalle completo.</small></div><a class="btn small" href="reportes.php">Ver todos</a></div><table><thead><tr><th>Venta</th><th>Fecha y hora</th><th>Mesa</th><th>Usuario</th><th>Caja</th><th>Subtotal</th><th>Ajuste</th><th>Venta neta</th><th></th></tr></thead><tbody><?php if($ventasMetodo->num_rows===0):?><tr><td colspan="9">No hay ventas cerradas registradas con este medio de pago.</td></tr><?php else: while($vm=$ventasMetodo->fetch_assoc()):?><tr><td><strong>#<?=e($vm['id'])?></strong></td><td><?=e($vm['cerrado_en']??$vm['creado_en'])?></td><td><strong><?=e($mesaNombrePorVenta[(int)$vm['id']]??($vm['mesa']??'Venta general'))?></strong></td><td><?=e($vm['usuario_nombre']??'Sistema')?></td><td>#<?=e($vm['caja_id']??'—')?></td><td><?=dinero((float)$vm['subtotal'])?></td><td><?=dinero((float)$vm['descuento'])?></td><td><strong><?=dinero(ventaNeta((float)$vm['total'],$vm['metodo_pago']))?></strong><?php if(esBold($vm['metodo_pago'])):?><small class="line-note">Bruto <?=dinero((float)$vm['total'])?> · descuentos <?=dinero(ventaDescuento4x1000((float)$vm['total'],$vm['metodo_pago'])+ventaComision((float)$vm['total'],$vm['metodo_pago']))?></small><?php endif;?></td><td><a class="btn small primary" href="reportes.php?venta=<?=e($vm['id'])?>&metodo=<?=urlencode($metodoFiltro)?>">Abrir venta</a> <a class="btn small" href="facturas.php?venta=<?=e($vm['id'])?>" target="_blank">Factura</a></td></tr><?php endwhile; endif;?></tbody></table></section>
<?php endif; ?>

<section class="panel table-wrap"><div class="section-head"><div><h3>Ventas realizadas</h3><small>Reporte de ventas cerradas y anuladas. Los cambios operativos quedan en Historial.</small></div></div><table><thead><tr><th>Venta</th><th>Fecha y hora</th><th>Mesa</th><th>Usuario</th><th>Caja</th><th>Método</th><th>Subtotal</th><th>Ajuste</th><th>Venta neta</th><th></th></tr></thead><tbody><?php while($v=$ventas->fetch_assoc()):?><tr><td><strong>#<?=e($v['id'])?></strong></td><td><?=e($v['cerrado_en']??$v['creado_en'])?></td><td><strong><?=e($mesaNombrePorVenta[(int)$v['id']]??($v['mesa']??'Venta general'))?></strong></td><td><?=e($v['usuario_nombre']??'Sistema')?></td><td>#<?=e($v['caja_id']??'—')?></td><td><?=e($metodosPago[$v['metodo_pago']]['nombre']??($v['metodo_pago']??'—'))?></td><td><?=dinero((float)$v['subtotal'])?></td><td><?=dinero((float)$v['descuento'])?></td><td><strong><?=dinero(ventaNeta((float)$v['total'],$v['metodo_pago']))?></strong><?php if(esBold($v['metodo_pago'])):?><small class="line-note">Bruto <?=dinero((float)$v['total'])?> · descuentos <?=dinero(ventaDescuento4x1000((float)$v['total'],$v['metodo_pago'])+ventaComision((float)$v['total'],$v['metodo_pago']))?></small><?php endif;?></td><td><a class="btn small primary" href="reportes.php?venta=<?=e($v['id'])?>">Abrir venta</a> <a class="btn small" href="facturas.php?venta=<?=e($v['id'])?>" target="_blank">Factura</a></td></tr><?php endwhile;?></tbody></table></section>


</div>
<?php require_once 'footer.php'; ?>
