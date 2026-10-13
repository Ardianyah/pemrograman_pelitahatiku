<?php
/* =====================================================================
   PelitaHatiKu - rapor_anak.php
   Orang Tua: melihat & mengunduh E-Rapor anak (hanya yang sudah diterbitkan)
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'orang_tua') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_user_login = $_SESSION['id_user'];

// -----------------------------------------------------------------
// AMBIL DATA ANAK MILIK AKUN INI (RBAC: hanya anak sendiri)
// -----------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT s.id_siswa, s.nama_siswa, s.nis, k.nama_kelas
    FROM orang_tua_siswa ots
    JOIN siswa s ON s.id_siswa = ots.id_siswa
    LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
    WHERE ots.id_user = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_user_login);
$stmt->execute();
$res_siswa = $stmt->get_result();
$siswa_data = $res_siswa->num_rows > 0 ? $res_siswa->fetch_assoc() : null;
$stmt->close();

$daftar_rapor = [];
if ($siswa_data) {
    $stmt2 = $conn->prepare("
        SELECT r.*, t.nama_tahun_ajaran, t.semester
        FROM rapor r
        JOIN tahun_ajaran t ON t.id_tahun_ajaran = r.id_tahun_ajaran
        WHERE r.id_siswa = ?
        ORDER BY t.id_tahun_ajaran DESC
    ");
    $stmt2->bind_param("i", $siswa_data['id_siswa']);
    $stmt2->execute();
    $res_rapor = $stmt2->get_result();
    while ($row = $res_rapor->fetch_assoc()) { $daftar_rapor[] = $row; }
    $stmt2->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>E-Rapor Anak - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,calendar_month,child_care,home,person,print" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        details.rapor-panel { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 12px; overflow: hidden; }
        details.rapor-panel summary { padding: 14px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; }
        details.rapor-panel summary::-webkit-details-marker { display: none; }
        details.rapor-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .rapor-body { padding: 16px; }
        .rapor-section-title { font-size: 11.5px; font-weight: 700; color: var(--color-primary-dark); margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.3px; }
        .rapor-section-title:not(:first-child) { margin-top: 14px; }
        .rapor-text { font-size: 13px; line-height: 1.6; }
        .badge-status { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-terbit { background: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-proses { background: var(--color-border); color: var(--color-text-muted); }
        .back-link { font-size: 12.5px; }
        @media print {
            .topbar, .no-print { display: none !important; }
            body { padding-bottom: 0; }
            details.rapor-panel { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_wali.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Beranda</a>
            <div class="topbar__title">E-Rapor Anak</div>
            <?php if ($siswa_data): ?>
                <div class="topbar__subtitle"><?php echo htmlspecialchars($siswa_data['nama_siswa']); ?> · <?php echo htmlspecialchars($siswa_data['nama_kelas'] ?? '-'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">

        <?php if (!$siswa_data): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div>
                <div class="empty-state__text">Akun Anda belum terhubung dengan data siswa. Silakan hubungi Admin sekolah.</div>
            </div>
        <?php elseif (count($daftar_rapor) === 0): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">assignment</span></div>
                <div class="empty-state__text">E-Rapor semester ini sedang dalam proses penyusunan.</div>
            </div>
        <?php else: ?>

            <?php foreach ($daftar_rapor as $r):
                $sudah_terbit = $r['status'] === 'diterbitkan';
            ?>
                <details class="rapor-panel" <?php echo $sudah_terbit ? '' : ''; ?>>
                    <summary>
                        <span><?php echo htmlspecialchars($r['nama_tahun_ajaran'] . ' - ' . $r['semester']); ?></span>
                        <span class="badge-status <?php echo $sudah_terbit ? 'badge-terbit' : 'badge-proses'; ?>">
                            <?php echo $sudah_terbit ? 'Diterbitkan' : 'Dalam Proses'; ?>
                        </span>
                    </summary>
                    <div class="rapor-body">
                        <?php if ($sudah_terbit): ?>
                            <div class="rapor-section-title">Capaian Pembelajaran</div>
                            <div class="rapor-text"><?php echo nl2br(htmlspecialchars($r['capaian_pembelajaran'])); ?></div>

                            <div class="rapor-section-title">Narasi Perkembangan</div>
                            <div class="rapor-text"><?php echo nl2br(htmlspecialchars($r['narasi_perkembangan'])); ?></div>

                            <div class="rapor-section-title">Diterbitkan</div>
                            <div class="rapor-text"><?php echo date('d M Y', strtotime($r['tanggal_terbit'])); ?></div>

                            <button type="button" class="btn btn-primary no-print" style="margin-top:16px;" onclick="window.print()">
                                <span class="material-symbols-outlined">print</span> Cetak / Unduh sebagai PDF
                            </button>
                        <?php else: ?>
                            <div class="rapor-text" style="color:var(--color-text-muted);">
                                E-Rapor semester ini sedang dalam proses penyusunan.
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>

            <p style="font-size:11px; color:var(--color-text-muted); margin-top:8px;">
                Tips: tekan "Cetak / Unduh sebagai PDF", lalu pada dialog cetak browser pilih tujuan "Save as PDF".
            </p>

        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_wali.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="absensi_anak.php" class="bottom-nav__item"><span class="material-symbols-outlined">calendar_month</span>Presensi</a>
        <a href="rapor_anak.php" class="bottom-nav__item active"><span class="material-symbols-outlined">assignment</span>E-Rapor</a>
        <a href="profil.php" class="bottom-nav__item"><span class="material-symbols-outlined">person</span>Profil</a>
    </div>

</body>
</html>
