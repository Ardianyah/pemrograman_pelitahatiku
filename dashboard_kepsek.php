<?php
/* =====================================================================
   PelitaHatiKu - dashboard_kepsek.php
   Dashboard Kepala Sekolah: monitoring & approval
   ===================================================================== */
session_start();

// -----------------------------------------------------------------
// PROTEKSI HALAMAN: hanya role 'kepala_sekolah' yang boleh mengakses
// -----------------------------------------------------------------
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kepala_sekolah') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';

$nama_kepsek = $_SESSION['nama_lengkap'];

$total_siswa        = 0;
$total_guru          = 0;
$total_kelas          = 0;
$hadir_hari_ini       = 0;
$persen_kehadiran     = 0;
$rapor_menunggu       = 0;
$rekap_kelas          = [];
$pengumuman_list      = [];

$conn = get_db_connection(); // otomatis die() dengan pesan aman jika koneksi gagal

// ---------------------------------------------------------------------
// Statistik ringkas
// ---------------------------------------------------------------------
$q1 = $conn->query("SELECT COUNT(*) AS jumlah FROM siswa WHERE status_aktif = 1");
if ($q1) { $total_siswa = (int)$q1->fetch_assoc()['jumlah']; }

$q2 = $conn->query("SELECT COUNT(*) AS jumlah FROM users WHERE role = 'guru' AND status_aktif = 1");
if ($q2) { $total_guru = (int)$q2->fetch_assoc()['jumlah']; }

$q3 = $conn->query("SELECT COUNT(*) AS jumlah FROM kelas");
if ($q3) { $total_kelas = (int)$q3->fetch_assoc()['jumlah']; }

$q4 = $conn->query("SELECT COUNT(*) AS jumlah FROM absensi WHERE tanggal = CURDATE() AND status = 'Hadir'");
if ($q4) { $hadir_hari_ini = (int)$q4->fetch_assoc()['jumlah']; }

$persen_kehadiran = $total_siswa > 0 ? round(($hadir_hari_ini / $total_siswa) * 100) : 0;

// E-Rapor berstatus menunggu review kepala sekolah
$q5 = $conn->query("SELECT COUNT(*) AS jumlah FROM rapor WHERE status = 'menunggu_review'");
if ($q5) { $rapor_menunggu = (int)$q5->fetch_assoc()['jumlah']; }

// ---------------------------------------------------------------------
// Rekapitulasi kehadiran per kelas hari ini
// ---------------------------------------------------------------------
$q6 = $conn->query("
    SELECT
        k.nama_kelas,
        COUNT(s.id_siswa) AS total_siswa_kelas,
        SUM(CASE WHEN a.status = 'Hadir' AND a.tanggal = CURDATE() THEN 1 ELSE 0 END) AS hadir_kelas
    FROM kelas k
    LEFT JOIN siswa s ON s.id_kelas = k.id_kelas AND s.status_aktif = 1
    LEFT JOIN absensi a ON a.id_siswa = s.id_siswa AND a.tanggal = CURDATE()
    GROUP BY k.id_kelas, k.nama_kelas
    ORDER BY k.nama_kelas ASC
");
if ($q6) {
    while ($row = $q6->fetch_assoc()) {
        $rekap_kelas[] = $row;
    }
}

// ---------------------------------------------------------------------
// Pengumuman terbaru (monitoring lintas kelas)
// ---------------------------------------------------------------------
$q7 = $conn->query("
    SELECT p.judul, p.tanggal_posting, u.nama_lengkap AS pembuat, k.nama_kelas
    FROM pengumuman p
    JOIN users u ON u.id_user = p.id_pembuat
    LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
    ORDER BY p.tanggal_posting DESC
    LIMIT 5
");
if ($q7) {
    while ($row = $q7->fetch_assoc()) {
        $pengumuman_list[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Dashboard Kepala Sekolah - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=calendar_month,campaign,check_circle,folder,home,notifications,person,school,trending_up" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .menu-tile {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            padding: 16px 12px;
            text-align: center;
            box-shadow: var(--shadow-soft);
            text-decoration: none;
            color: var(--color-text);
            position: relative;
        }
        .menu-tile__icon { font-size: 26px; margin-bottom: 8px; }
        .menu-tile__label { font-size: 12.5px; font-weight: 700; }
        .menu-tile__badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: var(--color-danger);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 20px;
            padding: 2px 7px;
        }
        .stat-grid-4 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-box-lg {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            padding: 16px;
            box-shadow: var(--shadow-soft);
        }
        .stat-box-lg__value { font-size: 22px; font-weight: 800; color: var(--color-primary); }
        .stat-box-lg__label { font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px; }

        .kelas-row {
            padding: 12px 0;
            border-bottom: 1px solid var(--color-border);
        }
        .kelas-row:last-child { border-bottom: none; }
        .kelas-row__top {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .kelas-row__count { font-size: 12px; font-weight: 500; color: var(--color-text-muted); }
        .progress-track {
            width: 100%;
            height: 8px;
            background-color: var(--color-border);
            border-radius: 20px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: var(--color-primary);
            border-radius: 20px;
        }

        .list-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
        }
        .list-row:last-child { border-bottom: none; }
        .list-row__meta { font-size: 11px; color: var(--color-text-muted); }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <!-- ============================================================
         TOPBAR
    ============================================================= -->
    <div class="topbar">
        <div>
            <div class="topbar__title">Assalamu'alaikum, <?php echo htmlspecialchars(explode(' ', $nama_kepsek)[0]); ?></div>
            <div class="topbar__subtitle">Dashboard Monitoring Kepala Sekolah</div>
        </div>
        <div class="topbar__avatar"><?php echo strtoupper(substr($nama_kepsek, 0, 1)); ?></div>
    </div>

    <div class="container">

        <!-- ========================================================
             STATISTIK RINGKAS
        ========================================================= -->
        <div class="stat-grid-4" style="margin-top:4px;">
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_siswa; ?></div>
                <div class="stat-box-lg__label">Total Siswa Aktif</div>
            </div>
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_guru; ?></div>
                <div class="stat-box-lg__label">Total Guru Aktif</div>
            </div>
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $persen_kehadiran; ?>%</div>
                <div class="stat-box-lg__label">Kehadiran Hari Ini</div>
            </div>
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_kelas; ?></div>
                <div class="stat-box-lg__label">Total Kelas</div>
            </div>
        </div>

        <!-- ========================================================
             MENU AKSES CEPAT
        ========================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">folder</span> Menu Kepala Sekolah</div>
        </div>

        <div class="menu-grid" style="margin-bottom:20px;">
            <a href="approval_rapor.php" class="menu-tile">
                <?php if ($rapor_menunggu > 0): ?>
                    <span class="menu-tile__badge"><?php echo $rapor_menunggu; ?></span>
                <?php endif; ?>
                <div class="menu-tile__icon"><span class="material-symbols-outlined">check_circle</span></div>
                <div class="menu-tile__label">Approval E-Rapor</div>
            </a>
            <a href="laporan_akademik.php" class="menu-tile">
                <div class="menu-tile__icon"><span class="material-symbols-outlined">trending_up</span></div>
                <div class="menu-tile__label">Laporan Akademik</div>
            </a>
            <a href="monitoring_absensi.php" class="menu-tile">
                <div class="menu-tile__icon"><span class="material-symbols-outlined">calendar_month</span></div>
                <div class="menu-tile__label">Monitoring Absensi</div>
            </a>
            <a href="monitoring_pengumuman.php" class="menu-tile">
                <div class="menu-tile__icon"><span class="material-symbols-outlined">campaign</span></div>
                <div class="menu-tile__label">Pengumuman</div>
            </a>
        </div>

        <!-- ========================================================
             REKAP KEHADIRAN PER KELAS
        ========================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">person</span> Rekap Kehadiran per Kelas</div>
            <span style="font-size:11px; color:var(--color-text-muted);"><?php echo date('d M Y'); ?></span>
        </div>

        <?php if (count($rekap_kelas) > 0): ?>
            <div class="card">
                <?php foreach ($rekap_kelas as $k):
                    $total_k = (int)$k['total_siswa_kelas'];
                    $hadir_k = (int)$k['hadir_kelas'];
                    $persen_k = $total_k > 0 ? round(($hadir_k / $total_k) * 100) : 0;
                ?>
                    <div class="kelas-row">
                        <div class="kelas-row__top">
                            <span><?php echo htmlspecialchars($k['nama_kelas']); ?></span>
                            <span class="kelas-row__count"><?php echo $hadir_k; ?>/<?php echo $total_k; ?> hadir</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $persen_k; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="margin-bottom:20px;">
                <div class="empty-state__icon"><span class="material-symbols-outlined">school</span></div>
                <div class="empty-state__text">Belum ada data kelas yang tercatat.</div>
            </div>
        <?php endif; ?>

        <!-- ========================================================
             PENGUMUMAN TERBARU
        ========================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">campaign</span> Pengumuman Terbaru</div>
        </div>

        <?php if (count($pengumuman_list) > 0): ?>
            <div class="card">
                <?php foreach ($pengumuman_list as $p): ?>
                    <div class="list-row">
                        <div>
                            <?php echo htmlspecialchars($p['judul']); ?>
                            <div class="list-row__meta">
                                oleh <?php echo htmlspecialchars($p['pembuat']); ?>
                                <?php echo $p['nama_kelas'] ? '· ' . htmlspecialchars($p['nama_kelas']) : '· Seluruh Sekolah'; ?>
                            </div>
                        </div>
                        <div class="list-row__meta"><?php echo date('d M', strtotime($p['tanggal_posting'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">notifications</span></div>
                <div class="empty-state__text">Belum ada pengumuman yang diposting.</div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ============================================================
         BOTTOM NAVIGATION
    ============================================================= -->
    <div class="bottom-nav">
        <a href="dashboard_kepsek.php" class="bottom-nav__item active">
            <span class="material-symbols-outlined">home</span>
            Beranda
        </a>
        <a href="approval_rapor.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">check_circle</span>
            E-Rapor
        </a>
        <a href="monitoring_absensi.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">calendar_month</span>
            Absensi
        </a>
        <a href="laporan_akademik.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">trending_up</span>
            Laporan
        </a>
    </div>

</body>
</html>
