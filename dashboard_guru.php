<?php
/* =====================================================================
   PelitaHatiKu - dashboard_guru.php
   Dashboard Guru: kelas yang diampu, status absensi hari ini, akses cepat
   ===================================================================== */
session_start();

// -----------------------------------------------------------------
// PROTEKSI HALAMAN: hanya role 'guru' yang boleh mengakses
// -----------------------------------------------------------------
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';

$id_guru    = $_SESSION['id_user'];
$nama_guru  = $_SESSION['nama_lengkap'];

$kelas_data       = [];
$total_siswa_kelas = 0;
$absensi_terisi    = 0;
$rapor_draft_count  = 0;
$pengumuman_list    = [];

$conn = get_db_connection(); // otomatis die() dengan pesan aman jika koneksi gagal

// ---------------------------------------------------------------------
// Ambil kelas yang diampu guru ini (wali kelas)
// ---------------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT k.id_kelas, k.nama_kelas, t.nama_tahun_ajaran
    FROM kelas k
    JOIN tahun_ajaran t ON t.id_tahun_ajaran = k.id_tahun_ajaran
    WHERE k.id_guru = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_guru);
$stmt->execute();
$res_kelas = $stmt->get_result();
if ($res_kelas->num_rows > 0) {
    $kelas_data = $res_kelas->fetch_assoc();
}
$stmt->close();

if (!empty($kelas_data)) {
    $id_kelas = $kelas_data['id_kelas'];

    // Jumlah siswa aktif di kelas ini
    $stmt2 = $conn->prepare("SELECT COUNT(*) AS jumlah FROM siswa WHERE id_kelas = ? AND status_aktif = 1");
    $stmt2->bind_param("i", $id_kelas);
    $stmt2->execute();
    $total_siswa_kelas = (int)$stmt2->get_result()->fetch_assoc()['jumlah'];
    $stmt2->close();

    // Berapa siswa yang absensinya SUDAH terisi hari ini
    $stmt3 = $conn->prepare("
        SELECT COUNT(*) AS jumlah
        FROM absensi a
        JOIN siswa s ON s.id_siswa = a.id_siswa
        WHERE s.id_kelas = ? AND a.tanggal = CURDATE()
    ");
    $stmt3->bind_param("i", $id_kelas);
    $stmt3->execute();
    $absensi_terisi = (int)$stmt3->get_result()->fetch_assoc()['jumlah'];
    $stmt3->close();

    // Jumlah e-rapor berstatus draft yang perlu diselesaikan guru
    $stmt4 = $conn->prepare("
        SELECT COUNT(*) AS jumlah
        FROM rapor r
        JOIN siswa s ON s.id_siswa = r.id_siswa
        WHERE s.id_kelas = ? AND r.status = 'draft'
    ");
    $stmt4->bind_param("i", $id_kelas);
    $stmt4->execute();
    $rapor_draft_count = (int)$stmt4->get_result()->fetch_assoc()['jumlah'];
    $stmt4->close();

    // Pengumuman yang pernah diposting untuk kelas ini
    $stmt5 = $conn->prepare("
        SELECT judul, tanggal_posting
        FROM pengumuman
        WHERE id_kelas = ?
        ORDER BY tanggal_posting DESC
        LIMIT 5
    ");
    $stmt5->bind_param("i", $id_kelas);
    $stmt5->execute();
    $res_peng = $stmt5->get_result();
    while ($row = $res_peng->fetch_assoc()) {
        $pengumuman_list[] = $row;
    }
    $stmt5->close();
}

$conn->close();

$absensi_sudah_lengkap = ($total_siswa_kelas > 0 && $absensi_terisi >= $total_siswa_kelas);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Dashboard Guru - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,bolt,calendar_month,campaign,check_circle,child_care,edit_note,home,hourglass_top,menu_book,notifications,photo_library,school" />
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
            background-color: var(--color-warning);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 20px;
            padding: 2px 7px;
        }
        .status-banner {
            border-radius: var(--radius-md);
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-banner.done {
            background-color: var(--color-primary-light);
            color: var(--color-primary-dark);
        }
        .status-banner.pending {
            background-color: #fef3c7;
            color: #92400e;
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
            <div class="topbar__title">Halo, <?php echo htmlspecialchars(explode(' ', $nama_guru)[0]); ?></div>
            <div class="topbar__subtitle">
                <?php echo !empty($kelas_data) ? 'Wali Kelas ' . htmlspecialchars($kelas_data['nama_kelas']) : 'Dashboard Guru'; ?>
            </div>
        </div>
        <div class="topbar__avatar"><?php echo strtoupper(substr($nama_guru, 0, 1)); ?></div>
    </div>

    <div class="container">

        <?php if (empty($kelas_data)): ?>

            <!-- Guru belum ditugaskan ke kelas manapun -->
            <div class="empty-state" style="margin-top:12px;">
                <div class="empty-state__icon"><span class="material-symbols-outlined">school</span></div>
                <div class="empty-state__text">Anda belum ditetapkan sebagai wali kelas. Silakan hubungi Admin sekolah.</div>
            </div>

        <?php else: ?>

            <!-- ====================================================
                 STATUS ABSENSI HARI INI
            ===================================================== -->
            <?php if ($absensi_sudah_lengkap): ?>
                <div class="status-banner done">
                    <span class="material-symbols-outlined">check_circle</span> Absensi hari ini sudah lengkap (<?php echo $absensi_terisi; ?>/<?php echo $total_siswa_kelas; ?> siswa).
                </div>
            <?php elseif ($absensi_terisi > 0): ?>
                <div class="status-banner pending">
                    <span class="material-symbols-outlined">hourglass_top</span> Absensi baru terisi <?php echo $absensi_terisi; ?> dari <?php echo $total_siswa_kelas; ?> siswa.
                </div>
            <?php else: ?>
                <div class="status-banner pending">
                    <span class="material-symbols-outlined">assignment</span> Belum ada data presensi untuk hari ini. Yuk mulai input!
                </div>
            <?php endif; ?>

            <!-- ====================================================
                 INFO KELAS
            ===================================================== -->
            <div class="card">
                <div class="card__title" style="margin-bottom:8px;"><span class="material-symbols-outlined">school</span> <?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></div>
                <div style="font-size:12.5px; color:var(--color-text-muted);">
                    Tahun Ajaran <?php echo htmlspecialchars($kelas_data['nama_tahun_ajaran']); ?> &middot;
                    <?php echo $total_siswa_kelas; ?> siswa aktif
                </div>
            </div>

            <!-- ====================================================
                 MENU AKSES CEPAT
            ===================================================== -->
            <div class="card__header">
                <div class="card__title"><span class="material-symbols-outlined">bolt</span> Aksi Cepat</div>
            </div>

            <div class="menu-grid" style="margin-bottom:20px;">
                <a href="input_absensi.php" class="menu-tile">
                    <div class="menu-tile__icon"><span class="material-symbols-outlined">calendar_month</span></div>
                    <div class="menu-tile__label">Input Absensi</div>
                </a>
                <a href="input_rapor.php" class="menu-tile">
                    <?php if ($rapor_draft_count > 0): ?>
                        <span class="menu-tile__badge"><?php echo $rapor_draft_count; ?></span>
                    <?php endif; ?>
                    <div class="menu-tile__icon"><span class="material-symbols-outlined">edit_note</span></div>
                    <div class="menu-tile__label">Input E-Rapor</div>
                </a>
                <a href="posting_pengumuman.php" class="menu-tile">
                    <div class="menu-tile__icon"><span class="material-symbols-outlined">campaign</span></div>
                    <div class="menu-tile__label">Buat Pengumuman</div>
                </a>
                <a href="input_hafalan_guru.php" class="menu-tile">
                    <div class="menu-tile__icon"><span class="material-symbols-outlined">menu_book</span></div>
                    <div class="menu-tile__label">Input Hafalan</div>
                </a>
                <a href="input_galeri_guru.php" class="menu-tile">
                    <div class="menu-tile__icon"><span class="material-symbols-outlined">photo_library</span></div>
                    <div class="menu-tile__label">Galeri Kegiatan</div>
                </a>
                <a href="daftar_siswa_kelas.php" class="menu-tile">
                    <div class="menu-tile__icon"><span class="material-symbols-outlined">child_care</span></div>
                    <div class="menu-tile__label">Daftar Siswa</div>
                </a>
            </div>

            <!-- ====================================================
                 PENGUMUMAN KELAS YANG PERNAH DIPOSTING
            ===================================================== -->
            <div class="card__header">
                <div class="card__title"><span class="material-symbols-outlined">campaign</span> Pengumuman Kelas Anda</div>
                <a href="posting_pengumuman.php" class="card__link">Buat Baru</a>
            </div>

            <?php if (count($pengumuman_list) > 0): ?>
                <div class="card">
                    <?php foreach ($pengumuman_list as $p): ?>
                        <div class="list-row">
                            <div><?php echo htmlspecialchars($p['judul']); ?></div>
                            <div class="list-row__meta"><?php echo date('d M', strtotime($p['tanggal_posting'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon"><span class="material-symbols-outlined">notifications</span></div>
                    <div class="empty-state__text">Belum ada pengumuman yang Anda posting untuk kelas ini.</div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- ============================================================
         BOTTOM NAVIGATION
    ============================================================= -->
    <div class="bottom-nav">
        <a href="dashboard_guru.php" class="bottom-nav__item active">
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

