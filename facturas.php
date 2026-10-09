<?php
require_once __DIR__ . '/funciones.php';
requerirLogin();

$ventaId=(int)($_GET['venta']??0);
$compraId=(int)($_GET['compra']??0);

$metodos=[
 'efectivo'=>'Efectivo','nequi'=>'Nequi','daviplata'=>'Daviplata','tarjeta'=>'Tarjeta','transferencia'=>'Bold','otro'=>'Otro'
];

/* FACTURA DE VENTA: se genera directamente desde pedidos/detalle_pedidos.
   No crea tablas ni modifica la base de datos. */
if($ventaId>0){
 $st=$conn->prepare("SELECT pe.*,u.nombre usuario_nombre,m.nombre mesa FROM pedidos pe LEFT JOIN usuarios u ON u.id=pe.usuario_id LEFT JOIN mesas m ON m.id=pe.mesa_id WHERE pe.id=? AND pe.estado IN ('cerrado','anulado')");
 $st->bind_param('i',$ventaId);$st->execute();$venta=$st->get_result()->fetch_assoc();
 if(!$venta){http_response_code(404);exit('Venta no encontrada.');}
 $st=$conn->prepare("SELECT d.*,p.nombre FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id WHERE d.pedido_id=? ORDER BY d.id");
 $st->bind_param('i',$ventaId);$st->execute();$det=$st->get_result();
 $titulo='Factura #'.$ventaId;
 ?>
 <!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($titulo)?></title>
 <style>
 body{font-family:Arial,sans-serif;background:#ecdfcd;color:#604a33;margin:0}.invoice{width:min(820px,94vw);margin:28px auto;background:#fff;padding:32px;border:1px solid #c2a88f;border-radius:14px;box-shadow:0 8px 30px rgba(96,74,51,.12)}.head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #be8c6b;padding-bottom:18px}.brand{display:flex;gap:14px;align-items:center}.brand img{width:68px;height:68px;object-fit:cover;border-radius:12px}.brand h1{margin:0;font-size:25px}.brand p{margin:4px 0;color:#916b5e}.number{text-align:right}.number strong{font-size:22px;color:#604a33}.meta{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:20px 0}.meta div{background:#ecdfcd;border:1px solid #c2a88f;padding:10px;border-radius:8px}.meta small{display:block;color:#916b5e}.meta b{display:block;margin-top:4px}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #ecdfcd;text-align:left}th{background:#be8c6b;color:#fff;font-size:12px}td.num,th.num{text-align:right}.totals{margin-left:auto;width:min(360px,100%);margin-top:18px}.totals div{display:flex;justify-content:space-between;padding:8px 0}.totals .grand{border-top:2px solid #111;font-size:23px;font-weight:900;padding:12px 0}.totals .grand b{font-size:25px}.totals .fees{font-size:12px;color:#555}.totals .net{border-top:1px solid #ccc;margin-top:5px;padding-top:10px;font-weight:700}.status{margin:18px 0;padding:12px;background:#ecdfcd;border-left:5px solid #916b5e}.actions{margin-top:22px;display:flex;gap:10px}.btn{border:0;border-radius:8px;padding:11px 16px;background:#be8c6b;color:#fff;font-weight:700;cursor:pointer;text-decoration:none}.btn.dark{background:#604a33}@media(max-width:650px){.meta{grid-template-columns:1fr 1fr}.head{flex-direction:column}.number{text-align:left}}@media print{body{background:#fff}.invoice{box-shadow:none;border:0;margin:0;width:auto;padding:0}.actions{display:none!important} @page{margin:12mm}}
 </style></head><body><main class="invoice">
 <div class="head"><div class="brand"><img src="assets/logo-888-vip-bar.jpeg" alt="888 VIP"><div><h1>888 VIP BAR & POS</h1><p>Comprobante / factura de venta</p></div></div><div class="number"><small>FACTURA</small><br><strong>FAC-<?=e($ventaId)?></strong></div></div>
 <div class="meta"><div><small>Fecha</small><b><?=e($venta['cerrado_en']??$venta['creado_en'])?></b></div><div><small>Mesa</small><b><?=e($venta['mesa']??('Mesa '.($venta['mesa_id']??'')))?></b></div><div><small>Usuario</small><b><?=e($venta['usuario_nombre']??'Sistema')?></b></div><div><small>Pago</small><b><?=e($metodos[$venta['metodo_pago']]??($venta['metodo_pago']??'—'))?></b></div></div>
 <?php if(($venta['estado']??'')==='anulado'):?><div class="status"><strong>VENTA ANULADA.</strong> Este documento se conserva como constancia de la operación.</div><?php endif; ?>
 <table><thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead><tbody><?php while($d=$det->fetch_assoc()):?><tr><td><?=e($d['nombre'])?></td><td class="num"><?=e($d['cantidad'])?></td><td class="num"><?=dinero((float)$d['precio'])?></td><td class="num"><?=dinero((float)$d['subtotal'])?></td></tr><?php endwhile;?></tbody></table>
 <div class="totals"><div><span>Subtotal</span><b><?=dinero((float)$venta['subtotal'])?></b></div><div><span>Descuento</span><b>-<?=dinero((float)$venta['descuento'])?></b></div><div class="grand"><span>TOTAL A PAGAR</span><b><?=dinero((float)$venta['total'])?></b></div><?php if(esBold($venta['metodo_pago'])):?><div class="fees"><span>4x1000 Bold</span><b>-<?=dinero(ventaDescuento4x1000((float)$venta['total'],$venta['metodo_pago']))?></b></div><div class="fees"><span>Comisión Bold 1,49%</span><b>-<?=dinero(ventaComision((float)$venta['total'],$venta['metodo_pago']))?></b></div><div class="net"><span>Neto recibido por el negocio</span><b><?=dinero(ventaNeta((float)$venta['total'],$venta['metodo_pago']))?></b></div><?php endif; ?></div>
 <div class="actions"><button type="button" class="btn" id="btnImprimirFactura" onclick="imprimirFactura()">🖨 Imprimir factura</button><button type="button" class="btn dark" onclick="window.close()">Cerrar</button><a class="btn dark" href="reportes.php?venta=<?=e($ventaId)?>">Volver a Reportes</a></div>
 <script>
function imprimirFactura(){
  try {
    window.focus();
    setTimeout(function(){ window.print(); }, 80);
  } catch(e) {
    alert('No se pudo abrir la impresión. Usa Ctrl+P para imprimir la factura.');
  }
}
</script>
</main></body></html>
 <?php exit;
}

/* FACTURA DE COMPRA: una sola pantalla puede registrar varias líneas.
   Cada línea crea un gasto de inventario y un movimiento de stock usando
   las columnas que ya existen. Se agrupan mediante gasto_origen_id. */
$titulo='Facturas';require_once 'header.php';
$mensaje='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='registrar_compra'){
  validarCsrf();
  $proveedor=trim((string)($_POST['proveedor']??''));
  $numero=trim((string)($_POST['numero_factura']??''));
  $fecha=trim((string)($_POST['fecha']??date('Y-m-d')));
  $observacion=trim((string)($_POST['observacion']??''));
  $ids=$_POST['producto_id']??[];$cant=$_POST['cantidad']??[];$costos=$_POST['costo']??[];
  if($numero==='')throw new RuntimeException('Escribe el número de factura del proveedor.');
  if(!is_array($ids)||count($ids)===0)throw new RuntimeException('Agrega al menos un producto.');
  $lineas=[];$total=0;
  for($i=0;$i<count($ids);$i++){
   $pid=(int)$ids[$i];$qty=(float)($cant[$i]??0);$costo=(float)($costos[$i]??0);
   if($pid<=0||$qty<=0)continue;
   if($costo<0)throw new RuntimeException('El costo no puede ser negativo.');
   $lineas[]=['pid'=>$pid,'qty'=>$qty,'costo'=>$costo];$total+=$qty*$costo;
  }
  if(!$lineas)throw new RuntimeException('Agrega cantidades válidas.');
  $uid=(int)($_SESSION['usuario']['id']??0);$caja=cajaAbierta($conn);$cajaId=$caja?(int)$caja['id']:null;
  $conn->begin_transaction();$rootId=0;
  foreach($lineas as $idx=>$l){
   $st=$conn->prepare("SELECT id,nombre,stock,unidad FROM productos WHERE id=? AND activo=1 FOR UPDATE");$st->bind_param('i',$l['pid']);$st->execute();$p=$st->get_result()->fetch_assoc();if(!$p)throw new RuntimeException('Uno de los productos no existe o está inactivo.');
   $antes=(float)$p['stock'];$nuevo=$antes+$l['qty'];$monto=$l['qty']*$l['costo'];
   $concepto='Factura compra '.$numero.' | '.($proveedor!==''?'Proveedor: '.$proveedor.' | ':'').'Producto: '.$p['nombre'].($observacion!==''?' | '.$observacion:'');
   $st=$conn->prepare("INSERT INTO gastos(usuario_id,producto_id,cantidad_inventario,caja_id,tipo,concepto,monto,frecuencia,fecha_inicio,proxima_fecha,estado,gasto_origen_id) VALUES(?,?,?,?,? ,? ,?,'unico',?,NULL,'pagado',?)");
   $tipo='inventario';$origenId=$rootId?:null;$st->bind_param('iidissdsi',$uid,$l['pid'],$l['qty'],$cajaId,$tipo,$concepto,$monto,$fecha,$origenId);$st->execute();$gid=$st->insert_id;if($rootId===0)$rootId=$gid;
   $st=$conn->prepare("UPDATE gastos SET gasto_origen_id=? WHERE id=?");$st->bind_param('ii',$rootId,$gid);$st->execute();
   $st=$conn->prepare("UPDATE productos SET stock=?,precio_compra=? WHERE id=?");$st->bind_param('ddi',$nuevo,$l['costo'],$l['pid']);$st->execute();
   $tipoMov='entrada';$origenMov='compra';$motivo='Factura compra '.$numero;$obs='Entrada de inventario por factura de proveedor'.($proveedor!==''?' - '.$proveedor:'');
   $st=$conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)");$st->bind_param('iissdddss',$l['pid'],$uid,$tipoMov,$origenMov,$l['qty'],$antes,$nuevo,$motivo,$obs);$st->execute();
   if($cajaId!==null){$cc='Compra factura '.$numero.' - '.$p['nombre'];$st=$conn->prepare("INSERT INTO movimientos_caja(usuario_id,caja_id,gasto_id,tipo,monto,concepto) VALUES(?,?,?,'salida',?,?)");$st->bind_param('iiids',$uid,$cajaId,$gid,$monto,$cc);$st->execute();}
  }
  $st=$conn->prepare("UPDATE gastos SET monto=?, concepto=? WHERE id=?");
   $conceptoRaiz='Factura compra '.$numero.($proveedor!==''?' | Proveedor: '.$proveedor:'').($observacion!==''?' | '.$observacion:'');
   $st->bind_param('dsi',$total,$conceptoRaiz,$rootId);$st->execute();
   $conn->commit();registrarHistorial($conn,'gastos','factura_compra','Factura de compra registrada y stock aumentado',['factura'=>$numero,'proveedor'=>$proveedor,'total'=>$total,'gasto_origen'=>$rootId,'lineas'=>count($lineas)]);flash('success','Factura registrada. El stock de todos los productos fue aumentado.');redir('facturas.php?compra='.$rootId);
 }
}catch(Throwable $e){if($conn->in_transaction)$conn->rollback();flash('error',$e->getMessage());redir('facturas.php');}

$productos=$conn->query("SELECT id,nombre,stock,unidad,precio_compra FROM productos WHERE activo=1 ORDER BY nombre");
$compras=$conn->query("SELECT g.id,g.gasto_origen_id,g.concepto,g.monto,g.fecha_inicio,g.creado_en,g.cantidad_inventario,p.nombre producto, COALESCE((SELECT SUM(g2.monto) FROM gastos g2 WHERE g2.tipo='inventario' AND (g2.id=COALESCE(g.gasto_origen_id,g.id) OR g2.gasto_origen_id=COALESCE(g.gasto_origen_id,g.id))),g.monto) AS total_factura FROM gastos g LEFT JOIN productos p ON p.id=g.producto_id WHERE g.tipo='inventario' AND (g.gasto_origen_id IS NULL OR g.gasto_origen_id=g.id) ORDER BY g.id DESC LIMIT 40");
$detalleCompra=null;$compraRoot=null;
if($compraId>0){$st=$conn->prepare("SELECT g.*,u.nombre usuario_nombre FROM gastos g LEFT JOIN usuarios u ON u.id=g.usuario_id WHERE g.id=? AND g.tipo='inventario'");$st->bind_param('i',$compraId);$st->execute();$compraRoot=$st->get_result()->fetch_assoc();if($compraRoot){$root=(int)($compraRoot['gasto_origen_id']?:$compraRoot['id']);$st=$conn->prepare("SELECT g.*,p.nombre producto,p.unidad FROM gastos g LEFT JOIN productos p ON p.id=g.producto_id WHERE g.tipo='inventario' AND (g.id=? OR g.gasto_origen_id=?) ORDER BY g.id");$st->bind_param('ii',$root,$root);$st->execute();$detalleCompra=$st->get_result();}}
?>
<style>
body{background:#f5f5f3!important;color:#202020!important}
.panel{background:#fff!important;border:1px solid #e4e1db!important;box-shadow:0 8px 26px rgba(0,0,0,.055)!important;border-radius:14px!important}
.invoice-kicker{display:block;font-size:11px;letter-spacing:.16em;font-weight:800;color:#967646;margin-bottom:3px}
.invoice-head h3{font-size:25px;margin:0 0 4px!important;font-weight:800;color:#171717}
.invoice-head small{color:#777!important}
.purchase-table{overflow:hidden;border:1px solid #e4e1db;border-radius:10px;margin-top:18px}
.purchase-table table{margin:0!important;width:100%}
.purchase-table th{background:#f5f4f1!important;color:#765c35!important;border-bottom:1px solid #dfdbd3!important;font-size:11px;letter-spacing:.05em;text-transform:uppercase}
.purchase-table td{background:#fff!important;color:#252525!important;border-bottom:1px solid #eceae6!important;padding:14px 15px!important}
.purchase-table tfoot th{background:#f0efec!important;color:#171717!important;border-top:1px solid #ddd9d1!important;border-bottom:0!important;padding:16px 15px!important}
.purchase-table .total-value{font-size:20px;color:#171717!important;font-weight:900}
.purchase-invoice .btn,.panel .btn{background:#171717!important;color:#fff!important;border-radius:9px!important}
.purchase-invoice .btn:hover,.panel .btn:hover{background:#2a2a2a!important}
@media(max-width:700px){.purchase-table{overflow-x:auto}.purchase-table table{min-width:650px}}
@media print{body{background:#fff!important}.panel{box-shadow:none!important;border:0!important}.purchase-table{border:1px solid #ddd!important}}
</style>
<div class="toolbar"><div><span class="eyebrow">FACTURACIÓN</span><h2>Facturas</h2><p>Genera comprobantes de venta y registra facturas de compra para aumentar el stock de una sola vez.</p></div></div>
<?php if($compraRoot): ?><section class="panel purchase-invoice"><div class="section-head invoice-head"><div><span class="invoice-kicker">FACTURA DE COMPRA</span><h3>#<?=e($compraRoot['id'])?></h3><small><?=e($compraRoot['concepto'])?> · <?=e($compraRoot['fecha_inicio'])?></small></div><button class="btn" onclick="window.print()">Imprimir</button></div><div class="purchase-table"><table><thead><tr><th>Producto</th><th>Cantidad</th><th>Costo unitario</th><th class="num">Total</th></tr></thead><tbody><?php $tc=0;while($dc=$detalleCompra->fetch_assoc()):$qty=(float)$dc['cantidad_inventario'];$m=(float)$dc['monto'];$tc+=$m;?><tr><td><?=e($dc['producto']??'—')?></td><td><?=e($qty)?> <?=e($dc['unidad']??'')?></td><td><?=dinero($qty>0?$m/$qty:0)?></td><td><?=dinero($m)?></td></tr><?php endwhile;?></tbody><tfoot><tr><th colspan="3">TOTAL FACTURA</th><th class="num total-value"><?=dinero($tc)?></th></tr></tfoot></table></section><?php endif; ?>
<section class="panel"><h3>Registrar factura de compra</h3><p>Agrega todos los productos de la factura. Al guardar, cada cantidad se suma inmediatamente al stock y queda registrada en el historial.</p><form method="post" id="compraForm"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="registrar_compra"><div class="form-grid"><label>Número de factura<input name="numero_factura" required placeholder="Ej. FV-1025"></label><label>Proveedor<input name="proveedor" placeholder="Ej. Distribuidora ABC"></label><label>Fecha<input type="date" name="fecha" value="<?=date('Y-m-d')?>" required></label></div><div class="table-wrap" style="margin-top:16px"><table id="lineasCompra"><thead><tr><th>Producto</th><th>Cantidad</th><th>Costo unitario</th><th>Total</th><th></th></tr></thead><tbody></tbody></table></div><button type="button" class="btn" id="addLinea">+ Agregar producto</button><div style="margin-top:14px;display:flex;justify-content:flex-end;gap:10px;align-items:center"><strong>Total factura: <span id="totalFactura">$ 0</span></strong><button class="btn primary">Guardar factura y aumentar stock</button></div></form></section>
<section class="panel table-wrap"><div class="section-head"><div><h3>Últimas facturas de compra</h3><small>Cada factura agrupa los gastos de inventario sin crear nuevas tablas.</small></div></div><table><thead><tr><th>Factura</th><th>Fecha</th><th>Concepto</th><th>Total</th><th></th></tr></thead><tbody><?php while($c=$compras->fetch_assoc()):?><tr><td><strong>#<?=e($c['id'])?></strong></td><td><?=e($c['fecha_inicio'])?></td><td><?=e($c['concepto'])?></td><td><?=dinero((float)$c['total_factura'])?></td><td><a class="btn small" href="facturas.php?compra=<?=e($c['id'])?>">Ver factura</a></td></tr><?php endwhile;?></tbody></table></section>
<script>
const productos=<?=json_encode((function()use($conn){$a=[];$q=$conn->query("SELECT id,nombre,stock,unidad,precio_compra FROM productos WHERE activo=1 ORDER BY nombre");while($p=$q->fetch_assoc())$a[]=$p;return $a;})(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const tbody=document.querySelector('#lineasCompra tbody'),add=document.getElementById('addLinea'),total=document.getElementById('totalFactura');
function dineroJs(n){return '$ '+Math.round(n).toLocaleString('es-CO');}
function recalcular(){let t=0;tbody.querySelectorAll('tr').forEach(r=>{const q=parseFloat(r.querySelector('[name="cantidad[]"]').value)||0,c=parseFloat(r.querySelector('[name="costo[]"]').value)||0;const s=q*c;t+=s;r.querySelector('.line-total').textContent=dineroJs(s)});total.textContent=dineroJs(t)}
function nuevaLinea(){const tr=document.createElement('tr');tr.innerHTML='<td><select name="producto_id[]" required><option value="">Seleccionar</option>'+productos.map(p=>'<option value="'+p.id+'">'+String(p.nombre).replace(/&/g,'&amp;').replace(/</g,'&lt;')+' — stock '+p.stock+' '+p.unidad+'</option>').join('')+'</select></td><td><input type="number" name="cantidad[]" min="0.01" step="0.01" value="1" required></td><td><input type="number" name="costo[]" min="0" step="1" value="0" required></td><td class="line-total">$ 0</td><td><button type="button" class="btn danger small quitar">Quitar</button></td>';tbody.appendChild(tr);tr.querySelectorAll('input,select').forEach(x=>x.addEventListener('input',recalcular));tr.querySelector('.quitar').onclick=()=>{tr.remove();recalcular()};}
add.onclick=nuevaLinea;nuevaLinea();
</script>
<?php require_once 'footer.php'; ?>
