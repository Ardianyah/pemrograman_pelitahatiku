<?php
/* =====================================================================
   PelitaHatiKu - monitoring_absensi.php
   Kepala Sekolah: rekap kehadiran per kelas (harian & bulanan), read-only
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kepala_sekolah') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

// -----------------------------------------------------------------
// TANGGAL TERPILIH (validasi format, default hari ini)
// -----------------------------------------------------------------
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || !strtotime($tanggal)) { $tanggal = date('Y-m-d'); }
if ($tanggal > date('Y-m-d')) { $tanggal = date('Y-m-d'); }
$bulan = substr($tanggal, 0, 7); // format YYYY-MM

// -----------------------------------------------------------------
// REKAP HARIAN PER KELAS
// -----------------------------------------------------------------
$rekap_harian = [];
$stmt = $conn->prepare("
    SELECT k.id_kelas, k.nama_kelas,
           COUNT(s.id_siswa) AS total_siswa,
           COALESCE(SUM(a.status = 'Hadir'), 0) AS hadir,
           COALESCE(SUM(a.status = 'Izin'), 0)  AS izin,
           COALESCE(SUM(a.status = 'Sakit'), 0) AS sakit,
           COALESCE(SUM(a.status = 'Alpa'), 0)  AS alpa
    FROM kelas k
    LEFT JOIN siswa s ON s.id_kelas = k.id_kelas AND s.status_aktif = 1
    LEFT JOIN absensi a ON a.id_siswa = s.id_siswa AND a.tanggal = ?
    GROUP BY k.id_kelas, k.nama_kelas
    ORDER BY k.nama_kelas ASC
");
$stmt->bind_param("s", $tanggal);
$stmt->execute();
$res = $stmt->get_result();
$total_semua = $hadir_semua = $izin_semua = $sakit_semua = $alpa_semua = 0;
while ($row = $res->fetch_assoc()) {
    $rekap_harian[] = $row;
    $total_semua += (int)$row['total_siswa'];
    $hadir_semua += (int)$row['hadir'];
    $izin_semua  += (int)$row['izin'];
    $sakit_semua += (int)$row['sakit'];
    $alpa_semua  += (int)$row['alpa'];
}
$stmt->close();
$belum_diisi_semua = $total_semua - ($hadir_semua + $izin_semua + $sakit_semua + $alpa_semua);

// -----------------------------------------------------------------
// DAFTAR SISWA TIDAK HADIR PADA TANGGAL TERPILIH
// -----------------------------------------------------------------
$tidak_hadir = [];
$stmt2 = $conn->prepare("
    SELECT s.nama_siswa, k.nama_kelas, a.status, a.keterangan
    FROM absensi a
    JOIN siswa s ON s.id_siswa = a.id_siswa
    LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
    WHERE a.tanggal = ? AND a.status <> 'Hadir'
    ORDER BY FIELD(a.status, 'Alpa', 'Sakit', 'Izin'), k.nama_kelas, s.nama_siswa
");
$stmt2->bind_param("s", $tanggal);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) { $tidak_hadir[] = $row; }
$stmt2->close();

// -----------------------------------------------------------------
// REKAP BULANAN PER KELAS (persentase hadir dari total catatan absensi)
// -----------------------------------------------------------------
$rekap_bulanan = [];
$stmt3 = $conn->prepare("
    SELECT k.nama_kelas,
           COUNT(a.id_absensi) AS total_catatan,
           COALESCE(SUM(a.status = 'Hadir'), 0) AS hadir
    FROM kelas k
    LEFT JOIN siswa s ON s.id_kelas = k.id_kelas AND s.status_aktif = 1
    LEFT JOIN absensi a ON a.id_siswa = s.id_siswa AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
    GROUP BY k.id_kelas, k.nama_kelas
    ORDER BY k.nama_kelas ASC
");
$stmt3->bind_param("s", $bulan);
$stmt3->execute();
$res3 = $stmt3->get_result();
while ($row = $res3->fetch_assoc()) { $rekap_bulanan[] = $row; }
$stmt3->close();

$conn->close();

$warna_status = ['Alpa' => 'badge-alpa', 'Sakit' => 'badge-sakit', 'Izin' => 'badge-izin'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Monitoring Absensi - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,calendar_month,celebration,check_circle,date_range,home,hourglass_top,school,trending_up,warning" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stat-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
        .stat-mini { background: var(--color-surface); border-radius: var(--radius-sm); padding: 12px 6px; text-align: center; box-shadow: var(--shadow-soft); }
        .stat-mini__value { font-size: 18px; font-weight: 800; }
        .stat-mini__label { font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px; }
        .kelas-row { padding: 12px 0; border-bottom: 1px solid var(--color-border); }
        .kelas-row:last-child { border-bottom: none; }
        .kelas-row__top { display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 700; margin-bottom: 6px; }
        .kelas-row__sub { font-size: 11.5px; color: var(--color-text-muted); margin-top: 6px; }
        .progress-track { width: 100%; height: 8px; background-color: var(--color-border); border-radius: 20px; overflow: hidden; }
        .progress-fill { height: 100%; background-color: var(--color-primary); border-radius: 20px; }
        .list-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; }
        .list-row:last-child { border-bottom: none; }
        .list-row__meta { font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px; }
        .badge-status { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; flex-shrink: 0; }
        .badge-alpa { background-color: #fee2e2; color: var(--color-danger); }
        .badge-sakit { background-color: #fef3c7; color: #92400e; }
        .badge-izin { background-color: #dbeafe; color: #1d4ed8; }
        .date-form { display: flex; gap: 8px; margin-bottom: 16px; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_kepsek.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Monitoring Absensi</div>
        </div>
    </div>

    <div class="container">

        <form method="GET" action="monitoring_absensi.php" class="date-form">
            <input type="date" name="tanggal" class="form-input" max="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo htmlspecialchars($tanggal); ?>" onchange="this.form.submit()">
        </form>

        <!-- RINGKASAN SELURUH SEKOLAH -->
        <div class="stat-grid-4">
            <div class="stat-mini"><div class="stat-mini__value" style="color:var(--color-primary);"><?php echo $hadir_semua; ?></div><div class="stat-mini__label">Hadir</div></div>
            <div class="stat-mini"><div class="stat-mini__value" style="color:#1d4ed8;"><?php echo $izin_semua; ?></div><div class="stat-mini__label">Izin</div></div>
            <div class="stat-mini"><div class="stat-mini__value" style="color:#92400e;"><?php echo $sakit_semua; ?></div><div class="stat-mini__label">Sakit</div></div>
            <div class="stat-mini"><div class="stat-mini__value" style="color:var(--color-danger);"><?php echo $alpa_semua; ?></div><div class="stat-mini__label">Alpa</div></div>
        </div>

        <?php if ($total_semua > 0 && $belum_diisi_semua === $total_semua): ?>
            <div class="empty-state" style="margin-bottom:16px;">
                <div class="empty-state__icon"><span class="material-symbols-outlined">assignment</span></div>
                <div class="empty-state__text">Belum ada data presensi untuk tanggal <?php echo date('d M Y', strtotime($tanggal)); ?>.</div>
            </div>
        <?php endif; ?>

        <!-- REKAP HARIAN PER KELAS -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">school</span> Rekap Harian per Kelas</div>
            <span style="font-size:11px; color:var(--color-text-muted);"><?php echo date('d M Y', strtotime($tanggal)); ?></span>
        </div>

        <?php if (count($rekap_harian) > 0): ?>
            <div class="card">
                <?php foreach ($rekap_harian as $k):
                    $total_k = (int)$k['total_siswa'];
                    $belum_k = $total_k - ((int)$k['hadir'] + (int)$k['izin'] + (int)$k['sakit'] + (int)$k['alpa']);
                    $persen_k = $total_k > 0 ? round(((int)$k['hadir'] / $total_k) * 100) : 0;
                ?>
                    <div class="kelas-row">
                        <div class="kelas-row__top">
                            <span><?php echo htmlspecialchars($k['nama_kelas']); ?></span>
                            <span style="color:var(--color-primary);"><?php echo $persen_k; ?>%</span>
                        </div>
                        <div class="progress-track"><div class="progress-fill" style="width: <?php echo $persen_k; ?>%;"></div></div>
                        <div class="kelas-row__sub">
                            H <?php echo (int)$k['hadir']; ?> · I <?php echo (int)$k['izin']; ?> ·
                            S <?php echo (int)$k['sakit']; ?> · A <?php echo (int)$k['alpa']; ?>
                            <?php echo $belum_k > 0 ? ' · <span class="material-symbols-outlined">hourglass_top</span> ' . $belum_k . ' belum diisi' : ''; ?>
                            (dari <?php echo $total_k; ?> siswa)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><div class="empty-state__icon"><span class="material-symbols-outlined">school</span></div><div class="empty-state__text">Belum ada data kelas.</div></div>
        <?php endif; ?>

        <!-- SISWA TIDAK HADIR -->
        <div class="card__header" style="margin-top:20px;">
            <div class="card__title"><span class="material-symbols-outlined">warning</span> Siswa Tidak Hadir (<?php echo count($tidak_hadir); ?>)</div>
        </div>

        <?php if (count($tidak_hadir) > 0): ?>
            <div class="card">
                <?php foreach ($tidak_hadir as $t): ?>
                    <div class="list-row">
                        <div>
                            <?php echo htmlspecialchars($t['nama_siswa']); ?>
                            <div class="list-row__meta">
                                <?php echo htmlspecialchars($t['nama_kelas'] ?? '-'); ?>
                                <?php echo $t['keterangan'] ? ' · ' . htmlspecialchars($t['keterangan']) : ''; ?>
                            </div>
                        </div>
                        <span class="badge-status <?php echo $warna_status[$t['status']]; ?>"><?php echo $t['status']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">celebration</span></div>
                <div class="empty-state__text">Tidak ada siswa yang tercatat izin/sakit/alpa pada tanggal ini.</div>
            </div>
        <?php endif; ?>

        <!-- REKAP BULANAN -->
        <div class="card__header" style="margin-top:20px;">
            <div class="card__title"><span class="material-symbols-outlined">date_range</span> Rekap Bulan <?php echo date('F Y', strtotime($bulan . '-01')); ?></div>
        </div>

        <div class="card">
            <?php foreach ($rekap_bulanan as $b):
                $persen_b = (int)$b['total_catatan'] > 0 ? round(((int)$b['hadir'] / (int)$b['total_catatan']) * 100) : 0;
            ?>
                <div class="kelas-row">
                    <div class="kelas-row__top">
                        <span><?php echo htmlspecialchars($b['nama_kelas']); ?></span>
                        <span style="color:var(--color-primary);"><?php echo (int)$b['total_catatan'] > 0 ? $persen_b . '%' : '-'; ?></span>
                    </div>
                    <div class="progress-track"><div class="progress-fill" style="width: <?php echo $persen_b; ?>%;"></div></div>
                    <div class="kelas-row__sub"><?php echo (int)$b['hadir']; ?> hadir dari <?php echo (int)$b['total_catatan']; ?> catatan presensi</div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_kepsek.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="approval_rapor.php" class="bottom-nav__item"><span class="material-symbols-outlined">check_circle</span>E-Rapor</a>
        <a href="monitoring_absensi.php" class="bottom-nav__item active"><span class="material-symbols-outlined">calendar_month</span>Absensi</a>
        <a href="laporan_akademik.php" class="bottom-nav__item"><span class="material-symbols-outlined">trending_up</span>Laporan</a>
    </div>

</body>
</html>
