<?php
/* =====================================================================
   PelitaHatiKu - hafalan_wali.php
   Orang Tua: melihat riwayat & perkembangan hafalan anak (read-only)
   RBAC: data diambil lewat relasi orang_tua_siswa milik akun yang login
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
// DATA ANAK MILIK AKUN INI
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
$res = $stmt->get_result();
$siswa_data = $res->num_rows > 0 ? $res->fetch_assoc() : null;
$stmt->close();

// -----------------------------------------------------------------
// FILTER KATEGORI
// -----------------------------------------------------------------
$kategori_valid = ['Surah Pendek', 'Doa Harian', 'Hadis'];
$filter = $_GET['kategori'] ?? 'semua';
if ($filter !== 'semua' && !in_array($filter, $kategori_valid, true)) { $filter = 'semua'; }

$daftar_hafalan = [];
$ringkasan = ['Surah Pendek' => 0, 'Doa Harian' => 0, 'Hadis' => 0];
$total_lancar = 0;

if ($siswa_data) {
    $id_siswa = $siswa_data['id_siswa'];

    // Ringkasan jumlah hafalan per kategori + yang sudah Lancar / Sangat Lancar
    $stmt2 = $conn->prepare("
        SELECT kategori, COUNT(*) AS jumlah,
               SUM(capaian IN ('Lancar','Sangat Lancar')) AS lancar
        FROM hafalan WHERE id_siswa = ? GROUP BY kategori
    ");
    $stmt2->bind_param("i", $id_siswa);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $ringkasan[$row['kategori']] = (int)$row['jumlah'];
        $total_lancar += (int)$row['lancar'];
    }
    $stmt2->close();

    // Daftar hafalan
    if ($filter === 'semua') {
        $stmt3 = $conn->prepare("
            SELECT h.*, u.nama_lengkap AS nama_guru
            FROM hafalan h LEFT JOIN users u ON u.id_user = h.id_guru
            WHERE h.id_siswa = ?
            ORDER BY h.tanggal_setor DESC, h.id_hafalan DESC
        ");
        $stmt3->bind_param("i", $id_siswa);
    } else {
        $stmt3 = $conn->prepare("
            SELECT h.*, u.nama_lengkap AS nama_guru
            FROM hafalan h LEFT JOIN users u ON u.id_user = h.id_guru
            WHERE h.id_siswa = ? AND h.kategori = ?
            ORDER BY h.tanggal_setor DESC, h.id_hafalan DESC
        ");
        $stmt3->bind_param("is", $id_siswa, $filter);
    }
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) { $daftar_hafalan[] = $row; }
    $stmt3->close();
}

$conn->close();

$total_hafalan = array_sum($ringkasan);

// Level capaian -> persen bar & kelas warna
$level = [
    'Belum Lancar'  => [25,  'lv-1'],
    'Cukup Lancar'  => [50,  'lv-2'],
    'Lancar'        => [75,  'lv-3'],
    'Sangat Lancar' => [100, 'lv-4'],
];
// Nama ikon Google Material Symbols per kategori
$ikon_kategori = ['Surah Pendek' => 'menu_book', 'Doa Harian' => 'volunteer_activism', 'Hadis' => 'auto_stories'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Hafalan Anak - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,auto_stories,calendar_month,chat_bubble,child_care,home,logout,menu,menu_book,person,photo_library,volunteer_activism" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stat-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
        .stat-mini { background: var(--color-surface); border-radius: var(--radius-sm); padding: 12px 6px; text-align: center; box-shadow: var(--shadow-soft); }
        .stat-mini__value { font-size: 18px; font-weight: 800; color: var(--color-primary); }
        .stat-mini__label { font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px; }
        .summary-note { font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px; }
        .filter-chip-row { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px; }
        .filter-chip {
            flex-shrink: 0; padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background: var(--color-surface); border: 1.5px solid var(--color-border); color: var(--color-text-muted);
        }
        .filter-chip.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        .hafalan-card { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); padding: 14px 16px; margin-bottom: 10px; }
        .hafalan-card__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .hafalan-card__title { font-size: 14px; font-weight: 700; }
        .hafalan-card__meta { font-size: 11.5px; color: var(--color-text-muted); margin-top: 3px; }
        .hafalan-card__note { font-size: 12.5px; line-height: 1.5; margin-top: 10px; }
        .badge-capaian { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; flex-shrink: 0; }
        .lv-1 { background: #fee2e2; color: var(--color-danger); }
        .lv-2 { background: #fef3c7; color: #92400e; }
        .lv-3 { background: #dcfce7; color: var(--color-primary-dark); }
        .lv-4 { background: var(--color-primary); color: #fff; }
        .bar-track { width: 100%; height: 6px; background: var(--color-border); border-radius: 20px; overflow: hidden; margin-top: 10px; }
        .bar-fill { height: 100%; border-radius: 20px; background: var(--color-primary); }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <!-- ============================================================
         TOPBAR + HAMBURGER MENU
    ============================================================= -->
    <div class="topbar">
        <div>
            <div class="topbar__title">Hafalan Anak</div>
            <?php if ($siswa_data): ?>
                <div class="topbar__subtitle"><?php echo htmlspecialchars($siswa_data['nama_siswa']); ?> &middot; <?php echo htmlspecialchars($siswa_data['nama_kelas'] ?? '-'); ?></div>
            <?php endif; ?>
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
            <a href="logout.php" style="color:#dc2626;">
                <div class="menu-itemss">
                <span class="material-symbols-outlined">logout</span>
                Keluar
                </div>
            </a>
        </div>
    </div>

    <div class="container">

        <?php if (!$siswa_data): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div>
                <div class="empty-state__text">Akun Anda belum terhubung dengan data siswa. Silakan hubungi Admin sekolah.</div>
            </div>
        <?php else: ?>

            <div class="stat-grid-3">
                <?php foreach ($ringkasan as $kat => $jumlah): ?>
                    <div class="stat-mini">
                        <div class="stat-mini__value"><?php echo $jumlah; ?></div>
                        <div class="stat-mini__label"><span class="material-symbols-outlined"><?php echo $ikon_kategori[$kat]; ?></span> <?php echo $kat; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($total_hafalan > 0): ?>
                <div class="summary-note"><?php echo $total_lancar; ?> dari <?php echo $total_hafalan; ?> hafalan sudah berada di tingkat Lancar atau Sangat Lancar.</div>
            <?php endif; ?>

            <div class="filter-chip-row">
                <a href="hafalan_wali.php" class="filter-chip <?php echo $filter === 'semua' ? 'active' : ''; ?>">Semua</a>
                <?php foreach ($kategori_valid as $kat): ?>
                    <a href="hafalan_wali.php?kategori=<?php echo urlencode($kat); ?>" class="filter-chip <?php echo $filter === $kat ? 'active' : ''; ?>">
                        <?php echo $kat; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (count($daftar_hafalan) > 0): ?>
                <?php foreach ($daftar_hafalan as $h):
                    list($persen, $kelas_lv) = $level[$h['capaian']];
                ?>
                    <div class="hafalan-card">
                        <div class="hafalan-card__top">
                            <div>
                                <div class="hafalan-card__title">
                                    <span class="material-symbols-outlined"><?php echo $ikon_kategori[$h['kategori']]; ?></span>
                                    <?php echo htmlspecialchars($h['judul_hafalan']); ?>
                                </div>
                                <div class="hafalan-card__meta">
                                    <?php echo htmlspecialchars($h['kategori']); ?> &middot;
                                    <?php echo date('d M Y', strtotime($h['tanggal_setor'])); ?>
                                    <?php echo $h['nama_guru'] ? ' &middot; ' . htmlspecialchars($h['nama_guru']) : ''; ?>
                                </div>
                            </div>
                            <span class="badge-capaian <?php echo $kelas_lv; ?>"><?php echo htmlspecialchars($h['capaian']); ?></span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $persen; ?>%;"></div></div>
                        <?php if (!empty($h['catatan'])): ?>
                            <div class="hafalan-card__note"><span class="material-symbols-outlined">chat_bubble</span> <?php echo htmlspecialchars($h['catatan']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon"><span class="material-symbols-outlined">menu_book</span></div>
                    <div class="empty-state__text">
                        <?php echo $filter === 'semua'
                            ? 'Belum ada catatan hafalan untuk ananda. Guru akan menambahkannya setelah setoran hafalan.'
                            : 'Belum ada catatan hafalan pada kategori ' . htmlspecialchars($filter) . '.'; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- ============================================================
         BOTTOM NAVIGATION
    ============================================================= -->
    <div class="bottom-nav">
        <a href="dashboard_wali.php" class="bottom-nav__item">
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
