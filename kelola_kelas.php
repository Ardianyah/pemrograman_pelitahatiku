<?php
/* =====================================================================
   PelitaHatiKu - kelola_kelas.php
   Admin: CRUD Master Kelas (+ penetapan wali kelas) & Tahun Ajaran
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
// PROSES: TAMBAH TAHUN AJARAN
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_tahun') {
    $nama_ta  = trim($_POST['nama_tahun_ajaran'] ?? '');
    $semester = $_POST['semester'] ?? '';
    $jadikan_aktif = isset($_POST['jadikan_aktif']) ? 1 : 0;

    if (empty($nama_ta) || empty($semester)) {
        $pesan_error = "Nama tahun ajaran dan semester wajib diisi.";
    } else {
        if ($jadikan_aktif) {
            $conn->query("UPDATE tahun_ajaran SET status_aktif = 0"); // non-aktifkan yang lain
        }
        $stmt = $conn->prepare("INSERT INTO tahun_ajaran (nama_tahun_ajaran, semester, status_aktif) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nama_ta, $semester, $jadikan_aktif);
        if ($stmt->execute()) {
            $pesan_sukses = "Tahun ajaran \"$nama_ta ($semester)\" berhasil ditambahkan.";
        } else {
            $pesan_error = "Gagal menyimpan: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: JADIKAN TAHUN AJARAN AKTIF
// -----------------------------------------------------------------
if (isset($_GET['aktifkan_ta'])) {
    $id_ta = (int)$_GET['aktifkan_ta'];
    $conn->query("UPDATE tahun_ajaran SET status_aktif = 0");
    $stmt = $conn->prepare("UPDATE tahun_ajaran SET status_aktif = 1 WHERE id_tahun_ajaran = ?");
    $stmt->bind_param("i", $id_ta);
    $stmt->execute();
    $stmt->close();
    $pesan_sukses = "Tahun ajaran aktif berhasil diperbarui.";
}

// -----------------------------------------------------------------
// PROSES: TAMBAH KELAS
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_kelas') {
    $nama_kelas      = trim($_POST['nama_kelas'] ?? '');
    $id_guru         = !empty($_POST['id_guru']) ? (int)$_POST['id_guru'] : null;
    $id_tahun_ajaran = (int)($_POST['id_tahun_ajaran'] ?? 0);

    if (empty($nama_kelas) || empty($id_tahun_ajaran)) {
        $pesan_error = "Nama kelas dan tahun ajaran wajib diisi.";
    } else {
        $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, id_guru, id_tahun_ajaran) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama_kelas, $id_guru, $id_tahun_ajaran);
        if ($stmt->execute()) {
            $pesan_sukses = "Kelas \"$nama_kelas\" berhasil ditambahkan.";
        } else {
            $pesan_error = "Gagal menyimpan: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: EDIT KELAS
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit_kelas') {
    $id_kelas        = (int)($_POST['id_kelas'] ?? 0);
    $nama_kelas      = trim($_POST['nama_kelas'] ?? '');
    $id_guru         = !empty($_POST['id_guru']) ? (int)$_POST['id_guru'] : null;
    $id_tahun_ajaran = (int)($_POST['id_tahun_ajaran'] ?? 0);

    if (empty($nama_kelas) || empty($id_tahun_ajaran)) {
        $pesan_error = "Nama kelas dan tahun ajaran wajib diisi.";
    } else {
        $stmt = $conn->prepare("UPDATE kelas SET nama_kelas = ?, id_guru = ?, id_tahun_ajaran = ? WHERE id_kelas = ?");
        $stmt->bind_param("siii", $nama_kelas, $id_guru, $id_tahun_ajaran, $id_kelas);
        if ($stmt->execute()) {
            $pesan_sukses = "Kelas berhasil diperbarui.";
        } else {
            $pesan_error = "Gagal memperbarui: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS KELAS
// -----------------------------------------------------------------
if (isset($_GET['hapus_kelas'])) {
    $id_hapus = (int)$_GET['hapus_kelas'];
    $cek = $conn->prepare("SELECT COUNT(*) AS jumlah FROM siswa WHERE id_kelas = ?");
    $cek->bind_param("i", $id_hapus);
    $cek->execute();
    $jumlah_siswa = (int)$cek->get_result()->fetch_assoc()['jumlah'];
    $cek->close();

    if ($jumlah_siswa > 0) {
        $pesan_error = "Kelas tidak bisa dihapus karena masih memiliki $jumlah_siswa siswa. Pindahkan siswa terlebih dahulu.";
    } else {
        $stmt = $conn->prepare("DELETE FROM kelas WHERE id_kelas = ?");
        $stmt->bind_param("i", $id_hapus);
        $stmt->execute();
        $stmt->close();
        $pesan_sukses = "Kelas berhasil dihapus.";
    }
}

// -----------------------------------------------------------------
// AMBIL DATA UNTUK EDIT KELAS (jika ada ?edit_kelas=id)
// -----------------------------------------------------------------
$data_edit_kelas = null;
if (isset($_GET['edit_kelas'])) {
    $id_edit = (int)$_GET['edit_kelas'];
    $stmt = $conn->prepare("SELECT * FROM kelas WHERE id_kelas = ?");
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) { $data_edit_kelas = $res->fetch_assoc(); }
    $stmt->close();
}

// -----------------------------------------------------------------
// DATA PENDUKUNG: daftar guru & tahun ajaran
// -----------------------------------------------------------------
$daftar_guru = [];
$res_guru = $conn->query("SELECT id_user, nama_lengkap FROM users WHERE role = 'guru' AND status_aktif = 1 ORDER BY nama_lengkap ASC");
while ($row = $res_guru->fetch_assoc()) { $daftar_guru[] = $row; }

$daftar_tahun_ajaran = [];
$res_ta = $conn->query("SELECT * FROM tahun_ajaran ORDER BY id_tahun_ajaran DESC");
while ($row = $res_ta->fetch_assoc()) { $daftar_tahun_ajaran[] = $row; }

// -----------------------------------------------------------------
// DAFTAR KELAS + jumlah siswa & nama wali kelas
// -----------------------------------------------------------------
$daftar_kelas = [];
$res_kelas = $conn->query("
    SELECT k.*, u.nama_lengkap AS nama_wali, t.nama_tahun_ajaran, t.semester,
           (SELECT COUNT(*) FROM siswa s WHERE s.id_kelas = k.id_kelas AND s.status_aktif = 1) AS jumlah_siswa
    FROM kelas k
    LEFT JOIN users u ON u.id_user = k.id_guru
    LEFT JOIN tahun_ajaran t ON t.id_tahun_ajaran = k.id_tahun_ajaran
    ORDER BY t.id_tahun_ajaran DESC, k.nama_kelas ASC
");
while ($row = $res_kelas->fetch_assoc()) { $daftar_kelas[] = $row; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Kelola Kelas &amp; Tahun Ajaran - PelitaHatiKu</title>
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
        .data-item__actions { display: flex; gap: 6px; flex-shrink: 0; }
        .icon-btn {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; border: 1px solid var(--color-border);
            background-color: var(--color-bg); cursor: pointer; text-decoration: none;
        }
        .badge-status { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; margin-top: 6px; }
        .badge-aktif { background-color: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-nonaktif { background-color: var(--color-border); color: var(--color-text-muted); }
        .back-link { font-size: 12.5px; }
        .section-divider { margin: 28px 0 14px 0; font-size: 15px; font-weight: 800; color: var(--color-text); }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_admin.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Kelola Kelas &amp; Tahun Ajaran</div>
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
             SEKSI 1: TAHUN AJARAN
        ============================================================= -->
        <div class="card__header" style="margin-top:4px;">
            <div class="card__title">ðŸ“… Tahun Ajaran</div>
        </div>

        <details class="form-panel">
            <summary>âž• Tambah Tahun Ajaran</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_kelas.php">
                    <input type="hidden" name="aksi" value="tambah_tahun">
                    <div class="form-group">
                        <label class="form-label">Nama Tahun Ajaran</label>
                        <input type="text" name="nama_tahun_ajaran" class="form-input" placeholder="contoh: 2026/2027" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-input" required>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="jadikan_aktif" id="jadikan_aktif" style="width:18px; height:18px;">
                        <label class="form-label" for="jadikan_aktif" style="margin:0;">Jadikan tahun ajaran aktif saat ini</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Tahun Ajaran</button>
                </form>
            </div>
        </details>

        <?php foreach ($daftar_tahun_ajaran as $ta): ?>
            <div class="data-item">
                <div class="data-item__top">
                    <div>
                        <div class="data-item__name"><?php echo htmlspecialchars($ta['nama_tahun_ajaran']); ?> â€” <?php echo htmlspecialchars($ta['semester']); ?></div>
                        <span class="badge-status <?php echo $ta['status_aktif'] ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                            <?php echo $ta['status_aktif'] ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                    </div>
                    <?php if (!$ta['status_aktif']): ?>
                        <a href="kelola_kelas.php?aktifkan_ta=<?php echo (int)$ta['id_tahun_ajaran']; ?>" class="icon-btn" title="Jadikan Aktif">âœ…</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- ============================================================
             SEKSI 2: KELAS
        ============================================================= -->
        <div class="section-divider">ðŸ« Master Kelas</div>

        <!-- FORM EDIT KELAS -->
        <?php if ($data_edit_kelas): ?>
            <details class="form-panel" open>
                <summary>âœï¸ Edit Kelas: <?php echo htmlspecialchars($data_edit_kelas['nama_kelas']); ?></summary>
                <div class="form-panel-body">
                    <form method="POST" action="kelola_kelas.php">
                        <input type="hidden" name="aksi" value="edit_kelas">
                        <input type="hidden" name="id_kelas" value="<?php echo (int)$data_edit_kelas['id_kelas']; ?>">

                        <div class="form-group">
                            <label class="form-label">Nama Kelas</label>
                            <input type="text" name="nama_kelas" class="form-input" required
                                   value="<?php echo htmlspecialchars($data_edit_kelas['nama_kelas']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Wali Kelas</label>
                            <select name="id_guru" class="form-input">
                                <option value="">â€” Belum ada wali kelas â€”</option>
                                <?php foreach ($daftar_guru as $g): ?>
                                    <option value="<?php echo (int)$g['id_user']; ?>"
                                        <?php echo ((int)$data_edit_kelas['id_guru'] === (int)$g['id_user']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['nama_lengkap']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tahun Ajaran</label>
                            <select name="id_tahun_ajaran" class="form-input" required>
                                <?php foreach ($daftar_tahun_ajaran as $ta): ?>
                                    <option value="<?php echo (int)$ta['id_tahun_ajaran']; ?>"
                                        <?php echo ((int)$data_edit_kelas['id_tahun_ajaran'] === (int)$ta['id_tahun_ajaran']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ta['nama_tahun_ajaran'] . ' - ' . $ta['semester']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="kelola_kelas.php" class="btn btn-outline" style="margin-top:8px;">Batal</a>
                    </form>
                </div>
            </details>
        <?php endif; ?>

        <!-- FORM TAMBAH KELAS -->
        <details class="form-panel">
            <summary>âž• Tambah Kelas Baru</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_kelas.php">
                    <input type="hidden" name="aksi" value="tambah_kelas">
                    <div class="form-group">
                        <label class="form-label">Nama Kelas</label>
                        <input type="text" name="nama_kelas" class="form-input" placeholder="contoh: TK B" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Wali Kelas</label>
                        <select name="id_guru" class="form-input">
                            <option value="">â€” Belum ada wali kelas â€”</option>
                            <?php foreach ($daftar_guru as $g): ?>
                                <option value="<?php echo (int)$g['id_user']; ?>"><?php echo htmlspecialchars($g['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tahun Ajaran</label>
                        <select name="id_tahun_ajaran" class="form-input" required>
                            <?php foreach ($daftar_tahun_ajaran as $ta): ?>
                                <option value="<?php echo (int)$ta['id_tahun_ajaran']; ?>">
                                    <?php echo htmlspecialchars($ta['nama_tahun_ajaran'] . ' - ' . $ta['semester']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Kelas Baru</button>
                </form>
            </div>
        </details>

        <!-- DAFTAR KELAS -->
        <?php if (count($daftar_kelas) > 0): ?>
            <?php foreach ($daftar_kelas as $k): ?>
                <div class="data-item">
                    <div class="data-item__top">
                        <div>
                            <div class="data-item__name"><?php echo htmlspecialchars($k['nama_kelas']); ?></div>
                            <div class="data-item__meta">
                                Wali: <?php echo htmlspecialchars($k['nama_wali'] ?? 'Belum ditentukan'); ?> Â·
                                <?php echo (int)$k['jumlah_siswa']; ?> siswa
                            </div>
                            <div class="data-item__meta">
                                <?php echo htmlspecialchars($k['nama_tahun_ajaran'] . ' - ' . $k['semester']); ?>
                            </div>
                        </div>
                        <div class="data-item__actions">
                            <a href="kelola_kelas.php?edit_kelas=<?php echo (int)$k['id_kelas']; ?>" class="icon-btn" title="Edit">âœï¸</a>
                            <a href="kelola_kelas.php?hapus_kelas=<?php echo (int)$k['id_kelas']; ?>" class="icon-btn" title="Hapus"
                               onclick="return confirm('Yakin ingin menghapus kelas \'<?php echo htmlspecialchars($k['nama_kelas'], ENT_QUOTES); ?>\'?');">ðŸ—‘ï¸</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon">ðŸ«</div>
                <div class="empty-state__text">Belum ada kelas. Tambahkan kelas baru di atas.</div>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>

