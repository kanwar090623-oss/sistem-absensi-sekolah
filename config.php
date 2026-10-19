<?php
/* SiAbsen - konfigurasi & fungsi bantu */

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
date_default_timezone_set('Asia/Makassar'); // WITA, ganti bila perlu

// ---- Pengaturan database (sesuaikan dengan server Anda) ----
const DB_HOST = 'localhost';
const DB_NAME = 'siabsen_baru';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'SiAbsen';
const STATUS = ['H' => 'Hadir', 'I' => 'Izin', 'S' => 'Sakit', 'A' => 'Alpa'];

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Koneksi database gagal. Periksa pengaturan di config.php dan pastikan database.sql sudah diimpor.');
}

/* ---------- Helper umum ---------- */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function require_login(): void
{
    if (!isset($_SESSION['uid'])) {
        redirect('login.php');
    }
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Sesi sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/* ---------- Tanggal & teks berbahasa Indonesia ---------- */
function hari_indo(string $d): string
{
    return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int)date('w', strtotime($d))];
}

function bulan_indo(int $m): string
{
    return [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$m];
}

function tgl_indo(string $d, bool $hari = false): string
{
    $t = strtotime($d);
    return ($hari ? hari_indo($d) . ', ' : '') . date('j', $t) . ' ' . bulan_indo((int)date('n', $t)) . ' ' . date('Y', $t);
}

function salam(): string
{
    $h = (int)date('G');
    if ($h < 11) return 'Selamat pagi';
    if ($h < 15) return 'Selamat siang';
    if ($h < 18) return 'Selamat sore';
    return 'Selamat malam';
}

function initials(string $nama): string
{
    $p = preg_split('/\s+/', trim($nama));
    $s = mb_substr($p[0], 0, 1);
    if (isset($p[1])) {
        $s .= mb_substr($p[1], 0, 1);
    }
    return mb_strtoupper($s);
}

/* ---------- Foto profil ---------- */
const UPLOAD_DIR = __DIR__ . '/uploads';
const FOTO_MAKS  = 20 * 1024 * 1024; // 20 MB

/** Simpan foto upload ke uploads/{siswa|guru}. Return: nama file baru, atau $lama bila tidak ada upload.
 *  Bila gagal, isi $err dan return $lama. */
function upload_foto(?array $file, string $folder, ?string $lama, ?string &$err = null): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $lama;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $err = 'Foto gagal diunggah (kode ' . $file['error'] . '). Coba lagi dengan file yang lebih kecil.';
        return $lama;
    }
    if ($file['size'] > FOTO_MAKS) {
        $err = 'Ukuran foto maksimal 20 MB.';
        return $lama;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ok[$mime])) {
        $err = 'Format foto harus JPG, PNG, atau WEBP.';
        return $lama;
    }
    $dir = UPLOAD_DIR . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $err = 'Folder uploads tidak bisa dibuat.';
        return $lama;
    }
    $baru = bin2hex(random_bytes(10)) . '.' . $ok[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $baru)) {
        $err = 'Foto gagal disimpan di server.';
        return $lama;
    }
    hapus_foto($folder, $lama);
    return $baru;
}

function hapus_foto(string $folder, ?string $nama): void
{
    if ($nama && preg_match('/^[a-f0-9]+\.(jpg|png|webp)$/', $nama)) {
        $p = UPLOAD_DIR . '/' . $folder . '/' . $nama;
        if (is_file($p)) {
            unlink($p);
        }
    }
}

function foto_url(string $folder, ?string $nama): string
{
    if ($nama && preg_match('/^[a-f0-9]+\.(jpg|png|webp)$/', $nama) && is_file(UPLOAD_DIR . '/' . $folder . '/' . $nama)) {
        return 'uploads/' . $folder . '/' . $nama;
    }
    return '';
}

/** Avatar: foto bila ada, kalau tidak inisial berwarna. */
function avatar(string $nama, ?string $foto, int $id, string $folder = 'siswa'): string
{
    $url = foto_url($folder, $foto);
    if ($url) {
        return '<span class="dot-avatar has-img"><img src="' . e($url) . '" alt="" loading="lazy"></span>';
    }
    return '<span class="dot-avatar av' . ($id % 5) . '">' . e(initials($nama)) . '</span>';
}

/* ---------- Ikon SVG ---------- */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'check'     => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M2 20h20"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book'      => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'     => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'eye'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'x'         => '<path d="M18 6L6 18M6 6l12 12"/>',
        'printer'   => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'save'      => '<path d="M20 6L9 17l-5-5"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}
