<?php
require __DIR__ . '/config.php';
require_login();

$kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();

$kelasId = (int)($_POST['kelas_id'] ?? $_GET['kelas'] ?? 0);
if (!$kelasId && $kelasList) {
    $kelasId = (int)$kelasList[0]['id'];
}
$namaKelas = '';
foreach ($kelasList as $k) {
    if ((int)$k['id'] === $kelasId) $namaKelas = $k['nama_kelas'];
}
if ($namaKelas === '') $kelasId = 0;

$tanggal = $_POST['tanggal'] ?? $_GET['tanggal'] ?? date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$dt || $dt->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
    $tanggal = date('Y-m-d');
}

/* ---- Simpan absensi ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $kelasId) {
    csrf_check();
    $ids = $pdo->prepare('SELECT id FROM siswa WHERE kelas_id = ?');
    $ids->execute([$kelasId]);
    $ids = $ids->fetchAll(PDO::FETCH_COLUMN);

    $ins = $pdo->prepare('INSERT INTO absensi (siswa_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)');

    $pdo->beginTransaction();
    try {
        foreach ($ids as $sid) {
            $s = $_POST['status'][$sid] ?? 'H';
            if (!isset(STATUS[$s])) $s = 'H';
            $ket = $s === 'H' ? null : mb_substr(trim($_POST['ket'][$sid] ?? ''), 0, 150);
            $ins->execute([$sid, $tanggal, $s, $ket ?: null]);
        }
        $pdo->commit();
        flash('ok', 'Absensi ' . $namaKelas . ' tanggal ' . tgl_indo($tanggal) . ' tersimpan.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('err', 'Absensi gagal disimpan. Coba lagi.');
    }
    redirect('absensi.php?kelas=' . $kelasId . '&tanggal=' . $tanggal);
}

/* ---- Data siswa + status pada tanggal terpilih ---- */
$siswa = [];
if ($kelasId) {
    $st = $pdo->prepare('SELECT s.id, s.nisn, s.nama, s.foto, a.status, a.keterangan
        FROM siswa s
        LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = ?
        WHERE s.kelas_id = ?
        ORDER BY s.nama');
    $st->execute([$tanggal, $kelasId]);
    $siswa = $st->fetchAll();
}
$tercatat = count(array_filter($siswa, fn($r) => $r['status'] !== null));

$pageTitle = 'Absensi';
$pageSub   = 'Semua siswa diawali Hadir. Ubah yang tidak hadir, lalu simpan.';
$active    = 'absensi';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$kelasList): ?>
  <section class="card empty">
    <img src="assets/img/logo.png" alt="">
    <h2>Belum ada kelas</h2>
    <p>Buat kelas dan tambahkan siswa dulu agar absensi bisa dicatat.</p>
    <div class="empty-actions"><a class="btn" href="kelas.php">Tambah kelas</a></div>
  </section>
<?php else: ?>

<form method="get" class="toolbar no-print" id="filterForm">
  <div class="field-inline">
    <label for="fKelas" class="sr">Kelas</label>
    <select class="select" id="fKelas" name="kelas" data-autosubmit>
      <?php foreach ($kelasList as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field-inline">
    <label for="fTgl" class="sr">Tanggal</label>
    <input class="input" type="date" id="fTgl" name="tanggal" value="<?= e($tanggal) ?>" max="<?= date('Y-m-d') ?>" data-autosubmit>
  </div>
  <?php if ($tanggal !== date('Y-m-d')): ?>
    <a class="btn ghost" href="absensi.php?kelas=<?= $kelasId ?>">Kembali ke hari ini</a>
  <?php endif; ?>
  <span class="chip <?= $siswa && $tercatat === count($siswa) ? 'h' : 'gray' ?>">
    <?= $siswa && $tercatat === count($siswa) ? 'Sudah tersimpan' : 'Belum disimpan' ?>
  </span>
</form>

<?php if (!$siswa): ?>
  <section class="card empty">
    <img src="assets/img/logo.png" alt="">
    <h2>Kelas <?= e($namaKelas) ?> belum punya siswa</h2>
    <p>Tambahkan siswa ke kelas ini agar bisa diabsen.</p>
    <div class="empty-actions"><a class="btn" href="siswa.php">Tambah siswa</a></div>
  </section>
<?php else: ?>

<form method="post" id="absenForm">
  <?= csrf_field() ?>
  <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
  <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">

  <section class="card flush">
    <div class="list-head">
      <div>
        <h2><?= e($namaKelas) ?></h2>
        <small class="hint"><?= count($siswa) ?> siswa · <?= e(tgl_indo($tanggal, true)) ?></small>
      </div>
      <div class="list-tools">
        <div class="search">
          <?= icon('search', 18) ?>
          <input class="input" type="search" placeholder="Cari nama atau NIS" aria-label="Cari siswa" data-filter=".student" data-empty="#noRes">
        </div>
        <button type="button" class="btn ghost" id="allPresent">Semua hadir</button>
      </div>
    </div>

    <?php foreach ($siswa as $r): $cur = $r['status'] ?? 'H'; ?>
      <div class="student" data-status="<?= $cur ?>" data-search="<?= e(mb_strtolower($r['nama'] . ' ' . $r['nisn'])) ?>">
        <?= avatar($r['nama'], $r['foto'] ?? null, (int)$r['id'], 'siswa') ?>
        <div class="who"><b><?= e($r['nama']) ?></b><small><?= e($r['nisn']) ?></small></div>
        <div class="seg" role="radiogroup" aria-label="Status <?= e($r['nama']) ?>">
          <?php foreach (STATUS as $k => $lbl): ?>
            <label class="seg-opt <?= strtolower($k) ?>">
              <input type="radio" name="status[<?= (int)$r['id'] ?>]" value="<?= $k ?>" <?= $cur === $k ? 'checked' : '' ?>>
              <span><?= $lbl ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="ket" <?= $cur === 'H' ? 'hidden' : '' ?>>
          <input class="input" type="text" name="ket[<?= (int)$r['id'] ?>]" maxlength="150" placeholder="Keterangan (opsional)" aria-label="Keterangan <?= e($r['nama']) ?>" value="<?= e($r['keterangan'] ?? '') ?>">
        </div>
      </div>
    <?php endforeach; ?>
    <p class="muted-center" id="noRes" hidden>Tidak ada siswa yang cocok dengan pencarian.</p>
  </section>

  <div class="savebar no-print">
    <div class="cnt" aria-live="polite">
      <span style="--c:var(--h)"><b data-n="H">0</b> Hadir</span>
      <span style="--c:var(--i)"><b data-n="I">0</b> Izin</span>
      <span style="--c:var(--s)"><b data-n="S">0</b> Sakit</span>
      <span style="--c:var(--a)"><b data-n="A">0</b> Alpa</span>
    </div>
    <em class="dirty-note" id="dirtyNote" hidden>Ada perubahan yang belum disimpan</em>
    <button class="btn light" type="submit" id="saveBtn"><?= icon('save', 18) ?> <span>Simpan absensi</span></button>
  </div>
</form>

<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
