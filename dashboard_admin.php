<?php
/* =====================================================================
   PelitaHatiKu - dashboard_admin.php
   Dashboard Admin: statistik ringkas + akses cepat ke modul CRUD
   ===================================================================== */
session_start();

// -----------------------------------------------------------------
// PROTEKSI HALAMAN: hanya role 'admin' yang boleh mengakses
// -----------------------------------------------------------------
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';

$nama_admin = $_SESSION['nama_lengkap'];

$total_siswa      = 0;
$total_guru       = 0;
$total_kelas      = 0;
$total_orang_tua  = 0;
$hadir_hari_ini   = 0;
$akun_nonaktif    = 0;

$conn = get_db_connection(); // otomatis die() dengan pesan aman jika koneksi gagal

// ---------------------------------------------------------------------
// Ambil statistik ringkas untuk kartu dashboard
// ---------------------------------------------------------------------
$q1 = $conn->query("SELECT COUNT(*) AS jumlah FROM siswa WHERE status_aktif = 1");
if ($q1) { $total_siswa = (int)$q1->fetch_assoc()['jumlah']; }

$q2 = $conn->query("SELECT COUNT(*) AS jumlah FROM users WHERE role = 'guru' AND status_aktif = 1");
if ($q2) { $total_guru = (int)$q2->fetch_assoc()['jumlah']; }

$q3 = $conn->query("SELECT COUNT(*) AS jumlah FROM kelas");
if ($q3) { $total_kelas = (int)$q3->fetch_assoc()['jumlah']; }

$q4 = $conn->query("SELECT COUNT(*) AS jumlah FROM users WHERE role = 'orang_tua' AND status_aktif = 1");
if ($q4) { $total_orang_tua = (int)$q4->fetch_assoc()['jumlah']; }

$q5 = $conn->query("SELECT COUNT(*) AS jumlah FROM absensi WHERE tanggal = CURDATE() AND status = 'Hadir'");
if ($q5) { $hadir_hari_ini = (int)$q5->fetch_assoc()['jumlah']; }

$q6 = $conn->query("SELECT COUNT(*) AS jumlah FROM users WHERE status_aktif = 0");
if ($q6) { $akun_nonaktif = (int)$q6->fetch_assoc()['jumlah']; }

// ---------------------------------------------------------------------
// Ambil 5 pengumuman terbaru (untuk kartu monitoring cepat)
// ---------------------------------------------------------------------
$pengumuman_list = [];
$q7 = $conn->query("
    SELECT p.judul, p.tanggal_posting, u.nama_lengkap AS pembuat
    FROM pengumuman p
    JOIN users u ON u.id_user = p.id_pembuat
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
    <title>Dashboard Admin - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=assignment,calendar_month,campaign,child_care,diversity_4,event,group,home,how_to_reg,logout,monitoring,notifications,person,photo_library,school,settings,warning" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style> 

        
        

        /* Animasi Muncul Halus */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        
        /* --- Media Query untuk Layar Lebar (Desktop/Tablet) --- */
        @media (min-width: 768px) {
            .desktop-nav {
                display: flex; 
            }
            .bottom-nav {
                display: none !important; 
            }
            .container {
                padding-bottom: 30px; 
            }
        }  
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
        }
        .menu-tile__icon {
            font-size: 26px;
            margin-bottom: 8px;
        }
        .menu-tile__label {
            font-size: 12.5px;
            font-weight: 700;
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
    <div class="topbar" style="display: flex; justify-content: space-between; align-items: center;">
        
        <!-- Kiri: Judul & Sapaan -->
        <div>
            <div class="topbar__title">Halo, <?php echo htmlspecialchars(explode(' ', $nama_admin)[0]); ?></div>
            <div class="topbar__subtitle">Panel Admin PelitaHatiKu</div>
        </div>

        <!-- Tengah: Menu Navigasi (Hanya terlihat di Desktop) -->
        <div class="desktop-nav">
            <a href="dashboard_admin.php" class="desktop-nav__item active">
                <span class="material-symbols-outlined">home</span> Beranda
            </a>
            <a href="kelola_siswa.php" class="desktop-nav__item">
                <span class="material-symbols-outlined">child_care</span> Siswa
            </a>
            <a href="kelola_guru.php" class="desktop-nav__item">
                <span class="material-symbols-outlined">group</span> Guru
            </a>
            <a href="kelola_akun.php" class="desktop-nav__item">
                <span class="material-symbols-outlined">how_to_reg</span> Akun
            </a>
        </div>

        <!-- Kanan: Avatar & Tombol Logout -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="user-menu-container">
                <!-- Tombol Avatar -->
                <button class="topbar-avatar-btn" id="avatarButton" title="Menu Pengguna">
                    <div class="topbar-avatar-circle">
                        <!-- <?php echo strtoupper(substr($nama_admin, 0, 1)); ?> -->
                        <img src="aset/img/1ibu-guru.png" alt="">
                    </div>
                </button>

                <!-- Box Menu Dropdown yang tersembunyi -->
                <div class="user-dropdown" id="userDropdown">
                    <!-- Opsi 1: Profil -->
                    <!-- <a href="profil.php" class="dropdown-item">
                        <span class="material-symbols-outlined">person</span> Profil Saya
                    </a> -->
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Opsi 2: Keluar -->
                    <a href="logout.php" class="dropdown-item text-danger" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                        <span class="material-symbols-outlined">logout</span> Keluar
                    </a>
                </div>
            </div>
            <!-- <a href="logout.php" class="btn-logout-desktop" onclick="return confirm('Apakah Anda yakin ingin keluar?');" title="Keluar Sistem">
                <span class="material-symbols-outlined">logout</span> Keluar
            </a> -->
        </div>

    </div>

    <div class="container">

        <!-- ========================================================
             STATISTIK RINGKAS
        ========================================================= -->
        <div class="card__header" style="margin-top:4px;">
            <div class="card__title">
                <span class="material-symbols-outlined">
                    monitoring
                </span> 
                Ringkasan Sistem
            </div>
        </div>

        <div class="stat-grid-4">
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_siswa; ?></div>
                <div class="stat-box-lg__label">Total Siswa Aktif</div>
            </div>
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_guru; ?></div>
                <div class="stat-box-lg__label">Total Guru Aktif</div>
            </div>
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_kelas; ?></div>
                <div class="stat-box-lg__label">Total Kelas</div>
            </div>
            <div class="stat-box-lg">
                <div class="stat-box-lg__value"><?php echo $total_orang_tua; ?></div>
                <div class="stat-box-lg__label">Akun Orang Tua</div>
            </div>
        </div>

        <div class="card">
            <div class="card__header" style="margin-bottom:8px;">
                <div class="card__title"><span class="material-symbols-outlined">calendar_month</span> Presensi Hari Ini</div>
            </div>
            <div style="font-size:13px; color:var(--color-text-muted);">
                <b style="color:var(--color-primary); font-size:16px;"><?php echo $hadir_hari_ini; ?></b> siswa tercatat Hadir hari ini.
                <?php if ($akun_nonaktif > 0): ?>
                    <br><span style="color:#d97706;"><span class="material-symbols-outlined">warning</span> <?php echo $akun_nonaktif; ?> akun berstatus tidak aktif.</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================================
             MENU AKSES CEPAT (Modul CRUD)
        ========================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">settings</span> Kelola Data</div>
        </div>

        <div class="menu-grid" style="margin-bottom:20px;">
            <a href="kelola_guru.php" class="menu-tile">
                <div class="menu-tile__icon">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <div class="menu-tile__label">Data Guru</div>
            </a>
            <a href="kelola_siswa.php" class="menu-tile">
                <div class="menu-tile__icon">
                    <span class="material-symbols-outlined">child_care</span>
                </div>
                <div class="menu-tile__label">Data Siswa</div>
            </a>
            <a href="kelola_kelas.php" class="menu-tile">
                <div class="menu-tile__icon">
                    <span class="material-symbols-outlined">school</span>
                </div>
                <div class="menu-tile__label">Kelas &amp; Tahun Ajaran</div>
            </a>
            <a href="kelola_akun.php" class="menu-tile">
                <div class="menu-tile__icon">
                    <span class="material-symbols-outlined">how_to_reg</span>
                </div>
                <div class="menu-tile__label">Manajemen Akun</div>
            </a>
            <a href="input_galeri_guru.php" class="menu-tile">
                <div class="menu-tile__icon">
                    <span class="material-symbols-outlined">photo_library</span>
                </div>
                <div class="menu-tile__label">Galeri Kegiatan</div>
            </a>
            <a href="kelola_pengumuman.php" class="menu-tile">
                <div class="menu-tile__icon">
                    <span class="material-symbols-outlined">campaign</span>
                </div>
                <div class="menu-tile__label">Pengumuman</div>
            </a>
        </div>

        <!-- ========================================================
             PENGUMUMAN TERBARU (monitoring cepat)
        ========================================================= -->
        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">campaign</span> Pengumuman Terbaru</div>
            <a href="kelola_pengumuman.php" class="card__link">Kelola</a>
        </div>

        <?php if (count($pengumuman_list) > 0): ?>
            <div class="card">
                <?php foreach ($pengumuman_list as $p): ?>
                    <div class="list-row">
                        <div>
                            <?php echo htmlspecialchars($p['judul']); ?>
                            <div class="list-row__meta">oleh <?php echo htmlspecialchars($p['pembuat']); ?></div>
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
        <a href="dashboard_admin.php" class="bottom-nav__item active">
            <span class="material-symbols-outlined">home</span>
            Beranda
        </a>
        <a href="kelola_siswa.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">child_care</span>
            Siswa
        </a>
        <a href="kelola_guru.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">group</span>
            Guru
        </a>
        <a href="kelola_akun.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">person</span>
            Akun
        </a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const avatarBtn = document.getElementById("avatarButton");
            const dropdown = document.getElementById("userDropdown");

            // Munculkan/Sembunyikan dropdown saat avatar diklik
            avatarBtn.addEventListener("click", function(event) {
                dropdown.classList.toggle("show");
                event.stopPropagation(); // Mencegah klik diteruskan ke window
            });

            // Tutup dropdown otomatis jika user mengklik area lain di luar kotak menu
            window.addEventListener("click", function(event) {
                if (!event.target.matches('.topbar-avatar-btn') && !event.target.closest('.topbar-avatar-btn')) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                }
            });
        });
    </script>

</body>
</html>

