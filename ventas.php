<?php
$titulo='Ventas'; require_once 'header.php';
$mesa=(int)($_GET['mesa']??0); $pedido=(int)($_GET['pedido']??0);
try {
 if($_SERVER['REQUEST_METHOD']==='POST') {
  validarCsrf(); $accion=$_POST['accion']??'';
  if($accion==='nombre_mesa') {
   $mesaNombreId=(int)($_POST['mesa_id']??0);
   $nombre=trim($_POST['nombre']??'');
   if($mesaNombreId<=0) throw new RuntimeException('Mesa no válida.');
   if($nombre==='') throw new RuntimeException('Escribe un nombre para la mesa o cliente.');
   if(mb_strlen($nombre,'UTF-8')>100) throw new RuntimeException('El nombre no puede superar 100 caracteres.');
   $st=$conn->prepare("SELECT nombre,estado FROM mesas WHERE id=? FOR UPDATE");
   $st->bind_param('i',$mesaNombreId); $st->execute();
   $mesaNombreRow=$st->get_result()->fetch_assoc();
   if(!$mesaNombreRow) throw new RuntimeException('La mesa no existe.');
   $anterior=trim((string)($mesaNombreRow['nombre']??''));
   if($anterior===$nombre){
    flash('success','El nombre ya estaba actualizado.');
   } else {
    $st=$conn->prepare("UPDATE mesas SET nombre=? WHERE id=?");
    $st->bind_param('si',$nombre,$mesaNombreId); $st->execute();
    $pedidoNombre=(int)($_POST['pedido_id']??0);
    if($pedidoNombre<=0){
     $st=$conn->prepare("SELECT id FROM pedidos WHERE mesa_id=? AND estado='abierto' ORDER BY id DESC LIMIT 1");
     $st->bind_param('i',$mesaNombreId); $st->execute();
     $rr=$st->get_result()->fetch_assoc(); $pedidoNombre=$rr?(int)$rr['id']:0;
    }
    registrarHistorial($conn,'mesas','cambiar_nombre','Nombre de mesa actualizado desde Ventas',[
      'mesa_id'=>$mesaNombreId,
      'pedido'=>$pedidoNombre?:null,
      'nombre_anterior'=>$anterior,
      'nombre_nuevo'=>$nombre
    ]);
    flash('success','Nombre actualizado correctamente: '.$nombre);
   }
   $redir='ventas.php?mesa='.$mesaNombreId; if(!empty($pedidoNombre)) $redir.='&pedido='.(int)$pedidoNombre; redir($redir);
  }
  if($accion==='guardar') {
   $mesa=(int)$_POST['mesa_id']; $pid=(int)($_POST['pedido_id']??0); $producto=(int)$_POST['producto_id']; $cantidad=(float)$_POST['cantidad'];
   if($mesa<=0||$producto<=0||$cantidad<=0) throw new RuntimeException('Selecciona mesa, producto y cantidad válida.');
   $caja=cajaAbierta($conn); if(!$caja) throw new RuntimeException('Primero abre una caja.'); $cajaId=(int)$caja['id']; $conn->begin_transaction();
   $st=$conn->prepare("SELECT id,precio,precio_compra,stock,nombre FROM productos WHERE id=? AND activo=1 FOR UPDATE"); $st->bind_param('i',$producto); $st->execute(); $p=$st->get_result()->fetch_assoc();
   if(!$p) throw new RuntimeException('El producto no existe o está inactivo.');
   if((float)$p['stock']<$cantidad) throw new RuntimeException('Stock insuficiente para '.$p['nombre'].'. Disponible: '.$p['stock']);
   if($pid>0) {
    $st=$conn->prepare("SELECT id,caja_id FROM pedidos WHERE id=? AND estado='abierto' AND mesa_id=? FOR UPDATE"); $st->bind_param('ii',$pid,$mesa); $st->execute(); $cu=$st->get_result()->fetch_assoc();
    if(!$cu) throw new RuntimeException('La cuenta seleccionada ya no está abierta.');
    if(!empty($cu['caja_id']) && (int)$cu['caja_id']!==$cajaId) throw new RuntimeException('Esta cuenta pertenece a otro cierre de caja.');
    if(empty($cu['caja_id'])) { $st=$conn->prepare("UPDATE pedidos SET caja_id=? WHERE id=?"); $st->bind_param('ii',$cajaId,$pid); $st->execute(); }
   } else {
    $uid=(int)$_SESSION['usuario']['id']; $st=$conn->prepare("SELECT id,caja_id FROM pedidos WHERE mesa_id=? AND estado='abierto' ORDER BY id LIMIT 1"); $st->bind_param('i',$mesa); $st->execute(); $row=$st->get_result()->fetch_assoc();
    if($row) { if(!empty($row['caja_id'])&&(int)$row['caja_id']!==$cajaId) throw new RuntimeException('La cuenta abierta pertenece a otro cierre de caja.'); $pid=(int)$row['id']; if(empty($row['caja_id'])){$st=$conn->prepare("UPDATE pedidos SET caja_id=? WHERE id=?");$st->bind_param('ii',$cajaId,$pid);$st->execute();} }
    else { $st=$conn->prepare("INSERT INTO pedidos(mesa_id,usuario_id,caja_id,estado,subtotal,descuento,total) VALUES(?,?,?,'abierto',0,0,0)"); $st->bind_param('iii',$mesa,$uid,$cajaId); $st->execute(); $pid=$st->insert_id; }
   }
   $st=$conn->prepare("SELECT id,cantidad,precio FROM detalle_pedidos WHERE pedido_id=? AND producto_id=? LIMIT 1"); $st->bind_param('ii',$pid,$producto); $st->execute(); $old=$st->get_result()->fetch_assoc();
   // El stock se descuenta EN EL MOMENTO en que el producto entra a la cuenta.
   // Si el producto ya estaba en la cuenta, solo se descuenta la cantidad adicional.
   $cantidadNueva=(float)$cantidad;
   if($old) { $cantidadNueva=(float)$old['cantidad']+$cantidad; $sub=$cantidadNueva*(float)$old['precio']; $st=$conn->prepare("UPDATE detalle_pedidos SET cantidad=?,subtotal=? WHERE id=?"); $st->bind_param('ddi',$cantidadNueva,$sub,$old['id']); $st->execute(); }
   else { $precio=(float)$p['precio']; $costo=(float)$p['precio_compra']; $sub=$precio*$cantidad; $st=$conn->prepare("INSERT INTO detalle_pedidos(pedido_id,producto_id,cantidad,precio,costo_unitario,subtotal) VALUES(?,?,?,?,?,?)"); $st->bind_param('iidddd',$pid,$producto,$cantidad,$precio,$costo,$sub); $st->execute(); }
   $stockAntes=(float)$p['stock']; $stockNuevo=$stockAntes-$cantidad; $st=$conn->prepare("UPDATE productos SET stock=? WHERE id=?"); $st->bind_param('di',$stockNuevo,$producto); $st->execute();
   $tipo='salida'; $origen='venta'; $mot='Venta #'.$pid; $obs='Salida de stock al agregar producto a la cuenta'; $uid=(int)($_SESSION['usuario']['id']??0);
   $st=$conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)"); $st->bind_param('iissdddss',$producto,$uid,$tipo,$origen,$cantidad,$stockAntes,$stockNuevo,$mot,$obs); $st->execute();
   actualizarTotalesPedido($conn,$pid); $st=$conn->prepare("UPDATE mesas SET estado='ocupada' WHERE id=?"); $st->bind_param('i',$mesa); $st->execute();
   $conn->commit(); registrarHistorial($conn,'ventas','agregar','Producto agregado a cuenta',['pedido'=>$pid,'mesa'=>$mesa,'producto'=>$producto,'cantidad'=>$cantidad]); flash('success','Producto agregado a la cuenta.'); redir('ventas.php?mesa='.$mesa.'&pedido='.$pid);
  }
  if($accion==='cantidad') {
   $did=(int)$_POST['detalle_id']; $pid=(int)$_POST['pedido_id']; $cantidad=(float)$_POST['cantidad']; if($cantidad<=0) throw new RuntimeException('La cantidad debe ser mayor que cero.');
   $conn->begin_transaction(); $st=$conn->prepare("SELECT d.*,p.nombre,p.stock,pe.mesa_id FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id JOIN pedidos pe ON pe.id=d.pedido_id WHERE d.id=? AND d.pedido_id=? AND pe.estado='abierto' FOR UPDATE"); $st->bind_param('ii',$did,$pid); $st->execute(); $d=$st->get_result()->fetch_assoc();
   if(!$d) throw new RuntimeException('Detalle no encontrado.');
   $antes=(float)$d['cantidad']; $delta=$cantidad-$antes;
   if($delta>0 && (float)$d['stock']<$delta) throw new RuntimeException('No hay stock suficiente. Disponible para agregar: '.$d['stock']);
   if($delta!==0.0){
    $stockAntes=(float)$d['stock']; $stockNuevo=$stockAntes-$delta; $st=$conn->prepare("UPDATE productos SET stock=? WHERE id=?"); $st->bind_param('di',$stockNuevo,$d['producto_id']); $st->execute();
    $tipo=$delta>0?'salida':'entrada'; $origen='venta'; $movCantidad=abs($delta); $mot='Venta #'.$pid; $obs=$delta>0?'Salida de stock por aumento de cantidad en cuenta':'Devolución de stock por reducción de cantidad en cuenta'; $uid=(int)($_SESSION['usuario']['id']??0);
    $st=$conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)"); $st->bind_param('iissdddss',$d['producto_id'],$uid,$tipo,$origen,$movCantidad,$stockAntes,$stockNuevo,$mot,$obs); $st->execute();
   }
   $sub=$cantidad*(float)$d['precio']; $st=$conn->prepare("UPDATE detalle_pedidos SET cantidad=?,subtotal=? WHERE id=?"); $st->bind_param('ddi',$cantidad,$sub,$did); $st->execute(); $total=actualizarTotalesPedido($conn,$pid); $conn->commit();
   registrarHistorial($conn,'ventas','cambiar_cantidad','Cantidad modificada',['pedido'=>$pid,'detalle'=>$did,'producto'=>$d['producto_id'],'antes'=>$antes,'despues'=>$cantidad,'total'=>$total]); flash('success','Cantidad actualizada.'); redir('ventas.php?mesa='.$d['mesa_id'].'&pedido='.$pid);
  }
  if($accion==='precio') {
   $did=(int)$_POST['detalle_id']; $pid=(int)$_POST['pedido_id']; $precio=(float)$_POST['precio']; if($precio<0) throw new RuntimeException('El precio no puede ser negativo.');
   $conn->begin_transaction(); $st=$conn->prepare("SELECT d.*,p.nombre,pe.mesa_id FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id JOIN pedidos pe ON pe.id=d.pedido_id WHERE d.id=? AND d.pedido_id=? AND pe.estado='abierto' FOR UPDATE"); $st->bind_param('ii',$did,$pid); $st->execute(); $d=$st->get_result()->fetch_assoc(); if(!$d) throw new RuntimeException('Detalle no encontrado.');
   $antes=(float)$d['precio']; $sub=$precio*(float)$d['cantidad']; $st=$conn->prepare("UPDATE detalle_pedidos SET precio=?,subtotal=? WHERE id=?"); $st->bind_param('ddi',$precio,$sub,$did); $st->execute(); $total=actualizarTotalesPedido($conn,$pid); $conn->commit();
   registrarHistorial($conn,'ventas','cambiar_precio','Precio especial aplicado',['pedido'=>$pid,'detalle'=>$did,'producto'=>$d['producto_id'],'producto_nombre'=>$d['nombre'],'precio_anterior'=>$antes,'precio_nuevo'=>$precio,'cantidad'=>$d['cantidad'],'total'=>$total]); flash('success','Precio de esta venta actualizado. El precio del producto no cambió.'); redir('ventas.php?mesa='.$d['mesa_id'].'&pedido='.$pid);
  }
  if($accion==='descuento') {
   $pid=(int)$_POST['pedido_id'];
   if(($_POST['confirmar_ajuste']??'')!=='1') throw new RuntimeException('No se aplicó ningún descuento. Para cambiar el total debes marcar primero la opción de ajuste.');
   $final=(float)$_POST['total_final']; if($final<0) throw new RuntimeException('El total no puede ser negativo.');
   $conn->begin_transaction(); $st=$conn->prepare("SELECT * FROM pedidos WHERE id=? AND estado='abierto' FOR UPDATE"); $st->bind_param('i',$pid); $st->execute(); $pe=$st->get_result()->fetch_assoc(); if(!$pe) throw new RuntimeException('Cuenta no encontrada.');
   $subtotal=(float)$pe['subtotal']; if($final>$subtotal) throw new RuntimeException('El valor final no puede ser mayor al subtotal.'); $descuento=$subtotal-$final;
   $st=$conn->prepare("UPDATE pedidos SET descuento=?,total=?,observacion=? WHERE id=?"); $obs=trim($_POST['motivo']??'')?:'Valor especial de cierre'; $st->bind_param('ddsi',$descuento,$final,$obs,$pid); $st->execute(); $conn->commit();
   registrarHistorial($conn,'ventas','cambiar_total','Valor final de cuenta modificado',['pedido'=>$pid,'subtotal'=>$subtotal,'total_anterior'=>$pe['total'],'total_nuevo'=>$final,'descuento'=>$descuento,'motivo'=>$obs]); flash('success','Valor final actualizado.'); redir('ventas.php?pedido='.$pid);
  }
  if($accion==='eliminar_detalle') {
   $did=(int)$_POST['detalle_id']; $pid=(int)$_POST['pedido_id']; $conn->begin_transaction(); $st=$conn->prepare("SELECT d.*,pe.mesa_id,p.nombre FROM detalle_pedidos d JOIN pedidos pe ON pe.id=d.pedido_id JOIN productos p ON p.id=d.producto_id WHERE d.id=? AND d.pedido_id=? AND pe.estado='abierto' FOR UPDATE"); $st->bind_param('ii',$did,$pid); $st->execute(); $d=$st->get_result()->fetch_assoc(); if(!$d) throw new RuntimeException('Detalle no encontrado.');
   // Al retirar un producto de una cuenta abierta, devolvemos su cantidad al stock.
   $stockAntes=(float)$d['stock']; $stockNuevo=$stockAntes+(float)$d['cantidad']; $st=$conn->prepare("UPDATE productos SET stock=? WHERE id=?"); $st->bind_param('di',$stockNuevo,$d['producto_id']); $st->execute();
   $tipo='entrada'; $origen='venta'; $mot='Venta #'.$pid; $obs='Devolución de stock al retirar producto de la cuenta'; $uid=(int)($_SESSION['usuario']['id']??0);
   $st=$conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)"); $st->bind_param('iissdddss',$d['producto_id'],$uid,$tipo,$origen,$d['cantidad'],$stockAntes,$stockNuevo,$mot,$obs); $st->execute();
   $st=$conn->prepare("DELETE FROM detalle_pedidos WHERE id=?"); $st->bind_param('i',$did); $st->execute(); $st=$conn->prepare("UPDATE pedidos SET descuento=0 WHERE id=?"); $st->bind_param('i',$pid); $st->execute();
   $st=$conn->prepare("SELECT COUNT(*) c FROM detalle_pedidos WHERE pedido_id=?"); $st->bind_param('i',$pid); $st->execute(); $n=(int)$st->get_result()->fetch_assoc()['c'];
   if(!$n){$st=$conn->prepare("UPDATE pedidos SET estado='anulado',subtotal=0,total=0 WHERE id=?");$st->bind_param('i',$pid);$st->execute();$st=$conn->prepare("UPDATE mesas SET estado='libre' WHERE id=?");$st->bind_param('i',$d['mesa_id']);$st->execute();} else actualizarTotalesPedido($conn,$pid);
   $conn->commit(); registrarHistorial($conn,'ventas','eliminar_producto','Producto retirado de cuenta',['pedido'=>$pid,'producto'=>$d['producto_id'],'nombre'=>$d['nombre'],'cantidad'=>$d['cantidad']]); flash('success','Producto retirado de la cuenta.'); redir('ventas.php?mesa='.$d['mesa_id'].'&pedido='.$pid);
  }
  if($accion==='cobrar') {
   $pid=(int)$_POST['pedido_id']; $metodo=$_POST['metodo_pago']??'efectivo'; $permitidos=['efectivo','nequi','daviplata','tarjeta','transferencia','otro']; if(!in_array($metodo,$permitidos,true)) throw new RuntimeException('Método de pago no válido.');
   $caja=cajaAbierta($conn); if(!$caja) throw new RuntimeException('No hay una caja abierta.'); $cajaId=(int)$caja['id']; $conn->begin_transaction();
   $st=$conn->prepare("SELECT * FROM pedidos WHERE id=? FOR UPDATE"); $st->bind_param('i',$pid); $st->execute(); $pe=$st->get_result()->fetch_assoc(); if(!$pe||$pe['estado']!=='abierto') throw new RuntimeException('La cuenta ya fue cerrada.');
   // El stock ya fue actualizado al agregar/quitar productos de la cuenta.
   // Aquí solo reconciliamos cuentas antiguas que fueron creadas antes de esta mejora.
   $ds=$conn->prepare("SELECT d.*,p.nombre,p.stock FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id WHERE d.pedido_id=? FOR UPDATE"); $ds->bind_param('i',$pid); $ds->execute(); $rs=$ds->get_result(); $items=[];
   while($d=$rs->fetch_assoc()){
    $st=$conn->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='salida' THEN cantidad ELSE -cantidad END),0) reservado FROM movimientos_inventario WHERE producto_id=? AND origen='venta' AND motivo=?"); $mot='Venta #'.$pid; $st->bind_param('is',$d['producto_id'],$mot); $st->execute(); $reservado=(float)$st->get_result()->fetch_assoc()['reservado'];
    $faltante=(float)$d['cantidad']-$reservado;
    if($faltante>0){ if((float)$d['stock']<$faltante) throw new RuntimeException('Stock insuficiente para '.$d['nombre'].'. Disponible: '.$d['stock']); $an=(float)$d['stock']; $nuevo=$an-$faltante; $st=$conn->prepare("UPDATE productos SET stock=? WHERE id=?"); $st->bind_param('di',$nuevo,$d['producto_id']); $st->execute(); $tipo='salida';$origen='venta';$obs='Ajuste de stock al cerrar una cuenta antigua';$uid=(int)($_SESSION['usuario']['id']??0);$st=$conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)");$st->bind_param('iissdddss',$d['producto_id'],$uid,$tipo,$origen,$faltante,$an,$nuevo,$mot,$obs);$st->execute(); }
    elseif($faltante<0){ $an=(float)$d['stock']; $nuevo=$an+abs($faltante); $st=$conn->prepare("UPDATE productos SET stock=? WHERE id=?"); $st->bind_param('di',$nuevo,$d['producto_id']); $st->execute(); $tipo='entrada';$origen='venta';$obs='Reconciliación de stock al cerrar cuenta';$uid=(int)($_SESSION['usuario']['id']??0);$st=$conn->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,observacion) VALUES(?,?,?,?,?,?,?,?,?)");$st->bind_param('iissdddss',$d['producto_id'],$uid,$tipo,$origen,abs($faltante),$an,$nuevo,$mot,$obs);$st->execute(); }
   }
   $st=$conn->prepare("UPDATE pedidos SET estado='cerrado',metodo_pago=?,caja_id=?,cerrado_en=NOW() WHERE id=?");$st->bind_param('sii',$metodo,$cajaId,$pid);$st->execute();$uid=$_SESSION['usuario']['id']??null;$valorVenta=(float)$pe['total'];$montoCaja=($metodo==='transferencia')?boldNeto($valorVenta):$valorVenta;$concepto='Venta #'.$pid.' - '.($metodo==='transferencia'?'Bold':'Efectivo/otro');if($metodo==='transferencia')$concepto.=' | Bruto '.dinero($valorVenta).' | 4x1000 '.dinero(boldCuatroxMil($valorVenta)).' | Bold 1,49% '.dinero(boldComision($valorVenta));$st=$conn->prepare("INSERT INTO movimientos_caja(usuario_id,caja_id,pedido_id,tipo,monto,concepto) VALUES(?,?,?,'entrada',?,?)");$st->bind_param('iiids',$uid,$cajaId,$pid,$montoCaja,$concepto);$st->execute();
   if(!empty($pe['mesa_id'])){$mesaNombreCierre='Mesa '.(int)$pe['mesa_id'];$st=$conn->prepare("SELECT nombre FROM mesas WHERE id=? FOR UPDATE");$st->bind_param('i',$pe['mesa_id']);$st->execute();$mesaRowCierre=$st->get_result()->fetch_assoc();if($mesaRowCierre&&!empty($mesaRowCierre['nombre']))$mesaNombreCierre=$mesaRowCierre['nombre'];$st=$conn->prepare("UPDATE mesas SET estado='libre',nombre=CONCAT('Mesa ',id) WHERE id=?");$st->bind_param('i',$pe['mesa_id']);$st->execute();}else{$mesaNombreCierre='Venta general';}
   $conn->commit(); registrarHistorial($conn,'ventas','cobrar','Venta cerrada y cobrada',['pedido'=>$pid,'mesa'=>$pe['mesa_id'],'mesa_nombre'=>$mesaNombreCierre,'total'=>$pe['total'],'metodo'=>$metodo,'caja'=>$cajaId]); flash('success','Venta finalizada. La mesa quedó libre y el nombre se actualizó para la siguiente atención.'); redir('ventas.php');
  }
 }
} catch(Throwable $e) { if($conn->in_transaction)$conn->rollback(); flash('error',$e->getMessage()); redir('ventas.php'.($mesa?'?mesa='.$mesa:($pedido?'?pedido='.$pedido:''))); }

$mesas=$conn->query("SELECT m.*,COALESCE((SELECT SUM(pe.total) FROM pedidos pe WHERE pe.mesa_id=m.id AND pe.estado='abierto'),0) cuenta FROM mesas m ORDER BY m.id");
$productos=$conn->query("SELECT p.*,COALESCE(c.nombre,'Sin categoría') categoria FROM productos p LEFT JOIN categorias c ON c.id=p.categoria_id WHERE p.activo=1 ORDER BY p.nombre");
$pedidoActual=0;$mesaActual=$mesa;
if($pedido){$st=$conn->prepare("SELECT id,mesa_id FROM pedidos WHERE id=? AND estado='abierto'");$st->bind_param('i',$pedido);$st->execute();$x=$st->get_result()->fetch_assoc();if($x){$pedidoActual=(int)$x['id'];$mesaActual=(int)$x['mesa_id'];}}
if(!$pedidoActual&&$mesaActual){$st=$conn->prepare("SELECT id FROM pedidos WHERE mesa_id=? AND estado='abierto' ORDER BY id LIMIT 1");$st->bind_param('i',$mesaActual);$st->execute();$x=$st->get_result()->fetch_assoc();if($x)$pedidoActual=(int)$x['id'];}
$mesaActualNombre='Mesa '.$mesaActual;if($mesaActual){$st=$conn->prepare("SELECT nombre FROM mesas WHERE id=?");$st->bind_param('i',$mesaActual);$st->execute();$mn=$st->get_result()->fetch_assoc();if($mn)$mesaActualNombre=$mn['nombre'];}
$det=[];$total=0;$subtotal=0;$descuento=0;$observacion='';
if($pedidoActual){$st=$conn->prepare("SELECT pe.subtotal,pe.total,pe.descuento,pe.observacion FROM pedidos pe WHERE pe.id=?");$st->bind_param('i',$pedidoActual);$st->execute();$pe=$st->get_result()->fetch_assoc();$subtotal=(float)($pe['subtotal']??0);$total=(float)($pe['total']??0);$descuento=(float)($pe['descuento']??0);$observacion=$pe['observacion']??'';$st=$conn->prepare("SELECT d.*,p.nombre,p.stock,p.precio precio_actual FROM detalle_pedidos d JOIN productos p ON p.id=d.producto_id WHERE d.pedido_id=? ORDER BY d.id");$st->bind_param('i',$pedidoActual);$st->execute();$rs=$st->get_result();while($d=$rs->fetch_assoc())$det[]=$d;}
?>
<div class="toolbar"><div><span class="eyebrow">VENTAS</span><h2>Ventas por mesa</h2><p>La cuenta se acumula por mesa. Puedes ajustar cantidades y precios solo para esta venta.</p></div></div>
<div class="sales-layout"><section><h3>Mesas</h3><div class="table-grid"><?php while($m=$mesas->fetch_assoc()):?><a class="table-card <?=$m['estado']?>" href="ventas.php?mesa=<?=$m['id']?>"><strong><?=e($m['nombre'])?></strong><span><?=e($m['estado'])?></span><?php if((float)$m['cuenta']>0):?><b><?=dinero((float)$m['cuenta'])?></b><?php else:?><small>Sin cuenta</small><?php endif;?></a><?php endwhile;?></div>
<?php if($mesaActual):?><section class="panel sale-panel"><div class="panel-title"><div><h2><?=e($mesaActualNombre)?></h2><p>Cuenta #<?=e($pedidoActual?:'nueva')?></p></div><b class="total"><?=dinero($total)?></b></div>
<form method="post" class="mesa-name-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="nombre_mesa"><input type="hidden" name="mesa_id" value="<?=$mesaActual?>"><label>Nombre de mesa / cliente<input name="nombre" value="<?=e($mesaActualNombre)?>" maxlength="100" placeholder="Ej: Juan, Familia Pérez..." required></label><button class="btn primary small">Actualizar nombre</button></form>
<form method="post" class="add-sale"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="mesa_id" value="<?=$mesaActual?>"><input type="hidden" name="pedido_id" value="<?=$pedidoActual?>"><label>Buscar producto<input id="buscarProductoVenta" type="search" placeholder="Escribe el nombre..." autocomplete="off"></label><label>Producto<select id="productoVenta" name="producto_id" required><option value="">Seleccionar producto</option><?php $productos->data_seek(0);while($p=$productos->fetch_assoc()):?><option value="<?=$p['id']?>" data-nombre="<?=e(mb_strtolower($p['nombre'],'UTF-8'))?>"><?=e($p['nombre'])?> — <?=dinero((float)$p['precio'])?> — stock <?=e($p['stock'])?></option><?php endwhile;?></select></label><label>Cantidad<input type="number" name="cantidad" value="1" min="0.01" step="0.01" required></label><button class="btn primary">Agregar</button></form>
<div class="table-wrap"><table><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio venta</th><th>Subtotal</th><th></th></tr></thead><tbody><?php foreach($det as $d):?><tr><td><strong><?=e($d['nombre'])?></strong><small class="line-note">Precio catálogo: <?=dinero((float)$d['precio_actual'])?></small></td><td><form method="post" class="mini-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="cantidad"><input type="hidden" name="detalle_id" value="<?=$d['id']?>"><input type="hidden" name="pedido_id" value="<?=$pedidoActual?>"><input type="number" name="cantidad" value="<?=e($d['cantidad'])?>" min="0.01" step="0.01"><button class="btn small">Guardar</button></form></td><td><form method="post" class="mini-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="precio"><input type="hidden" name="detalle_id" value="<?=$d['id']?>"><input type="hidden" name="pedido_id" value="<?=$pedidoActual?>"><input type="number" name="precio" value="<?=e($d['precio'])?>" min="0" step="1"><button class="btn small">Precio</button></form></td><td><?=dinero((float)$d['subtotal'])?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="eliminar_detalle"><input type="hidden" name="detalle_id" value="<?=$d['id']?>"><input type="hidden" name="pedido_id" value="<?=$pedidoActual?>"><button class="btn danger small" onclick="return confirm('¿Retirar este producto?')">Borrar</button></form></td></tr><?php endforeach;?></tbody></table></div>
<?php if($pedidoActual&&$det):?><div class="sale-summary"><div><span>Subtotal</span><strong><?=dinero($subtotal)?></strong></div><div><span>Ajuste actual</span><strong><?=dinero($descuento)?></strong></div><div><span>Total a cobrar</span><strong><?=dinero($total)?></strong></div></div>
<form method="post" class="discount-box" onsubmit="return document.getElementById('confirmar_ajuste').checked;">
<input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="descuento"><input type="hidden" name="pedido_id" value="<?=$pedidoActual?>"><input type="hidden" name="confirmar_ajuste" id="confirmar_ajuste_val" value="0">
<label class="adjust-check"><input type="checkbox" id="confirmar_ajuste" onchange="document.getElementById('confirmar_ajuste_val').value=this.checked?'1':'0';document.getElementById('total_ajuste').disabled=!this.checked;document.getElementById('motivo_ajuste').disabled=!this.checked;document.getElementById('btn_ajuste').disabled=!this.checked;"> <strong>Aplicar ajuste/descuento manual</strong> <span>Solo se modifica si tú lo autorizas.</span></label>
<label>Valor final de esta cuenta<input id="total_ajuste" type="number" name="total_final" value="<?=e($subtotal)?>" min="0" max="<?=e($subtotal)?>" step="1" required disabled></label>
<label>Motivo<input id="motivo_ajuste" name="motivo" value="" placeholder="Ej. descuento autorizado, promoción..." disabled></label>
<button id="btn_ajuste" class="btn" type="submit" disabled>Aplicar valor especial</button>
</form>
<?php $cajaVista=cajaAbierta($conn);?><form method="post" class="pay-box"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="accion" value="cobrar"><input type="hidden" name="pedido_id" value="<?=$pedidoActual?>"><label>Método de pago<select name="metodo_pago"><option value="efectivo">Efectivo</option><option value="nequi">Nequi</option><option value="daviplata">Daviplata</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Bold</option><option value="otro">Otro</option></select></label><button class="btn primary pay" <?=$cajaVista?'':'disabled'?>>Finalizar y cobrar <?=dinero($total)?></button></form><?php if(!$cajaVista):?><div class="alert error">No hay caja abierta. <a href="caja.php">Abrir caja</a> para cobrar.</div><?php endif;?><?php endif;?></section><?php else:?><div class="empty panel">Selecciona una mesa para comenzar o continuar una cuenta.</div><?php endif;?></section></div>
<script>(function(){const input=document.getElementById('buscarProductoVenta'),select=document.getElementById('productoVenta');if(!input||!select)return;input.addEventListener('input',function(){const q=this.value.trim().toLowerCase();let first=null;[...select.options].forEach((op,i)=>{if(i===0){op.hidden=false;return;}const match=!q||(op.dataset.nombre||op.textContent.toLowerCase()).includes(q);op.hidden=!match;if(match&&!first)first=op;});if(q&&first)select.value=first.value;if(!q)select.value='';});})();</script>
<?php require_once 'footer.php';?>
