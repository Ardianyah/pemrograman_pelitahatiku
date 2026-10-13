<?php
/* =====================================================================
   PelitaHatiKu - absensi_anak.php
   Orang Tua: melihat riwayat presensi anak (RBAC: hanya anak sendiri)
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
// AMBIL DATA ANAK MILIK AKUN INI
// -----------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT s.id_siswa, s.nama_siswa, k.nama_kelas
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

// -----------------------------------------------------------------
// BULAN TERPILIH (default bulan berjalan)
// -----------------------------------------------------------------
$bulan_pilih = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $bulan_pilih)) { $bulan_pilih = date('Y-m'); }

$rekap = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];
$daftar_absensi = [];

if ($siswa_data) {
    $id_siswa = $siswa_data['id_siswa'];

    $stmt2 = $conn->prepare("
        SELECT status, COUNT(*) AS jumlah
        FROM absensi
        WHERE id_siswa = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ?
        GROUP BY status
    ");
    $stmt2->bind_param("is", $id_siswa, $bulan_pilih);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) { $rekap[$row['status']] = (int)$row['jumlah']; }
    $stmt2->close();

    $stmt3 = $conn->prepare("
        SELECT tanggal, status, keterangan
        FROM absensi
        WHERE id_siswa = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ?
        ORDER BY tanggal DESC
    ");
    $stmt3->bind_param("is", $id_siswa, $bulan_pilih);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) { $daftar_absensi[] = $row; }
    $stmt3->close();
}

$conn->close();

$total_tercatat = array_sum($rekap);
$persen_hadir = $total_tercatat > 0 ? round(($rekap['Hadir'] / $total_tercatat) * 100) : 0;

$warna_status = ['Hadir' => 'badge-hadir', 'Izin' => 'badge-izin', 'Sakit' => 'badge-sakit', 'Alpa' => 'badge-alpa'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Presensi Anak - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,calendar_month,child_care,home,person" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stat-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
        .stat-mini { background: var(--color-surface); border-radius: var(--radius-sm); padding: 12px 6px; text-align: center; box-shadow: var(--shadow-soft); }
        .stat-mini__value { font-size: 18px; font-weight: 800; }
        .stat-mini__label { font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px; }
        .progress-track { width: 100%; height: 8px; background: var(--color-border); border-radius: 20px; overflow: hidden; margin-bottom: 20px; }
        .progress-fill { height: 100%; background: var(--color-primary); border-radius: 20px; }
        .list-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; }
        .list-row:last-child { border-bottom: none; }
        .list-row__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 2px; }
        .badge-status { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; flex-shrink: 0; }
        .badge-hadir { background: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-izin { background: #dbeafe; color: #1d4ed8; }
        .badge-sakit { background: #fef3c7; color: #92400e; }
        .badge-alpa { background: #fee2e2; color: var(--color-danger); }
        .month-form { margin-bottom: 16px; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_wali.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Beranda</a>
            <div class="topbar__title">Presensi Anak</div>
            <?php if ($siswa_data): ?>
                <div class="topbar__subtitle"><?php echo htmlspecialchars($siswa_data['nama_siswa']); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">

        <?php if (!$siswa_data): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div>
                <div class="empty-state__text">Akun Anda belum terhubung dengan data siswa. Silakan hubungi Admin sekolah.</div>
            </div>
        <?php else: ?>

            <form method="GET" action="absensi_anak.php" class="month-form">
                <input type="month" name="bulan" class="form-input" max="<?php echo date('Y-m'); ?>"
                       value="<?php echo htmlspecialchars($bulan_pilih); ?>" onchange="this.form.submit()">
            </form>

            <?php if ($total_tercatat === 0): ?>
                <div class="empty-state">
                    <div class="empty-state__icon"><span class="material-symbols-outlined">assignment</span></div>
                    <div class="empty-state__text">Belum ada data presensi untuk bulan ini.</div>
                </div>
            <?php else: ?>

                <div class="stat-grid-4">
                    <div class="stat-mini"><div class="stat-mini__value" style="color:var(--color-primary);"><?php echo $rekap['Hadir']; ?></div><div class="stat-mini__label">Hadir</div></div>
                    <div class="stat-mini"><div class="stat-mini__value" style="color:#1d4ed8;"><?php echo $rekap['Izin']; ?></div><div class="stat-mini__label">Izin</div></div>
                    <div class="stat-mini"><div class="stat-mini__value" style="color:#92400e;"><?php echo $rekap['Sakit']; ?></div><div class="stat-mini__label">Sakit</div></div>
                    <div class="stat-mini"><div class="stat-mini__value" style="color:var(--color-danger);"><?php echo $rekap['Alpa']; ?></div><div class="stat-mini__label">Alpa</div></div>
                </div>

                <div style="font-size:12px; color:var(--color-text-muted); margin-bottom:6px;">
                    Tingkat kehadiran: <b style="color:var(--color-primary);"><?php echo $persen_hadir; ?>%</b>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:<?php echo $persen_hadir; ?>%;"></div></div>

                <div class="card__header"><div class="card__title"><span class="material-symbols-outlined">assignment</span> Riwayat Harian</div></div>
                <div class="card">
                    <?php foreach ($daftar_absensi as $a): ?>
                        <div class="list-row">
                            <div>
                                <?php echo date('d M Y (l)', strtotime($a['tanggal'])); ?>
                                <?php if ($a['keterangan']): ?>
                                    <div class="list-row__meta"><?php echo htmlspecialchars($a['keterangan']); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="badge-status <?php echo $warna_status[$a['status']]; ?>"><?php echo $a['status']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_wali.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="absensi_anak.php" class="bottom-nav__item active"><span class="material-symbols-outlined">calendar_month</span>Presensi</a>
        <a href="rapor_anak.php" class="bottom-nav__item"><span class="material-symbols-outlined">assignment</span>E-Rapor</a>
        <a href="profil.php" class="bottom-nav__item"><span class="material-symbols-outlined">person</span>Profil</a>
    </div>

</body>
</html>
