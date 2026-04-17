<?php
/**
 * perpustakaan/index.php — Perpustakaan CRUD
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/helpers.php';
db_init();

/* ── POST Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    /* Tambah / Edit Buku */
    if (in_array($action, ['tambah_buku','edit_buku'])) {
        $kode     = strtoupper(trim(post('kode')));
        $judul    = trim(post('judul'));
        $penulis  = trim(post('penulis'));
        $kategori = trim(post('kategori'));
        $stok     = (int) post('stok', 1);
        $isbn     = trim(post('isbn'));

        if (!$judul || !$kode) {
            flash_set('error', 'Kode dan judul buku wajib diisi.');
        } else {
            try {
                if ($action === 'tambah_buku') {
                    db()->prepare('INSERT INTO buku (kode,judul,penulis,kategori,stok,isbn) VALUES (?,?,?,?,?,?)')
                        ->execute([$kode,$judul,$penulis,$kategori,$stok,$isbn]);
                    flash_set('success', "Buku \"$judul\" berhasil ditambahkan.");
                } else {
                    $id = (int) post('id');
                    db()->prepare('UPDATE buku SET kode=?,judul=?,penulis=?,kategori=?,stok=?,isbn=? WHERE id=?')
                        ->execute([$kode,$judul,$penulis,$kategori,$stok,$isbn,$id]);
                    flash_set('success', "Buku \"$judul\" berhasil diperbarui.");
                }
            } catch (PDOException) {
                flash_set('error', 'Kode buku sudah digunakan.');
            }
        }
    }

    /* Hapus Buku */
    if ($action === 'hapus_buku') {
        $id = (int) post('id');
        db()->prepare('DELETE FROM buku WHERE id=?')->execute([$id]);
        flash_set('success', 'Buku berhasil dihapus.');
    }

    /* Pinjam Buku */
    if ($action === 'pinjam') {
        $buku_id    = (int) post('buku_id');
        $nis        = trim(post('nis'));
        $nama_siswa = trim(post('nama_siswa'));
        $tgl_pinjam = post('tgl_pinjam', date('Y-m-d'));
        $tgl_kembali= post('tgl_kembali');

        // Cek stok tersedia
        $buku = db()->prepare('SELECT stok,(SELECT COUNT(*) FROM peminjaman WHERE buku_id=? AND status IN (\'aktif\',\'terlambat\')) AS dipinjam FROM buku WHERE id=?');
        $buku->execute([$buku_id, $buku_id]);
        $b = $buku->fetch();

        if ($b && ($b['stok'] - $b['dipinjam']) <= 0) {
            flash_set('error', 'Stok buku habis, tidak dapat dipinjam.');
        } else {
            db()->prepare('INSERT INTO peminjaman (nis,nama_siswa,buku_id,tgl_pinjam,tgl_kembali,status) VALUES (?,?,?,?,?,\'aktif\')')
                ->execute([$nis,$nama_siswa,$buku_id,$tgl_pinjam,$tgl_kembali]);
            flash_set('success', "Peminjaman atas nama $nama_siswa berhasil dicatat.");
        }
    }

    /* Kembalikan */
    if ($action === 'kembali') {
        $id = (int) post('id');
        db()->prepare("UPDATE peminjaman SET status='kembali' WHERE id=?")->execute([$id]);
        flash_set('success', 'Buku berhasil dikembalikan.');
    }

    redirect('/perpustakaan/');
}

/* ── GET: Data Buku ── */
$q       = get('q', '');
$f_kat   = get('kat', 'semua');
$tab     = get('tab', 'buku');
$page    = max(1,(int)get('page',1));
$per     = 8;

$where  = []; $params = [];
if ($f_kat !== 'semua') { $where[] = 'kategori=?'; $params[] = $f_kat; }
if ($q !== '')           { $where[] = '(judul LIKE ? OR penulis LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$clause = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cnt = db()->prepare("SELECT COUNT(*) FROM buku $clause"); $cnt->execute($params);
$pg  = paginate((int)$cnt->fetchColumn(), $per, $page);

$buku_stmt = db()->prepare("SELECT b.*, (SELECT COUNT(*) FROM peminjaman p WHERE p.buku_id=b.id AND p.status IN ('aktif','terlambat')) dipinjam FROM buku b $clause ORDER BY b.id DESC LIMIT ? OFFSET ?");
$buku_stmt->execute(array_merge($params, [$pg['per_page'], $pg['offset']]));
$buku_rows = $buku_stmt->fetchAll();

$kategori_list = db()->query("SELECT DISTINCT kategori FROM buku ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

/* Peminjaman aktif */
$pinjam_rows = db()->query("SELECT p.*,b.judul FROM peminjaman p JOIN buku b ON b.id=p.buku_id WHERE p.status IN ('aktif','terlambat') ORDER BY p.id DESC")->fetchAll();

/* Stats */
$stats = db()->query("SELECT COUNT(*) total_buku, SUM(stok) total_stok FROM buku")->fetch();
$aktif = db()->query("SELECT COUNT(*) FROM peminjaman WHERE status='aktif'")->fetchColumn();
$telat = db()->query("SELECT COUNT(*) FROM peminjaman WHERE status='terlambat'")->fetchColumn();

/* Edit row */
$edit_buku = null;
if (get('edit')) {
    $st = db()->prepare('SELECT * FROM buku WHERE id=?'); $st->execute([(int)get('edit')]);
    $edit_buku = $st->fetch();
}

$page_title  = 'Perpustakaan';
$active_menu = 'perpustakaan';
$accent      = '#0D9488'; $accent_light = 'rgba(13,148,136,.12)'; $accent_bg = '#F0FDFA';
require_once ROOT . '/config/layout.php';
?>

<?= flash_html() ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Perpustakaan Digital</h1>
    <p class="page-subtitle">Kelola koleksi buku dan peminjaman siswa</p>
  </div>
  <div class="flex gap-2">
    <button class="btn btn-ghost" onclick="openModal('modal-pinjam')">+ Pinjam Buku</button>
    <button class="btn btn-primary" onclick="openModal('modal-tambah-buku')">+ Tambah Buku</button>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon" style="background:#CCFBF1;color:#0D9488">📖</div><div class="stat-label">Total Judul</div><div class="stat-value" style="color:#0D9488"><?= $stats['total_buku'] ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#DCFCE7;color:#166534">📚</div><div class="stat-label">Total Eksemplar</div><div class="stat-value" style="color:#166534"><?= $stats['total_stok'] ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#DBEAFE;color:#1E40AF">🔄</div><div class="stat-label">Dipinjam</div><div class="stat-value" style="color:#1E40AF"><?= $aktif ?></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:#FEE2E2;color:#991B1B">⚠️</div><div class="stat-label">Terlambat</div><div class="stat-value" style="color:#991B1B"><?= $telat ?></div></div>
</div>

<!-- Tab -->
<div class="pills mb-4">
  <a href="?tab=buku&kat=<?= e($f_kat) ?>&q=<?= e($q) ?>"   class="pill <?= $tab==='buku'?'active':'' ?>">📚 Koleksi Buku</a>
  <a href="?tab=pinjam&kat=<?= e($f_kat) ?>&q=<?= e($q) ?>" class="pill <?= $tab==='pinjam'?'active':'' ?>">🔄 Peminjaman Aktif (<?= $aktif + $telat ?>)</a>
</div>

<?php if ($tab === 'buku'): ?>
<!-- Tabel Buku -->
<div class="table-wrapper">
  <div class="card-header">
    <div class="pills">
      <a href="?tab=buku&kat=semua&q=<?= e($q) ?>" class="pill <?= $f_kat==='semua'?'active':'' ?>">Semua</a>
      <?php foreach ($kategori_list as $k): ?>
      <a href="?tab=buku&kat=<?= urlencode($k) ?>&q=<?= e($q) ?>" class="pill <?= $f_kat===$k?'active':'' ?>"><?= e($k) ?></a>
      <?php endforeach; ?>
    </div>
    <form method="GET" style="display:contents">
      <input type="hidden" name="tab" value="buku">
      <input type="hidden" name="kat" value="<?= e($f_kat) ?>">
      <div class="search-wrap"><span class="search-icon">🔍</span>
        <input type="text" name="q" class="search-input" placeholder="Judul / penulis…" value="<?= e($q) ?>">
      </div>
    </form>
  </div>
  <table>
    <thead><tr><th>Kode</th><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($buku_rows as $b): ?>
    <tr>
      <td class="td-mono"><?= e($b['kode']) ?></td>
      <td class="td-bold"><?= e($b['judul']) ?></td>
      <td class="text-sm text-muted"><?= e($b['penulis']) ?></td>
      <td><?= badge($b['kategori'],'teal') ?></td>
      <td><?= badge_stok($b['stok'], $b['dipinjam']) ?></td>
      <td>
        <div class="flex gap-2">
          <a href="?edit=<?= $b['id'] ?>&tab=buku" class="btn btn-ghost btn-xs">Edit</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="hapus_buku">
            <input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button class="btn btn-danger btn-xs" data-confirm="Hapus buku <?= e($b['judul']) ?>?">Hapus</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="pagination">
    <?php for ($i=1;$i<=$pg['total_pages'];$i++): ?>
    <a href="?tab=buku&page=<?= $i ?>&kat=<?= e($f_kat) ?>&q=<?= e($q) ?>" class="pg-btn <?= $i===$pg['page']?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <span class="pg-info"><?= count($buku_rows) ?> / <?= $pg['total'] ?> buku</span>
  </div>
</div>

<?php else: ?>
<!-- Tabel Peminjaman -->
<div class="table-wrapper">
  <div class="card-header"><span>Peminjaman Aktif & Terlambat</span></div>
  <table>
    <thead><tr><th>NIS</th><th>Nama Siswa</th><th>Judul Buku</th><th>Tgl Pinjam</th><th>Tgl Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($pinjam_rows as $p): ?>
    <tr>
      <td class="td-mono"><?= e($p['nis']) ?></td>
      <td class="td-bold"><?= e($p['nama_siswa']) ?></td>
      <td class="text-sm"><?= e($p['judul']) ?></td>
      <td class="text-sm"><?= tgl_id($p['tgl_pinjam']) ?></td>
      <td class="text-sm" style="color:<?= $p['status']==='terlambat'?'#991B1B':'inherit' ?>"><?= tgl_id($p['tgl_kembali']) ?></td>
      <td><?= badge_peminjaman($p['status']) ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="kembali">
          <input type="hidden" name="id"     value="<?= $p['id'] ?>">
          <button class="btn btn-success btn-xs" data-confirm="Tandai buku dikembalikan?">Kembalikan</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Modal Tambah Buku -->
<div id="modal-tambah-buku" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Tambah Buku</span><button class="modal-close" onclick="closeModal('modal-tambah-buku')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah_buku">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Kode *</label><input type="text" name="kode" class="form-control" placeholder="BK009" required></div>
          <div class="form-group"><label class="form-label">Stok</label><input type="number" name="stok" class="form-control" value="1" min="0"></div>
        </div>
        <div class="form-group"><label class="form-label">Judul *</label><input type="text" name="judul" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Penulis</label><input type="text" name="penulis" class="form-control"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Kategori</label>
            <select name="kategori" class="form-control">
              <?php foreach ($kategori_list as $k): ?><option><?= e($k) ?></option><?php endforeach; ?>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control" placeholder="978-..."></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-tambah-buku')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Pinjam -->
<div id="modal-pinjam" class="modal-bg">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Catat Peminjaman</span><button class="modal-close" onclick="closeModal('modal-pinjam')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="pinjam">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Pilih Buku *</label>
          <select name="buku_id" class="form-control" required>
            <option value="">— Pilih buku —</option>
            <?php foreach (db()->query("SELECT b.id,b.judul,b.stok,(SELECT COUNT(*) FROM peminjaman p WHERE p.buku_id=b.id AND p.status IN ('aktif','terlambat')) d FROM buku b") as $b): ?>
            <?php $sisa = $b['stok'] - $b['d']; ?>
            <option value="<?= $b['id'] ?>" <?= $sisa<=0?'disabled':'' ?>><?= e($b['judul']) ?> (sisa <?= $sisa ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">NIS *</label><input type="text" name="nis" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Nama Siswa *</label><input type="text" name="nama_siswa" class="form-control" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Tgl Pinjam</label><input type="date" name="tgl_pinjam" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group"><label class="form-label">Tgl Kembali *</label><input type="date" name="tgl_kembali" class="form-control" required value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-pinjam')">Batal</button>
        <button type="submit" class="btn btn-primary">Catat Pinjam</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Buku -->
<?php if ($edit_buku): ?>
<div id="modal-edit-buku" class="modal-bg open">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit Buku</span><a href="?tab=buku" class="modal-close">×</a></div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_buku">
      <input type="hidden" name="id"     value="<?= $edit_buku['id'] ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Kode *</label><input type="text" name="kode" class="form-control" value="<?= e($edit_buku['kode']) ?>" required></div>
          <div class="form-group"><label class="form-label">Stok</label><input type="number" name="stok" class="form-control" value="<?= $edit_buku['stok'] ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Judul *</label><input type="text" name="judul" class="form-control" value="<?= e($edit_buku['judul']) ?>" required></div>
        <div class="form-group"><label class="form-label">Penulis</label><input type="text" name="penulis" class="form-control" value="<?= e($edit_buku['penulis']) ?>"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Kategori</label><input type="text" name="kategori" class="form-control" value="<?= e($edit_buku['kategori']) ?>"></div>
          <div class="form-group"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control" value="<?= e($edit_buku['isbn']) ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="?tab=buku" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once ROOT . '/config/layout_end.php'; ?>
