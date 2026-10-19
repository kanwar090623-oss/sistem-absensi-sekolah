<?php
/* Kerangka halaman aplikasi. Variabel dari halaman: $pageTitle, $pageSub, $active */
require_once __DIR__ . '/../config.php';
require_login();

$flash     = get_flash();
$pageTitle = $pageTitle ?? APP_NAME;
$pageSub   = $pageSub ?? '';
$active    = $active ?? '';
$namaUser  = $_SESSION['nama'] ?? 'Guru';
$fq = $pdo->prepare('SELECT foto FROM users WHERE id = ?');
$fq->execute([(int)$_SESSION['uid']]);
$fotoUser = $fq->fetchColumn() ?: null;

$nav = [
    'dashboard' => ['Dashboard', 'dashboard.php', 'dashboard'],
    'absensi'   => ['Absensi',   'absensi.php',   'check'],
    'rekap'     => ['Rekap',     'rekap.php',     'chart'],
    'siswa'     => ['Siswa',     'siswa.php',     'users'],
    'kelas'     => ['Kelas',     'kelas.php',     'book'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · SiAbsen</title>
<link rel="icon" type="image/png" href="assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php">
      <img src="assets/img/logo.png" alt="">
      <span>SiAbsen</span>
    </a>
    <nav class="nav" aria-label="Menu utama">
      <?php foreach ($nav as $key => [$label, $href, $ic]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
          <?= icon($ic) ?><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a class="side-profile" href="profil.php" title="Ubah profil">
        <?= avatar($namaUser, $fotoUser, 1, 'guru') ?>
        <div class="side-user">
          <b><?= e($namaUser) ?></b>
          <small>Guru / petugas</small>
        </div>
      </a>
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="icon-btn dark" type="submit" title="Keluar" aria-label="Keluar"><?= icon('logout', 18) ?></button>
      </form>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>

  <main class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menuBtn" type="button" aria-label="Buka menu"><?= icon('menu') ?></button>
      <div>
        <h1><?= e($pageTitle) ?></h1>
        <?php if ($pageSub): ?><p class="sub"><?= e($pageSub) ?></p><?php endif; ?>
      </div>
      <div class="today-chip no-print"><?= e(tgl_indo(date('Y-m-d'), true)) ?></div>
    </header>
    <div class="content">
<?php if ($flash): ?>
      <div id="flash" hidden data-type="<?= e($flash['type']) ?>" data-msg="<?= e($flash['msg']) ?>"></div>
<?php endif; ?>
