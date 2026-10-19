<?php
require __DIR__ . '/config.php';
require_login();

/* ---- Simpan / hapus ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $nama = '';
    try {
        if ($act === 'save') {
            $id   = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_kelas'] ?? '');
            if ($nama === '') {
                flash('err', 'Nama kelas tidak boleh kosong.');
            } elseif ($id) {
                $pdo->prepare('UPDATE kelas SET nama_kelas = ? WHERE id = ?')->execute([$nama, $id]);
                flash('ok', 'Kelas diubah menjadi ' . $nama . '.');
            } else {
                $pdo->prepare('INSERT INTO kelas (nama_kelas) VALUES (?)')->execute([$nama]);
                flash('ok', 'Kelas ' . $nama . ' ditambahkan.');
            }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $n = $pdo->prepare('SELECT COUNT(*) FROM siswa WHERE kelas_id = ?');
            $n->execute([$id]);
            $n = (int)$n->fetchColumn();
            if ($n > 0) {
                flash('err', 'Kelas masih memiliki ' . $n . ' siswa. Pindahkan atau hapus siswanya terlebih dahulu.');
            } else {
                $pdo->prepare('DELETE FROM kelas WHERE id = ?')->execute([$id]);
                flash('ok', 'Kelas dihapus.');
            }
        }
    } catch (PDOException $ex) {
        flash('err', $ex->getCode() === '23000'
            ? 'Kelas ' . $nama . ' sudah ada. Gunakan nama yang berbeda.'
            : 'Terjadi kesalahan pada database. Coba lagi.');
    }
    redirect('kelas.php');
}

$rows = $pdo->query('SELECT k.id, k.nama_kelas, COUNT(s.id) jml
    FROM kelas k LEFT JOIN siswa s ON s.kelas_id = k.id
    GROUP BY k.id, k.nama_kelas ORDER BY k.nama_kelas')->fetchAll();

$pageTitle = 'Kelas';
$pageSub   = count($rows) . ' kelas terdaftar';
$active    = 'kelas';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div class="search grow">
    <?= icon('search', 18) ?>
    <input class="input" type="search" placeholder="Cari kelas" aria-label="Cari kelas" data-filter=".kcard" data-empty="#noRes">
  </div>
  <button class="btn" type="button" data-modal="#modalKelas" data-title="Tambah kelas"><?= icon('plus', 18) ?> Tambah kelas</button>
</div>

<?php if (!$rows): ?>
  <section class="card empty">
    <img src="assets/img/logo.png" alt="">
    <h2>Belum ada kelas</h2>
    <p>Klik “Tambah kelas” untuk membuat kelas pertama.</p>
  </section>
<?php else: ?>
  <div class="kgrid">
    <?php foreach ($rows as $r): ?>
      <article class="card kcard" data-search="<?= e(mb_strtolower($r['nama_kelas'])) ?>">
        <div class="kcard-top">
          <h2><?= e($r['nama_kelas']) ?></h2>
          <span class="chip gray"><?= (int)$r['jml'] ?> siswa</span>
        </div>
        <div class="kcard-actions">
          <a class="btn sm" href="absensi.php?kelas=<?= (int)$r['id'] ?>"><?= icon('check', 16) ?> Absen</a>
          <a class="btn ghost sm" href="siswa.php?kelas=<?= (int)$r['id'] ?>">Lihat siswa</a>
          <span class="spacer"></span>
          <button class="icon-btn" type="button" title="Ubah" aria-label="Ubah <?= e($r['nama_kelas']) ?>"
                  data-modal="#modalKelas" data-title="Ubah nama kelas"
                  data-fill="<?= e(json_encode(['id' => $r['id'], 'nama_kelas' => $r['nama_kelas']])) ?>"><?= icon('edit', 18) ?></button>
          <form method="post" data-confirm="Kelas <?= e($r['nama_kelas']) ?> akan dihapus. Kelas yang masih memiliki siswa tidak bisa dihapus.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="icon-btn del" type="submit" title="Hapus" aria-label="Hapus <?= e($r['nama_kelas']) ?>"><?= icon('trash', 18) ?></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="muted-center" id="noRes" hidden>Tidak ada kelas yang cocok dengan pencarian.</p>
<?php endif; ?>

<!-- Modal tambah / ubah -->
<div class="modal" id="modalKelas" aria-hidden="true">
  <div class="modal-box sm" role="dialog" aria-modal="true" aria-labelledby="mKelasTitle">
    <div class="modal-head">
      <h3 class="modal-title" id="mKelasTitle">Tambah kelas</h3>
      <button class="icon-btn" type="button" data-close aria-label="Tutup"><?= icon('x', 18) ?></button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="">
      <div class="field">
        <label for="kNama">Nama kelas</label>
        <input class="input" id="kNama" name="nama_kelas" maxlength="50" placeholder="" required>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-close>Batal</button>
        <button type="submit" class="btn">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
