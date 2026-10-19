<?php
/* Kerangka halaman login & daftar. Variabel: $title, $lead */
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · SiAbsen</title>
<link rel="icon" type="image/png" href="assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth">
  <aside class="auth-side">
    <div class="auth-brand">
      <img src="assets/img/logo.png" alt="">
      <span>SiAbsen</span>
    </div>
    <div class="auth-art">
      <span class="ring r1"></span><span class="ring r2"></span>
      <img src="assets/img/logo.png" alt="Maskot SiAbsen: siswa SMA berseragam dan berpeci">
    </div>
    <div class="auth-foot">
      <h2>Catat kehadiran satu kelas dalam hitungan menit.</h2>
      <div class="clock" aria-live="off">
        <b data-clock>--:--</b>
        <span data-date></span>
      </div>
    </div>
  </aside>

  <main class="auth-main">
    <div class="auth-card">
      <h1><?= e($title) ?></h1>
      <p class="lead"><?= e($lead) ?></p>
      <?php if ($flash): ?>
        <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($flash['msg']) ?></div>
      <?php endif; ?>
