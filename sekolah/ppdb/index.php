<?php
/**
 * ppdb/index.php — PPDB CRUD
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/helpers.php';
db_init();

/* ────────────────────────────
   POST: Tambah / Edit / Hapus
──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'tambah' || $action === 'edit') {
        $no_daftar    = strtoupper(trim(post('no_daftar')));
        $nama         = trim(post('nama'));
        $asal_sekolah = trim(post('asal_sekolah'));
        $jalur        = post('jalur', 'Zonasi');
        $nilai        = (float) post('nilai', 0);
        $status       = post('status', 'verifikasi');
        $tanggal      = post('tanggal', date('Y-m-d'));

        if (!$nama || !$no_daftar) {
            flash_set('error', 'Nama dan nomor pendaftaran wajib diisi.');
        } else {
            try {
                if ($action === 'tambah') {
                    db()->prepare('INSERT INTO ppdb (no_daftar,nama,asal_sekolah,jalur,nilai,status,tanggal)
                                   VALUES (?,?,?,?,?,?,?)')
                        ->execute([$no_daftar, $nama, $asal_sekolah, $jalur, $nilai, $status, $tanggal]);
                    flash_set('success', "Pendaftar $nama berhasil ditambahkan.");
                } else {
                    $id = (int) post('id');
                    db()->prepare('UPDATE ppdb SET no_daftar=?,nama=?,asal_sekolah=?,jalur=?,nilai=?,status=?,tanggal=?
                                   WHERE id=?')
                        ->execute([$no_daftar, $nama, $asal_sekolah, $jalur, $nilai, $status, $tanggal, $id]);
                    flash_set('success', "Data $nama berhasil diperbarui.");
                }
            } catch (PDOException $ex) {
                flash_set('error', 'Gagal menyimpan: nomor pendaftaran sudah ada.');
            }
        }
    }

    if ($action === 'hapus') {
        $id   = (int) post('id');
        $nama = db()->prepare('SELECT nama FROM ppdb WHERE id=?');
        $nama->execute([$id]);
        $row  = $nama->fetch();
        db()->prepare('DELETE FROM ppdb WHERE id=?')->execute([$id]);
        flash_set('success', 'Data ' . ($row['nama'] ?? '') . ' berhasil dihapus.');
    }

    redirect('/ppdb/');
}

/* ────────────────────────────
   GET: Ambil Data
──────────────────────────── */
$q        = get('q', '');
$f_status = get('status', 'semua');
$page     = max(1, (int) get('page', 1));
$per_page = 10;

$where  = [];
$params = [];
if ($f_status !== 'semua') { $where[] = 'status=?';  $params[] = $f_status; }
if ($q !== '')              { $where[] = '(nama LIKE ? OR no_daftar LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db()->prepare("SELECT COUNT(*) FROM ppdb $clause")->execute($params) && true
    ? db()->prepare("SELECT COUNT(*) FROM ppdb $clause")->execute($params) ? db()->prepare("SELECT COUNT(*) FROM ppdb $clause") : null
    : 0;
// Simpler count:
$cnt_stmt = db()->prepare("SELECT COUNT(*) FROM ppdb $clause");
$cnt_stmt->execute($params);
$total = (int) $cnt_stmt->fetchColumn();

$pg      = paginate($total, $per_page, $page);
$data_st = db()->prepare("SELECT * FROM ppdb $clause ORDER BY id DESC LIMIT ? OFFSET ?");
$data_st->execute(array_merge($params, [$pg['per_page'], $pg['offset']]));
$rows    = $data_st->fetchAll();

/* Statistik */
$stats = db()->query("SELECT
    COUNT(*) total,
    SUM(CASE WHEN status='diterima'   THEN 1 ELSE 0 END) diterima,
    SUM(CASE WHEN status='verifikasi' THEN 1 ELSE 0 END) verifikasi,
    SUM(CASE WHEN status='ditolak'    THEN 1 ELSE 0 END) ditolak
    FROM ppdb")->fetch();
$kuota  = 120;
$persen = $stats['total'] > 0 ? round($stats['diterima'] / $kuota * 100) : 0;

/* Edit data (untuk pre-fill modal) */
$edit_row = null;
if (get('edit')) {
    $st = db()->prepare('SELECT * FROM ppdb WHERE id=?');
    $st->execute([(int)get('edit')]);
    $edit_row = $st->fetch();
}

/* ── Layout ── */
$page_title   = 'PPDB';
$active_menu  = 'ppdb';
$accent       = '#5B21B6';
$accent_light = 'rgba(91,33,182,.12)';
$accent_bg    = '#F5F3FF';
require_once ROOT . '/config/layout.php';
?>

<?= flash_html() ?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">Penerimaan Peserta Didik Baru</h1>
    <p class="page-subtitle">Kelola data pendaftar PPDB — TP 2025/2026</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-tambah')">+ Tambah Pendaftar</button>
</div>

<!-- Kuota Banner -->
<div style="background:var(--accent);border-radius:var(--radius-lg);padding:20px 24px;color:#fff;margin-bottom:22px">
  <div style="font-size:11px;font-weight:600;opacity:.7;text-transform:uppercase;letter-spacing:.5px">Kuota Siswa Baru</div>
  <div style="font-size:28px;font-weight:700;margin:5px 0 12px"><?= $stats['diterima'] ?> <span style="font-size:16px;opacity:.6">/ <?= $kuota ?> kursi</span></div>
  <div class="progress" style="background:rgba(255,255,255,.25)">
    <div class="progress-bar" style="width:<?= $persen ?>%;background:#fff"></div>
  </div>
  <div style="font-size:12px;opacity:.65;margin-top:6px"><?= $persen ?>% terisi &nbsp;·&nbsp; Sisa <?= $kuota - $stats['diterima'] ?> kursi</div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--accent-bg);color:var(--accent)">📋</div>
    <div class="stat-label">Total Pendaftar</div>
    <div class="stat-value" style="color:var(--accent)"><?= $stats['total'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#DCFCE7;color:#166534">✅</div>
    <div class="stat-label">Diterima</div>
    <div class="stat-value" style="color:#166534"><?= $stats['diterima'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#FEF3C7;color:#92400E">⏳</div>
    <div class="stat-label">Verifikasi</div>
    <div class="stat-value" style="color:#92400E"><?= $stats['verifikasi'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#FEE2E2;color:#991B1B">✗</div>
    <div class="stat-label">Ditolak</div>
    <div class="stat-value" style="color:#991B1B"><?= $stats['ditolak'] ?></div>
  </div>
</div>

<!-- Tabel -->
<div class="table-wrapper">
  <div class="card-header">
    <span>Daftar Pendaftar</span>
    <div class="flex gap-3 items-center" style="flex-wrap:wrap;gap:8px">
      <div class="pills">
        <?php foreach (['semua'=>'Semua','diterima'=>'Diterima','verifikasi'=>'Verifikasi','ditolak'=>'Ditolak'] as $v=>$l): ?>
        <a href="?status=<?= $v ?>&q=<?= e($q) ?>" class="pill <?= $f_status===$v?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" style="display:contents">
        <input type="hidden" name="status" value="<?= e($f_status) ?>">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input" placeholder="Cari nama / no daftar…" value="<?= e($q) ?>">
        </div>
      </form>
    </div>
  </div>
  <table>
    <thead><tr>
      <th>No. Daftar</th><th>Nama</th><th>Asal Sekolah</th><th>Jalur</th><th>Nilai</th><th>Tgl Daftar</th><th>Status</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="8" style="text-align:center;padding:36px;color:var(--n400)">Tidak ada data.</td></tr>
    <?php else: ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="td-mono"><?= e($r['no_daftar']) ?></td>
      <td class="td-bold"><?= e($r['nama']) ?></td>
      <td class="text-sm"><?= e($r['asal_sekolah']) ?></td>
      <td><?= badge_jalur($r['jalur']) ?></td>
      <td><strong><?= number_format($r['nilai'],1) ?></strong></td>
      <td class="text-muted text-sm"><?= tgl_id($r['tanggal']) ?></td>
      <td><?= badge_status_ppdb($r['status']) ?></td>
      <td>
        <div class="flex gap-2">
          <a href="?edit=<?= $r['id'] ?>&status=<?= e($f_status) ?>&q=<?= e($q) ?>"
             class="btn btn-ghost btn-xs">Edit</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id"     value="<?= $r['id'] ?>">
            <button class="btn btn-danger btn-xs" data-confirm="Hapus data <?= e($r['nama']) ?>?">Hapus</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
    <a href="?page=<?= $i ?>&status=<?= e($f_status) ?>&q=<?= e($q) ?>"
       class="pg-btn <?= $i===$pg['page']?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <span class="pg-info">Menampilkan <?= count($rows) ?> dari <?= $pg['total'] ?> data</span>
  </div>
</div>

<!-- Modal Tambah -->
<div id="modal-tambah" class="modal-bg <?= (!$edit_row && post('action')==='') ? '' : '' ?>">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Tambah Pendaftar Baru</span>
      <button class="modal-close" onclick="closeModal('modal-tambah')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">No. Pendaftaran *</label>
            <input type="text" name="no_daftar" class="form-control" placeholder="PPDB-009" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Daftar</label>
            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" name="nama" class="form-control" placeholder="Nama siswa" required>
        </div>
        <div class="form-group">
          <label class="form-label">Asal Sekolah</label>
          <input type="text" name="asal_sekolah" class="form-control" placeholder="SDN / MIS / SDIT...">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jalur</label>
            <select name="jalur" class="form-control">
              <option>Zonasi</option><option>Prestasi</option><option>Afirmasi</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nilai</label>
            <input type="number" name="nilai" class="form-control" placeholder="0–100" min="0" max="100" step="0.1">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="verifikasi">Verifikasi</option>
            <option value="diterima">Diterima</option>
            <option value="ditolak">Ditolak</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-tambah')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<?php if ($edit_row): ?>
<div id="modal-edit" class="modal-bg open">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Pendaftar</span>
      <a href="?status=<?= e($f_status) ?>&q=<?= e($q) ?>" class="modal-close">×</a>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id"     value="<?= $edit_row['id'] ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">No. Pendaftaran *</label>
            <input type="text" name="no_daftar" class="form-control" value="<?= e($edit_row['no_daftar']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?= e($edit_row['tanggal']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" name="nama" class="form-control" value="<?= e($edit_row['nama']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Asal Sekolah</label>
          <input type="text" name="asal_sekolah" class="form-control" value="<?= e($edit_row['asal_sekolah']) ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jalur</label>
            <select name="jalur" class="form-control">
              <?php foreach (['Zonasi','Prestasi','Afirmasi'] as $j): ?>
              <option <?= $edit_row['jalur']===$j?'selected':'' ?>><?= $j ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nilai</label>
            <input type="number" name="nilai" class="form-control" value="<?= $edit_row['nilai'] ?>" min="0" max="100" step="0.1">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach (['verifikasi'=>'Verifikasi','diterima'=>'Diterima','ditolak'=>'Ditolak'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $edit_row['status']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <a href="?status=<?= e($f_status) ?>&q=<?= e($q) ?>" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once ROOT . '/config/layout_end.php'; ?>
