<?php
/* =====================================================================
   PelitaHatiKu - input_hafalan_guru.php
   Guru: CRUD capaian hafalan siswa di kelas yang diampu
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_guru      = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

$kategori_valid = ['Surah Pendek', 'Doa Harian', 'Hadis'];
$capaian_valid  = ['Belum Lancar', 'Cukup Lancar', 'Lancar', 'Sangat Lancar'];

// -----------------------------------------------------------------
// KELAS YANG DIAMPU
// -----------------------------------------------------------------
$stmt = $conn->prepare("SELECT id_kelas, nama_kelas FROM kelas WHERE id_guru = ? LIMIT 1");
$stmt->bind_param("i", $id_guru);
$stmt->execute();
$res = $stmt->get_result();
$kelas_data = $res->num_rows > 0 ? $res->fetch_assoc() : null;
$stmt->close();

if (!$kelas_data) {
    header("Location: dashboard_guru.php");
    exit;
}
$id_kelas = (int)$kelas_data['id_kelas'];

// -----------------------------------------------------------------
// DAFTAR SISWA AKTIF DI KELAS (untuk dropdown & validasi kepemilikan)
// -----------------------------------------------------------------
$daftar_siswa = [];
$stmt = $conn->prepare("SELECT id_siswa, nama_siswa FROM siswa WHERE id_kelas = ? AND status_aktif = 1 ORDER BY nama_siswa ASC");
$stmt->bind_param("i", $id_kelas);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $daftar_siswa[] = $row; }
$stmt->close();
$id_siswa_boleh = array_map('intval', array_column($daftar_siswa, 'id_siswa'));

// -----------------------------------------------------------------
// PROSES: TAMBAH / EDIT
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['aksi'] ?? '', ['tambah', 'edit'], true)) {
    $aksi          = $_POST['aksi'];
    $id_hafalan    = (int)($_POST['id_hafalan'] ?? 0);
    $id_siswa      = (int)($_POST['id_siswa'] ?? 0);
    $kategori      = $_POST['kategori'] ?? '';
    $judul_hafalan = trim($_POST['judul_hafalan'] ?? '');
    $capaian       = $_POST['capaian'] ?? '';
    $catatan       = trim($_POST['catatan'] ?? '');
    $tanggal_setor = $_POST['tanggal_setor'] ?? '';

    if ($judul_hafalan === '' || $tanggal_setor === '') {
        $pesan_error = "Judul hafalan dan tanggal setor wajib diisi.";
    } elseif (!in_array($id_siswa, $id_siswa_boleh, true)) {
        $pesan_error = "Siswa tidak valid atau bukan bagian dari kelas Anda.";
    } elseif (!in_array($kategori, $kategori_valid, true) || !in_array($capaian, $capaian_valid, true)) {
        $pesan_error = "Kategori atau capaian tidak valid.";
    } elseif ($tanggal_setor > date('Y-m-d')) {
        $pesan_error = "Tanggal setor tidak boleh di masa depan.";
    } else {
        $catatan_val = $catatan !== '' ? $catatan : null;

        if ($aksi === 'tambah') {
            $stmt = $conn->prepare("
                INSERT INTO hafalan (id_siswa, id_guru, kategori, judul_hafalan, capaian, catatan, tanggal_setor)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisssss", $id_siswa, $id_guru, $kategori, $judul_hafalan, $capaian, $catatan_val, $tanggal_setor);
            $pesan_sukses = "Hafalan \"$judul_hafalan\" berhasil dicatat.";
        } else {
            // Pastikan data yang diedit memang milik siswa di kelas ini
            $stmt = $conn->prepare("
                UPDATE hafalan h JOIN siswa s ON s.id_siswa = h.id_siswa
                SET h.id_siswa = ?, h.id_guru = ?, h.kategori = ?, h.judul_hafalan = ?, h.capaian = ?, h.catatan = ?, h.tanggal_setor = ?
                WHERE h.id_hafalan = ? AND s.id_kelas = ?
            ");
            $stmt->bind_param("iisssssii", $id_siswa, $id_guru, $kategori, $judul_hafalan, $capaian, $catatan_val, $tanggal_setor, $id_hafalan, $id_kelas);
            $pesan_sukses = "Hafalan berhasil diperbarui.";
        }

        if (!$stmt->execute()) {
            $pesan_sukses = "";
            $pesan_error  = "Gagal menyimpan: " . $conn->error;
        }
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt = $conn->prepare("
        DELETE h FROM hafalan h JOIN siswa s ON s.id_siswa = h.id_siswa
        WHERE h.id_hafalan = ? AND s.id_kelas = ?
    ");
    $stmt->bind_param("ii", $id_hapus, $id_kelas);
    $stmt->execute();
    if ($stmt->affected_rows > 0) { $pesan_sukses = "Catatan hafalan berhasil dihapus."; }
    else { $pesan_error = "Data tidak ditemukan atau bukan bagian dari kelas Anda."; }
    $stmt->close();
}

// -----------------------------------------------------------------
// DATA UNTUK EDIT
// -----------------------------------------------------------------
$data_edit = null;
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $stmt = $conn->prepare("
        SELECT h.* FROM hafalan h JOIN siswa s ON s.id_siswa = h.id_siswa
        WHERE h.id_hafalan = ? AND s.id_kelas = ?
    ");
    $stmt->bind_param("ii", $id_edit, $id_kelas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) { $data_edit = $res->fetch_assoc(); }
    $stmt->close();
}

// -----------------------------------------------------------------
// DAFTAR HAFALAN (filter siswa opsional)
// -----------------------------------------------------------------
$filter_siswa = (int)($_GET['siswa'] ?? 0);
if (!in_array($filter_siswa, $id_siswa_boleh, true)) { $filter_siswa = 0; }

$sql = "
    SELECT h.*, s.nama_siswa
    FROM hafalan h JOIN siswa s ON s.id_siswa = h.id_siswa
    WHERE s.id_kelas = ?" . ($filter_siswa ? " AND h.id_siswa = ?" : "") . "
    ORDER BY h.tanggal_setor DESC, h.id_hafalan DESC
    LIMIT 100
";
$stmt = $conn->prepare($sql);
if ($filter_siswa) { $stmt->bind_param("ii", $id_kelas, $filter_siswa); }
else               { $stmt->bind_param("i", $id_kelas); }
$stmt->execute();
$res = $stmt->get_result();
$daftar_hafalan = [];
while ($row = $res->fetch_assoc()) { $daftar_hafalan[] = $row; }
$stmt->close();

$conn->close();

$kelas_lv = ['Belum Lancar' => 'lv-1', 'Cukup Lancar' => 'lv-2', 'Lancar' => 'lv-3', 'Sangat Lancar' => 'lv-4'];
$e = $data_edit; // alias singkat untuk form
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Input Hafalan - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=add_circle,calendar_month,campaign,chat_bubble,delete,edit,edit_note,home,menu_book,save" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        details.form-panel { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 16px; overflow: hidden; }
        details.form-panel summary { padding: 14px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; list-style: none; }
        details.form-panel summary::-webkit-details-marker { display: none; }
        details.form-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .form-panel-body { padding: 16px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .hafalan-item { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); padding: 14px 16px; margin-bottom: 10px; }
        .hafalan-item__top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
        .hafalan-item__title { font-size: 14px; font-weight: 700; }
        .hafalan-item__meta { font-size: 11.5px; color: var(--color-text-muted); margin-top: 3px; }
        .hafalan-item__note { font-size: 12.5px; margin-top: 8px; }
        .badge-capaian { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; display: inline-block; margin-top: 8px; }
        .lv-1 { background: #fee2e2; color: var(--color-danger); }
        .lv-2 { background: #fef3c7; color: #92400e; }
        .lv-3 { background: #dcfce7; color: var(--color-primary-dark); }
        .lv-4 { background: var(--color-primary); color: #fff; }
        .actions { display: flex; gap: 6px; flex-shrink: 0; }
        .icon-btn { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; border: 1px solid var(--color-border); background: var(--color-bg); text-decoration: none; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_guru.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Input Hafalan</div>
            <div class="topbar__subtitle"><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?><div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div><?php endif; ?>

        <!-- ============================================================
             FORM TAMBAH / EDIT
        ============================================================= -->
        <details class="form-panel" <?php echo $e ? 'open' : ''; ?>>
            <summary><?php echo $e ? '<span class="material-symbols-outlined">edit</span> Edit Catatan Hafalan' : '<span class="material-symbols-outlined">add_circle</span> Catat Hafalan Baru'; ?></summary>
            <div class="form-panel-body">
                <form method="POST" action="input_hafalan_guru.php">
                    <input type="hidden" name="aksi" value="<?php echo $e ? 'edit' : 'tambah'; ?>">
                    <?php if ($e): ?><input type="hidden" name="id_hafalan" value="<?php echo (int)$e['id_hafalan']; ?>"><?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Siswa</label>
                        <select name="id_siswa" class="form-input" required>
                            <option value="">Pilih siswa</option>
                            <?php foreach ($daftar_siswa as $s): ?>
                                <option value="<?php echo (int)$s['id_siswa']; ?>" <?php echo ($e && (int)$e['id_siswa'] === (int)$s['id_siswa']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['nama_siswa']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-input" required>
                                <?php foreach ($kategori_valid as $k): ?>
                                    <option value="<?php echo $k; ?>" <?php echo ($e && $e['kategori'] === $k) ? 'selected' : ''; ?>><?php echo $k; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Capaian</label>
                            <select name="capaian" class="form-input" required>
                                <?php foreach ($capaian_valid as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($e && $e['capaian'] === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Judul Hafalan</label>
                        <input type="text" name="judul_hafalan" class="form-input" maxlength="150" required
                               placeholder="contoh: QS. Al-Ikhlas / Doa Sebelum Makan"
                               value="<?php echo htmlspecialchars($e['judul_hafalan'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Setor</label>
                        <input type="date" name="tanggal_setor" class="form-input" max="<?php echo date('Y-m-d'); ?>" required
                               value="<?php echo htmlspecialchars($e['tanggal_setor'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan (opsional)</label>
                        <input type="text" name="catatan" class="form-input" maxlength="255"
                               value="<?php echo htmlspecialchars($e['catatan'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo $e ? 'Simpan Perubahan' : '<span class="material-symbols-outlined">save</span> Simpan Hafalan'; ?></button>
                    <?php if ($e): ?><a href="input_hafalan_guru.php" class="btn btn-outline" style="margin-top:8px;">Batal</a><?php endif; ?>
                </form>
            </div>
        </details>

        <!-- ============================================================
             FILTER & DAFTAR
        ============================================================= -->
        <form method="GET" action="input_hafalan_guru.php" style="margin-bottom:16px;">
            <select name="siswa" class="form-input" onchange="this.form.submit()">
                <option value="0">Semua siswa</option>
                <?php foreach ($daftar_siswa as $s): ?>
                    <option value="<?php echo (int)$s['id_siswa']; ?>" <?php echo $filter_siswa === (int)$s['id_siswa'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nama_siswa']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="card__header"><div class="card__title"><span class="material-symbols-outlined">menu_book</span> Riwayat Hafalan (<?php echo count($daftar_hafalan); ?>)</div></div>

        <?php if (count($daftar_hafalan) > 0): ?>
            <?php foreach ($daftar_hafalan as $h): ?>
                <div class="hafalan-item">
                    <div class="hafalan-item__top">
                        <div>
                            <div class="hafalan-item__title"><?php echo htmlspecialchars($h['judul_hafalan']); ?></div>
                            <div class="hafalan-item__meta">
                                <?php echo htmlspecialchars($h['nama_siswa']); ?> · <?php echo htmlspecialchars($h['kategori']); ?> ·
                                <?php echo date('d M Y', strtotime($h['tanggal_setor'])); ?>
                            </div>
                        </div>
                        <div class="actions">
                            <a href="input_hafalan_guru.php?edit=<?php echo (int)$h['id_hafalan']; ?>" class="icon-btn" title="Edit"><span class="material-symbols-outlined">edit</span></a>
                            <a href="input_hafalan_guru.php?hapus=<?php echo (int)$h['id_hafalan']; ?>" class="icon-btn" title="Hapus"
                               onclick="return confirm('Hapus catatan hafalan \'<?php echo htmlspecialchars($h['judul_hafalan'], ENT_QUOTES); ?>\'?');"><span class="material-symbols-outlined">delete</span></a>
                        </div>
                    </div>
                    <span class="badge-capaian <?php echo $kelas_lv[$h['capaian']]; ?>"><?php echo htmlspecialchars($h['capaian']); ?></span>
                    <?php if (!empty($h['catatan'])): ?>
                        <div class="hafalan-item__note"><span class="material-symbols-outlined">chat_bubble</span> <?php echo htmlspecialchars($h['catatan']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">menu_book</span></div>
                <div class="empty-state__text">Belum ada catatan hafalan. Tambahkan setoran pertama di atas.</div>
            </div>
        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_guru.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="input_absensi.php" class="bottom-nav__item"><span class="material-symbols-outlined">calendar_month</span>Absensi</a>
        <a href="input_rapor.php" class="bottom-nav__item"><span class="material-symbols-outlined">edit_note</span>E-Rapor</a>
        <a href="posting_pengumuman.php" class="bottom-nav__item"><span class="material-symbols-outlined">campaign</span>Pengumuman</a>
    </div>

</body>
</html>
