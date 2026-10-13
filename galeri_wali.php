<?php
/* =====================================================================
   PelitaHatiKu - galeri_wali.php
   Orang Tua: feed foto kegiatan kelas anak (grid 1 kolom di mobile)
   RBAC: hanya foto dari kelas anak yang terhubung ke akun ini
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
// DATA ANAK & KELAS
// -----------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT s.id_siswa, s.nama_siswa, s.id_kelas, k.nama_kelas
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
// PAGINASI (12 foto per halaman)
// -----------------------------------------------------------------
$per_halaman   = 12;
$halaman       = max(1, (int)($_GET['hal'] ?? 1));
$total_foto    = 0;
$total_halaman = 1;
$daftar_foto   = [];

if ($siswa_data && $siswa_data['id_kelas']) {
    $id_kelas = (int)$siswa_data['id_kelas'];

    $stmt2 = $conn->prepare("SELECT COUNT(*) AS jumlah FROM galeri_kegiatan WHERE id_kelas = ?");
    $stmt2->bind_param("i", $id_kelas);
    $stmt2->execute();
    $total_foto = (int)$stmt2->get_result()->fetch_assoc()['jumlah'];
    $stmt2->close();

    $total_halaman = max(1, (int)ceil($total_foto / $per_halaman));
    if ($halaman > $total_halaman) { $halaman = $total_halaman; }
    $offset = ($halaman - 1) * $per_halaman;

    $stmt3 = $conn->prepare("
        SELECT g.*, u.nama_lengkap AS pengunggah
        FROM galeri_kegiatan g
        JOIN users u ON u.id_user = g.id_pengunggah
        WHERE g.id_kelas = ?
        ORDER BY g.tanggal_kegiatan DESC, g.id_galeri DESC
        LIMIT ? OFFSET ?
    ");
    $stmt3->bind_param("iii", $id_kelas, $per_halaman, $offset);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) { $daftar_foto[] = $row; }
    $stmt3->close();
}

$conn->close();

$folder_galeri = 'uploads/galeri/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Galeri Kegiatan - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=arrow_back,arrow_forward,assignment,calendar_month,child_care,home,image,logout,menu,menu_book,person,photo_library,school" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Mobile-first: 1 kolom menumpuk ke bawah */
        .galeri-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        .galeri-card { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); overflow: hidden; }
        .galeri-card__img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; background: var(--color-primary-light); cursor: zoom-in; }
        .galeri-card__placeholder {
            width: 100%; aspect-ratio: 4 / 3; display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: var(--color-primary-light); color: var(--color-primary-dark); font-size: 12px; gap: 6px;
        }
        .galeri-card__placeholder .material-symbols-outlined { font-size: 40px; }
        .galeri-card__body { padding: 14px 16px 16px; }
        .galeri-card__title { font-size: 14.5px; font-weight: 700; }
        .galeri-card__desc { font-size: 12.5px; line-height: 1.55; margin-top: 6px; }
        .galeri-card__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 10px; display: flex; flex-wrap: wrap; gap: 4px 12px; }
        .galeri-card__meta span { display: inline-flex; align-items: center; gap: 4px; }
        .galeri-card__meta .material-symbols-outlined { font-size: 15px; }
        .pager { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 12.5px; }
        .pager a { display: inline-flex; align-items: center; gap: 4px; padding: 8px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--color-primary); color: var(--color-primary); font-weight: 600; }
        .pager .disabled { visibility: hidden; }
        .lightbox { position: fixed; inset: 0; background: rgba(15,23,42,0.88); z-index: 300; display: none; align-items: center; justify-content: center; padding: 16px; }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 100%; max-height: 100%; border-radius: var(--radius-sm); }
        /* Tablet ke atas: 2 kolom */
        @media (min-width: 640px) { .galeri-grid { grid-template-columns: repeat(2, 1fr); } }
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
            <div class="topbar__title">Galeri Kegiatan</div>
            <?php if ($siswa_data && $siswa_data['nama_kelas']): ?>
                <div class="topbar__subtitle">Kelas <?php echo htmlspecialchars($siswa_data['nama_kelas']); ?></div>
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
        <?php elseif (!$siswa_data['id_kelas']): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">school</span></div>
                <div class="empty-state__text">Ananda belum ditempatkan di kelas, sehingga galeri belum tersedia.</div>
            </div>
        <?php elseif (count($daftar_foto) === 0): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">photo_library</span></div>
                <div class="empty-state__text">Belum ada foto kegiatan untuk kelas ini. Foto akan muncul setelah guru mengunggahnya.</div>
            </div>
        <?php else: ?>

            <div class="galeri-grid">
                <?php foreach ($daftar_foto as $g):
                    $path_file = $folder_galeri . $g['nama_file'];
                    $ada_file  = is_file(__DIR__ . '/' . $path_file);
                ?>
                    <div class="galeri-card">
                        <?php if ($ada_file): ?>
                            <img class="galeri-card__img" src="<?php echo htmlspecialchars($path_file); ?>"
                                 alt="<?php echo htmlspecialchars($g['judul']); ?>" loading="lazy"
                                 onclick="openLightbox(this.src)">
                        <?php else: ?>
                            <div class="galeri-card__placeholder"><span class="material-symbols-outlined">image</span>Foto belum tersedia</div>
                        <?php endif; ?>
                        <div class="galeri-card__body">
                            <div class="galeri-card__title"><?php echo htmlspecialchars($g['judul']); ?></div>
                            <?php if (!empty($g['deskripsi'])): ?>
                                <div class="galeri-card__desc"><?php echo nl2br(htmlspecialchars($g['deskripsi'])); ?></div>
                            <?php endif; ?>
                            <div class="galeri-card__meta">
                                <span><span class="material-symbols-outlined">calendar_month</span><?php echo date('d M Y', strtotime($g['tanggal_kegiatan'])); ?></span>
                                <span><span class="material-symbols-outlined">person</span><?php echo htmlspecialchars($g['pengunggah']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_halaman > 1): ?>
                <div class="pager">
                    <a href="galeri_wali.php?hal=<?php echo $halaman - 1; ?>" class="<?php echo $halaman <= 1 ? 'disabled' : ''; ?>"><span class="material-symbols-outlined">arrow_back</span> Sebelumnya</a>
                    <span>Halaman <?php echo $halaman; ?> / <?php echo $total_halaman; ?></span>
                    <a href="galeri_wali.php?hal=<?php echo $halaman + 1; ?>" class="<?php echo $halaman >= $total_halaman ? 'disabled' : ''; ?>">Berikutnya <span class="material-symbols-outlined">arrow_forward</span></a>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <div class="lightbox" id="lightbox" onclick="this.classList.remove('open')">
        <img id="lightboxImg" src="" alt="Foto kegiatan">
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
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightbox').classList.add('open');
        }
    </script>

</body>
</html>
