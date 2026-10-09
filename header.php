<?php
require_once __DIR__ . '/funciones.php';
requerirLogin();
$flash = obtenerFlash();
$u = usuarioActual();
$current = basename($_SERVER['PHP_SELF']);
$nav = [
  'index.php'=>['home','Inicio'],
  'ventas.php'=>['cart','Ventas'],
  'mesas.php'=>['table','Mesas'],
  'caja.php'=>['wallet','Caja'],
  'reportes.php'=>['chart','Reportes'],
  'productos.php'=>['box','Productos'],
  'inventario.php'=>['layers','Inventario'],
  'gastos.php'=>['receipt','Gastos'],
  'facturas.php'=>['file','Facturas'],
  'historial.php'=>['history','Historial'],
];
$icons = [
  'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6h5v6"/>',
  'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="19" cy="20" r="1.5"/><path d="M3 4h2l2.2 10.5h11.6L21 7H6"/>',
  'table' => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M4 10h16M9 5v14M15 5v14"/>',
  'wallet' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H20v14H6.5A2.5 2.5 0 0 1 4 16.5z"/><path d="M4 8h16v5h-5a2.5 2.5 0 0 0 0 5h5"/><circle cx="15.5" cy="15.5" r=".8" fill="currentColor" stroke="none"/>',
  'chart' => '<path d="M4 19V9M10 19V5M16 19v-8M22 19H2"/>',
  'box' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
  'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
  'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
  'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h4M9 13h6M9 17h6"/>',
  'history' => '<path d="M4 12a8 8 0 1 0 2.3-5.7"/><path d="M4 4v5h5M12 7v5l3 2"/>',
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? '888 VIP POS') ?></title>
<link rel="stylesheet" href="assets/estilos.css?v=20261007top">
<script defer src="assets/app.js"></script>
</head>
<body>
<div class="vip-bg"></div>
<div class="app-shell">
<main class="main">
<header class="topbar">
  <div class="topbar-brand">
    <img src="assets/logo-888-vip-transparent.png" class="top-logo" alt="888 VIP BAR">
    <div><b>888 VIP</b><small>BAR · CONTROL Y ADMINISTRACIÓN</small></div>
  </div>
  <nav class="main-nav">
    <?php foreach($nav as $href=>$item): ?>
      <a href="<?=e($href)?>" class="<?= $current===$href?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$item[0]] ?></svg></span><span><?=e($item[1])?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="topbar-right">
    <div class="top-user"><span class="top-user-icon">◉</span><div><b><?=e($u['nombre'])?></b><small>POS 888 VIP</small></div></div>
    <div class="clock" id="clock"></div>
  </div>
</header>
<?php if($flash): ?><div class="alert <?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>
