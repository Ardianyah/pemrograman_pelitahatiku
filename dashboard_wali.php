<?php
/* =====================================================================
   PelitaHatiKu - dashboard_wali.php
   Dashboard Orang Tua/Wali Murid
   ===================================================================== */
session_start();

// -----------------------------------------------------------------
// PROTEKSI HALAMAN: hanya role 'orang_tua' yang boleh mengakses
// -----------------------------------------------------------------
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'orang_tua') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';

$id_user_login = $_SESSION['id_user'];
$nama_wali      = $_SESSION['nama_lengkap'];

$siswa_data       = [];
$pengumuman_list  = [];
$absensi_bulan_ini = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];

$conn = get_db_connection(); // otomatis die() dengan pesan aman jika koneksi gagal

if ($conn) {

    // ---------------------------------------------------------
    // Ambil data anak (siswa) yang terhubung dengan akun wali ini
    // ---------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT s.id_siswa, s.nama_siswa, s.nis, s.foto_siswa, k.nama_kelas
        FROM orang_tua_siswa ots
        JOIN siswa s ON s.id_siswa = ots.id_siswa
        LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
        WHERE ots.id_user = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id_user_login);
    $stmt->execute();
    $res_siswa = $stmt->get_result();
    if ($res_siswa->num_rows > 0) {
        $siswa_data = $res_siswa->fetch_assoc();
    }
    $stmt->close();

    // ---------------------------------------------------------
    // Rekap absensi bulan berjalan (jika ada data siswa)
    // ---------------------------------------------------------
    if (!empty($siswa_data)) {
        $id_siswa = $siswa_data['id_siswa'];
        $stmt2 = $conn->prepare("
            SELECT status, COUNT(*) AS jumlah
            FROM absensi
            WHERE id_siswa = ? AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
            GROUP BY status
        ");
        $stmt2->bind_param("i", $id_siswa);
        $stmt2->execute();
        $res_absensi = $stmt2->get_result();
        while ($row = $res_absensi->fetch_assoc()) {
            $absensi_bulan_ini[$row['status']] = (int)$row['jumlah'];
        }
        $stmt2->close();
    }

    // ---------------------------------------------------------
    // Ambil pengumuman: global (id_kelas NULL) + pengumuman kelas siswa
    // ---------------------------------------------------------
    $id_kelas_siswa = !empty($siswa_data) ? $siswa_data['id_kelas'] ?? null : null;

    if (!empty($siswa_data)) {
        $stmt3 = $conn->prepare("
            SELECT p.judul, p.isi, p.tanggal_posting, u.nama_lengkap AS pembuat
            FROM pengumuman p
            JOIN users u ON u.id_user = p.id_pembuat
            LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
            WHERE p.id_kelas IS NULL OR k.id_kelas = (
                SELECT id_kelas FROM siswa WHERE id_siswa = ?
            )
            ORDER BY p.tanggal_posting DESC
            LIMIT 10
        ");
        $stmt3->bind_param("i", $siswa_data['id_siswa']);
        $stmt3->execute();
        $res_pengumuman = $stmt3->get_result();
        while ($row = $res_pengumuman->fetch_assoc()) {
            $pengumuman_list[] = $row;
        }
        $stmt3->close();
    }

    $conn->close();
}

$total_hadir_bulan_ini = $absensi_bulan_ini['Hadir'];
$total_izin_sakit      = $absensi_bulan_ini['Izin'] + $absensi_bulan_ini['Sakit'];
$total_alpa            = $absensi_bulan_ini['Alpa'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Dashboard Wali - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,calendar_month,campaign,home,how_to_reg,logout,menu,menu_book,monitoring,notifications,person,photo_library" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <!-- ============================================================
         TOPBAR + HAMBURGER MENU
    ============================================================= -->
    <div class="topbar">
        <div>
            <div class="topbar__title">Halo, <?php echo htmlspecialchars(explode(' ', $nama_wali)[0]); ?> </div>
            <div class="topbar__subtitle">Selamat datang di PelitaHatiKu</div>
        </div>
        <div class="hamburger-btn" onclick="toggleDrawer()"><span class="material-symbols-outlined">menu</span></div>
    </div>

    <!-- Drawer Menu -->
    <div class="drawer-overlay" id="drawerOverlay" onclick="toggleDrawer()"></div>
    <div class="drawer-menu" id="drawerMenu">
        <div style="font-weight:700; font-size:16px; margin-bottom:20px;">Menu</div>
        <div style="display:flex; flex-direction:column; gap:16px; font-size:14px;">
            <a href="dashboard_wali.php">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">home</span>
                Beranda
                </div>
            </a>
            <a href="absensi_anak.php">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">calendar_month</span>
                Presensi Anak
                </div>
            </a>
            <a href="rapor_anak.php">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">assignment</span>
                E-Rapor
                </div>
            </a>
            <a href="hafalan_wali.php">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">menu_book</span>
                Hafalan
                </div>
            </a>
            <a href="galeri_wali.php">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">photo_library</span>
                Galeri Kegiatan
                </div>
            </a>
            <a href="profil.php">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">person</span>
                Profil Saya
                </div>
            </a>
            <a href="login.php?logout=1" style="color:#dc2626;">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">logout</span>
                Keluar
                </div>
            </a>
        </div>
    </div>

    <div class="container">

        <!-- ========================================================
             SLIDER KARTU PENGUMUMAN
        ========================================================= -->
        <div class="card__header" style="margin-top: 4px;">
            <div class="card__title"><span class="material-symbols-outlined">campaign</span> Pengumuman Terbaru</div>
            <a href="pengumuman.php" class="card__link">Lihat Semua</a>
        </div>

        <?php if (count($pengumuman_list) > 0): ?>
            <div class="announcement-slider">
                <?php foreach ($pengumuman_list as $p): ?>
                    <div class="announcement-card">
                        <div>
                            <span class="announcement-card__badge">Pengumuman</span>
                            <div class="announcement-card__title"><?php echo htmlspecialchars($p['judul']); ?></div>
                            <div class="announcement-card__desc"><?php echo htmlspecialchars(mb_strimwidth($p['isi'], 0, 90, '...')); ?></div>
                        </div>
                        <div class="announcement-card__date">
                            <span class="material-symbols-outlined">calendar_month</span> <?php echo date('d M Y', strtotime($p['tanggal_posting'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="margin-bottom: 20px;">
                <div class="empty-state__icon">
                    <span class="material-symbols-outlined">notifications</span>
                </div>
                <div class="empty-state__text">Belum ada pengumuman untuk saat ini.</div>
            </div>
        <?php endif; ?>

        <!-- ========================================================
             KARTU RINGKASAN SISWA
        ========================================================= -->
        <?php if (!empty($siswa_data)): ?>
            <div class="student-summary-card">
                <img
                    class="student-summary-card__photo"
                    src="<?php echo !empty($siswa_data['foto_siswa']) ? htmlspecialchars($siswa_data['foto_siswa']) : 'assets/img/default-avatar.png'; ?>"
                    alt="Foto <?php echo htmlspecialchars($siswa_data['nama_siswa']); ?>"
                >
                <div>
                    <div class="student-summary-card__name"><?php echo htmlspecialchars($siswa_data['nama_siswa']); ?></div>
                    <div class="student-summary-card__meta">
                        NIS: <?php echo htmlspecialchars($siswa_data['nis']); ?> &middot;
                        Kelas <?php echo htmlspecialchars($siswa_data['nama_kelas'] ?? '-'); ?>
                    </div>
                </div>
            </div>

            <!-- Statistik Absensi Bulan Ini -->
            <div class="card__header">
                <div class="card__title"><span class="material-symbols-outlined">monitoring</span> Presensi Bulan Ini</div>
                <a href="absensi_anak.php" class="card__link">Detail</a>
            </div>

            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-box__value"><?php echo $total_hadir_bulan_ini; ?></div>
                    <div class="stat-box__label">Hadir</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box__value"><?php echo $total_izin_sakit; ?></div>
                    <div class="stat-box__label">Izin/Sakit</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box__value"><?php echo $total_alpa; ?></div>
                    <div class="stat-box__label">Alpa</div>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon">
                    <span class="material-symbols-outlined">how_to_reg</span>
                </div>
                <div class="empty-state__text">Belum ada data presensi untuk hari ini.</div>
            </div>
        <?php endif; ?>

        <!-- ========================================================
             MENU AKSES CEPAT
        ========================================================= -->
        <div class="card">
            <div class="card__title" style="margin-bottom:12px;">Akses Cepat</div>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px;">
                <a href="rapor_anak.php" class="btn btn-outline" style="text-decoration:none;">
                    <span class="material-symbols-outlined">assignment</span>
                E-Rapor</a>
                <a href="hafalan_wali.php" class="btn btn-outline" style="text-decoration:none;">
                    <span class="material-symbols-outlined">menu_book</span>
                    Hafalan
                </a>
                <a href="galeri_wali.php" class="btn btn-outline" style="text-decoration:none;">
                    <span class="material-symbols-outlined">photo_library</span>
                    Galeri
                </a>
                <a href="profil.php" class="btn btn-outline" style="text-decoration:none;">
                    <span class="material-symbols-outlined">person</span>
                    Profil
                </a>
            </div>
        </div>

    </div>

    <!-- ============================================================
         BOTTOM NAVIGATION
    ============================================================= -->
    <div class="bottom-nav">
        <a href="dashboard_wali.php" class="bottom-nav__item active">
            <span class="material-symbols-outlined">home</span>
            Beranda
        </a>
        <a href="absensi_anak.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">calendar_month</span>
            Presensi
        </a>
        <a href="rapor_anak.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">assignment</span>
            E-Rapor
        </a>
        <a href="profil.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">person</span>
            Profil
        </a>
    </div>

    <script>
        function toggleDrawer() {
            document.getElementById('drawerMenu').classList.toggle('open');
            document.getElementById('drawerOverlay').classList.toggle('open');
        }
    </script>

</body>
</html>

