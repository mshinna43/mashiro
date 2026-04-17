<?php
/**
 * config/helpers.php
 * Fungsi utilitas bersama: flash, redirect, badge, format
 */

/* ── Session & Flash Messages ── */
function flash_set(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function flash_html(): string {
    $f = flash_get();
    if (!$f) return '';
    $icons = ['success'=>'✅','error'=>'❌','warning'=>'⚠️','info'=>'ℹ️'];
    $icon  = $icons[$f['type']] ?? 'ℹ️';
    return '<div class="alert alert-'.$f['type'].'">'.$icon.' '.htmlspecialchars($f['msg']).'</div>';
}

/* ── Redirect ── */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/* ── Format ── */
function rupiah(int $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function tgl_id(string $date): string {
    if (!$date) return '—';
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    [$y, $m, $d] = explode('-', $date);
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}

/* ── HTML Badges ── */
function badge(string $text, string $color): string {
    return '<span class="badge badge-'.$color.'">'.htmlspecialchars($text).'</span>';
}

function badge_status_ppdb(string $s): string {
    return match($s) {
        'diterima'   => badge('Diterima',   'green'),
        'ditolak'    => badge('Ditolak',    'red'),
        'verifikasi' => badge('Verifikasi', 'amber'),
        default      => badge($s,           'gray'),
    };
}

function badge_jalur(string $j): string {
    return match($j) {
        'Zonasi'   => badge('Zonasi',   'blue'),
        'Prestasi' => badge('Prestasi', 'purple'),
        'Afirmasi' => badge('Afirmasi', 'teal'),
        default    => badge($j,         'gray'),
    };
}

function badge_stok(int $stok, int $dipinjam): string {
    $sisa = $stok - $dipinjam;
    if ($sisa <= 0) return badge('Habis',       'red');
    if ($sisa <= 2) return badge('Sisa '.$sisa, 'amber');
    return badge('Tersedia '.$sisa, 'green');
}

function badge_peminjaman(string $s): string {
    return match($s) {
        'aktif'     => badge('Aktif',     'green'),
        'terlambat' => badge('Terlambat', 'red'),
        'kembali'   => badge('Kembali',   'gray'),
        default     => badge($s,          'gray'),
    };
}

function badge_absen(string $s): string {
    return match($s) {
        'hadir' => badge('Hadir', 'green'),
        'absen' => badge('Absen', 'red'),
        'izin'  => badge('Izin',  'amber'),
        'sakit' => badge('Sakit', 'blue'),
        default => badge($s,      'gray'),
    };
}

function badge_spp(string $s): string {
    return match($s) {
        'lunas'     => badge('Lunas',       'green'),
        'belum'     => badge('Belum Bayar', 'amber'),
        'menunggak' => badge('Menunggak',   'red'),
        default     => badge($s,            'gray'),
    };
}

/* ── Safe Input ── */
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ── Pagination helper ── */
function paginate(int $total, int $per_page, int $page): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page        = max(1, min($page, $total_pages));
    $offset      = ($page - 1) * $per_page;
    return ['page' => $page, 'per_page' => $per_page, 'offset' => $offset,
            'total' => $total, 'total_pages' => $total_pages];
}
