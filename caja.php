<?php
$titulo='Caja'; require_once 'header.php';
$stockCajaId=(int)($_GET['stock_caja']??0);
$detalleCajaId=(int)($_GET['detalle_caja']??0);
$detalleCaja=null; $ventasCierre=null; $gastosCierre=null; $ventasCierreTotal=0; $gastosCierreTotal=0;
if($detalleCajaId>0){
 $stDC=$conn->prepare("SELECT c.*,u.nombre usuario_nombre FROM cajas c LEFT JOIN usuarios u ON u.id=c.usuario_id WHERE c.id=?");
 $stDC->bind_param('i',$detalleCajaId); $stDC->execute(); $detalleCaja=$stDC->get_result()->fetch_assoc();
 if($detalleCaja){
  $stV=$conn->prepare("SELECT pe.id,pe.cerrado_en,pe.total,pe.metodo_pago,pe.mesa_id,m.nombre mesa,u.nombre usuario_nombre FROM pedidos pe LEFT JOIN mesas m ON m.id=pe.mesa_id LEFT JOIN usuarios u ON u.id=pe.usuario_id WHERE pe.caja_id=? AND pe.estado='cerrado' ORDER BY COALESCE(pe.cerrado_en,pe.creado_en) ASC,pe.id ASC");
  $stV->bind_param('i',$detalleCajaId); $stV->execute(); $ventasCierre=$stV->get_result();
  $stG=$conn->prepare("SELECT g.id,g.fecha_inicio,g.creado_en,g.concepto,g.tipo,mc.monto,g.estado,p.nombre producto FROM movimientos_caja mc INNER JOIN gastos g ON g.id=mc.gasto_id LEFT JOIN productos p ON p.id=g.producto_id WHERE mc.caja_id=? AND mc.tipo='salida' AND mc.gasto_id IS NOT NULL ORDER BY mc.creado_en ASC,mc.id ASC");
  $stG->bind_param('i',$detalleCajaId); $stG->execute(); $gastosCierre=$stG->get_result();
  $stT=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE caja_id=? AND estado='cerrado'"); $stT->bind_param('i',$detalleCajaId); $stT->execute(); $rsT=$stT->get_result(); $ventasCierreTotal=0.0; while($vr=$rsT->fetch_assoc()) $ventasCierreTotal += ventaNeta((float)$vr['total'],$vr['metodo_pago']);
  $stT=$conn->prepare("SELECT COALESCE(SUM(monto),0) total FROM movimientos_caja WHERE caja_id=? AND tipo='salida' AND gasto_id IS NOT NULL"); $stT->bind_param('i',$detalleCajaId); $stT->execute(); $gastosCierreTotal=(float)$stT->get_result()->fetch_assoc()['total'];
 }
}
$stockCierreSeleccionado=null;
if($stockCajaId>0){
 $stStock=$conn->prepare("SELECT datos,creado_en,usuario_id FROM historial WHERE modulo='caja' AND accion='cerrar' ORDER BY id DESC");
 $stStock->execute(); $rsStock=$stStock->get_result();
 while($hs=$rsStock->fetch_assoc()){
  $dd=json_decode((string)$hs['datos'],true);
  if(is_array($dd) && (int)($dd['caja']??0)===$stockCajaId && !empty($dd['stock_cierre'])){
   $stockCierreSeleccionado=['datos'=>$dd,'fecha'=>$hs['creado_en']]; break;
  }
 }
}
$caja=cajaAbierta($conn);
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  validarCsrf(); $accion=$_POST['accion']??''; $uid=(int)($_SESSION['usuario']['id']??0);
  if($accion==='abrir'){
   if($caja) throw new RuntimeException('Ya hay una caja abierta.');
   $inicial=(float)($_POST['monto_inicial']??0); if($inicial<0) throw new RuntimeException('El monto inicial no puede ser negativo.');
   $st=$conn->prepare("INSERT INTO cajas(usuario_id,monto_inicial,estado) VALUES(?,?,'abierta')");$st->bind_param('id',$uid,$inicial);$st->execute();
   registrarHistorial($conn,'caja','abrir','Caja abierta',['caja'=>$st->insert_id,'monto_inicial'=>$inicial]); flash('success','Caja abierta correctamente.');
  }
  if($accion==='cerrar'){
   if(!$caja) throw new RuntimeException('No hay una caja abierta.');
   $final=(float)($_POST['monto_final']??0); if($final<0) throw new RuntimeException('El efectivo final no puede ser negativo.');
   $cid=(int)$caja['id'];
   $st=$conn->prepare("SELECT COUNT(*) c FROM pedidos WHERE estado='abierto' AND caja_id=?");$st->bind_param('i',$cid);$st->execute();if((int)$st->get_result()->fetch_assoc()['c']>0)throw new RuntimeException('No puedes cerrar la caja mientras existan cuentas abiertas. Primero cobra o anula las cuentas pendientes.');
   // La caja física solo cuenta efectivo. Bold/Nequi/Daviplata/Tarjeta no aumentan el efectivo físico.
   $st=$conn->prepare("SELECT COALESCE(SUM(CASE WHEN pe.metodo_pago='efectivo' THEN mc.monto ELSE 0 END),0) efectivo_ventas, COALESCE(SUM(CASE WHEN mc.tipo='salida' THEN mc.monto ELSE 0 END),0) gastos FROM movimientos_caja mc LEFT JOIN pedidos pe ON pe.id=mc.pedido_id WHERE mc.caja_id=?");$st->bind_param('i',$cid);$st->execute();$resCaja=$st->get_result()->fetch_assoc();
   $efectivoVentas=(float)($resCaja['efectivo_ventas']??0);$gastosCaja=(float)($resCaja['gastos']??0);$efectivoCalculado=(float)$caja['monto_inicial']+$efectivoVentas-$gastosCaja;
   $dif=$final-$efectivoCalculado;
   $obs=trim($_POST['observacion']??'');
   $stockCierre=[];
   $rsProductos=$conn->query("SELECT id,nombre,stock,unidad FROM productos ORDER BY nombre ASC");
   while($prod=$rsProductos->fetch_assoc()){
    $stockCierre[]=['producto_id'=>(int)$prod['id'],'producto'=>(string)$prod['nombre'],'stock'=>(float)$prod['stock'],'unidad'=>(string)$prod['unidad']];
   }
   $st=$conn->prepare("UPDATE cajas SET monto_esperado=?,monto_final=?,diferencia=?,estado='cerrada',cierre_en=NOW(),observacion=? WHERE id=? AND estado='abierta'");$st->bind_param('dddsi',$efectivoCalculado,$final,$dif,$obs,$cid);$st->execute();
   registrarHistorial($conn,'caja','cerrar','Caja cerrada',['caja'=>$cid,'base'=>$caja['monto_inicial'],'ventas_efectivo'=>$efectivoVentas,'gastos'=>$gastosCaja,'final'=>$final,'diferencia'=>$dif,'stock_cierre'=>$stockCierre]); flash('success','Caja cerrada. El cierre quedó guardado en reportes.');
  }
  redir('caja.php');
 }
}catch(Throwable $e){flash('error',$e->getMessage());redir('caja.php');}
$caja=cajaAbierta($conn);
$ult=$conn->query("SELECT c.*,u.nombre usuario_nombre, COALESCE((SELECT SUM(mc.monto) FROM movimientos_caja mc WHERE mc.caja_id=c.id AND mc.tipo='salida' AND mc.gasto_id IS NOT NULL),0) gastos_total_cierre FROM cajas c LEFT JOIN usuarios u ON u.id=c.usuario_id ORDER BY c.id DESC LIMIT 20");
$ultRows=[]; while($cr=$ult->fetch_assoc()){ $stVC=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE caja_id=? AND estado='cerrado'"); $stVC->bind_param('i',$cr['id']); $stVC->execute(); $rsVC=$stVC->get_result(); $cr['ventas_total_cierre']=0.0; while($vr=$rsVC->fetch_assoc()) $cr['ventas_total_cierre']+=ventaNeta((float)$vr['total'],$vr['metodo_pago']); $ultRows[]=$cr; }
if($caja){
 $cid=(int)$caja['id'];
 $st=$conn->prepare("SELECT COALESCE(SUM(CASE WHEN pe.metodo_pago='efectivo' THEN mc.monto ELSE 0 END),0) efectivo_ventas,COALESCE(SUM(CASE WHEN mc.tipo='salida' THEN mc.monto ELSE 0 END),0) salidas FROM movimientos_caja mc LEFT JOIN pedidos pe ON pe.id=mc.pedido_id WHERE mc.caja_id=?");
 $st->bind_param('i',$cid);$st->execute();$mov=$st->get_result()->fetch_assoc();
 $stN=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE caja_id=? AND estado='cerrado'");$stN->bind_param('i',$cid);$stN->execute();$rsN=$stN->get_result();$ventasNetaCaja=0.0;while($vn=$rsN->fetch_assoc())$ventasNetaCaja+=ventaNeta((float)$vn['total'],$vn['metodo_pago']);
 $mov['ventas_total']=$ventasNetaCaja;
 $mov['efectivo_ventas']=(float)($mov['efectivo_ventas']??0);
 $mov['salidas']=(float)($mov['salidas']??0);
 $efectivoCaja=(float)$caja['monto_inicial']+$mov['efectivo_ventas']-$mov['salidas'];
}else{$mov=['efectivo_ventas'=>0,'salidas'=>0,'ventas_total'=>0];$efectivoCaja=0;}
?>
<div class="toolbar"><div><span class="eyebrow">CONTROL</span><h2>Caja</h2><p>Cada venta queda asociada a la caja abierta y el cierre genera su propio resumen.</p></div></div>
<?php if($detalleCaja): ?>
<section class="panel table-wrap">
 <div class="section-head"><div><h3>Caja #<?=e($detalleCajaId)?> — ventas y gastos del cierre</h3><small>Apertura: <?=e($detalleCaja['apertura_en'])?> · Cierre: <?=e($detalleCaja['cierre_en']??'—')?></small></div><a class="btn small" href="caja.php">Volver a Caja</a></div>
 <div class="stats"><div class="stat"><small>Ventas del cierre</small><b><?=dinero($ventasCierreTotal)?></b></div><div class="stat"><small>Gastos del cierre</small><b><?=dinero($gastosCierreTotal)?></b></div><div class="stat"><small>Neto ventas - gastos</small><b><?=dinero($ventasCierreTotal-$gastosCierreTotal)?></b></div></div>
 <h3 style="margin-top:20px">Ventas de este cierre</h3>
 <table><thead><tr><th>Venta</th><th>Fecha y hora</th><th>Mesa</th><th>Método</th><th>Venta neta</th></tr></thead><tbody>
 <?php if(!$ventasCierre || !$ventasCierre->num_rows): ?><tr><td colspan="5">No hay ventas registradas en este cierre.</td></tr><?php else: while($vc=$ventasCierre->fetch_assoc()): ?><tr><td><strong>#<?=e($vc['id'])?></strong></td><td><?=e($vc['cerrado_en']??'—')?></td><td><?=e($vc['mesa']??'Venta general')?></td><td><?=e(nombreMetodoPago($vc['metodo_pago']??'—'))?></td><td><strong><?=dinero(ventaNeta((float)$vc['total'],$vc['metodo_pago']))?></strong><?php if(esBold($vc['metodo_pago'])):?><small class="line-note">Bruto <?=dinero((float)$vc['total'])?> · 4x1000 <?=dinero(ventaDescuento4x1000((float)$vc['total'],$vc['metodo_pago']))?> · Bold <?=dinero(ventaComision((float)$vc['total'],$vc['metodo_pago']))?></small><?php endif;?></td></tr><?php endwhile; endif; ?>
 </tbody></table>
 <h3 style="margin-top:24px">Gastos de este cierre</h3>
 <table><thead><tr><th>Gasto</th><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Producto</th><th>Valor</th></tr></thead><tbody>
 <?php if(!$gastosCierre || !$gastosCierre->num_rows): ?><tr><td colspan="6">No hay gastos registrados en este cierre.</td></tr><?php else: while($gc=$gastosCierre->fetch_assoc()): ?><tr><td>#<?=e($gc['id'])?></td><td><?=e($gc['fecha_inicio'])?></td><td><?=e($gc['concepto'])?></td><td><?=e($gc['tipo'])?></td><td><?=e($gc['producto']??'—')?></td><td><strong><?=dinero((float)$gc['monto'])?></strong></td></tr><?php endwhile; endif; ?>
 </tbody></table>
</section>
<?php endif; ?>

<?php if($stockCierreSeleccionado): $sc=$stockCierreSeleccionado['datos']; ?>
<section class="panel table-wrap"><div class="section-head"><div><h3>Stock al cierre de caja #<?=e($stockCajaId)?></h3><small>Existencias registradas exactamente al momento de cerrar la caja · <?=e($stockCierreSeleccionado['fecha'])?></small></div><a class="btn small" href="caja.php">Volver a Caja</a></div><table><thead><tr><th>Producto</th><th>Stock al cierre</th><th>Unidad</th></tr></thead><tbody><?php foreach(($sc['stock_cierre']??[]) as $sp): ?><tr><td><?=e($sp['producto']??'')?></td><td><strong><?=e(rtrim(rtrim(number_format((float)($sp['stock']??0),2,'.',''),'0'),'.'))?></strong></td><td><?=e($sp['unidad']??'unidad')?></td></tr><?php endforeach; ?></tbody></table></section>
<?php endif; ?>
<?php if($caja): ?>
<div class="stats"><div class="stat"><small>Caja #<?=$caja['id']?></small><b>ABIERTA</b></div><div class="stat"><small>Base inicial</small><b><?=dinero((float)$caja['monto_inicial'])?></b></div><div class="stat"><small>Ventas netas</small><b><?=dinero((float)$mov['ventas_total'])?></b></div><div class="stat"><small>Gastos</small><b><?=dinero((float)$mov['salidas'])?></b></div><div class="stat"><small>Efectivo en caja</small><b><?=dinero((float)$efectivoCaja)?></b></div></div>
<section class="panel"><h3>Cerrar caja #<?=$caja['id']?></h3><p>Escribe el efectivo/valor final contado. El sistema separa las ventas netas, los gastos y el efectivo físico. Bold no se suma al efectivo de caja.</p><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="cerrar"><label>Valor final contado<input type="number" step="0.01" min="0" name="monto_final" required></label><label class="span-2">Observación<textarea name="observacion" placeholder="Ej. cierre normal, faltante, sobrante..."></textarea></label><button class="btn primary">Cerrar caja</button></form></section>
<?php else: ?>
<section class="panel"><h2>Abrir nueva caja</h2><p>Abre la caja antes de empezar a cobrar. Las ventas nuevas se guardarán dentro de este cierre.</p><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="abrir"><label>Monto inicial<input type="number" step="0.01" min="0" name="monto_inicial" value="0" required></label><button class="btn primary">Abrir caja</button></form></section>
<?php endif; ?>
<section class="panel table-wrap"><h3>Últimos cierres</h3><table><thead><tr><th>Caja</th><th>Apertura</th><th>Cierre</th><th>Inicial</th><th>Final</th><th>Diferencia</th><th>Ventas netas</th><th>Gastos</th><th>Estado</th><th>Detalle</th><th>Stock</th></tr></thead><tbody><?php foreach($ultRows as $c):?><tr><td>#<?=e($c['id'])?></td><td><?=e($c['apertura_en'])?></td><td><?=e($c['cierre_en']??'—')?></td><td><?=dinero((float)$c['monto_inicial'])?></td><td><?=isset($c['monto_final'])?dinero((float)$c['monto_final']):'—'?></td><td><?=isset($c['diferencia'])?dinero((float)$c['diferencia']):'—'?></td><td><strong><?=dinero((float)$c['ventas_total_cierre'])?></strong></td><td><?=dinero((float)$c['gastos_total_cierre'])?></td><td><?=e($c['estado'])?></td><td><a class="btn small" href="caja.php?detalle_caja=<?=e($c['id'])?>">Ver ventas y gastos</a></td><td><?php if($c['estado']==='cerrada'): ?><a class="btn small" href="caja.php?stock_caja=<?=e($c['id'])?>">Ver stock al cierre</a><?php else: ?><span class="line-note">Se verá al cerrar</span><?php endif; ?></td></tr><?php endforeach;?></tbody></table></section>
<?php if($caja): ?>
<?php $rsStockActual=$conn->query("SELECT nombre,stock,unidad FROM productos ORDER BY nombre ASC"); ?>
<section class="panel table-wrap"><div class="section-head"><div><h3>Stock actual de la caja #<?=e($caja['id'])?></h3><small>Este es el stock disponible ahora. Al cerrar la caja se guardará una fotografía de estas existencias para consultarla después.</small></div></div><table><thead><tr><th>Producto</th><th>Stock actual</th><th>Unidad</th></tr></thead><tbody><?php while($sa=$rsStockActual->fetch_assoc()): ?><tr><td><?=e($sa['nombre'])?></td><td><strong><?=e(rtrim(rtrim(number_format((float)$sa['stock'],2,'.',''),'0'),'.'))?></strong></td><td><?=e($sa['unidad'])?></td></tr><?php endwhile; ?></tbody></table></section>
<?php endif; ?>
<?php require_once 'footer.php';?>
