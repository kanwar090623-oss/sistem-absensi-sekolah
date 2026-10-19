<?php
require __DIR__ . '/config.php';
require_login();

$kf = (int)($_POST['kf'] ?? $_GET['kelas'] ?? 0); // filter kelas

/* ---- Simpan / hapus ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $nisn = '';
    try {
        if ($act === 'save') {
            $id   = (int)($_POST['id'] ?? 0);
            $nisn  = trim($_POST['nisn'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $jk   = $_POST['jenis_kelamin'] ?? 'L';
            $kid  = (int)($_POST['kelas_id'] ?? 0);

            // foto lama (hanya saat ubah data)
            $fotoLama = null;
            if ($id) {
                $q = $pdo->prepare('SELECT foto FROM siswa WHERE id = ?');
                $q->execute([$id]);
                $fotoLama = $q->fetchColumn() ?: null;
            }

            if ($nisn === '' || $nama === '' || !$kid || !in_array($jk, ['L', 'P'], true)) {
                flash('err', 'Lengkapi NISN, nama, jenis kelamin, dan kelas terlebih dahulu.');
            } else {
                $errFoto = null;
                $foto = upload_foto($_FILES['foto'] ?? null, 'siswa', $fotoLama, $errFoto);
                if (!$errFoto && $id && !empty($_POST['hapus_foto']) && empty($_FILES['foto']['name'])) {
                    hapus_foto('siswa', $fotoLama);
                    $foto = null;
                }
                if ($errFoto) {
                    flash('err', $errFoto);
                } elseif ($id) {
                    $pdo->prepare('UPDATE siswa SET nisn = ?, nama = ?, jenis_kelamin = ?, kelas_id = ?, foto = ? WHERE id = ?')
                        ->execute([$nisn, $nama, $jk, $kid, $foto, $id]);
                    flash('ok', 'Data ' . $nama . ' diperbarui.');
                } else {
                    $pdo->prepare('INSERT INTO siswa (nisn, nama, jenis_kelamin, kelas_id, foto) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$nisn, $nama, $jk, $kid, $foto]);
                    flash('ok', $nama . ' ditambahkan.');
                }
            }
        } elseif ($act === 'delete') {
            $did = (int)($_POST['id'] ?? 0);
            $q = $pdo->prepare('SELECT foto FROM siswa WHERE id = ?');
            $q->execute([$did]);
            $fotoDel = $q->fetchColumn() ?: null;
            $pdo->prepare('DELETE FROM siswa WHERE id = ?')->execute([$did]);
            hapus_foto('siswa', $fotoDel);
            flash('ok', 'Siswa dihapus beserta riwayat absensinya.');
        }
    } catch (PDOException $ex) {
        flash('err', $ex->getCode() === '23000'
            ? 'NISN ' . $nisn . ' sudah dipakai siswa lain. Gunakan NISN yang berbeda.'
            : 'Terjadi kesalahan pada database. Coba lagi.');
    }
    redirect('siswa.php' . ($kf ? '?kelas=' . $kf : ''));
}

$kelasList = $pdo->query('SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();

$sql = 'SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.kelas_id';
$args = [];
if ($kf) {
    $sql .= ' WHERE s.kelas_id = ?';
    $args[] = $kf;
}
$st = $pdo->prepare($sql . ' ORDER BY k.nama_kelas, s.nama');
$st->execute($args);
$rows = $st->fetchAll();

$pageTitle = 'Siswa';
$pageSub   = count($rows) . ' siswa terdaftar' . ($kf ? ' di kelas ini' : '');
$active    = 'siswa';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$kelasList): ?>
  <section class="card empty">
    <img src="assets/img/logo.png" alt="">
    <h2>Buat kelas terlebih dahulu</h2>
    <p>Siswa harus masuk ke sebuah kelas. Tambahkan kelas dulu, lalu kembali ke sini.</p>
    <div class="empty-actions"><a class="btn" href="kelas.php">Tambah kelas</a></div>
  </section>
<?php else: ?>

<div class="toolbar">
  <div class="search grow">
    <?= icon('search', 18) ?>
    <input class="input" type="search" placeholder="Cari nama, NISN, atau kelas" aria-label="Cari siswa" data-filter=".item" data-empty="#noRes">
  </div>
  <form method="get">
    <label for="fKelas" class="sr">Filter kelas</label>
    <select class="select" id="fKelas" name="kelas" data-autosubmit>
      <option value="0">Semua kelas</option>
      <?php foreach ($kelasList as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kf ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <button class="btn" type="button" data-modal="#modalSiswa" data-title="Tambah siswa"
          data-fill="<?= e(json_encode(['kelas_id' => $kf ?: ''])) ?>"><?= icon('plus', 18) ?> Tambah siswa</button>
</div>

<section class="card flush">
  <div class="table-wrap">
    <table class="t">
      <thead>
        <tr><th>Siswa</th><th>NISN</th><th>L/P</th><th>Kelas</th><th class="num">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="item" data-search="<?= e(mb_strtolower($r['nisn'] . ' ' . $r['nama'] . ' ' . $r['nama_kelas'])) ?>">
            <td>
              <div class="cell-name">
                <?= avatar($r['nama'], $r['foto'] ?? null, (int)$r['id'], 'siswa') ?>
                <?= e($r['nama']) ?>
              </div>
            </td>
            <td><?= e($r['nisn']) ?></td>
            <td><?= $r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
            <td><span class="chip gray"><?= e($r['nama_kelas']) ?></span></td>
            <td class="num">
              <div class="row-actions">
                <button class="icon-btn" type="button" title="Ubah" aria-label="Ubah <?= e($r['nama']) ?>"
                        data-modal="#modalSiswa" data-title="Ubah data siswa"
                        data-fill="<?= e(json_encode(['id' => $r['id'], 'nisn' => $r['nisn'], 'nama' => $r['nama'], 'jenis_kelamin' => $r['jenis_kelamin'], 'kelas_id' => $r['kelas_id'], 'foto_url' => foto_url('siswa', $r['foto'] ?? null)])) ?>"><?= icon('edit', 18) ?></button>
                <form method="post" data-confirm="<?= e($r['nama']) ?> akan dihapus beserta seluruh riwayat absensinya. Tindakan ini tidak bisa dibatalkan.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="kf" value="<?= $kf ?>">
                  <button class="icon-btn del" type="submit" title="Hapus" aria-label="Hapus <?= e($r['nama']) ?>"><?= icon('trash', 18) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted-center" id="noRes" <?= $rows ? 'hidden' : '' ?>>
    <?= $rows ? 'Tidak ada siswa yang cocok dengan pencarian.' : 'Belum ada siswa. Klik “Tambah siswa” untuk memulai.' ?>
  </p>
</section>

<!-- Modal tambah / ubah -->
<div class="modal" id="modalSiswa" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="mSiswaTitle">
    <div class="modal-head">
      <h3 class="modal-title" id="mSiswaTitle">Tambah siswa</h3>
      <button class="icon-btn" type="button" data-close aria-label="Tutup"><?= icon('x', 18) ?></button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="kf" value="<?= $kf ?>">
      <div class="field">
        <label>Foto profil <small class="opt">(opsional, maks. 20 MB)</small></label>
        <div class="foto-pick">
          <span class="foto-prev" data-preview-box><img alt="" data-preview hidden><?= icon('users', 22) ?></span>
          <div>
            <input type="file" name="foto" id="sFoto" accept="image/png,image/jpeg,image/webp" data-preview-input>
            <label class="foto-del" data-del-wrap hidden><input type="checkbox" name="hapus_foto" value="1"> Hapus foto saat ini</label>
          </div>
        </div>
      </div>
      <div class="field">
        <label for="sNisn">NISN</label>
        <input class="input" id="sNisn" name="nisn" maxlength="20" placeholder="Nomor induk siswa" required>
      </div>
      <div class="field">
        <label for="sNama">Nama lengkap</label>
        <input class="input" id="sNama" name="nama" maxlength="100" placeholder="Nama siswa" required>
      </div>
      <div class="field">
        <label>Jenis kelamin</label>
        <div class="pills">
          <label class="pill"><input type="radio" name="jenis_kelamin" value="L" checked><span>Laki-laki</span></label>
          <label class="pill"><input type="radio" name="jenis_kelamin" value="P"><span>Perempuan</span></label>
        </div>
      </div>
      <div class="field">
        <label for="sKelas">Kelas</label>
        <select class="select" id="sKelas" name="kelas_id" required>
          <option value="">Pilih kelas</option>
          <?php foreach ($kelasList as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= e($k['nama_kelas']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn ghost" data-close>Batal</button>
        <button type="submit" class="btn">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
