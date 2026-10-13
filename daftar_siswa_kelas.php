<?php
/* =====================================================================
   PelitaHatiKu - daftar_siswa_kelas.php
   Guru: Melihat daftar siswa di kelas yang diampu (read-only)
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_guru = $_SESSION['id_user'];

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
// DAFTAR SISWA + rekap kehadiran bulan berjalan + nama orang tua
// -----------------------------------------------------------------
$daftar_siswa = [];
$stmt2 = $conn->prepare("
    SELECT
        s.id_siswa, s.nis, s.nama_siswa, s.jenis_kelamin, s.tanggal_lahir, s.foto_siswa,
        (SELECT COUNT(*) FROM absensi a WHERE a.id_siswa = s.id_siswa
            AND a.status = 'Hadir' AND MONTH(a.tanggal) = MONTH(CURDATE()) AND YEAR(a.tanggal) = YEAR(CURDATE())) AS hadir_bulan_ini,
        (SELECT u.nama_lengkap FROM orang_tua_siswa ots
            JOIN users u ON u.id_user = ots.id_user
            WHERE ots.id_siswa = s.id_siswa LIMIT 1) AS nama_orang_tua
    FROM siswa s
    WHERE s.id_kelas = ? AND s.status_aktif = 1
    ORDER BY s.nama_siswa ASC
");
$stmt2->bind_param("i", $id_kelas);
$stmt2->execute();
$res_siswa = $stmt2->get_result();
while ($row = $res_siswa->fetch_assoc()) { $daftar_siswa[] = $row; }
$stmt2->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Daftar Siswa - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=calendar_month,campaign,child_care,edit_note,home,person" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .siswa-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 10px;
        }
        .siswa-item__photo {
            width: 46px; height: 46px;
            border-radius: 50%;
            background-color: var(--color-primary-light);
            border: 2px solid var(--color-primary);
            object-fit: cover;
            flex-shrink: 0;
        }
        .siswa-item__name { font-size: 13.5px; font-weight: 700; }
        .siswa-item__meta { font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px; }
        .siswa-item__badge {
            margin-left: auto;
            font-size: 11px;
            font-weight: 700;
            color: var(--color-primary-dark);
            background-color: var(--color-primary-light);
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
            flex-shrink: 0;
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
            <div class="topbar__title">Daftar Siswa</div>
            <div class="topbar__subtitle"><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></div>
        </div>
    </div>

    <div class="container">

        <div class="card__header" style="margin-top:4px;">
            <div class="card__title"><span class="material-symbols-outlined">child_care</span> <?php echo count($daftar_siswa); ?> Siswa Aktif</div>
        </div>

        <?php if (count($daftar_siswa) > 0): ?>
            <?php foreach ($daftar_siswa as $s):
                $umur = $s['tanggal_lahir'] ? floor((strtotime('now') - strtotime($s['tanggal_lahir'])) / (365.25 * 24 * 3600)) : null;
            ?>
                <div class="siswa-item">
                    <img class="siswa-item__photo"
                         src="<?php echo !empty($s['foto_siswa']) ? htmlspecialchars($s['foto_siswa']) : 'assets/img/default-avatar.png'; ?>"
                         alt="Foto <?php echo htmlspecialchars($s['nama_siswa']); ?>">
                    <div>
                        <div class="siswa-item__name"><?php echo htmlspecialchars($s['nama_siswa']); ?></div>
                        <div class="siswa-item__meta">
                            NIS <?php echo htmlspecialchars($s['nis']); ?> ·
                            <?php echo $s['jenis_kelamin'] === 'L' ? 'L' : 'P'; ?>
                            <?php echo $umur !== null ? ' · ' . $umur . ' th' : ''; ?>
                        </div>
                        <div class="siswa-item__meta">
                            <span class="material-symbols-outlined">person</span> <?php echo htmlspecialchars($s['nama_orang_tua'] ?? 'Belum ada data orang tua'); ?>
                        </div>
                    </div>
                    <div class="siswa-item__badge">
                        <?php echo (int)$s['hadir_bulan_ini']; ?>x hadir
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div>
                <div class="empty-state__text">Belum ada siswa aktif yang terdaftar di kelas ini.</div>
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
        <a href="posting_pengumuman.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">campaign</span>
            Pengumuman
        </a>
    </div>

</body>
</html>
