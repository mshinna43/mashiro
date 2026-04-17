<?php
/**
 * config/database.php
 * Koneksi & inisialisasi SQLite
 * Membuat tabel + data awal secara otomatis jika belum ada
 */

define('DB_PATH', __DIR__ . '/../data/sekolah.db');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    }
    return $pdo;
}

function db_init(): void {
    $pdo = db();

    /* ─────────────────────────────────────
       1. PPDB
    ───────────────────────────────────── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ppdb (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            no_daftar    TEXT    UNIQUE NOT NULL,
            nama         TEXT    NOT NULL,
            asal_sekolah TEXT    NOT NULL,
            jalur        TEXT    NOT NULL DEFAULT 'Zonasi',
            nilai        REAL    NOT NULL DEFAULT 0,
            status       TEXT    NOT NULL DEFAULT 'verifikasi',
            tanggal      TEXT    NOT NULL,
            created_at   TEXT    DEFAULT (datetime('now','localtime'))
        )
    ");

    /* ─────────────────────────────────────
       2. Perpustakaan
    ───────────────────────────────────── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buku (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            kode      TEXT    UNIQUE NOT NULL,
            judul     TEXT    NOT NULL,
            penulis   TEXT    NOT NULL,
            kategori  TEXT    NOT NULL,
            stok      INTEGER NOT NULL DEFAULT 1,
            isbn      TEXT,
            created_at TEXT   DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS peminjaman (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            nis          TEXT    NOT NULL,
            nama_siswa   TEXT    NOT NULL,
            buku_id      INTEGER NOT NULL REFERENCES buku(id),
            tgl_pinjam   TEXT    NOT NULL,
            tgl_kembali  TEXT    NOT NULL,
            status       TEXT    NOT NULL DEFAULT 'aktif',
            created_at   TEXT    DEFAULT (datetime('now','localtime'))
        )
    ");

    /* ─────────────────────────────────────
       3. Absensi Peserta
    ───────────────────────────────────── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kegiatan (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            nama       TEXT    NOT NULL,
            deskripsi  TEXT,
            created_at TEXT    DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS absensi_peserta (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            kegiatan_id INTEGER NOT NULL REFERENCES kegiatan(id),
            nis         TEXT    NOT NULL,
            nama        TEXT    NOT NULL,
            kelas       TEXT    NOT NULL,
            tanggal     TEXT    NOT NULL,
            status      TEXT    NOT NULL DEFAULT 'hadir',
            waktu       TEXT,
            created_at  TEXT    DEFAULT (datetime('now','localtime')),
            UNIQUE(kegiatan_id, nis, tanggal)
        )
    ");

    /* ─────────────────────────────────────
       4. Pembayaran SPP
    ───────────────────────────────────── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS siswa (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            nis       TEXT    UNIQUE NOT NULL,
            nama      TEXT    NOT NULL,
            kelas     TEXT    NOT NULL,
            nominal   INTEGER NOT NULL DEFAULT 350000,
            created_at TEXT   DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS spp (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            siswa_id    INTEGER NOT NULL REFERENCES siswa(id),
            bulan       TEXT    NOT NULL,
            nominal     INTEGER NOT NULL,
            status      TEXT    NOT NULL DEFAULT 'belum',
            metode      TEXT,
            jatuh_tempo TEXT    NOT NULL,
            tgl_bayar   TEXT,
            created_at  TEXT    DEFAULT (datetime('now','localtime')),
            UNIQUE(siswa_id, bulan)
        )
    ");

    /* ─────────────────────────────────────
       5. Absensi Siswa
    ───────────────────────────────────── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS absensi_siswa (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            nis       TEXT    NOT NULL,
            nama      TEXT    NOT NULL,
            kelas     TEXT    NOT NULL,
            mapel     TEXT    NOT NULL,
            tanggal   TEXT    NOT NULL,
            status    TEXT    NOT NULL DEFAULT 'hadir',
            keterangan TEXT,
            created_at TEXT   DEFAULT (datetime('now','localtime')),
            UNIQUE(nis, mapel, tanggal)
        )
    ");

    /* ─────────────────────────────────────
       Seed data awal (hanya jika kosong)
    ───────────────────────────────────── */
    _seed_ppdb($pdo);
    _seed_buku($pdo);
    _seed_kegiatan($pdo);
    _seed_siswa($pdo);
    _seed_absensi_siswa($pdo);
}

/* ── Seed: PPDB ── */
function _seed_ppdb(PDO $pdo): void {
    if ($pdo->query('SELECT COUNT(*) FROM ppdb')->fetchColumn() > 0) return;
    $rows = [
        ['PPDB-001','Ahmad Rizky Pratama',   'SDN 01 Merdeka',   'Zonasi',   88.5,'diterima',  '2025-06-10'],
        ['PPDB-002','Bella Sari Dewi',        'SDN 05 Harapan',   'Prestasi', 92.0,'diterima',  '2025-06-10'],
        ['PPDB-003','Cahyo Wibowo',           'SDN 12 Cendekia',  'Zonasi',   79.0,'verifikasi','2025-06-11'],
        ['PPDB-004','Dewi Rahayu Putri',      'MIS Al-Hidayah',   'Afirmasi', 85.5,'diterima',  '2025-06-11'],
        ['PPDB-005','Eko Firmansyah',         'SDN 03 Nusantara', 'Zonasi',   62.0,'ditolak',   '2025-06-12'],
        ['PPDB-006','Fira Ananda Lestari',    'SDN 07 Bintang',   'Prestasi', 95.0,'diterima',  '2025-06-12'],
        ['PPDB-007','Gilang Prayuda',         'SDIT Taqwa',       'Zonasi',   75.5,'verifikasi','2025-06-13'],
        ['PPDB-008','Hana Maharani',          'SDN 02 Sejahtera', 'Afirmasi', 83.0,'diterima',  '2025-06-13'],
    ];
    $st = $pdo->prepare('INSERT INTO ppdb (no_daftar,nama,asal_sekolah,jalur,nilai,status,tanggal) VALUES (?,?,?,?,?,?,?)');
    foreach ($rows as $r) $st->execute($r);
}

/* ── Seed: Buku & Peminjaman ── */
function _seed_buku(PDO $pdo): void {
    if ($pdo->query('SELECT COUNT(*) FROM buku')->fetchColumn() > 0) return;
    $buku = [
        ['BK001','Laskar Pelangi',        'Andrea Hirata',      'Novel',    5,'978-602-14-1234-5'],
        ['BK002','Negeri 5 Menara',        'Ahmad Fuadi',        'Novel',    3,'978-602-14-2234-5'],
        ['BK003','Bumi',                   'Tere Liye',          'Fiksi',    6,'978-602-14-3234-5'],
        ['BK004','Matematika Kelas 8',     'Kemendikbud',        'Pelajaran',20,'978-602-14-4234-5'],
        ['BK005','IPA Terpadu Kelas 7',    'Tim Penulis',        'Pelajaran',18,'978-602-14-5234-5'],
        ['BK006','Sejarah Indonesia',      'Sartono Kartodirdjo','Sejarah',   8,'978-602-14-6234-5'],
        ['BK007','KBBI',                   'Pusat Bahasa',       'Referensi', 4,'978-602-14-7234-5'],
        ['BK008','Harry Potter (Ind.)',    'J.K. Rowling',       'Fiksi',     3,'978-602-14-8234-5'],
    ];
    $st = $pdo->prepare('INSERT INTO buku (kode,judul,penulis,kategori,stok,isbn) VALUES (?,?,?,?,?,?)');
    foreach ($buku as $b) $st->execute($b);

    $pinjam = [
        ['0812345','Ahmad Rizky',   1,'2025-06-10','2025-06-24','aktif'],
        ['0812346','Bella Dewi',    3,'2025-06-12','2025-06-26','aktif'],
        ['0812347','Cahyo Wibowo',  2,'2025-06-08','2025-06-22','terlambat'],
        ['0812348','Dewi Rahayu',   8,'2025-06-14','2025-06-28','aktif'],
        ['0812349','Eko Firmansyah',6,'2025-06-05','2025-06-19','terlambat'],
    ];
    $st2 = $pdo->prepare('INSERT INTO peminjaman (nis,nama_siswa,buku_id,tgl_pinjam,tgl_kembali,status) VALUES (?,?,?,?,?,?)');
    foreach ($pinjam as $p) $st2->execute($p);
}

/* ── Seed: Kegiatan & Absensi Peserta ── */
function _seed_kegiatan(PDO $pdo): void {
    if ($pdo->query('SELECT COUNT(*) FROM kegiatan')->fetchColumn() > 0) return;
    $kg = [['Pelatihan OSIS','Pelatihan kepemimpinan OSIS'],['Pramuka','Kegiatan Pramuka'],
           ['English Club','Klub Bahasa Inggris'],['Basket','Ekstrakurikuler Basket'],['Paduan Suara','Paduan Suara']];
    $st = $pdo->prepare('INSERT INTO kegiatan (nama,deskripsi) VALUES (?,?)');
    foreach ($kg as $k) $st->execute($k);

    $peserta = [
        [1,'0812345','Ahmad Rizky Pratama', '8A','2025-06-18','hadir', '08:02'],
        [1,'0812346','Bella Sari Dewi',      '8A','2025-06-18','hadir', '08:05'],
        [1,'0812347','Cahyo Wibowo',          '8B','2025-06-18','absen', null],
        [1,'0812348','Dewi Rahayu Putri',     '8B','2025-06-18','izin',  null],
        [1,'0812349','Eko Firmansyah',        '8C','2025-06-18','hadir', '08:10'],
        [1,'0812350','Fira Ananda Lestari',   '8A','2025-06-18','hadir', '08:03'],
        [1,'0812351','Gilang Prayuda',         '9A','2025-06-18','sakit', null],
        [1,'0812352','Hana Maharani',          '9B','2025-06-18','hadir', '08:08'],
    ];
    $st2 = $pdo->prepare('INSERT INTO absensi_peserta (kegiatan_id,nis,nama,kelas,tanggal,status,waktu) VALUES (?,?,?,?,?,?,?)');
    foreach ($peserta as $p) $st2->execute($p);
}

/* ── Seed: Siswa & SPP ── */
function _seed_siswa(PDO $pdo): void {
    if ($pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn() > 0) return;
    $list = [
        ['0812345','Ahmad Rizky Pratama','8A',350000],
        ['0812346','Bella Sari Dewi',     '8A',350000],
        ['0812347','Cahyo Wibowo',         '8B',350000],
        ['0812348','Dewi Rahayu Putri',    '8B',350000],
        ['0812349','Eko Firmansyah',       '8C',350000],
        ['0812350','Fira Ananda Lestari',  '8A',350000],
        ['0812351','Gilang Prayuda',        '9A',375000],
        ['0812352','Hana Maharani',         '9B',375000],
        ['0812353','Ivan Setiawan',         '7A',325000],
        ['0812354','Jasmine Putri',         '7B',325000],
    ];
    $st = $pdo->prepare('INSERT INTO siswa (nis,nama,kelas,nominal) VALUES (?,?,?,?)');
    foreach ($list as $s) $st->execute($s);

    $spp = [
        [1,'Juni 2025',  350000,'belum',   null,        '2025-06-30',null],
        [2,'Juni 2025',  350000,'lunas',   'Tunai',     '2025-06-30','2025-06-05'],
        [3,'Juni 2025',  350000,'belum',   null,        '2025-06-30',null],
        [4,'Mei 2025',   350000,'menunggak',null,       '2025-05-31',null],
        [5,'Juni 2025',  350000,'lunas',   'Transfer',  '2025-06-30','2025-06-03'],
        [6,'Mei 2025',   350000,'menunggak',null,       '2025-05-31',null],
        [7,'Juni 2025',  375000,'lunas',   'E-Wallet',  '2025-06-30','2025-06-02'],
        [8,'Juni 2025',  375000,'belum',   null,        '2025-06-30',null],
        [9,'Juni 2025',  325000,'lunas',   'Tunai',     '2025-06-30','2025-06-01'],
        [10,'April 2025',325000,'menunggak',null,       '2025-04-30',null],
    ];
    $st2 = $pdo->prepare('INSERT INTO spp (siswa_id,bulan,nominal,status,metode,jatuh_tempo,tgl_bayar) VALUES (?,?,?,?,?,?,?)');
    foreach ($spp as $s) $st2->execute($s);
}

/* ── Seed: Absensi Siswa ── */
function _seed_absensi_siswa(PDO $pdo): void {
    if ($pdo->query('SELECT COUNT(*) FROM absensi_siswa')->fetchColumn() > 0) return;
    $data = [
        ['0812345','Ahmad Rizky Pratama','8A','Matematika','2025-06-18','hadir',   ''],
        ['0812346','Bella Sari Dewi',     '8A','Matematika','2025-06-18','hadir',   ''],
        ['0812347','Cahyo Wibowo',         '8A','Matematika','2025-06-18','absen',   ''],
        ['0812348','Dewi Rahayu Putri',    '8A','Matematika','2025-06-18','izin',    'Acara keluarga'],
        ['0812349','Eko Firmansyah',       '8A','Matematika','2025-06-18','hadir',   ''],
        ['0812350','Fira Ananda Lestari',  '8A','Matematika','2025-06-18','hadir',   ''],
        ['0812351','Gilang Prayuda',        '8A','Matematika','2025-06-18','sakit',   'Demam'],
        ['0812352','Hana Maharani',         '8A','Matematika','2025-06-18','hadir',   ''],
        ['0812353','Ivan Setiawan',         '8A','Matematika','2025-06-18','hadir',   ''],
        ['0812354','Jasmine Putri',         '8A','Matematika','2025-06-18','hadir',   ''],
    ];
    $st = $pdo->prepare('INSERT INTO absensi_siswa (nis,nama,kelas,mapel,tanggal,status,keterangan) VALUES (?,?,?,?,?,?,?)');
    foreach ($data as $d) $st->execute($d);
}
