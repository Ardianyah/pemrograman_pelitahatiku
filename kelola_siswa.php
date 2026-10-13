<?php
/* =====================================================================
   PelitaHatiKu - kelola_siswa.php
   Admin: CRUD biodata Siswa + penetapan kelas + status aktif
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
// PROSES: TAMBAH SISWA BARU
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nis            = trim($_POST['nis'] ?? '');
    $nama_siswa     = trim($_POST['nama_siswa'] ?? '');
    $jenis_kelamin  = $_POST['jenis_kelamin'] ?? '';
    $tempat_lahir   = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir  = $_POST['tanggal_lahir'] ?? '';
    $alamat         = trim($_POST['alamat'] ?? '');
    $id_kelas       = !empty($_POST['id_kelas']) ? (int)$_POST['id_kelas'] : null;

    if (empty($nis) || empty($nama_siswa) || empty($jenis_kelamin)) {
        $pesan_error = "NIS, nama siswa, dan jenis kelamin wajib diisi.";
    } else {
        $cek = $conn->prepare("SELECT id_siswa FROM siswa WHERE nis = ?");
        $cek->bind_param("s", $nis);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $pesan_error = "NIS \"$nis\" sudah terdaftar, gunakan NIS lain.";
        } else {
            $tgl_val = $tanggal_lahir !== '' ? $tanggal_lahir : null;
            $stmt = $conn->prepare("
                INSERT INTO siswa (nis, nama_siswa, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, id_kelas, status_aktif)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("ssssssi", $nis, $nama_siswa, $jenis_kelamin, $tempat_lahir, $tgl_val, $alamat, $id_kelas);
            if ($stmt->execute()) {
                $pesan_sukses = "Siswa \"$nama_siswa\" berhasil ditambahkan.";
            } else {
                $pesan_error = "Gagal menyimpan data: " . $conn->error;
            }
            $stmt->close();
        }
        $cek->close();
    }
}

// -----------------------------------------------------------------
// PROSES: EDIT DATA SISWA
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id_siswa       = (int)($_POST['id_siswa'] ?? 0);
    $nama_siswa     = trim($_POST['nama_siswa'] ?? '');
    $jenis_kelamin  = $_POST['jenis_kelamin'] ?? '';
    $tempat_lahir   = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir  = $_POST['tanggal_lahir'] ?? '';
    $alamat         = trim($_POST['alamat'] ?? '');
    $id_kelas       = !empty($_POST['id_kelas']) ? (int)$_POST['id_kelas'] : null;
    $status_aktif   = isset($_POST['status_aktif']) ? 1 : 0;

    if (empty($nama_siswa) || empty($jenis_kelamin)) {
        $pesan_error = "Nama siswa dan jenis kelamin wajib diisi.";
    } else {
        $tgl_val = $tanggal_lahir !== '' ? $tanggal_lahir : null;
        $stmt = $conn->prepare("
            UPDATE siswa
            SET nama_siswa = ?, jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?, alamat = ?, id_kelas = ?, status_aktif = ?
            WHERE id_siswa = ?
        ");
        $stmt->bind_param("sssssiii", $nama_siswa, $jenis_kelamin, $tempat_lahir, $tgl_val, $alamat, $id_kelas, $status_aktif, $id_siswa);
        if ($stmt->execute()) {
            $pesan_sukses = "Data siswa berhasil diperbarui.";
        } else {
            $pesan_error = "Gagal memperbarui data: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS SISWA
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM siswa WHERE id_siswa = ?");
    $stmt->bind_param("i", $id_hapus);
    if ($stmt->execute()) {
        $pesan_sukses = "Data siswa berhasil dihapus beserta seluruh riwayat absensi & e-rapor terkait.";
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
    $stmt = $conn->prepare("SELECT * FROM siswa WHERE id_siswa = ?");
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) { $data_edit = $res->fetch_assoc(); }
    $stmt->close();
}

// -----------------------------------------------------------------
// DAFTAR KELAS (untuk dropdown & filter)
// -----------------------------------------------------------------
$daftar_kelas = [];
$res_kelas = $conn->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
while ($row = $res_kelas->fetch_assoc()) { $daftar_kelas[] = $row; }

// -----------------------------------------------------------------
// FILTER KELAS (opsional, via ?kelas=id)
// -----------------------------------------------------------------
$filter_kelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;

// -----------------------------------------------------------------
// DAFTAR SISWA
// -----------------------------------------------------------------
$daftar_siswa = [];
if ($filter_kelas > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, k.nama_kelas
        FROM siswa s LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
        WHERE s.id_kelas = ?
        ORDER BY s.nama_siswa ASC
    ");
    $stmt->bind_param("i", $filter_kelas);
    $stmt->execute();
    $res_siswa = $stmt->get_result();
} else {
    $res_siswa = $conn->query("
        SELECT s.*, k.nama_kelas
        FROM siswa s LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
        ORDER BY s.nama_siswa ASC
    ");
}
while ($row = $res_siswa->fetch_assoc()) { $daftar_siswa[] = $row; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Kelola Siswa - PelitaHatiKu</title>
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
        details.form-panel summary {
            padding: 14px 16px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            list-style: none;
        }
        details.form-panel summary::-webkit-details-marker { display: none; }
        details.form-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .form-panel-body { padding: 16px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
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
        .badge-status { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; margin-top: 6px; }
        .badge-aktif { background-color: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-nonaktif { background-color: #fee2e2; color: var(--color-danger); }
        .filter-chip-row { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px; }
        .filter-chip {
            flex-shrink: 0;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background-color: var(--color-surface);
            border: 1.5px solid var(--color-border);
            color: var(--color-text-muted);
        }
        .filter-chip.active { background-color: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_admin.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Kelola Siswa</div>
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
             FORM EDIT
        ============================================================= -->
        <?php if ($data_edit): ?>
            <details class="form-panel" open>
                <summary>âœï¸ Edit Siswa: <?php echo htmlspecialchars($data_edit['nama_siswa']); ?></summary>
                <div class="form-panel-body">
                    <form method="POST" action="kelola_siswa.php">
                        <input type="hidden" name="aksi" value="edit">
                        <input type="hidden" name="id_siswa" value="<?php echo (int)$data_edit['id_siswa']; ?>">

                        <div class="form-group">
                            <label class="form-label">NIS</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($data_edit['nis']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Siswa</label>
                            <input type="text" name="nama_siswa" class="form-input" required
                                   value="<?php echo htmlspecialchars($data_edit['nama_siswa']); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-input" required>
                                    <option value="L" <?php echo $data_edit['jenis_kelamin'] === 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="P" <?php echo $data_edit['jenis_kelamin'] === 'P' ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-input"
                                       value="<?php echo htmlspecialchars($data_edit['tanggal_lahir'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="form-input"
                                   value="<?php echo htmlspecialchars($data_edit['tempat_lahir'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Alamat</label>
                            <input type="text" name="alamat" class="form-input"
                                   value="<?php echo htmlspecialchars($data_edit['alamat'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kelas</label>
                            <select name="id_kelas" class="form-input">
                                <option value="">â€” Belum ditempatkan â€”</option>
                                <?php foreach ($daftar_kelas as $k): ?>
                                    <option value="<?php echo (int)$k['id_kelas']; ?>"
                                        <?php echo ((int)$data_edit['id_kelas'] === (int)$k['id_kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($k['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="status_aktif" id="status_aktif" style="width:18px; height:18px;"
                                   <?php echo $data_edit['status_aktif'] ? 'checked' : ''; ?>>
                            <label class="form-label" for="status_aktif" style="margin:0;">Siswa Aktif</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="kelola_siswa.php" class="btn btn-outline" style="margin-top:8px;">Batal</a>
                    </form>
                </div>
            </details>
        <?php endif; ?>

        <!-- ============================================================
             FORM TAMBAH SISWA
        ============================================================= -->
        <details class="form-panel">
            <summary>âž• Tambah Siswa Baru</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_siswa.php">
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="form-group">
                        <label class="form-label">NIS</label>
                        <input type="text" name="nis" class="form-input" placeholder="contoh: 2025002" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Siswa</label>
                        <input type="text" name="nama_siswa" class="form-input" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-input" required>
                                <option value="">Pilih</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alamat</label>
                        <input type="text" name="alamat" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kelas</label>
                        <select name="id_kelas" class="form-input">
                            <option value="">â€” Belum ditempatkan â€”</option>
                            <?php foreach ($daftar_kelas as $k): ?>
                                <option value="<?php echo (int)$k['id_kelas']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Siswa Baru</button>
                </form>
                <p style="font-size:11.5px; color:var(--color-text-muted); margin-top:12px;">
                    Setelah siswa tersimpan, hubungkan dengan akun Orang Tua di halaman
                    <a href="kelola_akun.php" style="color:var(--color-primary); font-weight:600;">Kelola Akun</a>.
                </p>
            </div>
        </details>

        <!-- ============================================================
             FILTER KELAS
        ============================================================= -->
        <div class="filter-chip-row">
            <a href="kelola_siswa.php" class="filter-chip <?php echo $filter_kelas === 0 ? 'active' : ''; ?>">Semua Kelas</a>
            <?php foreach ($daftar_kelas as $k): ?>
                <a href="kelola_siswa.php?kelas=<?php echo (int)$k['id_kelas']; ?>"
                   class="filter-chip <?php echo $filter_kelas === (int)$k['id_kelas'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($k['nama_kelas']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================
             DAFTAR SISWA
        ============================================================= -->
        <div class="card__header">
            <div class="card__title">ðŸ§’ Daftar Siswa (<?php echo count($daftar_siswa); ?>)</div>
        </div>

        <?php if (count($daftar_siswa) > 0): ?>
            <?php foreach ($daftar_siswa as $s): ?>
                <div class="data-item">
                    <div class="data-item__top">
                        <div>
                            <div class="data-item__name"><?php echo htmlspecialchars($s['nama_siswa']); ?></div>
                            <div class="data-item__meta">
                                NIS <?php echo htmlspecialchars($s['nis']); ?> Â·
                                <?php echo $s['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?> Â·
                                <?php echo htmlspecialchars($s['nama_kelas'] ?? 'Belum ada kelas'); ?>
                            </div>
                            <span class="badge-status <?php echo $s['status_aktif'] ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                <?php echo $s['status_aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </div>
                        <div class="data-item__actions">
                            <a href="kelola_siswa.php?edit=<?php echo (int)$s['id_siswa']; ?>" class="icon-btn" title="Edit">âœï¸</a>
                            <a href="kelola_siswa.php?hapus=<?php echo (int)$s['id_siswa']; ?>" class="icon-btn" title="Hapus"
                               onclick="return confirm('Yakin ingin menghapus data siswa \'<?php echo htmlspecialchars($s['nama_siswa'], ENT_QUOTES); ?>\'? Seluruh riwayat absensi & e-rapor akan ikut terhapus.');">ðŸ—‘ï¸</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon">ðŸ§’</div>
                <div class="empty-state__text">Belum ada data siswa untuk filter ini.</div>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>

