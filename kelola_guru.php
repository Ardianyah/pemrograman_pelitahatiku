<?php
/* =====================================================================
   PelitaHatiKu - kelola_guru.php
   Admin: CRUD biodata Guru (username, password awal, biodata, status aktif)
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$pesan_sukses = "";
$pesan_error  = "";

// -----------------------------------------------------------------
// PROSES: TAMBAH GURU BARU
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $username     = trim($_POST['username'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');

    if (empty($username) || empty($password) || empty($nama_lengkap)) {
        $pesan_error = "Username, password, dan nama lengkap wajib diisi.";
    } elseif (strlen($password) < 6) {
        $pesan_error = "Password minimal 6 karakter.";
    } else {
        $cek = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $pesan_error = "Username sudah digunakan, silakan pilih username lain.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, role, nama_lengkap, email, no_hp, status_aktif)
                VALUES (?, ?, 'guru', ?, ?, ?, 1)
            ");
            $email_val = $email !== '' ? $email : null;
            $stmt->bind_param("sssss", $username, $hash, $nama_lengkap, $email_val, $no_hp);
            if ($stmt->execute()) {
                $pesan_sukses = "Akun Guru \"$nama_lengkap\" berhasil ditambahkan.";
            } else {
                $pesan_error = "Gagal menyimpan data: " . $conn->error;
            }
            $stmt->close();
        }
        $cek->close();
    }
}

// -----------------------------------------------------------------
// PROSES: EDIT BIODATA GURU
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id_user      = (int)($_POST['id_user'] ?? 0);
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $status_aktif = isset($_POST['status_aktif']) ? 1 : 0;

    if (empty($nama_lengkap)) {
        $pesan_error = "Nama lengkap wajib diisi.";
    } else {
        $email_val = $email !== '' ? $email : null;
        $stmt = $conn->prepare("
            UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, status_aktif = ?
            WHERE id_user = ? AND role = 'guru'
        ");
        $stmt->bind_param("sssii", $nama_lengkap, $email_val, $no_hp, $status_aktif, $id_user);
        if ($stmt->execute()) {
            $pesan_sukses = "Biodata guru berhasil diperbarui.";
        } else {
            $pesan_error = "Gagal memperbarui data: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS GURU
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ? AND role = 'guru'");
    $stmt->bind_param("i", $id_hapus);
    if ($stmt->execute()) {
        $pesan_sukses = "Akun guru berhasil dihapus. Kelas yang sebelumnya diampu kini tidak memiliki wali kelas.";
    } else {
        $pesan_error = "Gagal menghapus: " . $conn->error;
    }
    $stmt->close();
}

// -----------------------------------------------------------------
// AMBIL DATA UNTUK EDIT (jika ada ?edit=id)
// -----------------------------------------------------------------
$data_edit = null;
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ? AND role = 'guru'");
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) { $data_edit = $res->fetch_assoc(); }
    $stmt->close();
}

// -----------------------------------------------------------------
// DAFTAR SEMUA GURU + kelas yang diampu (jika ada)
// -----------------------------------------------------------------
$daftar_guru = [];
$res = $conn->query("
    SELECT u.id_user, u.username, u.nama_lengkap, u.email, u.no_hp, u.status_aktif,
           k.nama_kelas
    FROM users u
    LEFT JOIN kelas k ON k.id_guru = u.id_user
    WHERE u.role = 'guru'
    ORDER BY u.nama_lengkap ASC
");
while ($row = $res->fetch_assoc()) { $daftar_guru[] = $row; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Kelola Guru - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=home,logout,settings,school,group,assignment,diversity_4,how_to_reg,event,child_care,person,monitoring,photo_library,calendar_month,campaign,group_add,edit,delete,manage_accounts,admin_panel_settings,diversity_3" />
    <link rel="stylesheet" href="style.css">
    <style>
        details.form-panel {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 16px;
            overflow: hidden;
        }
        details.form-panel summary {
            padding: 14px 16px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            list-style: none;
            display: flex;
            /* justify-content: space-between; */
            align-items: center;
        }
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
        .data-item__actions { display: flex; gap: 6px; flex-shrink: 0; }
        .icon-btn {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            border: 1px solid var(--color-border);
            background-color: var(--color-bg);
            cursor: pointer;
            text-decoration: none;
        }
        .badge-status {
            display: inline-block;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 20px;
            margin-top: 6px;
        }
        .badge-aktif { background-color: var(--color-bg); color: var(--color-primary-dark); }
        .badge-nonaktif { background-color: #fee2e2; color: var(--color-danger); }
        .back-link { font-size: 12.5px; color: var(--color-primary); font-weight: 600; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_admin.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Kelola Guru</div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <!-- ============================================================
             FORM EDIT (muncul jika ?edit=id)
        ============================================================= -->
        <?php if ($data_edit): ?>
            <details class="form-panel" open>
                <summary><span class="material-symbols-outlined">edit</span> Edit Guru: <?php echo htmlspecialchars($data_edit['nama_lengkap']); ?></summary>
                <div class="form-panel-body">
                    <form method="POST" action="kelola_guru.php">
                        <input type="hidden" name="aksi" value="edit">
                        <input type="hidden" name="id_user" value="<?php echo (int)$data_edit['id_user']; ?>">

                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($data_edit['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-input" required
                                   value="<?php echo htmlspecialchars($data_edit['nama_lengkap']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input"
                                   value="<?php echo htmlspecialchars($data_edit['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. HP</label>
                            <input type="text" name="no_hp" class="form-input"
                                   value="<?php echo htmlspecialchars($data_edit['no_hp'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="status_aktif" id="status_aktif" style="width:18px; height:18px;"
                                   <?php echo $data_edit['status_aktif'] ? 'checked' : ''; ?>>
                            <label class="form-label" for="status_aktif" style="margin:0;">Akun Aktif</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="kelola_guru.php" class="btn btn-outline" style="margin-top:8px;">Batal</a>
                    </form>
                    <p style="font-size:11.5px; color:var(--color-text-muted); margin-top:12px;">
                        Untuk reset password, penetapan wali kelas, atau ubah role, gunakan halaman
                        <a href="kelola_akun.php" style="color:var(--color-primary); font-weight:600;">Kelola Akun</a> / <a href="kelola_kelas.php" style="color:var(--color-primary); font-weight:600;">Kelola Kelas</a>.
                    </p>
                </div>
            </details>
        <?php endif; ?>

        <!-- ============================================================
             FORM TAMBAH GURU BARU
        ============================================================= -->
        <details class="form-panel">
            <summary><span class="material-symbols-outlined">group_add</span> Tambah Guru Baru</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_guru.php">
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" placeholder="contoh: guru02" required>
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

                    <button type="submit" class="btn btn-primary">Simpan Guru Baru</button>
                </form>
            </div>
        </details>

        <!-- ============================================================
             DAFTAR GURU
        ============================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">diversity_3</span> Daftar Guru (<?php echo count($daftar_guru); ?>)</div>
        </div>

        <?php if (count($daftar_guru) > 0): ?>
            <?php foreach ($daftar_guru as $g): ?>
                <div class="data-item">
                    <div class="data-item__top">
                        <div>
                            <div class="data-item__name"><?php echo htmlspecialchars($g['nama_lengkap']); ?></div>
                            <div class="data-item__meta">
                                @<?php echo htmlspecialchars($g['username']); ?>
                                <?php echo $g['nama_kelas'] ? ' Â· Wali ' . htmlspecialchars($g['nama_kelas']) : ' Â· Belum wali kelas'; ?>
                            </div>
                            <div class="data-item__meta"><?php echo htmlspecialchars($g['no_hp'] ?? '-'); ?></div>
                            <span class="badge-status <?php echo $g['status_aktif'] ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                <?php echo $g['status_aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </div>
                        <div class="data-item__actions">
                            <a href="kelola_guru.php?edit=<?php echo (int)$g['id_user']; ?>" class="icon-btn" title="Edit"><span class="material-symbols-outlined">edit</span></a>
                            <a href="kelola_guru.php?hapus=<?php echo (int)$g['id_user']; ?>" class="icon-btn" title="Hapus"
                               onclick="return confirm('Yakin ingin menghapus akun guru \'<?php echo htmlspecialchars($g['nama_lengkap'], ENT_QUOTES); ?>\'? Tindakan ini tidak dapat dibatalkan.');"><span class="material-symbols-outlined">delete</span></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">group</span></div>
                <div class="empty-state__text">Belum ada data guru. Tambahkan guru baru di atas.</div>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>

