<?php
require __DIR__ . '/config.php';

if (isset($_SESSION['uid'])) {
    redirect('dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $st = $pdo->prepare('SELECT id, nama, password FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['uid']  = (int)$u['id'];
        $_SESSION['nama'] = $u['nama'];
        redirect('dashboard.php');
    }
    $error = 'Email atau kata sandi salah. Periksa lagi, lalu coba masuk kembali.';
}

$title = 'Masuk';
$lead  = 'Masuk untuk mencatat dan melihat absensi siswa.';
require __DIR__ . '/includes/auth_top.php';
?>
      <?php if ($error): ?>
        <div class="alert err" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form" data-loading novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Email</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="" autocomplete="email" required autofocus>
        </div>
        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="pw">
            <input class="input" type="password" id="password" name="password" placeholder="" autocomplete="current-password" required>
            <button type="button" class="pw-btn" data-toggle-pass aria-label="Tampilkan kata sandi"><?= icon('eye', 18) ?></button>
          </div>
        </div>
        <button class="btn block" type="submit"><span>Masuk</span></button>
      </form>

      <p class="auth-alt">Belum punya akun? <a href="register.php"><b>Daftar sekarang</b></a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
