<?php
require __DIR__ . '/config.php';
require_login();

$today = date('Y-m-d');

/* ---- Ringkasan hari ini ---- */
$totalSiswa = (int)$pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn();
$totalKelas = (int)$pdo->query('SELECT COUNT(*) FROM kelas')->fetchColumn();

$st = $pdo->prepare('SELECT status, COUNT(*) c FROM absensi WHERE tanggal = ? GROUP BY status');
$st->execute([$today]);
$cnt = array_fill_keys(array_keys(STATUS), 0);
foreach ($st as $r) {
    $cnt[$r['status']] = (int)$r['c'];
}
$sudah  = array_sum($cnt);
$belum  = max(0, $totalSiswa - $sudah);
$persen = $sudah ? (int)round($cnt['H'] / $sudah * 100) : 0;

/* ---- Donut ---- */
$C = 2 * M_PI * 52;
$segs = [];
$off = 0;
if ($sudah > 0) {
    foreach (STATUS as $k => $_) {
        if ($cnt[$k] === 0) continue;
        $len = $cnt[$k] / $sudah * $C;
        $segs[] = [$k, $len, $off];
        $off += $len;
    }
}

/* ---- Kehadiran 7 hari terakhir ---- */
$from = date('Y-m-d', strtotime('-6 days'));
$st = $pdo->prepare("SELECT tanggal, SUM(status = 'H') h, COUNT(*) t FROM absensi WHERE tanggal BETWEEN ? AND ? GROUP BY tanggal");
$st->execute([$from, $today]);
$byDay = [];
foreach ($st as $r) {
    $byDay[$r['tanggal']] = $r;
}
$week = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $row = $byDay[$d] ?? null;
    $week[] = [
        'd'   => $d,
        'pct' => $row && $row['t'] > 0 ? (int)round($row['h'] / $row['t'] * 100) : null,
    ];
}

/* ---- Progres absensi per kelas hari ini ---- */
$st = $pdo->prepare("SELECT k.id, k.nama_kelas, COUNT(s.id) total, COUNT(a.id) sudah, COALESCE(SUM(a.status = 'H'), 0) hadir
    FROM kelas k
    LEFT JOIN siswa s ON s.kelas_id = k.id
    LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = ?
    GROUP BY k.id, k.nama_kelas
    ORDER BY k.nama_kelas");
$st->execute([$today]);
$kelasProg = $st->fetchAll();

/* ---- Alpa terbanyak bulan ini ---- */
$awalBulan = date('Y-m-01');
$st = $pdo->prepare("SELECT s.id, s.nama, s.foto, k.nama_kelas, COUNT(*) c
    FROM absensi a
    JOIN siswa s ON s.id = a.siswa_id
    JOIN kelas k ON k.id = s.kelas_id
    WHERE a.status = 'A' AND a.tanggal >= ?
    GROUP BY s.id, s.nama, s.foto, k.nama_kelas
    ORDER BY c DESC, s.nama
    LIMIT 5");
$st->execute([$awalBulan]);
$topAlpa = $st->fetchAll();

$pageTitle = salam() . ', ' . explode(' ', trim($_SESSION['nama']))[0];
$pageSub   = 'Ringkasan absensi hari ini';
$active    = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<?php if ($totalKelas === 0 || $totalSiswa === 0): ?>
  <section class="card empty">
    <img src="assets/img/logo.png" alt="">
    <h2>Mulai dari data kelas dan siswa</h2>
    <p>Tambahkan kelas terlebih dahulu, lalu masukkan siswanya. Setelah itu absensi bisa langsung dicatat.</p>
    <div class="empty-actions">
      <a class="btn" href="kelas.php">Tambah kelas</a>
      <a class="btn ghost" href="siswa.php">Tambah siswa</a>
    </div>
  </section>
<?php else: ?>

<div class="grid">
  <!-- Hari ini -->
  <section class="card c-7">
    <div class="card-head">
      <h2>Absensi hari ini</h2>
      <a class="btn sm" href="absensi.php?tanggal=<?= $today ?>"><?= icon('check', 16) ?> Catat absensi</a>
    </div>
    <div class="donut-wrap">
      <div class="donut">
        <svg viewBox="0 0 140 140" role="img" aria-label="Komposisi kehadiran hari ini">
          <circle cx="70" cy="70" r="52" fill="none" stroke="#EAEEF8" stroke-width="16"/>
          <?php foreach ($segs as [$k, $len, $o]): ?>
            <circle class="c-<?= $k ?>" cx="70" cy="70" r="52" fill="none" stroke-width="16"
              stroke-dasharray="<?= round($len, 2) ?> <?= round($C - $len, 2) ?>"
              stroke-dashoffset="<?= -round($o, 2) ?>" transform="rotate(-90 70 70)"/>
          <?php endforeach; ?>
        </svg>
        <div class="donut-c">
          <?php if ($sudah): ?>
            <div><b><span data-count="<?= $persen ?>">0</span>%</b><small>hadir</small></div>
          <?php else: ?>
            <div><small>Belum ada<br>data</small></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="tiles">
        <?php foreach (STATUS as $k => $lbl): ?>
          <div class="tile t-<?= strtolower($k) ?>">
            <b data-count="<?= $cnt[$k] ?>">0</b>
            <span><?= $lbl ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="foot-note">
      <?= $sudah ?> dari <?= $totalSiswa ?> siswa sudah tercatat<?= $belum ? ', ' . $belum . ' siswa belum diabsen.' : '.' ?>
    </p>
  </section>

  <!-- 7 hari -->
  <section class="card c-5">
    <div class="card-head"><h2>Kehadiran 7 hari terakhir</h2></div>
    <div class="week">
      <?php foreach ($week as $w): $isToday = $w['d'] === $today; ?>
        <div class="wbar" title="<?= e(tgl_indo($w['d'], true)) ?>: <?= $w['pct'] === null ? 'belum ada data' : $w['pct'] . '% hadir' ?>">
          <span class="pct"><?= $w['pct'] === null ? '–' : $w['pct'] . '%' ?></span>
          <div class="colwrap">
            <div class="col <?= $isToday ? 'today' : '' ?>" style="--h:<?= $w['pct'] === null ? 3 : max(3, $w['pct']) ?>%"></div>
          </div>
          <span class="day"><?= e(mb_substr(hari_indo($w['d']), 0, 3)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Per kelas -->
  <section class="card c-7">
    <div class="card-head"><h2>Progres per kelas</h2><span class="hint"><?= e(tgl_indo($today)) ?></span></div>
    <ul class="klist">
      <?php foreach ($kelasProg as $k):
          $done = $k['total'] > 0 && (int)$k['sudah'] >= (int)$k['total'];
          $w = $k['total'] > 0 ? round($k['sudah'] / $k['total'] * 100) : 0; ?>
        <li>
          <div>
            <div class="klist-top">
              <b><?= e($k['nama_kelas']) ?></b>
              <span class="chip <?= $done ? 'h' : ($k['sudah'] > 0 ? 'i' : 'gray') ?>">
                <?= $done ? 'Selesai' : ($k['sudah'] > 0 ? 'Sebagian' : 'Belum diabsen') ?>
              </span>
            </div>
            <div class="bar" role="progressbar" aria-valuenow="<?= $w ?>" aria-valuemin="0" aria-valuemax="100"><i style="--w:<?= $w ?>%"></i></div>
            <small class="hint"><?= (int)$k['sudah'] ?> / <?= (int)$k['total'] ?> siswa tercatat</small>
          </div>
          <a class="btn ghost sm" href="absensi.php?kelas=<?= (int)$k['id'] ?>&amp;tanggal=<?= $today ?>"><?= $done ? 'Lihat' : 'Absen' ?> <?= icon('arrow', 14) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- Alpa terbanyak -->
  <section class="card c-5">
    <div class="card-head"><h2>Alpa terbanyak bulan ini</h2></div>
    <?php if (!$topAlpa): ?>
      <p class="muted-center">Belum ada siswa yang alpa bulan ini.</p>
    <?php else: ?>
      <ul class="people">
        <?php foreach ($topAlpa as $p): ?>
          <li>
            <?= avatar($p['nama'], $p['foto'] ?? null, (int)$p['id'], 'siswa') ?>
            <div class="who"><b><?= e($p['nama']) ?></b><small><?= e($p['nama_kelas']) ?></small></div>
            <span class="chip a"><?= (int)$p['c'] ?>× alpa</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
