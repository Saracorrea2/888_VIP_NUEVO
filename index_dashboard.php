<?php
$titulo='Inicio';
require_once 'header.php';

procesarGastosRecurrentes($conn);

$hoy=date('Y-m-d');

/* Ventas netas cerradas de hoy */
$q=$conn->prepare("SELECT total,metodo_pago FROM pedidos WHERE estado='cerrado' AND DATE(COALESCE(cerrado_en,creado_en))=?");
$q->bind_param('s',$hoy);$q->execute(); $ventasHoy=0.0;
$rrHoy=$q->get_result(); while($rv=$rrHoy->fetch_assoc()) $ventasHoy += ventaNeta((float)$rv['total'],$rv['metodo_pago']);

/* Gastos registrados hoy */
$q=$conn->prepare("SELECT COALESCE(SUM(monto),0) v FROM gastos WHERE DATE(fecha_inicio)=? AND estado IN ('pagado','activo')");
$q->bind_param('s',$hoy);
$q->execute();
$gastosHoy=(float)$q->get_result()->fetch_assoc()['v'];

/* Costo de productos vendidos hoy */
$q=$conn->prepare("SELECT COALESCE(SUM(d.cantidad*d.costo_unitario),0) v
                   FROM detalle_pedidos d
                   INNER JOIN pedidos pe ON pe.id=d.pedido_id
                   WHERE pe.estado='cerrado'
                   AND DATE(COALESCE(pe.cerrado_en,pe.creado_en))=?");
$q->bind_param('s',$hoy);
$q->execute();
$costoVentasHoy=(float)$q->get_result()->fetch_assoc()['v'];

$gananciaBrutaHoy=$ventasHoy-$costoVentasHoy;
$gananciaNetaHoy=$gananciaBrutaHoy-$gastosHoy;

/* Ventas por método de pago sin duplicar por productos */
$metodosPago=[
 'efectivo'=>['nombre'=>'Efectivo','icono'=>'💵'],
 'nequi'=>['nombre'=>'Nequi','icono'=>'📱'],
 'daviplata'=>['nombre'=>'Daviplata','icono'=>'📲'],
 'tarjeta'=>['nombre'=>'Tarjeta','icono'=>'💳'],
 'transferencia'=>['nombre'=>'Bold','icono'=>'🏦'],
 'otro'=>['nombre'=>'Otro','icono'=>'✨']
];

$ventasPorMetodo=[];
foreach($metodosPago as $metodo=>$info){
    $ventasPorMetodo[$metodo]=['ventas'=>0,'cantidad'=>0,'costo'=>0,'ganancia'=>0];
}

$q=$conn->prepare("SELECT id,COALESCE(metodo_pago,'otro') metodo_pago,total
                   FROM pedidos
                   WHERE estado='cerrado'
                   AND DATE(COALESCE(cerrado_en,creado_en))=?");
$q->bind_param('s',$hoy);
$q->execute();
$rs=$q->get_result();

$qc=$conn->prepare("SELECT COALESCE(SUM(cantidad*costo_unitario),0) costo
                    FROM detalle_pedidos WHERE pedido_id=?");

while($r=$rs->fetch_assoc()){
    $metodo=$r['metodo_pago'];
    if(!isset($ventasPorMetodo[$metodo])) $metodo='otro';

    $pedidoId=(int)$r['id'];
    $qc->bind_param('i',$pedidoId);
    $qc->execute();
    $costo=(float)$qc->get_result()->fetch_assoc()['costo'];
    $venta=(float)$r['total'];

    $ventasPorMetodo[$metodo]['ventas'] += ventaNeta($venta,$metodo);
    $ventasPorMetodo[$metodo]['cantidad']++;
    $ventasPorMetodo[$metodo]['costo'] += $costo;
    $ventasPorMetodo[$metodo]['ganancia'] += ventaNeta($venta,$metodo)-$costo;
}

$sb=$conn->query("SELECT COUNT(*) c FROM productos WHERE activo=1 AND stock<=stock_minimo")->fetch_assoc()['c'];
$mes=0.0; $rm=$conn->query("SELECT total,metodo_pago FROM pedidos WHERE estado='cerrado' AND MONTH(COALESCE(cerrado_en,creado_en))=MONTH(CURDATE()) AND YEAR(COALESCE(cerrado_en,creado_en))=YEAR(CURDATE())"); while($mr=$rm->fetch_assoc()) $mes += ventaNeta((float)$mr['total'],$mr['metodo_pago']);
$ocup=$conn->query("SELECT COUNT(*) c FROM mesas WHERE estado='ocupada'")->fetch_assoc()['c'];
?>

<section class="hero"><div><span class="eyebrow">CONTROL CENTRAL</span><h1>Bienvenida a 888 VIP</h1><p>Administra las ventas por mesa, los pagos y el inventario desde un solo lugar.</p><a class="btn primary" href="ventas.php">Nueva venta</a></div><div class="hero-number">888</div></section>

<div class="stats">
<div class="stat"><small>Ventas de hoy</small><b><?=dinero($ventasHoy)?></b></div>
<div class="stat"><small>Gastos de hoy</small><b><?=dinero($gastosHoy)?></b></div>
<div class="stat"><small>Ganancia de hoy</small><b><?=dinero($gananciaNetaHoy)?></b></div>
<div class="stat"><small>Mesas ocupadas</small><b><?=e($ocup)?></b></div>
</div>

<section class="panel" style="margin-top:20px;">
<h2>Resultado de hoy</h2>
<p>Ventas menos costo de productos y gastos registrados hoy.</p>
<div class="stats" style="margin-top:16px;">
<div class="stat"><small>Ventas</small><b><?=dinero($ventasHoy)?></b></div>
<div class="stat"><small>Costo de productos</small><b><?=dinero($costoVentasHoy)?></b></div>
<div class="stat"><small>Ganancia bruta</small><b><?=dinero($gananciaBrutaHoy)?></b></div>
<div class="stat"><small>Ganancia neta</small><b><?=dinero($gananciaNetaHoy)?></b></div>
</div>
</section>

<section class="panel" style="margin-top:20px;">
<h2>Ventas por método de pago</h2>
<p>Ventas y ganancia bruta de cada forma de pago.</p>
<div class="table-wrap">
<table>
<thead><tr><th>Método</th><th>Ventas</th><th>Cantidad</th><th>Costo productos</th><th>Ganancia</th></tr></thead>
<tbody>
<?php foreach($metodosPago as $metodo=>$info): ?>
<tr>
<td><strong><?=e($info['icono'].' '.$info['nombre'])?></strong></td>
<td><?=dinero($ventasPorMetodo[$metodo]['ventas'])?></td>
<td><?=e($ventasPorMetodo[$metodo]['cantidad'])?></td>
<td><?=dinero($ventasPorMetodo[$metodo]['costo'])?></td>
<td><strong><?=dinero($ventasPorMetodo[$metodo]['ganancia'])?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><th>TOTAL</th><th><?=dinero($ventasHoy)?></th><th><?=e(array_sum(array_column($ventasPorMetodo,'cantidad')))?></th><th><?=dinero($costoVentasHoy)?></th><th><?=dinero($gananciaBrutaHoy)?></th></tr></tfoot>
</table>
</div>
</section>

<div class="grid-2" style="margin-top:20px;">
<section class="panel"><h2>Accesos rápidos</h2><div class="quick-grid"><a href="ventas.php">Nueva venta<small>Agregar productos y cobrar</small></a><a href="mesas.php">Mesas<small>Ver cuentas abiertas</small></a><a href="productos.php">Productos<small>Buscar y editar</small></a><a href="gastos.php">Gastos<small>Registrar y consultar</small></a><a href="reportes.php">Reportes<small>Consultar caja y ventas</small></a></div></section>
<section class="panel"><h2>Inventario</h2><p class="big-number"><?=e($sb)?></p><p>productos en stock bajo o agotado.</p><a href="inventario.php" class="btn">Revisar inventario</a></section>
</div>

<?php require_once 'footer.php';?>