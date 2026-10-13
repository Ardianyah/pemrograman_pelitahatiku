<?php
/* =====================================================================
   PelitaHatiKu - kelola_akun.php
   Admin: Manajemen Akun lintas role, reset password, kode akses Orang Tua
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_admin_login = $_SESSION['id_user'];
$pesan_sukses    = "";
$pesan_error     = "";

/**
 * Generate kode akses unik format PH-XXXXXX (6 digit angka).
 */
function generate_kode_akses(mysqli $conn): string {
    do {
        $kode = 'PH-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cek = $conn->prepare("SELECT id_user FROM users WHERE kode_akses = ?");
        $cek->bind_param("s", $kode);
        $cek->execute();
        $ada = $cek->get_result()->num_rows > 0;
        $cek->close();
    } while ($ada);
    return $kode;
}

// -----------------------------------------------------------------
// PROSES: TAMBAH AKUN STAF (Admin / Kepala Sekolah)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_staf') {
    $username     = trim($_POST['username'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $role         = $_POST['role'] ?? '';

    if (empty($username) || empty($password) || empty($nama_lengkap) || !in_array($role, ['admin', 'kepala_sekolah'], true)) {
        $pesan_error = "Semua kolom wajib diisi dengan role yang valid.";
    } elseif (strlen($password) < 6) {
        $pesan_error = "Password minimal 6 karakter.";
    } else {
        $cek = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $pesan_error = "Username sudah digunakan.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $email_val = $email !== '' ? $email : null;
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, role, nama_lengkap, email, no_hp, status_aktif)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("ssssss", $username, $hash, $role, $nama_lengkap, $email_val, $no_hp);
            if ($stmt->execute()) {
                $pesan_sukses = "Akun staf \"$nama_lengkap\" berhasil ditambahkan.";
            } else {
                $pesan_error = "Gagal menyimpan: " . $conn->error;
            }
            $stmt->close();
        }
        $cek->close();
    }
}

// -----------------------------------------------------------------
// PROSES: TAMBAH AKUN ORANG TUA (otomatis generate kode akses)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_wali') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $id_siswa     = (int)($_POST['id_siswa'] ?? 0);
    $hubungan     = $_POST['hubungan'] ?? 'Wali';

    if (empty($nama_lengkap) || empty($id_siswa)) {
        $pesan_error = "Nama lengkap dan siswa terkait wajib diisi.";
    } else {
        $kode_akses = generate_kode_akses($conn);
        $email_val  = $email !== '' ? $email : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO users (kode_akses, role, nama_lengkap, email, no_hp, status_aktif)
                VALUES (?, 'orang_tua', ?, ?, ?, 1)
            ");
            $stmt->bind_param("ssss", $kode_akses, $nama_lengkap, $email_val, $no_hp);
            $stmt->execute();
            $id_user_baru = $conn->insert_id;
            $stmt->close();

            $stmt2 = $conn->prepare("INSERT INTO orang_tua_siswa (id_user, id_siswa, hubungan) VALUES (?, ?, ?)");
            $stmt2->bind_param("iis", $id_user_baru, $id_siswa, $hubungan);
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();
            $pesan_sukses = "Akun Orang Tua \"$nama_lengkap\" berhasil dibuat. Kode Akses: <b>$kode_akses</b> â€” catat &amp; sampaikan ke wali murid.";
        } catch (Exception $e) {
            $conn->rollback();
            $pesan_error = "Gagal membuat akun: " . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------
// PROSES: TOGGLE STATUS AKTIF
// -----------------------------------------------------------------
if (isset($_GET['toggle'])) {
    $id_toggle = (int)$_GET['toggle'];
    if ($id_toggle === $id_admin_login) {
        $pesan_error = "Anda tidak bisa menonaktifkan akun Anda sendiri.";
    } else {
        $conn->query("UPDATE users SET status_aktif = 1 - status_aktif WHERE id_user = $id_toggle");
        $pesan_sukses = "Status akun berhasil diperbarui.";
    }
}

// -----------------------------------------------------------------
// PROSES: REGENERATE KODE AKSES ORANG TUA
// -----------------------------------------------------------------
if (isset($_GET['regen_kode'])) {
    $id_user = (int)$_GET['regen_kode'];
    $kode_baru = generate_kode_akses($conn);
    $stmt = $conn->prepare("UPDATE users SET kode_akses = ? WHERE id_user = ? AND role = 'orang_tua'");
    $stmt->bind_param("si", $kode_baru, $id_user);
    $stmt->execute();
    $stmt->close();
    $pesan_sukses = "Kode Akses baru: <b>$kode_baru</b> â€” catat &amp; sampaikan ke wali murid. Kode lama tidak berlaku lagi.";
}

// -----------------------------------------------------------------
// PROSES: RESET PASSWORD (Staf: admin/kepsek/guru)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'reset_password') {
    $id_user      = (int)($_POST['id_user'] ?? 0);
    $password_baru = trim($_POST['password_baru'] ?? '');

    if (strlen($password_baru) < 6) {
        $pesan_error = "Password baru minimal 6 karakter.";
    } else {
        $hash = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ? AND role IN ('admin','kepala_sekolah','guru')");
        $stmt->bind_param("si", $hash, $id_user);
        if ($stmt->execute()) {
            $pesan_sukses = "Password berhasil direset.";
        } else {
            $pesan_error = "Gagal mereset password: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS AKUN
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    if ($id_hapus === $id_admin_login) {
        $pesan_error = "Anda tidak bisa menghapus akun Anda sendiri.";
    } else {
        $cek_admin = $conn->query("SELECT role FROM users WHERE id_user = $id_hapus")->fetch_assoc();
        if ($cek_admin && $cek_admin['role'] === 'admin') {
            $sisa_admin = $conn->query("SELECT COUNT(*) AS jumlah FROM users WHERE role = 'admin'")->fetch_assoc()['jumlah'];
            if ((int)$sisa_admin <= 1) {
                $pesan_error = "Tidak bisa menghapus admin terakhir dalam sistem.";
            }
        }
        if (empty($pesan_error)) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt->bind_param("i", $id_hapus);
            $stmt->execute();
            $stmt->close();
            $pesan_sukses = "Akun berhasil dihapus.";
        }
    }
}

// -----------------------------------------------------------------
// DATA PENDUKUNG: siswa untuk dropdown akun orang tua
// -----------------------------------------------------------------
$daftar_siswa = [];
$res_siswa = $conn->query("SELECT id_siswa, nama_siswa, nis FROM siswa WHERE status_aktif = 1 ORDER BY nama_siswa ASC");
while ($row = $res_siswa->fetch_assoc()) { $daftar_siswa[] = $row; }

// -----------------------------------------------------------------
// FILTER ROLE (tab)
// -----------------------------------------------------------------
$filter_role = $_GET['role'] ?? 'semua';
$role_valid  = ['semua', 'admin', 'kepala_sekolah', 'guru', 'orang_tua'];
if (!in_array($filter_role, $role_valid, true)) { $filter_role = 'semua'; }

$sql_users = "SELECT * FROM users";
if ($filter_role !== 'semua') {
    $sql_users .= " WHERE role = '" . $conn->real_escape_string($filter_role) . "'";
}
$sql_users .= " ORDER BY role ASC, nama_lengkap ASC";
$daftar_users = [];
$res_users = $conn->query($sql_users);
while ($row = $res_users->fetch_assoc()) { $daftar_users[] = $row; }

$label_role = [
    'admin'          => 'Admin',
    'kepala_sekolah' => 'Kepala Sekolah',
    'guru'           => 'Guru',
    'orang_tua'      => 'Orang Tua',
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Kelola Akun - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        details.form-panel {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 16px;
            overflow: hidden;
        }
        details.form-panel summary { padding: 14px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; list-style: none; }
        details.form-panel summary::-webkit-details-marker { display: none; }
        details.form-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .form-panel-body { padding: 16px; }
        .data-item {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 10px;
        }
        .data-item__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .data-item__name { font-size: 14px; font-weight: 700; }
        .data-item__meta { font-size: 12px; color: var(--color-text-muted); margin-top: 3px; }
        .data-item__actions { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
        .icon-btn {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; border: 1px solid var(--color-border);
            background-color: var(--color-bg); cursor: pointer; text-decoration: none;
        }
        .badge-status { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; margin-top: 6px; margin-right: 4px; }
        .badge-aktif { background-color: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-nonaktif { background-color: #fee2e2; color: var(--color-danger); }
        .badge-role { background-color: #e0e7ff; color: #4338ca; }
        .filter-chip-row { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px; }
        .filter-chip {
            flex-shrink: 0; padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background-color: var(--color-surface); border: 1.5px solid var(--color-border); color: var(--color-text-muted);
        }
        .filter-chip.active { background-color: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        .back-link { font-size: 12.5px; }
        .kode-box {
            font-family: monospace;
            font-weight: 700;
            background-color: var(--color-primary-light);
            color: var(--color-primary-dark);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_admin.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Kelola Akun</div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success"><?php echo $pesan_sukses; /* mengandung tag <b> untuk kode akses */ ?></div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <!-- ============================================================
             FORM TAMBAH AKUN STAF
        ============================================================= -->
        <details class="form-panel">
            <summary>âž• Tambah Akun Staf (Admin / Kepala Sekolah)</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_akun.php">
                    <input type="hidden" name="aksi" value="tambah_staf">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-input" required>
                            <option value="admin">Admin</option>
                            <option value="kepala_sekolah">Kepala Sekolah</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password Awal</label>
                        <input type="text" name="password" class="form-input" placeholder="Minimal 6 karakter" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="no_hp" class="form-input">
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Akun Staf</button>
                </form>
                <p style="font-size:11.5px; color:var(--color-text-muted); margin-top:12px;">
                    Untuk akun Guru, gunakan halaman <a href="kelola_guru.php" style="color:var(--color-primary); font-weight:600;">Kelola Guru</a>.
                </p>
            </div>
        </details>

        <!-- ============================================================
             FORM TAMBAH AKUN ORANG TUA
        ============================================================= -->
        <details class="form-panel">
            <summary>âž• Tambah Akun Orang Tua (Kode Akses otomatis)</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_akun.php">
                    <input type="hidden" name="aksi" value="tambah_wali">
                    <div class="form-group">
                        <label class="form-label">Siswa Terkait</label>
                        <select name="id_siswa" class="form-input" required>
                            <option value="">Pilih siswa</option>
                            <?php foreach ($daftar_siswa as $s): ?>
                                <option value="<?php echo (int)$s['id_siswa']; ?>">
                                    <?php echo htmlspecialchars($s['nama_siswa'] . ' (NIS ' . $s['nis'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hubungan</label>
                        <select name="hubungan" class="form-input" required>
                            <option value="Ayah">Ayah</option>
                            <option value="Ibu">Ibu</option>
                            <option value="Wali">Wali</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap Orang Tua/Wali</label>
                        <input type="text" name="nama_lengkap" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="no_hp" class="form-input">
                    </div>
                    <button type="submit" class="btn btn-primary">Buat Akun &amp; Kode Akses</button>
                </form>
            </div>
        </details>

        <!-- ============================================================
             FILTER ROLE
        ============================================================= -->
        <div class="filter-chip-row">
            <a href="kelola_akun.php?role=semua" class="filter-chip <?php echo $filter_role === 'semua' ? 'active' : ''; ?>">Semua</a>
            <a href="kelola_akun.php?role=admin" class="filter-chip <?php echo $filter_role === 'admin' ? 'active' : ''; ?>">Admin</a>
            <a href="kelola_akun.php?role=kepala_sekolah" class="filter-chip <?php echo $filter_role === 'kepala_sekolah' ? 'active' : ''; ?>">Kepala Sekolah</a>
            <a href="kelola_akun.php?role=guru" class="filter-chip <?php echo $filter_role === 'guru' ? 'active' : ''; ?>">Guru</a>
            <a href="kelola_akun.php?role=orang_tua" class="filter-chip <?php echo $filter_role === 'orang_tua' ? 'active' : ''; ?>">Orang Tua</a>
        </div>

        <!-- ============================================================
             DAFTAR AKUN
        ============================================================= -->
        <div class="card__header">
            <div class="card__title">ðŸ” Daftar Akun (<?php echo count($daftar_users); ?>)</div>
        </div>

        <?php if (count($daftar_users) > 0): ?>
            <?php foreach ($daftar_users as $u): ?>
                <div class="data-item">
                    <div class="data-item__top">
                        <div>
                            <div class="data-item__name"><?php echo htmlspecialchars($u['nama_lengkap']); ?></div>
                            <div class="data-item__meta">
                                <?php if ($u['role'] === 'orang_tua'): ?>
                                    Kode Akses: <span class="kode-box"><?php echo htmlspecialchars($u['kode_akses']); ?></span>
                                <?php else: ?>
                                    @<?php echo htmlspecialchars($u['username']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="data-item__meta"><?php echo htmlspecialchars($u['no_hp'] ?? '-'); ?></div>
                            <span class="badge-status badge-role"><?php echo $label_role[$u['role']]; ?></span>
                            <span class="badge-status <?php echo $u['status_aktif'] ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                <?php echo $u['status_aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </div>
                        <div class="data-item__actions">
                            <?php if ($u['role'] === 'orang_tua'): ?>
                                <a href="kelola_akun.php?regen_kode=<?php echo (int)$u['id_user']; ?>" class="icon-btn" title="Buat Ulang Kode Akses"
                                   onclick="return confirm('Buat Kode Akses baru untuk \'<?php echo htmlspecialchars($u['nama_lengkap'], ENT_QUOTES); ?>\'? Kode lama tidak akan berlaku lagi.');">ðŸ”„</a>
                            <?php else: ?>
                                <button type="button" class="icon-btn" title="Reset Password"
                                        onclick="document.getElementById('resetModal<?php echo (int)$u['id_user']; ?>').classList.toggle('open-inline')">ðŸ”‘</button>
                            <?php endif; ?>
                            <a href="kelola_akun.php?toggle=<?php echo (int)$u['id_user']; ?>&role=<?php echo $filter_role; ?>" class="icon-btn" title="Aktifkan/Nonaktifkan">
                                <?php echo $u['status_aktif'] ? 'ðŸš«' : 'âœ…'; ?>
                            </a>
                            <a href="kelola_akun.php?hapus=<?php echo (int)$u['id_user']; ?>&role=<?php echo $filter_role; ?>" class="icon-btn" title="Hapus"
                               onclick="return confirm('Yakin ingin menghapus akun \'<?php echo htmlspecialchars($u['nama_lengkap'], ENT_QUOTES); ?>\'?');">ðŸ—‘ï¸</a>
                        </div>
                    </div>

                    <?php if ($u['role'] !== 'orang_tua'): ?>
                        <!-- Form reset password (disembunyikan, dibuka via tombol ðŸ”‘) -->
                        <form method="POST" action="kelola_akun.php" id="resetModal<?php echo (int)$u['id_user']; ?>"
                              style="display:none; margin-top:12px; border-top:1px solid var(--color-border); padding-top:12px;">
                            <input type="hidden" name="aksi" value="reset_password">
                            <input type="hidden" name="id_user" value="<?php echo (int)$u['id_user']; ?>">
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input type="text" name="password_baru" class="form-input" placeholder="Minimal 6 karakter" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon">ðŸ”</div>
                <div class="empty-state__text">Belum ada akun untuk filter ini.</div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Form reset password disembunyikan secara default; class 'open-inline'
        // ditambahkan/dihapus lewat onclick pada tombol ðŸ”‘ di atas.
    </script>
    <style>
        form[id^="resetModal"].open-inline { display: block !important; }
    </style>

</body>
</html>

