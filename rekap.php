<?php
require __DIR__ . '/config.php';
require_login();

$kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();

$kelasId = (int)($_GET['kelas'] ?? 0);
if (!$kelasId && $kelasList) {
    $kelasId = (int)$kelasList[0]['id'];
}
$namaKelas = '';
foreach ($kelasList as $k) {
    if ((int)$k['id'] === $kelasId) $namaKelas = $k['nama_kelas'];
}

$bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = date('Y-m');
}
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));

$rekap = [];
$hariEfektif = 0;
if ($namaKelas !== '') {
    $st = $pdo->prepare("SELECT COUNT(DISTINCT a.tanggal) FROM absensi a
        JOIN siswa s ON s.id = a.siswa_id
        WHERE s.kelas_id = ? AND a.tanggal BETWEEN ? AND ?");
    $st->execute([$kelasId, $awal, $akhir]);
    $hariEfektif = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT s.id, s.nisn, s.nama, s.foto,
            COALESCE(SUM(a.status = 'H'), 0) h,
            COALESCE(SUM(a.status = 'I'), 0) i,
            COALESCE(SUM(a.status = 'S'), 0) sk,
            COALESCE(SUM(a.status = 'A'), 0) al
        FROM siswa s
        LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal BETWEEN ? AND ?
        WHERE s.kelas_id = ?
        GROUP BY s.id, s.nisn, s.nama, s.foto
        ORDER BY s.nama");
    $st->execute([$awal, $akhir, $kelasId]);
    foreach ($st as $r) {
        $r['pct'] = $hariEfektif > 0 ? (int)round($r['h'] / $hariEfektif * 100) : null;
        $rekap[] = $r;
    }
}

/* ---- Ekspor CSV ---- */
if (isset($_GET['export']) && $namaKelas !== '') {
    $file = 'rekap-' . preg_replace('/[^A-Za-z0-9]+/', '-', $namaKelas) . '-' . $bulan . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo "\xEF\xBB\xBF"; // agar terbaca benar di Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['NIS', 'Nama', 'Hadir', 'Izin', 'Sakit', 'Alpa', 'Persen Hadir'], ',', '"', '\\');
    foreach ($rekap as $r) {
        fputcsv($out, [$r['nis'], $r['nama'], $r['h'], $r['i'], $r['sk'], $r['al'], $r['pct'] === null ? '-' : $r['pct'] . '%'], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

/* ---- Ringkasan ---- */
$totalAlpa = array_sum(array_column($rekap, 'al'));
$pcts = array_filter(array_column($rekap, 'pct'), fn($v) => $v !== null);
$rata = $pcts ? (int)round(array_sum($pcts) / count($pcts)) : null;

$pageTitle = 'Rekap kehadiran';
$pageSub   = $namaKelas !== '' ? $namaKelas . ' · ' . bulan_indo((int)substr($bulan, 5, 2)) . ' ' . substr($bulan, 0, 4) : 'Pilih kelas dan bulan';
$active    = 'rekap';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$kelasList): ?>
  <section class="card empty">
    <img src="assets/img/logo.png" alt="">
    <h2>Belum ada data untuk direkap</h2>
    <p>Tambahkan kelas dan siswa, lalu catat absensi terlebih dahulu.</p>
    <div class="empty-actions"><a class="btn" href="kelas.php">Tambah kelas</a></div>
  </section>
<?php else: ?>

<form method="get" class="toolbar no-print">
  <div class="field-inline">
    <label for="rKelas" class="sr">Kelas</label>
    <select class="select" id="rKelas" name="kelas" data-autosubmit>
      <?php foreach ($kelasList as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field-inline">
    <label for="rBulan" class="sr">Bulan</label>
    <input class="input" type="month" id="rBulan" name="bulan" value="<?= e($bulan) ?>" data-autosubmit>
  </div>
  <span class="spacer"></span>
  <button type="button" class="btn ghost" onclick="window.print()"><?= icon('printer', 18) ?> Cetak</button>
  <a class="btn" href="rekap.php?kelas=<?= $kelasId ?>&amp;bulan=<?= e($bulan) ?>&amp;export=1"><?= icon('download', 18) ?> Ekspor CSV</a>
</form>

<div class="tiles wide">
  <div class="tile t-neutral"><b><?= $hariEfektif ?></b><span>Hari efektif</span></div>
  <div class="tile t-h"><b><?= $rata === null ? '–' : $rata . '%' ?></b><span>Rata-rata kehadiran</span></div>
  <div class="tile t-a"><b><?= (int)$totalAlpa ?></b><span>Total alpa</span></div>
</div>

<section class="card flush">
  <?php if (!$rekap): ?>
    <p class="muted-center">Kelas ini belum memiliki siswa.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="t">
      <thead>
        <tr>
          <th>Siswa</th>
          <th class="num">Hadir</th><th class="num">Izin</th><th class="num">Sakit</th><th class="num">Alpa</th>
          <th class="pcol">Kehadiran</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rekap as $r):
            $lv = $r['pct'] === null ? '' : ($r['pct'] >= 90 ? 'ok' : ($r['pct'] >= 75 ? 'mid' : 'low')); ?>
          <tr>
            <td>
              <div class="cell-name">
                <?= avatar($r['nama'], $r['foto'] ?? null, (int)$r['id'], 'siswa') ?>
                <div class="who"><b><?= e($r['nama']) ?></b><small><?= e($r['nisn']) ?></small></div>
              </div>
            </td>
            <td class="num"><span class="chip h"><?= (int)$r['h'] ?></span></td>
            <td class="num"><span class="chip i"><?= (int)$r['i'] ?></span></td>
            <td class="num"><span class="chip s"><?= (int)$r['sk'] ?></span></td>
            <td class="num"><span class="chip a"><?= (int)$r['al'] ?></span></td>
            <td class="pcol">
              <?php if ($r['pct'] === null): ?>
                <span class="hint">Belum ada data</span>
              <?php else: ?>
                <div class="pct-cell">
                  <div class="bar <?= $lv ?>"><i style="--w:<?= $r['pct'] ?>%"></i></div>
                  <b><?= $r['pct'] ?>%</b>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<p class="foot-note no-print">Persentase dihitung dari jumlah hari yang memiliki catatan absensi pada bulan tersebut.</p>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
