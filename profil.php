<?php
require __DIR__ . '/config.php';
require_login();

$uid = (int)$_SESSION['uid'];
$st = $pdo->prepare('SELECT id, nama, email, foto FROM users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch();
if (!$user) {
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama = trim($_POST['nama'] ?? '');
    if ($nama === '' || mb_strlen($nama) > 100) {
        flash('err', 'Isi nama lengkap (maksimal 100 karakter).');
    } else {
        $errFoto = null;
        $foto = upload_foto($_FILES['foto'] ?? null, 'guru', $user['foto'], $errFoto);
        if (!$errFoto && !empty($_POST['hapus_foto']) && empty($_FILES['foto']['name'])) {
            hapus_foto('guru', $user['foto']);
            $foto = null;
        }
        if ($errFoto) {
            flash('err', $errFoto);
        } else {
            $pdo->prepare('UPDATE users SET nama = ?, foto = ? WHERE id = ?')->execute([$nama, $foto, $uid]);
            $_SESSION['nama'] = $nama;
            flash('ok', 'Profil diperbarui.');
        }
    }
    redirect('profil.php');
}

$pageTitle = 'Profil saya';
$pageSub   = 'Ubah nama dan foto profil';
$active    = '';
require __DIR__ . '/includes/header.php';
$fotoNow = foto_url('guru', $user['foto']);
?>

<section class="card" style="max-width:520px">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label>Foto profil <small class="opt">(JPG/PNG/WEBP, maks. 20 MB)</small></label>
      <div class="foto-pick">
        <span class="foto-prev" data-preview-box>
          <img alt="" data-preview src="<?= e($fotoNow) ?>" <?= $fotoNow ? '' : 'hidden' ?>>
          <?= $fotoNow ? '' : icon('users', 22) ?>
        </span>
        <div>
          <input type="file" name="foto" accept="image/png,image/jpeg,image/webp" data-preview-input>
          <?php if ($fotoNow): ?>
            <label class="foto-del"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto saat ini</label>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="field">
      <label for="pNama">Nama lengkap</label>
      <input class="input" id="pNama" name="nama" maxlength="100" value="<?= e($user['nama']) ?>" required>
    </div>
    <div class="field">
      <label>Email</label>
      <input class="input" value="<?= e($user['email']) ?>" disabled>
    </div>
    <button class="btn" type="submit">Simpan</button>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
