<?php
/**
 * pembayaran-spp/index.php — Pembayaran SPP CRUD
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/helpers.php';
db_init();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    /* Tambah Siswa */
    if ($action === 'tambah_siswa') {
        $nis    = trim(post('nis'));
        $nama   = trim(post('nama'));
        $kelas  = trim(post('kelas'));
        $nominal= (int) str_replace(['.','Rp',' ',''], '', post('nominal', 350000));
        if (!$nis || !$nama) { flash_set('error','NIS dan nama wajib.'); }
        else {
            try {
                db()->prepare('INSERT INTO siswa (nis,nama,kelas,nominal) VALUES (?,?,?,?)')->execute([$nis,$nama,$kelas,$nominal]);
                flash_set('success',"Siswa $nama ditambahkan.");
            } catch(PDOException) { flash_set('error','NIS sudah terdaftar.'); }
        }
    }

    /* Catat Pembayaran */
    if ($action === 'bayar') {
        $siswa_id = (int) post('siswa_id');
        $bulan    = trim(post('bulan'));
        $nominal  = (int) post('nominal', 0);
        $metode   = trim(post('metode', 'Tunai'));
        $jt       = post('jatuh_tempo', date('Y-m-d'));
        if (!$siswa_id || !$bulan) { flash_set('error','Pilih siswa dan bulan.'); }
        else {
            db()->prepare('INSERT INTO spp (siswa_id,bulan,nominal,status,metode,jatuh_tempo,tgl_bayar)
                VALUES (?,?,?,\'lunas\',?,?,?)
                ON CONFLICT(siswa_id,bulan) DO UPDATE SET status=\'lunas\',metode=excluded.metode,tgl_bayar=excluded.tgl_bayar')
                ->execute([$siswa_id,$bulan,$nominal,$metode,$jt,date('Y-m-d')]);
            flash_set('success',"Pembayaran bulan $bulan berhasil dicatat.");
        }
    }

    /* Generate tagihan bulan baru */
    if ($action === 'generate') {
        $bulan = trim(post('bulan'));
        $jt    = trim(post('jatuh_tempo'));
        $siswa_list = db()->query('SELECT * FROM siswa')->fetchAll();
        $n = 0;
        foreach ($siswa_list as $s) {
            try {
                db()->prepare('INSERT OR IGNORE INTO spp (siswa_id,bulan,nominal,status,jatuh_tempo) VALUES (?,?,?,\'belum\',?)')
                    ->execute([$s['id'],$bulan,$s['nominal'],$jt]);
                $n++;
            } catch(PDOException) {}
        }
        flash_set('success',"Tagihan bulan $bulan berhasil di-generate untuk $n siswa.");
    }

    /* Hapus tagihan */
    if ($action === 'hapus_tagihan') {
        $id = (int) post('id');
        db()->prepare('DELETE FROM spp WHERE id=?')->execute([$id]);
        flash_set('success','Tagihan dihapus.');
    }

    redirect('/pembayaran-spp/');
}

/* ── GET ── */
$f_status = get('status','semua');
$q        = get('q','');
$page     = max(1,(int)get('page',1));
$per      = 10;

$where = []; $params = [];
if ($f_status !== 'semua') { $where[] = 's.status=?'; $params[] = $f_status; }
if ($q !== '')              { $where[] = '(si.nama LIKE ? OR si.nis LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$clause = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cnt = db()->prepare("SELECT COUNT(*) FROM spp s JOIN siswa si ON si.id=s.siswa_id $clause");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), $per, $page);

$rows_st = db()->prepare("SELECT s.*,si.nis,si.nama,si.kelas FROM spp s JOIN siswa si ON si.id=s.siswa_id $clause ORDER BY s.id DESC LIMIT ? OFFSET ?");
$rows_st->execute(array_merge($params,[$pg['per_page'],$pg['offset']]));
$rows = $rows_st->fetchAll();

$stats = db()->query("SELECT
    SUM(CASE WHEN status='lunas' THEN nominal ELSE 0 END) masuk,
    SUM(CASE WHEN status<>'lunas' THEN nominal ELSE 0 END) belum,
    COUNT(CASE WHEN status='lunas' THEN 1 END) n_lunas,
    COUNT(CASE WHEN status='belum' THEN 1 END) n_belum,
    COUNT(CASE WHEN status='menunggak' THEN 1 END) n_tunggak
    FROM spp")->fetch();

$siswa_list = db()->query('SELECT * FROM siswa ORDER BY kelas,nama')->fetchAll();

$page_title  = 'Pembayaran SPP';
$active_menu = 'pembayaran-spp';
$accent='#D97706'; $accent_light='rgba(217,119,6,.12)'; $accent_bg='#FFFBEB';
require_once ROOT . '/config/layout.php';
?>

<?= flash_html() ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Pembayaran SPP</h1>
    <p class="page-subtitle">Kelola tagihan dan rekap keuangan SPP siswa</p>
  </div>
  <div class="flex gap-2">
    <button class="btn btn-ghost" onclick="openModal('modal-siswa')">+ Tambah Siswa</button>
    <button class="btn btn-ghost" onclick="openModal('modal-generate')">⚙ Generate Tagihan</button>
    <button class="btn btn-primary" onclick="openModal('modal-bayar')">+ Catat Pembayaran</button>
  </div>
</div>

<!-- Summary -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
  <div style="background:#16A34A;border-radius:var(--radius-lg);padding:20px 22px;color:#fff">
    <div style="font-size:11px;font-weight:600;opacity:.7;text-transform:uppercase;letter-spacing:.5px">Total Terbayar</div>
    <div style="font-size:26px;font-weight:700;margin:5px 0 3px"><?= rupiah((int)($stats['masuk']??0)) ?></div>
    <div style="font-size:12px;opacity:.65"><?= $stats['n_lunas']??0 ?> siswa sudah lunas</div>
  </div>
  <div style="background:var(--accent);border-radius:var(--radius-lg);padding:20px 22px;color:#fff">
    <div style="font-size:11px;font-weight:600;opacity:.7;text-transform:uppercase;letter-spacing:.5px">Belum / Menunggak</div>
    <div style="font-size:26px;font-weight:700;margin:5px 0 3px"><?= rupiah((int)($stats['belum']??0)) ?></div>
    <div style="font-size:12px;opacity:.65"><?= ($stats['n_belum']??0) + ($stats['n_tunggak']??0) ?> siswa perlu ditindak</div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon" style="background:var(--accent-bg);color:var(--accent)">💳</div><div class="stat-label">Total Tagihan</div><div class="stat-value" style="color:var(--accent)"><?= ($stats['n_lunas']??0)+($stats['n_belum']??0)+($stats['n_tunggak']??0) ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#DCFCE7;color:#166534">✅</div><div class="stat-label">Lunas</div><div class="stat-value" style="color:#166534"><?= $stats['n_lunas']??0 ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEF3C7;color:#92400E">⏳</div><div class="stat-label">Belum Bayar</div><div class="stat-value" style="color:#92400E"><?= $stats['n_belum']??0 ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEE2E2;color:#991B1B">⚠️</div><div class="stat-label">Menunggak</div><div class="stat-value" style="color:#991B1B"><?= $stats['n_tunggak']??0 ?></div></div>
</div>

<div class="table-wrapper">
  <div class="card-header">
    <span>Daftar Tagihan</span>
    <div class="flex gap-3 items-center" style="flex-wrap:wrap;gap:8px">
      <div class="pills">
        <?php foreach(['semua'=>'Semua','lunas'=>'Lunas','belum'=>'Belum','menunggak'=>'Menunggak'] as $v=>$l): ?>
        <a href="?status=<?= $v ?>&q=<?= e($q) ?>" class="pill <?= $f_status===$v?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" style="display:contents">
        <input type="hidden" name="status" value="<?= e($f_status) ?>">
        <div class="search-wrap"><span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input" placeholder="Nama / NIS…" value="<?= e($q) ?>">
        </div>
      </form>
    </div>
  </div>
  <table>
    <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Bulan</th><th>Nominal</th><th>Jatuh Tempo</th><th>Tgl Bayar</th><th>Metode</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if(!$rows): ?>
    <tr><td colspan="10" style="text-align:center;padding:36px;color:var(--n400)">Tidak ada data.</td></tr>
    <?php else: foreach($rows as $r): ?>
    <tr>
      <td class="td-mono"><?= e($r['nis']) ?></td>
      <td class="td-bold"><?= e($r['nama']) ?></td>
      <td><?= badge($r['kelas'],'gray') ?></td>
      <td class="text-sm"><?= e($r['bulan']) ?></td>
      <td style="font-family:var(--mono);font-size:13px;font-weight:600"><?= rupiah($r['nominal']) ?></td>
      <td class="text-sm text-muted"><?= tgl_id($r['jatuh_tempo']) ?></td>
      <td class="text-sm text-muted"><?= $r['tgl_bayar'] ? tgl_id($r['tgl_bayar']) : '—' ?></td>
      <td class="text-sm"><?= $r['metode'] ? e($r['metode']) : '—' ?></td>
      <td><?= badge_spp($r['status']) ?></td>
      <td>
        <div class="flex gap-2">
          <?php if($r['status']!=='lunas'): ?>
          <button class="btn btn-success btn-xs" onclick="quickBayar(<?= $r['siswa_id'] ?>,<?= $r['id'] ?>,'<?= e($r['bulan']) ?>',<?= $r['nominal'] ?>)">Bayar</button>
          <?php endif; ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="hapus_tagihan">
            <input type="hidden" name="id"     value="<?= $r['id'] ?>">
            <button class="btn btn-danger btn-xs" data-confirm="Hapus tagihan ini?">Hapus</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <div class="pagination">
    <?php for($i=1;$i<=$pg['total_pages'];$i++): ?>
    <a href="?page=<?= $i ?>&status=<?= e($f_status) ?>&q=<?= e($q) ?>" class="pg-btn <?= $i===$pg['page']?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <span class="pg-info"><?= count($rows) ?> / <?= $pg['total'] ?> data</span>
  </div>
</div>

<!-- Modal Tambah Siswa -->
<div id="modal-siswa" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Tambah Data Siswa</span><button class="modal-close" onclick="closeModal('modal-siswa')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah_siswa">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">NIS *</label><input type="text" name="nis" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Kelas</label><input type="text" name="kelas" class="form-control" placeholder="8A"></div>
        </div>
        <div class="form-group"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Nominal SPP / Bulan</label><input type="number" name="nominal" class="form-control" value="350000" min="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-siswa')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Generate Tagihan -->
<div id="modal-generate" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Generate Tagihan Bulan Baru</span><button class="modal-close" onclick="closeModal('modal-generate')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="generate">
      <div class="modal-body">
        <div class="alert alert-info">ℹ️ Sistem akan membuat tagihan untuk semua siswa terdaftar yang belum memiliki tagihan bulan ini.</div>
        <div class="form-group"><label class="form-label">Bulan Tagihan *</label>
          <select name="bulan" class="form-control" required>
            <?php
            for ($i=0;$i<12;$i++) {
                $d = date('F Y', mktime(0,0,0,date('m')+$i,1));
                $v = date('F Y', mktime(0,0,0,date('m')+$i,1));
                echo "<option>$v</option>";
            }
            ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Jatuh Tempo</label>
          <input type="date" name="jatuh_tempo" class="form-control" value="<?= date('Y-m-t') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-generate')">Batal</button>
        <button type="submit" class="btn btn-primary">Generate</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Bayar -->
<div id="modal-bayar" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Catat Pembayaran SPP</span><button class="modal-close" onclick="closeModal('modal-bayar')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="bayar">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Pilih Siswa *</label>
          <select name="siswa_id" id="sel-siswa" class="form-control" required onchange="updateNominal(this)">
            <option value="">— Pilih siswa —</option>
            <?php foreach($siswa_list as $s): ?>
            <option value="<?= $s['id'] ?>" data-nominal="<?= $s['nominal'] ?>"><?= e($s['nama']) ?> (<?= e($s['kelas']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Bulan *</label>
          <select name="bulan" class="form-control" required>
            <?php for($i=0;$i<12;$i++) echo '<option>'.date('F Y',mktime(0,0,0,date('m')+$i,1)).'</option>'; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nominal</label>
            <input type="number" name="nominal" id="inp-nominal" class="form-control" value="350000">
          </div>
          <div class="form-group"><label class="form-label">Jatuh Tempo</label>
            <input type="date" name="jatuh_tempo" class="form-control" value="<?= date('Y-m-t') ?>">
          </div>
        </div>
        <div class="form-group"><label class="form-label">Metode</label>
          <select name="metode" class="form-control">
            <option>Tunai</option><option>Transfer</option><option>E-Wallet</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-bayar')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateNominal(sel) {
    var opt = sel.options[sel.selectedIndex];
    var nom = opt.dataset.nominal;
    if (nom) document.getElementById('inp-nominal').value = nom;
}
function quickBayar(siswaId, tagihanId, bulan, nominal) {
    var sel = document.getElementById('sel-siswa');
    for (var i=0;i<sel.options.length;i++) {
        if (sel.options[i].value == siswaId) { sel.selectedIndex = i; break; }
    }
    document.getElementById('inp-nominal').value = nominal;
    var bSelect = document.querySelector('[name=bulan]');
    for (var i=0;i<bSelect.options.length;i++) {
        if (bSelect.options[i].value === bulan) { bSelect.selectedIndex = i; break; }
    }
    openModal('modal-bayar');
}
</script>

<?php require_once ROOT . '/config/layout_end.php'; ?>
