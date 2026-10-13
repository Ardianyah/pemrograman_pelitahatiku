<?php
/* =====================================================================
   PelitaHatiKu - pengumuman.php
   Orang Tua: melihat seluruh pengumuman (Sekolah + kelas anaknya)
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
// AMBIL DATA ANAK MILIK AKUN INI (RBAC: hanya kelas anak sendiri)
// -----------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT s.id_siswa, s.id_kelas, k.nama_kelas
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

$id_kelas_anak = $siswa_data['id_kelas'] ?? null;

// -----------------------------------------------------------------
// FILTER TAB: semua | sekolah | kelas
// -----------------------------------------------------------------
$filter = $_GET['filter'] ?? 'semua';
if (!in_array($filter, ['semua', 'sekolah', 'kelas'], true)) { $filter = 'semua'; }

$daftar_pengumuman = [];

if ($siswa_data) {
    $sql = "
        SELECT p.id_pengumuman, p.judul, p.isi, p.tanggal_posting,
               u.nama_lengkap AS pembuat, k.nama_kelas
        FROM pengumuman p
        JOIN users u ON u.id_user = p.id_pembuat
        LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
        WHERE p.id_kelas IS NULL
    ";
    $params = [];
    $types  = "";

    if ($id_kelas_anak !== null) {
        $sql .= " OR p.id_kelas = ?";
        $params[] = $id_kelas_anak;
        $types   .= "i";
    }

    if ($filter === 'sekolah') {
        $sql = "
            SELECT p.id_pengumuman, p.judul, p.isi, p.tanggal_posting,
                   u.nama_lengkap AS pembuat, k.nama_kelas
            FROM pengumuman p
            JOIN users u ON u.id_user = p.id_pembuat
            LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
            WHERE p.id_kelas IS NULL
        ";
        $params = []; $types = "";
    } elseif ($filter === 'kelas' && $id_kelas_anak !== null) {
        $sql = "
            SELECT p.id_pengumuman, p.judul, p.isi, p.tanggal_posting,
                   u.nama_lengkap AS pembuat, k.nama_kelas
            FROM pengumuman p
            JOIN users u ON u.id_user = p.id_pembuat
            LEFT JOIN kelas k ON k.id_kelas = p.id_kelas
            WHERE p.id_kelas = ?
        ";
        $params = [$id_kelas_anak]; $types = "i";
    } elseif ($filter === 'kelas' && $id_kelas_anak === null) {
        $sql = null; // anak belum punya kelas, tidak ada pengumuman kelas
    }

    if ($sql) {
        $sql .= " ORDER BY p.tanggal_posting DESC, p.id_pengumuman DESC";
        if (!empty($params)) {
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param($types, ...$params);
            $stmt2->execute();
            $res = $stmt2->get_result();
        } else {
            $res = $conn->query($sql);
        }
        while ($row = $res->fetch_assoc()) { $daftar_pengumuman[] = $row; }
        if (isset($stmt2)) { $stmt2->close(); }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Pengumuman - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-chip-row { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px; }
        .filter-chip {
            flex-shrink: 0; padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background: var(--color-surface); border: 1.5px solid var(--color-border); color: var(--color-text-muted);
        }
        .filter-chip.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        .peng-item { background: var(--color-surface); border-radius: var(--radius-md); padding: 16px; box-shadow: var(--shadow-soft); margin-bottom: 12px; }
        .peng-item__title { font-size: 15px; font-weight: 700; margin-bottom: 6px; }
        .peng-item__body { font-size: 13px; line-height: 1.6; color: var(--color-text); }
        .peng-item__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; }
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
            <a href="dashboard_wali.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Beranda</a>
            <div class="topbar__title">Pengumuman</div>
        </div>
    </div>

    <div class="container">

        <?php if (!$siswa_data): ?>
            <div class="empty-state">
                <div class="empty-state__icon">ðŸ‘¶</div>
                <div class="empty-state__text">Akun Anda belum terhubung dengan data siswa. Silakan hubungi Admin sekolah.</div>
            </div>
        <?php else: ?>

            <div class="filter-chip-row">
                <a href="pengumuman.php?filter=semua" class="filter-chip <?php echo $filter === 'semua' ? 'active' : ''; ?>">Semua</a>
                <a href="pengumuman.php?filter=sekolah" class="filter-chip <?php echo $filter === 'sekolah' ? 'active' : ''; ?>">Seluruh Sekolah</a>
                <?php if ($id_kelas_anak !== null): ?>
                    <a href="pengumuman.php?filter=kelas" class="filter-chip <?php echo $filter === 'kelas' ? 'active' : ''; ?>">
                        Kelas <?php echo htmlspecialchars($siswa_data['nama_kelas'] ?? ''); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (count($daftar_pengumuman) > 0): ?>
                <?php foreach ($daftar_pengumuman as $p): ?>
                    <div class="peng-item">
                        <div class="peng-item__title"><?php echo htmlspecialchars($p['judul']); ?></div>
                        <div class="peng-item__body"><?php echo nl2br(htmlspecialchars($p['isi'])); ?></div>
                        <div class="peng-item__meta">
                            <span class="badge-target <?php echo $p['nama_kelas'] ? 'kelas' : ''; ?>">
                                <?php echo $p['nama_kelas'] ? htmlspecialchars($p['nama_kelas']) : 'Seluruh Sekolah'; ?>
                            </span>
                            <span>oleh <?php echo htmlspecialchars($p['pembuat']); ?></span>
                            <span>ðŸ—“ï¸ <?php echo date('d M Y', strtotime($p['tanggal_posting'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon">ðŸ“­</div>
                    <div class="empty-state__text">Belum ada pengumuman untuk filter ini.</div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_wali.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ </span>Beranda</a>
        <a href="absensi_anak.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ—“ï¸</span>Presensi</a>
        <a href="rapor_anak.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ“„</span>E-Rapor</a>
        <a href="profil.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ‘¤</span>Profil</a>
    </div>

</body>
</html>
