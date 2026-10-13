<?php
/* =====================================================================
   PelitaHatiKu - monitoring_pengumuman.php
   Kepala Sekolah: memantau seluruh pengumuman (Admin & Guru), read-only
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kepala_sekolah') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

// -----------------------------------------------------------------
// FILTER: semua | sekolah (id_kelas NULL) | id kelas
// -----------------------------------------------------------------
$filter = $_GET['filter'] ?? 'semua';

$daftar_kelas = [];
$res_k = $conn->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
while ($row = $res_k->fetch_assoc()) { $daftar_kelas[] = $row; }

$sql = "
    SELECT p.id_pengumuman, p.judul, p.isi, p.tanggal_posting,
           u.nama_lengkap AS pembuat, u.role AS role_pembuat,
           k.nama_kelas
    FROM pengumuman p
    JOIN users u ON u.id_user = p.id_pembuat
    LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
";
$daftar = [];

if ($filter === 'sekolah') {
    $res = $conn->query($sql . " WHERE p.id_kelas IS NULL ORDER BY p.tanggal_posting DESC, p.id_pengumuman DESC");
} elseif (ctype_digit($filter)) {
    $id_kelas = (int)$filter;
    $stmt = $conn->prepare($sql . " WHERE p.id_kelas = ? ORDER BY p.tanggal_posting DESC, p.id_pengumuman DESC");
    $stmt->bind_param("i", $id_kelas);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $filter = 'semua';
    $res = $conn->query($sql . " ORDER BY p.tanggal_posting DESC, p.id_pengumuman DESC");
}
while ($row = $res->fetch_assoc()) { $daftar[] = $row; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Monitoring Pengumuman - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=calendar_month,campaign,check_circle,home,notifications,trending_up" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-chip-row { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px; }
        .filter-chip {
            flex-shrink: 0; padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background: var(--color-surface); border: 1.5px solid var(--color-border); color: var(--color-text-muted);
        }
        .filter-chip.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        .peng-item { background: var(--color-surface); border-radius: var(--radius-md); padding: 14px 16px; box-shadow: var(--shadow-soft); margin-bottom: 10px; }
        .peng-item__title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .peng-item__body { font-size: 12.5px; line-height: 1.55; }
        .peng-item__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px 10px; }
        .badge-target { font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; background: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-target.kelas { background: #e0e7ff; color: #4338ca; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_kepsek.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Monitoring Pengumuman</div>
        </div>
    </div>

    <div class="container">

        <div class="filter-chip-row">
            <a href="monitoring_pengumuman.php" class="filter-chip <?php echo $filter === 'semua' ? 'active' : ''; ?>">Semua</a>
            <a href="monitoring_pengumuman.php?filter=sekolah" class="filter-chip <?php echo $filter === 'sekolah' ? 'active' : ''; ?>">Seluruh Sekolah</a>
            <?php foreach ($daftar_kelas as $k): ?>
                <a href="monitoring_pengumuman.php?filter=<?php echo (int)$k['id_kelas']; ?>"
                   class="filter-chip <?php echo $filter === (string)$k['id_kelas'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($k['nama_kelas']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="card__header">
            <div class="card__title"><span class="material-symbols-outlined">campaign</span> <?php echo count($daftar); ?> Pengumuman</div>
        </div>

        <?php if (count($daftar) > 0): ?>
            <?php foreach ($daftar as $p): ?>
                <div class="peng-item">
                    <div class="peng-item__title"><?php echo htmlspecialchars($p['judul']); ?></div>
                    <div class="peng-item__body"><?php echo nl2br(htmlspecialchars($p['isi'])); ?></div>
                    <div class="peng-item__meta">
                        <span class="badge-target <?php echo $p['nama_kelas'] ? 'kelas' : ''; ?>">
                            <?php echo $p['nama_kelas'] ? htmlspecialchars($p['nama_kelas']) : 'Seluruh Sekolah'; ?>
                        </span>
                        <span>oleh <?php echo htmlspecialchars($p['pembuat']); ?> (<?php echo $p['role_pembuat'] === 'admin' ? 'Admin' : 'Guru'; ?>)</span>
                        <span><span class="material-symbols-outlined">calendar_month</span> <?php echo date('d M Y', strtotime($p['tanggal_posting'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">notifications</span></div>
                <div class="empty-state__text">Belum ada pengumuman untuk filter ini.</div>
            </div>
        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_kepsek.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="approval_rapor.php" class="bottom-nav__item"><span class="material-symbols-outlined">check_circle</span>E-Rapor</a>
        <a href="monitoring_absensi.php" class="bottom-nav__item"><span class="material-symbols-outlined">calendar_month</span>Absensi</a>
        <a href="laporan_akademik.php" class="bottom-nav__item"><span class="material-symbols-outlined">trending_up</span>Laporan</a>
    </div>

</body>
</html>
