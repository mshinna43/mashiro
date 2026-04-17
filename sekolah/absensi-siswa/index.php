<?php
/**
 * absensi-siswa/index.php — Absensi Siswa Harian CRUD
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/helpers.php';
db_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    /* Simpan absensi bulk */
    if ($action === 'simpan') {
        $kelas   = trim(post('kelas'));
        $mapel   = trim(post('mapel'));
        $tanggal = post('tanggal', date('Y-m-d'));
        $statuses= post('status', []);
        $kets    = post('ket', []);

        $upsert = db()->prepare('INSERT INTO absensi_siswa (nis,nama,kelas,mapel,tanggal,status,keterangan)
            VALUES (?,?,?,?,?,?,?)
            ON CONFLICT(nis,mapel,tanggal) DO UPDATE SET status=excluded.status,keterangan=excluded.keterangan');

        $n = 0;
        foreach ($statuses as $nis => $st) {
            $nama = post('nama_'.$nis, $nis);
            $ket  = $kets[$nis] ?? '';
            $upsert->execute([$nis, $nama, $kelas, $mapel, $tanggal, $st, $ket]);
            $n++;
        }
        flash_set('success', "Absensi $kelas — $mapel berhasil disimpan ($n siswa).");
    }

    /* Tambah siswa */
    if ($action === 'tambah_siswa') {
        $nis   = trim(post('nis'));
        $nama  = trim(post('nama'));
        $kelas = trim(post('kelas'));
        $mapel = trim(post('mapel'));
        $tgl   = post('tanggal', date('Y-m-d'));
        if (!$nis || !$nama) { flash_set('error','NIS dan nama wajib.'); }
        else {
            db()->prepare('INSERT OR IGNORE INTO absensi_siswa (nis,nama,kelas,mapel,tanggal,status) VALUES (?,?,?,?,?,\'hadir\')')
                ->execute([$nis,$nama,$kelas,$mapel,$tgl]);
            flash_set('success',"Siswa $nama ditambahkan.");
        }
        redirect("/absensi-siswa/?kelas=".urlencode(post('kelas'))."&mapel=".urlencode(post('mapel'))."&tgl=".post('tanggal'));
    }

    redirect('/absensi-siswa/?kelas='.urlencode(post('kelas','8A')).'&mapel='.urlencode(post('mapel','Matematika')).'&tgl='.post('tanggal',date('Y-m-d')));
}

/* ── GET ── */
$kelas_list = ['7A','7B','8A','8B','8C','9A','9B'];
$mapel_list = ['Matematika','Bahasa Indonesia','IPA','IPS','Bahasa Inggris','PJOK','Seni Budaya'];

$kelas   = get('kelas','8A');
$mapel   = get('mapel','Matematika');
$tanggal = get('tgl', date('Y-m-d'));

$siswa = db()->prepare('SELECT * FROM absensi_siswa WHERE kelas=? AND mapel=? AND tanggal=? ORDER BY nama');
$siswa->execute([$kelas, $mapel, $tanggal]);
$siswa_rows = $siswa->fetchAll();

$hadir = count(array_filter($siswa_rows, fn($s) => $s['status']==='hadir'));
$absen = count(array_filter($siswa_rows, fn($s) => $s['status']==='absen'));
$izin  = count(array_filter($siswa_rows, fn($s) => $s['status']==='izin'));
$sakit = count(array_filter($siswa_rows, fn($s) => $s['status']==='sakit'));
$total = count($siswa_rows);
$pct   = $total > 0 ? round($hadir/$total*100) : 0;

/* Rekap mingguan */
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($tanggal)));
$rekap = [];
for ($i=0;$i<5;$i++) {
    $d = date('Y-m-d', strtotime("+$i days", strtotime($week_start)));
    $row = db()->prepare('SELECT
        SUM(CASE WHEN status=\'hadir\' THEN 1 ELSE 0 END) h,
        SUM(CASE WHEN status=\'absen\' THEN 1 ELSE 0 END) a,
        SUM(CASE WHEN status=\'izin\'  THEN 1 ELSE 0 END) i,
        SUM(CASE WHEN status=\'sakit\' THEN 1 ELSE 0 END) s
        FROM absensi_siswa WHERE kelas=? AND tanggal=?');
    $row->execute([$kelas,$d]);
    $r = $row->fetch();
    $rekap[] = ['hari'=>date('D',strtotime($d)),'tgl'=>$d,'data'=>$r];
}

$page_title  = 'Absensi Siswa';
$active_menu = 'absensi-siswa';
$accent='#16A34A'; $accent_light='rgba(22,163,74,.12)'; $accent_bg='#F0FDF4';
require_once ROOT . '/config/layout.php';
?>

<?= flash_html() ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Absensi Siswa Harian</h1>
    <p class="page-subtitle">Rekam kehadiran siswa per kelas & mata pelajaran</p>
  </div>
  <button class="btn btn-ghost" onclick="openModal('modal-tambah-siswa')">+ Tambah Siswa</button>
</div>

<!-- Selector -->
<div style="background:#fff;border:1px solid var(--n100);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:20px">
  <form method="GET" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:11px;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.5px">Kelas:</span>
      <select name="kelas" class="form-control" style="width:auto" onchange="this.form.submit()">
        <?php foreach($kelas_list as $k): ?>
        <option <?= $kelas===$k?'selected':'' ?>><?= $k ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:11px;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.5px">Mapel:</span>
      <select name="mapel" class="form-control" style="width:auto" onchange="this.form.submit()">
        <?php foreach($mapel_list as $m): ?>
        <option <?= $mapel===$m?'selected':'' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:11px;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.5px">Tanggal:</span>
      <input type="date" name="tgl" class="form-control" style="width:auto" value="<?= e($tanggal) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon" style="background:#DCFCE7;color:#166534">✅</div><div class="stat-label">Hadir</div><div class="stat-value" style="color:#166534"><?= $hadir ?></div><div class="stat-desc"><?= $pct ?>%</div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEE2E2;color:#991B1B">✗</div><div class="stat-label">Absen</div><div class="stat-value" style="color:#991B1B"><?= $absen ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEF3C7;color:#92400E">📝</div><div class="stat-label">Izin</div><div class="stat-value" style="color:#92400E"><?= $izin ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#DBEAFE;color:#1E40AF">🏥</div><div class="stat-label">Sakit</div><div class="stat-value" style="color:#1E40AF"><?= $sakit ?></div></div>
</div>

<div class="two-col">
  <!-- Tabel Absensi -->
  <form method="POST">
    <input type="hidden" name="action"  value="simpan">
    <input type="hidden" name="kelas"   value="<?= e($kelas) ?>">
    <input type="hidden" name="mapel"   value="<?= e($mapel) ?>">
    <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">

    <div class="table-wrapper">
      <div class="card-header">
        <span>Kelas <?= e($kelas) ?> — <?= e($mapel) ?> — <?= tgl_id($tanggal) ?></span>
        <div class="flex items-center gap-3">
          <div class="progress" style="width:100px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
          <span style="font-size:12px;color:var(--n400)"><?= $pct ?>%</span>
          <button type="submit" class="btn btn-primary btn-sm">💾 Simpan</button>
        </div>
      </div>

      <?php if(!$siswa_rows): ?>
      <div style="text-align:center;padding:40px;color:var(--n400)">
        Belum ada data absensi. Klik "+ Tambah Siswa" untuk menambah.
      </div>
      <?php else: ?>
      <table>
        <thead><tr><th>#</th><th>NIS</th><th>Nama</th><th>Status</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach($siswa_rows as $i=>$s): ?>
        <input type="hidden" name="nama_<?= e($s['nis']) ?>" value="<?= e($s['nama']) ?>">
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td class="td-mono"><?= e($s['nis']) ?></td>
          <td class="td-bold"><?= e($s['nama']) ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <?php
              $opts = ['hadir'=>['H','#DCFCE7','#166534','#BBF7D0'],'absen'=>['A','#FEE2E2','#991B1B','#FECACA'],'izin'=>['I','#FEF3C7','#92400E','#FDE68A'],'sakit'=>['S','#DBEAFE','#1E40AF','#BFDBFE']];
              foreach($opts as $v=>[$l,$bg,$fg,$bd]):
                $sel = $s['status']===$v;
              ?>
              <label style="cursor:pointer">
                <input type="radio" name="status[<?= e($s['nis']) ?>]" value="<?= $v ?>" <?= $sel?'checked':'' ?> class="radio-st" style="display:none">
                <span class="stog" data-bg="<?= $bg ?>" data-fg="<?= $fg ?>" data-bd="<?= $bd ?>"
                  style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:600;cursor:pointer;transition:all .14s;border:1px solid <?= $sel?$bd:'var(--n200)' ?>;background:<?= $sel?$bg:'#fff' ?>;color:<?= $sel?$fg:'var(--n400)' ?>"><?= $l ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </td>
          <td><input type="text" name="ket[<?= e($s['nis']) ?>]" class="form-control" style="padding:5px 9px;font-size:12px" placeholder="Keterangan…" value="<?= e($s['keterangan'] ?? '') ?>"></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <div style="padding:12px 18px;border-top:1px solid var(--n100);display:flex;justify-content:flex-end">
        <button type="submit" class="btn btn-primary">💾 Simpan Absensi</button>
      </div>
    </div>
  </form>

  <!-- Kolom Kanan -->
  <div>
    <!-- Meter -->
    <div class="card mb-4" style="margin-bottom:16px">
      <div class="card-header">Kehadiran Hari Ini</div>
      <div style="padding:20px;text-align:center">
        <div style="font-size:46px;font-weight:700;color:var(--accent);line-height:1"><?= $pct ?>%</div>
        <div style="font-size:12px;color:var(--n400);margin-top:4px"><?= $hadir ?> hadir dari <?= $total ?> siswa</div>
        <div class="progress" style="margin:14px 0 6px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        <?php if($absen > 0): ?>
        <div class="alert alert-warning" style="margin-top:12px;text-align:left">
          ⚠️ <?= $absen ?> siswa absen tanpa keterangan.
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Rekap Mingguan -->
    <div class="card">
      <div class="card-header">Rekap Minggu Ini — Kelas <?= e($kelas) ?></div>
      <div style="padding:0">
        <table style="width:100%;font-size:12px">
          <thead><tr>
            <th style="padding:9px 14px;font-size:10px;font-weight:600;color:var(--n400);background:var(--n50);text-align:left">Hari</th>
            <th style="padding:9px 8px;font-size:10px;font-weight:600;color:#166534;background:var(--n50);text-align:center">H</th>
            <th style="padding:9px 8px;font-size:10px;font-weight:600;color:#991B1B;background:var(--n50);text-align:center">A</th>
            <th style="padding:9px 8px;font-size:10px;font-weight:600;color:#92400E;background:var(--n50);text-align:center">I</th>
            <th style="padding:9px 8px;font-size:10px;font-weight:600;color:#1E40AF;background:var(--n50);text-align:center">S</th>
          </tr></thead>
          <tbody>
          <?php foreach($rekap as $r):
            $is_today = ($r['tgl'] === $tanggal);
            $d = $r['data'];
          ?>
          <tr style="<?= $is_today?'background:var(--accent-bg)':'' ?>">
            <td style="padding:9px 14px;font-weight:<?= $is_today?'600':'400' ?>;color:<?= $is_today?'var(--accent)':'var(--n600)' ?>;border-top:1px solid var(--n100)">
              <?= $r['hari'] ?> <span style="font-size:10px;color:var(--n400)"><?= date('d/m',strtotime($r['tgl'])) ?></span>
            </td>
            <td style="text-align:center;padding:9px 8px;border-top:1px solid var(--n100);font-weight:600;color:#166534"><?= $d['h'] ?? '—' ?></td>
            <td style="text-align:center;padding:9px 8px;border-top:1px solid var(--n100);font-weight:600;color:#991B1B"><?= $d['a'] ?? '—' ?></td>
            <td style="text-align:center;padding:9px 8px;border-top:1px solid var(--n100);font-weight:600;color:#92400E"><?= $d['i'] ?? '—' ?></td>
            <td style="text-align:center;padding:9px 8px;border-top:1px solid var(--n100);font-weight:600;color:#1E40AF"><?= $d['s'] ?? '—' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Siswa -->
<div id="modal-tambah-siswa" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Tambah Siswa ke Absensi</span><button class="modal-close" onclick="closeModal('modal-tambah-siswa')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action"  value="tambah_siswa">
      <input type="hidden" name="kelas"   value="<?= e($kelas) ?>">
      <input type="hidden" name="mapel"   value="<?= e($mapel) ?>">
      <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">NIS *</label><input type="text" name="nis" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Kelas</label><input type="text" name="kelas_siswa" class="form-control" value="<?= e($kelas) ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-tambah-siswa')">Batal</button>
        <button type="submit" class="btn btn-primary">Tambah</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('.radio-st').forEach(function(radio) {
  radio.addEventListener('change', function() {
    var group = document.querySelectorAll('input[name="'+this.name+'"]');
    group.forEach(function(r) {
      var span = r.nextElementSibling;
      if (r.checked) {
        span.style.background  = span.dataset.bg;
        span.style.color       = span.dataset.fg;
        span.style.borderColor = span.dataset.bd;
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
