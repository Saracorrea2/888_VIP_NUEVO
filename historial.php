<?php
$titulo='Historial'; require_once 'header.php';
$mod=trim($_GET['modulo']??'');$accion=trim($_GET['accion']??'');
$where=[];$params=[];$types='';
if($mod!==''){$where[]='h.modulo=?';$params[]=$mod;$types.='s';}if($accion!==''){$where[]='h.accion=?';$params[]=$accion;$types.='s';}
$sql="SELECT h.*,u.nombre usuario FROM historial h LEFT JOIN usuarios u ON u.id=h.usuario_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY h.id DESC LIMIT 500";
$st=$conn->prepare($sql);if($params)$st->bind_param($types,...$params);$st->execute();$hs=$st->get_result();
$mods=$conn->query("SELECT DISTINCT modulo FROM historial ORDER BY modulo");$accs=$conn->query("SELECT DISTINCT accion FROM historial ORDER BY accion");
?>
<div class="toolbar"><div><span class="eyebrow">AUDITORÍA</span><h2>Historial</h2><p>Cada operación importante queda registrada con usuario, fecha, acción y detalle. Usa solo los filtros de módulo y acción.</p></div></div>
<section class="panel"><form method="get" class="form-grid"><label>Módulo<select name="modulo"><option value="">Todos</option><?php while($m=$mods->fetch_assoc()):?><option value="<?=e($m['modulo'])?>" <?=$mod===$m['modulo']?'selected':''?>><?=e($m['modulo'])?></option><?php endwhile;?></select></label><label>Acción<select name="accion"><option value="">Todas</option><?php while($a=$accs->fetch_assoc()):?><option value="<?=e($a['accion'])?>" <?=$accion===$a['accion']?'selected':''?>><?=e($a['accion'])?></option><?php endwhile;?></select></label><button class="btn primary">Filtrar</button><a class="btn" href="historial.php">Limpiar</a></form></section>
<section class="panel table-wrap"><table><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Descripción</th><th>Detalle</th></tr></thead><tbody><?php while($h=$hs->fetch_assoc()): $datos=[]; if(!empty($h['datos'])){ $tmp=json_decode($h['datos'],true); if(is_array($tmp)) $datos=$tmp; } ?><tr><td><?=e($h['creado_en'])?></td><td><?=e($h['usuario']??'Sistema')?></td><td><?=e($h['modulo'])?></td><td><span class="history-tag"><?=e($h['accion'])?></span></td><td><?=e($h['descripcion'])?></td><td><?php if($datos):?><details><summary>Ver detalle</summary><div class="history-detail"><?php foreach($datos as $clave=>$valor):?><div><strong><?=e(ucwords(str_replace('_',' ',$clave)))?>:</strong> <?php if(is_array($valor)) echo e(json_encode($valor,JSON_UNESCAPED_UNICODE)); else echo e((string)$valor);?></div><?php endforeach;?></div></details><?php else:?>—<?php endif;?></td></tr><?php endwhile;?></tbody></table></section>
<?php require_once 'footer.php';?>
