<?php
/* =====================================================================
   PelitaHatiKu - posting_pengumuman.php
   Guru: Membuat pengumuman khusus untuk kelas yang diampu
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_guru = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

// -----------------------------------------------------------------
// PASTIKAN GURU MEMANG WALI KELAS
// -----------------------------------------------------------------
$stmt = $conn->prepare("SELECT id_kelas, nama_kelas FROM kelas WHERE id_guru = ? LIMIT 1");
$stmt->bind_param("i", $id_guru);
$stmt->execute();
$res_kelas = $stmt->get_result();
$kelas_data = $res_kelas->num_rows > 0 ? $res_kelas->fetch_assoc() : null;
$stmt->close();

if (!$kelas_data) {
    header("Location: dashboard_guru.php");
    exit;
}
$id_kelas = $kelas_data['id_kelas'];

// -----------------------------------------------------------------
// PROSES: POSTING PENGUMUMAN BARU
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'posting') {
    $judul = trim($_POST['judul'] ?? '');
    $isi   = trim($_POST['isi'] ?? '');
    $tanggal_posting = $_POST['tanggal_posting'] ?? date('Y-m-d');

    if (empty($judul) || empty($isi)) {
        $pesan_error = "Judul dan isi pengumuman wajib diisi.";
    } else {
        $stmt2 = $conn->prepare("
            INSERT INTO pengumuman (judul, isi, id_kelas, id_pembuat, tanggal_posting)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt2->bind_param("ssiis", $judul, $isi, $id_kelas, $id_guru, $tanggal_posting);
        if ($stmt2->execute()) {
            $pesan_sukses = "Pengumuman \"$judul\" berhasil diposting ke kelas " . htmlspecialchars($kelas_data['nama_kelas']) . ".";
        } else {
            $pesan_error = "Gagal memposting: " . $conn->error;
        }
        $stmt2->close();
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS PENGUMUMAN (hanya milik guru ini sendiri)
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt3 = $conn->prepare("DELETE FROM pengumuman WHERE id_pengumuman = ? AND id_pembuat = ? AND id_kelas = ?");
    $stmt3->bind_param("iii", $id_hapus, $id_guru, $id_kelas);
    if ($stmt3->execute() && $stmt3->affected_rows > 0) {
        $pesan_sukses = "Pengumuman berhasil dihapus.";
    } else {
        $pesan_error = "Pengumuman tidak ditemukan atau bukan milik Anda.";
    }
    $stmt3->close();
}

// -----------------------------------------------------------------
// DAFTAR PENGUMUMAN KELAS INI
// -----------------------------------------------------------------
$daftar_pengumuman = [];
$stmt4 = $conn->prepare("
    SELECT id_pengumuman, judul, isi, tanggal_posting
    FROM pengumuman
    WHERE id_kelas = ?
    ORDER BY tanggal_posting DESC, id_pengumuman DESC
");
$stmt4->bind_param("i", $id_kelas);
$stmt4->execute();
$res_peng = $stmt4->get_result();
while ($row = $res_peng->fetch_assoc()) { $daftar_pengumuman[] = $row; }
$stmt4->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Pengumuman Kelas - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=add_circle,assignment,calendar_month,campaign,delete,edit_note,home,notifications" />
    <link rel="stylesheet" href="icons.css">
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
        textarea.form-input { min-height: 100px; resize: vertical; line-height: 1.4; }
        .peng-item {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 10px;
        }
        .peng-item__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .peng-item__title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .peng-item__body { font-size: 12.5px; color: var(--color-text); line-height: 1.5; }
        .peng-item__date { font-size: 11px; color: var(--color-text-muted); margin-top: 8px; }
        .icon-btn {
            width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; border: 1px solid var(--color-border);
            background-color: var(--color-bg); cursor: pointer; text-decoration: none;
        }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_guru.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Pengumuman Kelas</div>
            <div class="topbar__subtitle"><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></div>
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
             FORM POSTING PENGUMUMAN BARU
        ============================================================= -->
        <details class="form-panel" open>
            <summary><span class="material-symbols-outlined">add_circle</span> Buat Pengumuman Baru</summary>
            <div class="form-panel-body">
                <form method="POST" action="posting_pengumuman.php">
                    <input type="hidden" name="aksi" value="posting">

                    <div class="form-group">
                        <label class="form-label">Judul</label>
                        <input type="text" name="judul" class="form-input" placeholder="contoh: Pengumpulan Foto Kegiatan" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Isi Pengumuman</label>
                        <textarea name="isi" class="form-input" required placeholder="Tuliskan detail pengumuman di sini..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Posting</label>
                        <input type="date" name="tanggal_posting" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">campaign</span> Posting Pengumuman</button>
                </form>
                <p style="font-size:11.5px; color:var(--color-text-muted); margin-top:10px;">
                    Pengumuman ini hanya akan tampil untuk orang tua di kelas <?php echo htmlspecialchars($kelas_data['nama_kelas']); ?>.
                </p>
            </div>
        </details>

        <!-- ============================================================
             DAFTAR PENGUMUMAN
        ============================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">assignment</span> Riwayat Pengumuman (<?php echo count($daftar_pengumuman); ?>)</div>
        </div>

        <?php if (count($daftar_pengumuman) > 0): ?>
            <?php foreach ($daftar_pengumuman as $p): ?>
                <div class="peng-item">
                    <div class="peng-item__top">
                        <div style="flex:1;">
                            <div class="peng-item__title"><?php echo htmlspecialchars($p['judul']); ?></div>
                            <div class="peng-item__body"><?php echo nl2br(htmlspecialchars($p['isi'])); ?></div>
                            <div class="peng-item__date"><span class="material-symbols-outlined">calendar_month</span> <?php echo date('d M Y', strtotime($p['tanggal_posting'])); ?></div>
                        </div>
                        <a href="posting_pengumuman.php?hapus=<?php echo (int)$p['id_pengumuman']; ?>" class="icon-btn" title="Hapus"
                           onclick="return confirm('Yakin ingin menghapus pengumuman \'<?php echo htmlspecialchars($p['judul'], ENT_QUOTES); ?>\'?');"><span class="material-symbols-outlined">delete</span></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">notifications</span></div>
                <div class="empty-state__text">Belum ada pengumuman yang Anda posting untuk kelas ini.</div>
            </div>
        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_guru.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">home</span>
            Beranda
        </a>
        <a href="input_absensi.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">calendar_month</span>
            Absensi
        </a>
        <a href="input_rapor.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">edit_note</span>
            E-Rapor
        </a>
        <a href="posting_pengumuman.php" class="bottom-nav__item active">
            <span class="material-symbols-outlined">campaign</span>
            Pengumuman
        </a>
    </div>

</body>
</html>
