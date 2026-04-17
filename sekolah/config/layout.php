<?php
/**
 * config/layout.php
 * Header & sidebar layout bersama
 * Dipanggil: require_once ROOT.'/config/layout.php';
 * Variabel yang harus di-set sebelum include:
 *   $page_title  — judul halaman
 *   $active_menu — nama menu aktif (ppdb|perpustakaan|absensi-peserta|pembayaran-spp|absensi-siswa)
 *   $accent      — warna aksen CSS (opsional, default purple)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$accent      = $accent      ?? '#5B21B6';
$accent_light= $accent_light ?? 'rgba(91,33,182,.12)';
$accent_bg   = $accent_bg   ?? '#F5F3FF';

$menus = [
    ['id'=>'ppdb',             'icon'=>'📋','label'=>'PPDB',             'href'=>'/ppdb/'],
    ['id'=>'perpustakaan',     'icon'=>'📚','label'=>'Perpustakaan',     'href'=>'/perpustakaan/'],
    ['id'=>'absensi-peserta',  'icon'=>'✅','label'=>'Absensi Peserta',  'href'=>'/absensi-peserta/'],
    ['id'=>'pembayaran-spp',   'icon'=>'💳','label'=>'Pembayaran SPP',   'href'=>'/pembayaran-spp/'],
    ['id'=>'absensi-siswa',    'icon'=>'👥','label'=>'Absensi Siswa',    'href'=>'/absensi-siswa/'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> — SMP Negeri 1</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    :root {
      --accent      : <?= $accent ?>;
      --accent-light: <?= $accent_light ?>;
      --accent-bg   : <?= $accent_bg ?>;
    }
    .nav-item.active { background: var(--accent); color: #fff; }
    .nav-item.active .nav-icon { opacity: 1; }
    .sidebar-brand .brand-icon { background: var(--accent-bg); color: var(--accent); }
    .topbar-chip { background: var(--accent-bg); color: var(--accent); }
    .btn-primary { background: var(--accent); }
    .pill.active  { background: var(--accent); border-color: var(--accent); }
    .pg-btn.active{ background: var(--accent); border-color: var(--accent); }
    .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-light); }
    .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-light); }
    .progress-bar { background: var(--accent); }
  </style>
</head>
<body>
<div class="layout">

  <!-- ═══ Sidebar ═══ -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">🎓</div>
      <div class="brand-name">SMP Negeri 1 Contoh</div>
      <div class="brand-sub">Sistem Informasi Sekolah</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-label">Menu Utama</div>
      <?php foreach ($menus as $m): ?>
      <a href="<?= $m['href'] ?>" class="nav-item <?= ($active_menu === $m['id']) ? 'active' : '' ?>">
        <div class="nav-icon"><?= $m['icon'] ?></div>
        <?= $m['label'] ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">v2.0 &nbsp;·&nbsp; TP 2025/2026</div>
  </aside>

  <!-- ═══ Main Content ═══ -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-title"><?= e($page_title) ?></div>
      <div class="topbar-right">
        <span class="topbar-chip"><?= date('d M Y') ?></span>
        <span class="topbar-date"><?= date('H:i') ?> WIB</span>
        <div class="avatar-xs" style="background:var(--accent-bg);color:var(--accent);">AD</div>
      </div>
    </header>
    <div class="page-body">
<?php /* konten halaman dimulai di sini */ ?>
