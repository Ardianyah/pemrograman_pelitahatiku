<?php
/* =====================================================================
   PelitaHatiKu - kelola/kelola_pengumuman.php
   Admin: CRUD pengumuman untuk seluruh sekolah atau kelas tertentu
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_admin = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

// -----------------------------------------------------------------
// PROSES: TAMBAH PENGUMUMAN
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $judul  = trim($_POST['judul'] ?? '');
    $isi    = trim($_POST['isi'] ?? '');
    $target = $_POST['target'] ?? 'sekolah'; // 'sekolah' atau id_kelas
    $tanggal_posting = $_POST['tanggal_posting'] ?? date('Y-m-d');

    if (empty($judul) || empty($isi)) {
        $pesan_error = "Judul dan isi pengumuman wajib diisi.";
    } else {
        $id_kelas = ($target === 'sekolah') ? null : (int)$target;
        $stmt = $conn->prepare("INSERT INTO pengumuman (judul, isi, id_kelas, id_pembuat, tanggal_posting) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiis", $judul, $isi, $id_kelas, $id_admin, $tanggal_posting);
        if ($stmt->execute()) {
            $pesan_sukses = "Pengumuman \"$judul\" berhasil diposting.";
        } else {
            $pesan_error = "Gagal menyimpan: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: EDIT PENGUMUMAN
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id_pengumuman = (int)($_POST['id_pengumuman'] ?? 0);
    $judul  = trim($_POST['judul'] ?? '');
    $isi    = trim($_POST['isi'] ?? '');
    $target = $_POST['target'] ?? 'sekolah';
    $tanggal_posting = $_POST['tanggal_posting'] ?? date('Y-m-d');

    if (empty($judul) || empty($isi)) {
        $pesan_error = "Judul dan isi pengumuman wajib diisi.";
    } else {
        $id_kelas = ($target === 'sekolah') ? null : (int)$target;
        $stmt = $conn->prepare("UPDATE pengumuman SET judul = ?, isi = ?, id_kelas = ?, tanggal_posting = ? WHERE id_pengumuman = ?");
        $stmt->bind_param("ssisi", $judul, $isi, $id_kelas, $tanggal_posting, $id_pengumuman);
        if ($stmt->execute()) {
            $pesan_sukses = "Pengumuman berhasil diperbarui.";
        } else {
            $pesan_error = "Gagal memperbarui: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS PENGUMUMAN
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM pengumuman WHERE id_pengumuman = ?");
    $stmt->bind_param("i", $id_hapus);
    $stmt->execute();
    $stmt->close();
    $pesan_sukses = "Pengumuman berhasil dihapus.";
}

// -----------------------------------------------------------------
// DATA PENDUKUNG: daftar kelas
// -----------------------------------------------------------------
$daftar_kelas = [];
$res_k = $conn->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
while ($row = $res_k->fetch_assoc()) { $daftar_kelas[] = $row; }

// -----------------------------------------------------------------
// AMBIL DATA UNTUK EDIT
// -----------------------------------------------------------------
$data_edit = null;
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM pengumuman WHERE id_pengumuman = ?");
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) { $data_edit = $res->fetch_assoc(); }
    $stmt->close();
}

// -----------------------------------------------------------------
// DAFTAR SEMUA PENGUMUMAN
// -----------------------------------------------------------------
$daftar_pengumuman = [];
$res = $conn->query("
    SELECT p.*, u.nama_lengkap AS pembuat, u.role AS role_pembuat, k.nama_kelas
    FROM pengumuman p
    JOIN users u ON u.id_user = p.id_pembuat
    LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
    ORDER BY p.tanggal_posting DESC, p.id_pengumuman DESC
");
while ($row = $res->fetch_assoc()) { $daftar_pengumuman[] = $row; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Kelola Pengumuman - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=notifications,assignment,person,calendar_month,logout,home,how_to_reg,campaign,edit,delete" />
    <link rel="stylesheet" href="../style.css">
    <style>
        details.form-panel { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 16px; overflow: hidden; }
        details.form-panel summary { padding: 14px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; list-style: none; }
        details.form-panel summary::-webkit-details-marker { display: none; }
        details.form-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .form-panel-body { padding: 16px; }
        textarea.form-input { min-height: 100px; resize: vertical; line-height: 1.4; }
        .peng-item { background: var(--color-surface); border-radius: var(--radius-md); padding: 14px 16px; box-shadow: var(--shadow-soft); margin-bottom: 10px; }
        .peng-item__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .peng-item__title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .peng-item__body { font-size: 12.5px; line-height: 1.5; }
        .peng-item__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px 10px; }
        .badge-target { font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; background: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-target.kelas { background: #e0e7ff; color: #4338ca; }
        .data-item__actions { display: flex; gap: 6px; flex-shrink: 0; }
        .icon-btn { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; border: 1px solid var(--color-border); background: var(--color-bg); cursor: pointer; text-decoration: none; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="../dashboard_admin.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Kelola Pengumuman</div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?><div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div><?php endif; ?>

        <!-- FORM EDIT -->
        <?php if ($data_edit): ?>
            <details class="form-panel" open>
                <summary>
                    <span class="material-symbols-outlined">
                        edit
                    </span>
                    Edit Pengumuman</summary>
                <div class="form-panel-body">
                    <form method="POST" action="kelola_pengumuman.php">
                        <input type="hidden" name="aksi" value="edit">
                        <input type="hidden" name="id_pengumuman" value="<?php echo (int)$data_edit['id_pengumuman']; ?>">

                        <div class="form-group">
                            <label class="form-label">Judul</label>
                            <input type="text" name="judul" class="form-input" required value="<?php echo htmlspecialchars($data_edit['judul']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Isi Pengumuman</label>
                            <textarea name="isi" class="form-input" required><?php echo htmlspecialchars($data_edit['isi']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target</label>
                            <select name="target" class="form-input">
                                <option value="sekolah" <?php echo $data_edit['id_kelas'] === null ? 'selected' : ''; ?>>Seluruh Sekolah</option>
                                <?php foreach ($daftar_kelas as $k): ?>
                                    <option value="<?php echo (int)$k['id_kelas']; ?>" <?php echo ((int)$data_edit['id_kelas'] === (int)$k['id_kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($k['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Posting</label>
                            <input type="date" name="tanggal_posting" class="form-input" required value="<?php echo htmlspecialchars($data_edit['tanggal_posting']); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="kelola_pengumuman.php" class="btn btn-outline" style="margin-top:8px;">Batal</a>
                    </form>
                </div>
            </details>
        <?php endif; ?>

        <!-- FORM TAMBAH -->
        <details class="form-panel">
            <summary>âž• Buat Pengumuman Baru</summary>
            <div class="form-panel-body">
                <form method="POST" action="kelola_pengumuman.php">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="form-group">
                        <label class="form-label">Judul</label>
                        <input type="text" name="judul" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Isi Pengumuman</label>
                        <textarea name="isi" class="form-input" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target</label>
                        <select name="target" class="form-input">
                            <option value="sekolah">Seluruh Sekolah</option>
                            <?php foreach ($daftar_kelas as $k): ?>
                                <option value="<?php echo (int)$k['id_kelas']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Posting</label>
                        <input type="date" name="tanggal_posting" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">ðŸ“¢ Posting Pengumuman</button>
                </form>
            </div>
        </details>

        <div class="card__header"><div class="card__title">ðŸ“‹ Semua Pengumuman (<?php echo count($daftar_pengumuman); ?>)</div></div>

        <?php if (count($daftar_pengumuman) > 0): ?>
            <?php foreach ($daftar_pengumuman as $p): ?>
                <div class="peng-item">
                    <div class="peng-item__top">
                        <div style="flex:1;">
                            <div class="peng-item__title"><?php echo htmlspecialchars($p['judul']); ?></div>
                            <div class="peng-item__body"><?php echo nl2br(htmlspecialchars($p['isi'])); ?></div>
                            <div class="peng-item__meta">
                                <span class="badge-target <?php echo $p['nama_kelas'] ? 'kelas' : ''; ?>">
                                    <?php echo $p['nama_kelas'] ? htmlspecialchars($p['nama_kelas']) : 'Seluruh Sekolah'; ?>
                                </span>
                                <span>oleh <?php echo htmlspecialchars($p['pembuat']); ?></span>
                                <span>ðŸ—“ï¸ <?php echo date('d M Y', strtotime($p['tanggal_posting'])); ?></span>
                            </div>
                        </div>
                        <div class="data-item__actions">
                            <a href="kelola_pengumuman.php?edit=<?php echo (int)$p['id_pengumuman']; ?>" class="icon-btn" title="Edit">
                                <span class="material-symbols-outlined">
                                    edit
                                </span>
                            </a>
                            <a href="kelola_pengumuman.php?hapus=<?php echo (int)$p['id_pengumuman']; ?>" class="icon-btn" title="Hapus"
                               onclick="return confirm('Yakin ingin menghapus pengumuman \'<?php echo htmlspecialchars($p['judul'], ENT_QUOTES); ?>\'?');">
                                <span class="material-symbols-outlined">
                                    delete
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state"><div class="empty-state__icon">ðŸ“­</div><div class="empty-state__text">Belum ada pengumuman.</div></div>
        <?php endif; ?>

    </div>

</body>
</html>
