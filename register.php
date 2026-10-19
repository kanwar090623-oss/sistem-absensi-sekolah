<?php
require __DIR__ . '/config.php';

if (isset($_SESSION['uid'])) {
    redirect('dashboard.php');
}

$error = '';
$nama  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($nama === '' || mb_strlen($nama) > 100) {
        $error = 'Isi nama lengkap Anda (maksimal 100 karakter).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email belum benar. Contoh: nama@sekolah.sch.id';
    } elseif (strlen($pass) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } elseif ($pass !== $pass2) {
        $error = 'Konfirmasi kata sandi belum sama. Ketik ulang dengan teliti.';
    } else {
        $cek = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $error = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
        } else {
            $ins = $pdo->prepare('INSERT INTO users (nama, email, password) VALUES (?, ?, ?)');
            $ins->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('ok', 'Akun berhasil dibuat. Silakan masuk.');
            redirect('login.php');
        }
    }
}

$title = 'Buat akun';
$lead  = 'Daftar sebagai guru atau petugas absensi.';
require __DIR__ . '/includes/auth_top.php';
?>
      <?php if ($error): ?>
        <div class="alert err" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form" data-loading novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="nama">Nama lengkap</label>
          <input class="input" type="text" id="nama" name="nama" value="<?= e($nama) ?>" placeholder="" autocomplete="name" required autofocus>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="" autocomplete="email" required>
        </div>
        <div class="field">
          <label for="pw">Kata sandi</label>
          <div class="pw">
            <input class="input" type="password" id="pw" name="password" placeholder="" autocomplete="new-password" required>
            <button type="button" class="pw-btn" data-toggle-pass aria-label="Tampilkan kata sandi"><?= icon('eye', 18) ?></button>
          </div>
          <div class="meter" id="meter" data-lv="0"><i></i><i></i><i></i></div>
          <span class="hint" id="meterTxt"></span>
        </div>
        <div class="field">
          <label for="pw2">Ulangi kata sandi</label>
          <input class="input" type="password" id="pw2" name="password2" placeholder="" autocomplete="new-password" required>
          <span class="hint" id="matchTxt"></span>
        </div>
        <button class="btn block" type="submit"><span>Buat akun</span></button>
      </form>

      <p class="auth-alt">Sudah punya akun? <a href="login.php"><b>Masuk</b></a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
