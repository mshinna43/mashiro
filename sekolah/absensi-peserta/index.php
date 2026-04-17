<?php
/**
 * absensi-peserta/index.php — Absensi Peserta CRUD
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/helpers.php';
db_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    /* Tambah kegiatan */
    if ($action === 'tambah_kegiatan') {
        $nama = trim(post('nama'));
        $desk = trim(post('deskripsi'));
        if (!$nama) { flash_set('error','Nama kegiatan wajib diisi.'); }
        else {
            db()->prepare('INSERT INTO kegiatan (nama,deskripsi) VALUES (?,?)')->execute([$nama,$desk]);
            flash_set('success',"Kegiatan \"$nama\" ditambahkan.");
        }
    }

    /* Hapus kegiatan */
    if ($action === 'hapus_kegiatan') {
        $id = (int)post('id');
        db()->prepare('DELETE FROM absensi_peserta WHERE kegiatan_id=?')->execute([$id]);
        db()->prepare('DELETE FROM kegiatan WHERE id=?')->execute([$id]);
        flash_set('success','Kegiatan berhasil dihapus.');
    }

    /* Simpan absensi (bulk update status peserta) */
    if ($action === 'simpan_absensi') {
        $kg_id   = (int) post('kegiatan_id');
        $tanggal = post('tanggal', date('Y-m-d'));
        $statuses = post('status', []);   // array [nis => status]

        $upsert = db()->prepare('INSERT INTO absensi_peserta (kegiatan_id,nis,nama,kelas,tanggal,status,waktu)
            VALUES (?,?,?,?,?,?,?)
            ON CONFLICT(kegiatan_id,nis,tanggal) DO UPDATE SET status=excluded.status,waktu=excluded.waktu');

        foreach ($statuses as $nis => $st) {
            $nama  = post('nama_'.$nis, $nis);
            $kelas = post('kelas_'.$nis, '');
            $waktu = ($st === 'hadir') ? date('H:i') : null;
            $upsert->execute([$kg_id, $nis, $nama, $kelas, $tanggal, $st, $waktu]);
        }
        flash_set('success','Absensi berhasil disimpan.');
    }

    /* Tambah peserta ke kegiatan */
    if ($action === 'tambah_peserta') {
        $kg_id   = (int) post('kegiatan_id');
        $nis     = trim(post('nis'));
        $nama    = trim(post('nama'));
        $kelas   = trim(post('kelas'));
        $tanggal = post('tanggal', date('Y-m-d'));
        if (!$nis || !$nama) { flash_set('error','NIS dan nama wajib diisi.'); }
        else {
            db()->prepare('INSERT OR IGNORE INTO absensi_peserta (kegiatan_id,nis,nama,kelas,tanggal,status) VALUES (?,?,?,?,?,?)')
                ->execute([$kg_id,$nis,$nama,$kelas,$tanggal,'hadir']);
            flash_set('success',"Peserta $nama ditambahkan.");
        }
    }

    redirect('/absensi-peserta/?kg='.post('kegiatan_id'));
}

/* ── GET ── */
$kg_list  = db()->query('SELECT * FROM kegiatan ORDER BY id')->fetchAll();
$kg_id    = (int) get('kg', $kg_list[0]['id'] ?? 0);
$tanggal  = get('tgl', date('Y-m-d'));
$kg_aktif = null;
foreach ($kg_list as $k) { if ($k['id'] === $kg_id) { $kg_aktif = $k; break; } }

$peserta = [];
if ($kg_id) {
    $peserta = db()->prepare('SELECT * FROM absensi_peserta WHERE kegiatan_id=? AND tanggal=? ORDER BY nama')
        ->execute([$kg_id, $tanggal]) ? [] : [];
    $st = db()->prepare('SELECT * FROM absensi_peserta WHERE kegiatan_id=? AND tanggal=? ORDER BY nama');
    $st->execute([$kg_id, $tanggal]);
    $peserta = $st->fetchAll();
}

$hadir = count(array_filter($peserta, fn($p) => $p['status']==='hadir'));
$absen = count(array_filter($peserta, fn($p) => $p['status']==='absen'));
$izin  = count(array_filter($peserta, fn($p) => $p['status']==='izin'));
$sakit = count(array_filter($peserta, fn($p) => $p['status']==='sakit'));
$total = count($peserta);
$pct   = $total > 0 ? round($hadir/$total*100) : 0;

$page_title  = 'Absensi Peserta';
$active_menu = 'absensi-peserta';
$accent      = '#1D4ED8'; $accent_light='rgba(29,78,216,.12)'; $accent_bg='#EFF6FF';
require_once ROOT . '/config/layout.php';
?>

<?= flash_html() ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Absensi Peserta Kegiatan</h1>
    <p class="page-subtitle">Rekam kehadiran peserta pelatihan & ekstrakurikuler</p>
  </div>
  <div class="flex gap-2">
    <button class="btn btn-ghost" onclick="openModal('modal-kegiatan')">+ Tambah Kegiatan</button>
    <?php if ($kg_id): ?>
    <button class="btn btn-ghost" onclick="openModal('modal-peserta')">+ Tambah Peserta</button>
    <?php endif; ?>
  </div>
</div>

<!-- Pilih Kegiatan & Tanggal -->
<div style="background:#fff;border:1px solid var(--n100);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
  <div style="font-size:11px;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.5px">Kegiatan:</div>
  <div class="pills">
    <?php foreach ($kg_list as $k): ?>
    <a href="?kg=<?= $k['id'] ?>&tgl=<?= e($tanggal) ?>" class="pill <?= $kg_id===$k['id']?'active':'' ?>"><?= e($k['nama']) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="GET" style="display:flex;align-items:center;gap:8px;margin-left:auto">
    <input type="hidden" name="kg" value="<?= $kg_id ?>">
    <label style="font-size:11px;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.5px">Tanggal:</label>
    <input type="date" name="tgl" class="form-control" style="width:auto" value="<?= e($tanggal) ?>" onchange="this.form.submit()">
  </form>
  <?php if ($kg_aktif): ?>
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="hapus_kegiatan">
    <input type="hidden" name="id"     value="<?= $kg_id ?>">
    <button class="btn btn-danger btn-xs" data-confirm="Hapus kegiatan ini beserta semua data absensi?">Hapus Kegiatan</button>
  </form>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon" style="background:#DCFCE7;color:#166534">✅</div><div class="stat-label">Hadir</div><div class="stat-value" style="color:#166534"><?= $hadir ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEE2E2;color:#991B1B">✗</div><div class="stat-label">Absen</div><div class="stat-value" style="color:#991B1B"><?= $absen ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEF3C7;color:#92400E">📝</div><div class="stat-label">Izin</div><div class="stat-value" style="color:#92400E"><?= $izin ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#DBEAFE;color:#1E40AF">🏥</div><div class="stat-label">Sakit</div><div class="stat-value" style="color:#1E40AF"><?= $sakit ?></div></div>
</div>

<?php if ($kg_id): ?>
<form method="POST">
  <input type="hidden" name="action"      value="simpan_absensi">
  <input type="hidden" name="kegiatan_id" value="<?= $kg_id ?>">
  <input type="hidden" name="tanggal"     value="<?= e($tanggal) ?>">

  <div class="table-wrapper">
    <div class="card-header">
      <span>📋 <?= e($kg_aktif['nama'] ?? '') ?> — <?= tgl_id($tanggal) ?></span>
      <div class="flex items-center gap-3">
        <div class="progress" style="width:120px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        <span style="font-size:12px;color:var(--n400)"><?= $pct ?>% hadir</span>
        <button type="submit" class="btn btn-primary btn-sm">💾 Simpan</button>
      </div>
    </div>
    <?php if (!$peserta): ?>
    <div style="text-align:center;padding:40px;color:var(--n400)">
      Belum ada peserta. Klik "+ Tambah Peserta" untuk menambahkan.
    </div>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Waktu</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($peserta as $i => $p): ?>
      <input type="hidden" name="nama_<?= e($p['nis']) ?>"  value="<?= e($p['nama']) ?>">
      <input type="hidden" name="kelas_<?= e($p['nis']) ?>" value="<?= e($p['kelas']) ?>">
      <tr>
        <td class="text-muted text-sm"><?= $i+1 ?></td>
        <td class="td-mono"><?= e($p['nis']) ?></td>
        <td class="td-bold"><?= e($p['nama']) ?></td>
        <td><?= badge($p['kelas'],'gray') ?></td>
        <td style="font-family:var(--mono);font-size:12px"><?= $p['waktu'] ?? '—' ?></td>
        <td>
          <div style="display:flex;gap:4px">
            <?php foreach (['hadir'=>'H','absen'=>'A','izin'=>'I','sakit'=>'S'] as $v=>$l):
              $colors = ['hadir'=>'#DCFCE7:#166534:#BBF7D0','absen'=>'#FEE2E2:#991B1B:#FECACA','izin'=>'#FEF3C7:#92400E:#FDE68A','sakit'=>'#DBEAFE:#1E40AF:#BFDBFE'];
              [$bg,$fg,$bd] = explode(':',$colors[$v]);
              $sel = $p['status']===$v;
            ?>
            <label style="cursor:pointer">
              <input type="radio" name="status[<?= e($p['nis']) ?>]" value="<?= $v ?>" <?= $sel?'checked':'' ?> style="display:none" class="radio-status">
              <span class="stog-btn" style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid <?= $sel?$bd:'var(--n200)' ?>;background:<?= $sel?$bg:'#fff' ?>;color:<?= $sel?$fg:'var(--n400)' ?>;transition:all .14s"><?= $l ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</form>
<?php else: ?>
<div class="card" style="padding:40px;text-align:center;color:var(--n400)">Pilih kegiatan di atas atau tambah kegiatan baru.</div>
<?php endif; ?>

<!-- Modal Kegiatan -->
<div id="modal-kegiatan" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Tambah Kegiatan</span><button class="modal-close" onclick="closeModal('modal-kegiatan')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah_kegiatan">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nama Kegiatan *</label><input type="text" name="nama" class="form-control" placeholder="Pramuka, OSIS, Basket..." required></div>
        <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2" placeholder="Keterangan kegiatan..."></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-kegiatan')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah Peserta -->
<div id="modal-peserta" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Tambah Peserta</span><button class="modal-close" onclick="closeModal('modal-peserta')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action"      value="tambah_peserta">
      <input type="hidden" name="kegiatan_id" value="<?= $kg_id ?>">
      <input type="hidden" name="tanggal"     value="<?= e($tanggal) ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">NIS *</label><input type="text" name="nis" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Kelas</label><input type="text" name="kelas" class="form-control" placeholder="8A"></div>
        </div>
        <div class="form-group"><label class="form-label">Nama Peserta *</label><input type="text" name="nama" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-peserta')">Batal</button>
        <button type="submit" class="btn btn-primary">Tambah</button>
      </div>
    </form>
  </div>
</div>

<script>
/* Update visual toggle saat radio dipilih */
document.querySelectorAll('.radio-status').forEach(function(radio) {
  radio.addEventListener('change', function() {
    var group = document.querySelectorAll('input[name="'+this.name+'"]');
    var colors = {hadir:['#DCFCE7','#166534','#BBF7D0'],absen:['#FEE2E2','#991B1B','#FECACA'],
                  izin:['#FEF3C7','#92400E','#FDE68A'],sakit:['#DBEAFE','#1E40AF','#BFDBFE']};
    group.forEach(function(r) {
      var span = r.nextElementSibling;
      if (r.checked) {
        var c = colors[r.value] || ['#fff','#888','#ccc'];
        span.style.background   = c[0];
        span.style.color        = c[1];
        span.style.borderColor  = c[2];
      } else {
        span.style.background  = '#fff';
        span.style.color       = 'var(--n400)';
        span.style.borderColor = 'var(--n200)';
      }
    });
  });
});
</script>

<?php require_once ROOT . '/config/layout_end.php'; ?>
