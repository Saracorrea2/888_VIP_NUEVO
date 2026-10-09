<?php
$titulo='Inicio';
require_once 'header.php';
procesarGastosRecurrentes($conn);
$hoy=date('Y-m-d');

$metodosPago=[
 'efectivo'=>['nombre'=>'Efectivo','icono'=>'💵'],
 'nequi'=>['nombre'=>'Nequi','icono'=>'📱'],
 'daviplata'=>['nombre'=>'Daviplata','icono'=>'📲'],
 'tarjeta'=>['nombre'=>'Tarjeta','icono'=>'💳'],
 'transferencia'=>['nombre'=>'Bold','icono'=>'▣'],
 'otro'=>['nombre'=>'Otro','icono'=>'✦']
];

/* Todas las cifras cerradas del dashboard pasan por ventaNeta(). */
$q=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE estado='cerrado' AND DATE(COALESCE(cerrado_en,creado_en))=?");
$q->bind_param('s',$hoy);$q->execute();$ventasHoy=0.0;$cantidadHoy=0;
$rsHoy=$q->get_result();while($rv=$rsHoy->fetch_assoc()){ $ventasHoy+=ventaNeta((float)$rv['total'],$rv['metodo_pago']);$cantidadHoy++; }

$q=$conn->prepare("SELECT COALESCE(SUM(monto),0) v FROM gastos WHERE DATE(fecha_inicio)=? AND estado IN ('pagado','activo')");
$q->bind_param('s',$hoy);$q->execute();$gastosHoy=(float)$q->get_result()->fetch_assoc()['v'];

$q=$conn->prepare("SELECT COALESCE(SUM(d.cantidad*d.costo_unitario),0) v FROM detalle_pedidos d INNER JOIN pedidos pe ON pe.id=d.pedido_id WHERE pe.estado='cerrado' AND DATE(COALESCE(pe.cerrado_en,pe.creado_en))=?");
$q->bind_param('s',$hoy);$q->execute();$costoVentasHoy=(float)$q->get_result()->fetch_assoc()['v'];
$gananciaBrutaHoy=$ventasHoy-$costoVentasHoy;$gananciaNetaHoy=$gananciaBrutaHoy-$gastosHoy;

$ventasPorMetodo=[];foreach($metodosPago as $metodo=>$info)$ventasPorMetodo[$metodo]=['ventas'=>0,'cantidad'=>0,'costo'=>0,'ganancia'=>0];
$q=$conn->query("SELECT id,COALESCE(metodo_pago,'otro') metodo_pago,total FROM pedidos WHERE estado='cerrado' ORDER BY COALESCE(cerrado_en,creado_en) DESC,id DESC");
$qc=$conn->prepare("SELECT COALESCE(SUM(cantidad*costo_unitario),0) costo FROM detalle_pedidos WHERE pedido_id=?");
while($r=$q->fetch_assoc()){
 $metodo=strtolower(trim((string)$r['metodo_pago']));if(!isset($ventasPorMetodo[$metodo]))$metodo='otro';
 $pid=(int)$r['id'];$qc->bind_param('i',$pid);$qc->execute();$costo=(float)$qc->get_result()->fetch_assoc()['costo'];
 $neto=ventaNeta((float)$r['total'],$metodo);$ventasPorMetodo[$metodo]['ventas']+=$neto;$ventasPorMetodo[$metodo]['cantidad']++;$ventasPorMetodo[$metodo]['costo']+=$costo;$ventasPorMetodo[$metodo]['ganancia']+=$neto-$costo;
}
/* Resumen de método de pago del mes seleccionado. */
foreach($ventasPorMetodo as $m=>$_){$ventasPorMetodo[$m]['ventasMes']=0;$ventasPorMetodo[$m]['cantidadMes']=0;}

/* Mes actual, por fecha: ventas netas - gastos. */
$mesConsulta=isset($_GET['mes'])&&preg_match('/^\d{4}-\d{2}$/',$_GET['mes'])?$_GET['mes']:date('Y-m');$inicioMes=$mesConsulta.'-01';$finMes=date('Y-m-t',strtotime($inicioMes));
$stMes=$conn->prepare("SELECT DATE(COALESCE(cerrado_en,creado_en)) fecha,total,metodo_pago FROM pedidos WHERE estado='cerrado' AND DATE(COALESCE(cerrado_en,creado_en)) BETWEEN ? AND ? ORDER BY fecha DESC,id DESC");$stMes->bind_param('ss',$inicioMes,$finMes);$stMes->execute();$ventasPorFecha=[];$rr=$stMes->get_result();
while($vr=$rr->fetch_assoc()){ $f=$vr['fecha'];if(!isset($ventasPorFecha[$f]))$ventasPorFecha[$f]=['ventas'=>0,'cantidad'=>0];$ventasPorFecha[$f]['ventas']+=ventaNeta((float)$vr['total'],$vr['metodo_pago']);$ventasPorFecha[$f]['cantidad']++; }
$stG=$conn->prepare("SELECT DATE(fecha_inicio) fecha,COALESCE(SUM(monto),0) gastos FROM gastos WHERE estado IN ('pagado','activo') AND DATE(fecha_inicio) BETWEEN ? AND ? GROUP BY DATE(fecha_inicio)");$stG->bind_param('ss',$inicioMes,$finMes);$stG->execute();$gastosPorFecha=[];$rg=$stG->get_result();while($gr=$rg->fetch_assoc())$gastosPorFecha[$gr['fecha']]=(float)$gr['gastos'];
$fechas=array_unique(array_merge(array_keys($ventasPorFecha),array_keys($gastosPorFecha)));rsort($fechas);$netoRows=[];
foreach($fechas as $fecha){$v=$ventasPorFecha[$fecha]['ventas']??0;$g=$gastosPorFecha[$fecha]??0;$netoRows[]=['fecha'=>$fecha,'ventas'=>$v,'gastos'=>$g,'neto'=>$v-$g,'cantidad'=>$ventasPorFecha[$fecha]['cantidad']??0];}
$ventasMesTotal=0;$gastosMesTotal=0;$netoMesTotal=0;foreach($netoRows as $nr){$ventasMesTotal+=$nr['ventas'];$gastosMesTotal+=$nr['gastos'];$netoMesTotal+=$nr['neto'];}
/* Resultado del mes seleccionado: costo real de productos vendidos en ese mes. */
$stCostoMes=$conn->prepare("SELECT COALESCE(SUM(d.cantidad*d.costo_unitario),0) costo FROM detalle_pedidos d INNER JOIN pedidos pe ON pe.id=d.pedido_id WHERE pe.estado='cerrado' AND DATE(COALESCE(pe.cerrado_en,pe.creado_en)) BETWEEN ? AND ?");$stCostoMes->bind_param('ss',$inicioMes,$finMes);$stCostoMes->execute();$costoVentasMes=(float)$stCostoMes->get_result()->fetch_assoc()['costo'];
$gananciaBrutaMes=$ventasMesTotal-$costoVentasMes;$gananciaNetaMes=$gananciaBrutaMes-$gastosMesTotal;
$mesesES=['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];$mesNombre=$mesesES[substr($mesConsulta,5,2)].' de '.substr($mesConsulta,0,4);

/* Ventas por método para el mismo mes mostrado en el dashboard. */
$stMetodoMes=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE estado='cerrado' AND DATE(COALESCE(cerrado_en,creado_en)) BETWEEN ? AND ?");
$stMetodoMes->bind_param('ss',$inicioMes,$finMes);$stMetodoMes->execute();$rm=$stMetodoMes->get_result();
while($r=$rm->fetch_assoc()){
 $m=strtolower(trim((string)$r['metodo_pago']));if(!isset($ventasPorMetodo[$m]))$m='otro';
 $ventasPorMetodo[$m]['ventasMes']+=ventaNeta((float)$r['total'],$m);$ventasPorMetodo[$m]['cantidadMes']++;
}

$ultimasVentas=$conn->prepare("SELECT pe.id,pe.total,pe.metodo_pago,pe.cerrado_en,pe.creado_en,m.nombre mesa FROM pedidos pe LEFT JOIN mesas m ON m.id=pe.mesa_id WHERE pe.estado='cerrado' AND DATE(COALESCE(pe.cerrado_en,pe.creado_en))=? ORDER BY COALESCE(pe.cerrado_en,pe.creado_en) DESC,pe.id DESC LIMIT 8");$ultimasVentas->bind_param('s',$hoy);$ultimasVentas->execute();$ultimasVentas=$ultimasVentas->get_result();
$ocup=(int)$conn->query("SELECT COUNT(*) c FROM mesas WHERE estado='ocupada'")->fetch_assoc()['c'];$sb=(int)$conn->query("SELECT COUNT(*) c FROM productos WHERE activo=1 AND stock<=stock_minimo")->fetch_assoc()['c'];

$metodoMesTotal=array_sum(array_column($ventasPorMetodo,'ventasMes'));$palette=['efectivo'=>'#d5a93b','transferencia'=>'#a77a1d','nequi'=>'#727b8a','daviplata'=>'#59616d','tarjeta'=>'#40464f','otro'=>'#777'];
$segments=[];$start=0;foreach($metodosPago as $m=>$info){$pct=$metodoMesTotal>0?($ventasPorMetodo[$m]['ventasMes']/$metodoMesTotal*100):0;if($pct>0){$segments[]=$palette[$m].' '.$start.'% '.($start+$pct).'%';$start+=$pct;}}
$donutBg=$segments?'conic-gradient('.implode(',',$segments).')':'conic-gradient(#333 0 100%)';
?>

<section class="hero">
 <div><span class="eyebrow">BUENAS BEBIDAS · GRANDES MOMENTOS</span><h1>Bienvenido a 888 VIP</h1><p>Panel de control del bar: ventas netas, caja, gastos, mesas e inventario en un solo lugar.</p><a class="btn primary" href="ventas.php">+ Nueva venta</a></div>
 <div class="hero-number">888</div>
</section>

<div class="stats">
 <div class="stat"><small>Ventas hoy · neto recibido</small><b><?=dinero($ventasHoy)?></b></div>
 <div class="stat"><small>Gastos hoy</small><b><?=dinero($gastosHoy)?></b></div>
 <div class="stat"><small>Venta neta hoy</small><b><?=dinero($gananciaNetaHoy)?></b><span class="line-note">Ventas netas − gastos − costo producto</span></div>
 <div class="stat"><small>Ventas del mes · neto</small><b><?=dinero($ventasMesTotal)?></b></div>
</div>

<section class="panel">
 <div class="section-head"><div><h3>Accesos rápidos</h3><small>Las funciones principales del POS</small></div></div>
 <div class="quick-grid">
  <a href="ventas.php">▣ &nbsp; Nueva venta<small>Facturación rápida</small></a>
  <a href="mesas.php">♜ &nbsp; Mesas<small><?=e($ocup)?> mesas ocupadas</small></a>
  <a href="caja.php">▤ &nbsp; Caja<small>Abrir, revisar y cerrar</small></a>
  <a href="reportes.php">▥ &nbsp; Reportes<small>Análisis y estadísticas</small></a>
 </div>
</section>

<div class="grid-2">
<section class="panel">
 <div class="section-head"><div><h3>Ventas por método de pago (neto) · mes</h3><small>Resumen de <?=e($mesNombre)?> · Bold descuenta 4×1000 + 1,49%.</small></div></div>
 <div class="donut-layout">
  <div class="donut-wrap"><div class="donut" style="background:<?=$donutBg?>"></div><div class="donut-center"><strong><?=dinero($metodoMesTotal)?></strong><small>Total neto del mes</small></div></div>
  <div class="method-list">
   <?php foreach($metodosPago as $m=>$info): $pct=$metodoMesTotal>0?($ventasPorMetodo[$m]['ventasMes']/$metodoMesTotal*100):0; ?>
    <div class="method-row"><span class="method-dot" style="background:<?=e($palette[$m])?>"></span><span><?=e($info['nombre'])?></span><b><?=dinero($ventasPorMetodo[$m]['ventasMes'])?></b><em><?=number_format($pct,1,',','.')?>%</em></div>
   <?php endforeach; ?>
  </div>
 </div>
</section>

<section class="panel">
 <div class="section-head"><div><h3>Últimas ventas</h3><small>Ventas cerradas de hoy</small></div><a class="btn small" href="reportes.php">Ver todas →</a></div>
 <div class="table-wrap"><table><thead><tr><th>Hora</th><th>Método</th><th>Valor neto</th></tr></thead><tbody>
 <?php if($ultimasVentas->num_rows): while($uv=$ultimasVentas->fetch_assoc()): $mn=ventaNeta((float)$uv['total'],$uv['metodo_pago']); ?>
 <tr><td><?=e(date('H:i',strtotime($uv['cerrado_en']??$uv['creado_en'])))?></td><td><strong><?=e(nombreMetodoPago($uv['metodo_pago']))?></strong><?php if(esBold($uv['metodo_pago'])):?><small class="line-note">Bruto <?=dinero((float)$uv['total'])?> · costos <?=dinero(boldDescuentos((float)$uv['total']))?></small><?php endif;?></td><td><strong><?=dinero($mn)?></strong></td></tr>
 <?php endwhile; else: ?><tr><td colspan="3">Todavía no hay ventas cerradas hoy.</td></tr><?php endif; ?>
 </tbody></table></div>
</section>
</div>

<section class="panel">
 <div class="section-head"><div><h3>Venta neta del mes por fecha</h3><small>Las ventas Bold ya aparecen con 4×1000 y comisión Bold descontados.</small></div></div>
 <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin:0 0 16px"><label style="display:grid;gap:5px;font-size:11px;color:#aaa">Mes<input name="mes" type="month" value="<?=e($mesConsulta)?>"></label><button class="btn primary" type="submit">Consultar mes</button></form>
 <div class="stats" style="margin-bottom:16px"><div class="stat"><small>Ventas netas</small><b><?=dinero($ventasMesTotal)?></b></div><div class="stat"><small>Gastos</small><b><?=dinero($gastosMesTotal)?></b></div><div class="stat"><small>Venta neta</small><b><?=dinero($netoMesTotal)?></b></div><div class="stat"><small>Ventas realizadas</small><b><?=e(array_sum(array_map(fn($r)=>(int)$r['cantidad'],$netoRows)))?></b></div></div>
 <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Ventas netas</th><th>Gastos</th><th>Venta neta</th><th>Ventas</th></tr></thead><tbody>
 <?php if($netoRows): foreach($netoRows as $nr): ?><tr><td><?=e($nr['fecha'])?></td><td><?=dinero($nr['ventas'])?></td><td><?=dinero($nr['gastos'])?></td><td><strong><?=dinero($nr['neto'])?></strong></td><td><?=e($nr['cantidad'])?></td></tr><?php endforeach; else: ?><tr><td colspan="5">No hay movimientos en <?=e($mesConsulta)?>.</td></tr><?php endif; ?>
 </tbody><tfoot><tr><th>TOTAL MES</th><th><?=dinero($ventasMesTotal)?></th><th><?=dinero($gastosMesTotal)?></th><th><?=dinero($netoMesTotal)?></th><th><?=e(array_sum(array_map(fn($r)=>(int)$r['cantidad'],$netoRows)))?></th></tr></tfoot></table></div>
</section>

<div class="grid-2">
<section class="panel"><div class="section-head"><div><h3>Resultado del mes</h3><small>Resultado de <?=e($mesNombre)?>. El costo de productos se mantiene separado de la comisión de Bold.</small></div></div><div class="stats"><div class="stat"><small>Ventas netas</small><b><?=dinero($ventasMesTotal)?></b></div><div class="stat"><small>Costo productos</small><b><?=dinero($costoVentasMes)?></b></div><div class="stat"><small>Ganancia bruta</small><b><?=dinero($gananciaBrutaMes)?></b></div><div class="stat"><small>Ganancia neta</small><b><?=dinero($gananciaNetaMes)?></b></div></div></section>
<section class="panel"><div class="section-head"><div><h3>Inventario</h3><small>Control de existencias</small></div></div><p class="big-number"><?=e($sb)?></p><p>productos en stock bajo o agotado.</p><a href="inventario.php" class="btn">Revisar inventario</a></section>
</div>

<?php require_once 'footer.php'; ?>
