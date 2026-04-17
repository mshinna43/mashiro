# Sistem Informasi Sekolah — v2.0
## Dengan Backend SQLite + PHP Full CRUD

---

## Teknologi

| Komponen  | Detail                                          |
|-----------|-------------------------------------------------|
| Backend   | PHP 8.1+ (native, tanpa framework)              |
| Database  | SQLite 3 via PDO (file `data/sekolah.db`)       |
| Frontend  | HTML5 + CSS3 custom (tanpa library eksternal)   |
| Font      | Google Fonts (Plus Jakarta Sans, DM Mono)       |

> **Tidak perlu install MySQL / MariaDB.**
> Database SQLite dibuat otomatis saat pertama kali diakses.

---

## Struktur Proyek

```
sekolah/
│
├── index.php                    ← Redirect ke /ppdb/
├── .htaccess                    ← Keamanan & routing Apache
│
├── config/
│   ├── database.php             ← Koneksi PDO + inisialisasi tabel + seed data
│   ├── helpers.php              ← Fungsi utilitas (flash, badge, format, dll)
│   ├── layout.php               ← Header + sidebar bersama
│   └── layout_end.php           ← Footer + JS bersama
│
├── assets/
│   └── css/
│       └── app.css              ← Design system lengkap (CSS variables, komponen)
│
├── data/                        ← Dibuat otomatis
│   └── sekolah.db               ← File database SQLite
│
├── ppdb/
│   └── index.php                ← CRUD Pendaftaran Siswa Baru
│
├── perpustakaan/
│   └── index.php                ← CRUD Buku + Peminjaman
│
├── absensi-peserta/
│   └── index.php                ← CRUD Absensi Peserta Kegiatan
│
├── pembayaran-spp/
│   └── index.php                ← CRUD Tagihan & Pembayaran SPP
│
└── absensi-siswa/
    └── index.php                ← CRUD Absensi Siswa Harian
```

---

## Cara Menjalankan

### Opsi 1 — PHP Built-in Server (paling mudah)
```bash
# Masuk ke folder sekolah
cd sekolah/

# Jalankan server lokal
php -S localhost:8000

# Buka di browser:
http://localhost:8000/ppdb/
http://localhost:8000/perpustakaan/
http://localhost:8000/absensi-peserta/
http://localhost:8000/pembayaran-spp/
http://localhost:8000/absensi-siswa/
```

### Opsi 2 — XAMPP / Laragon
1. Salin folder `sekolah/` ke `htdocs/` (XAMPP) atau `www/` (Laragon)
2. Pastikan `mod_rewrite` Apache aktif
3. Jalankan Apache
4. Buka `http://localhost/sekolah/`

### Opsi 3 — VPS / Hosting
1. Upload semua file via FTP/SFTP
2. Pastikan folder `data/` bisa ditulis oleh web server:
   ```bash
   chmod 755 data/
   ```
3. Buka domain/subdomain yang mengarah ke folder `sekolah/`

---

## Fitur CRUD per Modul

### 1. PPDB (Ungu)
- ✅ Tambah pendaftar baru (modal form)
- ✅ Edit data pendaftar (modal edit)
- ✅ Hapus pendaftar (dengan konfirmasi)
- ✅ Filter status (Diterima / Verifikasi / Ditolak)
- ✅ Search nama & nomor pendaftaran
- ✅ Pagination
- ✅ Progress bar kuota

### 2. Perpustakaan (Teal)
- ✅ Tambah / edit / hapus buku
- ✅ Catat peminjaman baru (cek stok otomatis)
- ✅ Tandai buku dikembalikan
- ✅ Filter kategori & search judul/penulis
- ✅ Tab: Koleksi Buku | Peminjaman Aktif
- ✅ Badge stok real-time (Tersedia / Sisa N / Habis)

### 3. Absensi Peserta (Biru)
- ✅ Tambah kegiatan baru
- ✅ Hapus kegiatan beserta data absensi
- ✅ Tambah peserta ke kegiatan
- ✅ Rekam absensi bulk (H/A/I/S) dengan toggle visual
- ✅ Filter per kegiatan & per tanggal
- ✅ Simpan ke database (upsert — aman disubmit ulang)

### 4. Pembayaran SPP (Amber)
- ✅ Tambah data siswa baru
- ✅ Generate tagihan massal untuk seluruh siswa
- ✅ Catat pembayaran per siswa per bulan
- ✅ Filter status & search
- ✅ Quick-pay dari tabel (klik Bayar → modal terisi otomatis)
- ✅ Hapus tagihan

### 5. Absensi Siswa (Hijau)
- ✅ Rekam absensi harian per kelas + mapel + tanggal
- ✅ Toggle H/A/I/S dengan visual interaktif
- ✅ Kolom keterangan per siswa
- ✅ Rekap mingguan (5 hari)
- ✅ Meter persentase kehadiran
- ✅ Tambah siswa baru ke sesi absensi

---

## Standar Kode

```
Setiap file PHP mengikuti pola:
  1. Docblock      — nama file, fungsi, author
  2. Require       — database + helpers
  3. POST handler  — semua aksi form (tambah/edit/hapus)
  4. GET handler   — ambil data, filter, paginate
  5. HTML template — render tampilan

Penamaan:
  - Variabel       : $snake_case
  - Fungsi         : snake_case()
  - Tabel database : snake_case
  - Kolom          : snake_case

Keamanan:
  - htmlspecialchars() via e() untuk semua output
  - PDO prepared statements untuk semua query
  - POST-Redirect-GET pattern untuk semua form
  - Flash message via session untuk feedback
```

---

## Menghubungkan ke MySQL (Opsional)

Ubah fungsi `db()` di `config/database.php`:

```php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=sekolah;charset=utf8mb4',
            'root',       // username
            '',           // password
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
```

Catatan: Ubah juga sintaks `ON CONFLICT` (SQLite) menjadi
`INSERT ... ON DUPLICATE KEY UPDATE` (MySQL) di beberapa query.
