<?php
/* =====================================================================
   PelitaHatiKu - laporan_akademik.php
   Kepala Sekolah: laporan perkembangan siswa secara agregat
   (progres E-Rapor per kelas + kehadiran & capaian per siswa)
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kepala_sekolah') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

// -----------------------------------------------------------------
// PILIH TAHUN AJARAN (default: yang aktif)
// -----------------------------------------------------------------
$daftar_ta = [];
$res_ta = $conn->query("SELECT * FROM tahun_ajaran ORDER BY id_tahun_ajaran DESC");
while ($row = $res_ta->fetch_assoc()) { $daftar_ta[] = $row; }

$id_ta = isset($_GET['ta']) ? (int)$_GET['ta'] : 0;
if ($id_ta === 0) {
    foreach ($daftar_ta as $ta) { if ($ta['status_aktif']) { $id_ta = (int)$ta['id_tahun_ajaran']; break; } }
    if ($id_ta === 0 && count($daftar_ta) > 0) { $id_ta = (int)$daftar_ta[0]['id_tahun_ajaran']; }
}

// -----------------------------------------------------------------
// PROGRES E-RAPOR PER KELAS
// -----------------------------------------------------------------
$progres_kelas = [];
$total_siswa = $total_terbit = $total_disetujui = $total_review = $total_draft = 0;

$stmt = $conn->prepare("
    SELECT k.id_kelas, k.nama_kelas,
           COUNT(DISTINCT s.id_siswa) AS total_siswa,
           COALESCE(SUM(r.status = 'diterbitkan'), 0)     AS terbit,
           COALESCE(SUM(r.status = 'disetujui'), 0)       AS disetujui,
           COALESCE(SUM(r.status = 'menunggu_review'), 0) AS review,
           COALESCE(SUM(r.status = 'draft'), 0)           AS draft
    FROM kelas k
    LEFT JOIN siswa s ON s.id_kelas = k.id_kelas AND s.status_aktif = 1
    LEFT JOIN rapor r ON r.id_siswa = s.id_siswa AND r.id_tahun_ajaran = ?
    GROUP BY k.id_kelas, k.nama_kelas
    ORDER BY k.nama_kelas ASC
");
$stmt->bind_param("i", $id_ta);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $progres_kelas[] = $row;
    $total_siswa     += (int)$row['total_siswa'];
    $total_terbit    += (int)$row['terbit'];
    $total_disetujui += (int)$row['disetujui'];
    $total_review    += (int)$row['review'];
    $total_draft     += (int)$row['draft'];
}
$stmt->close();
$total_belum = $total_siswa - ($total_terbit + $total_disetujui + $total_review + $total_draft);
$persen_terbit = $total_siswa > 0 ? round(($total_terbit / $total_siswa) * 100) : 0;

// -----------------------------------------------------------------
// DETAIL PER SISWA (dikelompokkan per kelas di PHP)
// -----------------------------------------------------------------
$siswa_per_kelas = [];
$stmt2 = $conn->prepare("
    SELECT s.id_siswa, s.nama_siswa, k.nama_kelas,
           r.status AS status_rapor, r.capaian_pembelajaran, r.narasi_perkembangan,
           (SELECT COUNT(*) FROM absensi a WHERE a.id_siswa = s.id_siswa) AS total_absensi,
           (SELECT COUNT(*) FROM absensi a WHERE a.id_siswa = s.id_siswa AND a.status = 'Hadir') AS total_hadir
    FROM siswa s
    JOIN kelas k ON k.id_kelas = s.id_kelas
    LEFT JOIN rapor r ON r.id_siswa = s.id_siswa AND r.id_tahun_ajaran = ?
    WHERE s.status_aktif = 1
    ORDER BY k.nama_kelas ASC, s.nama_siswa ASC
");
$stmt2->bind_param("i", $id_ta);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) { $siswa_per_kelas[$row['nama_kelas']][] = $row; }
$stmt2->close();

$conn->close();

$label_status = [
    'draft' => ['Draft', 'badge-abu'],
    'menunggu_review' => ['Menunggu Review', 'badge-kuning'],
    'disetujui' => ['Disetujui', 'badge-hijau'],
    'diterbitkan' => ['Diterbitkan', 'badge-hijau'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Laporan Akademik - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=calendar_month,check_circle,child_care,event,home,school,trending_up" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stat-grid-4 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px; }
        .stat-box-lg { background: var(--color-surface); border-radius: var(--radius-md); padding: 16px; box-shadow: var(--shadow-soft); }
        .stat-box-lg__value { font-size: 22px; font-weight: 800; color: var(--color-primary); }
        .stat-box-lg__label { font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px; }
        .kelas-row { padding: 12px 0; border-bottom: 1px solid var(--color-border); }
        .kelas-row:last-child { border-bottom: none; }
        .kelas-row__top { display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 700; margin-bottom: 6px; }
        .kelas-row__sub { font-size: 11.5px; color: var(--color-text-muted); margin-top: 6px; }
        .progress-track { width: 100%; height: 8px; background: var(--color-border); border-radius: 20px; overflow: hidden; display: flex; }
        .seg-terbit { background: var(--color-primary); }
        .seg-setuju { background: #86efac; }
        .seg-review { background: #fbbf24; }
        .seg-draft  { background: #cbd5e1; }
        details.siswa-detail { border-bottom: 1px solid var(--color-border); }
        details.siswa-detail:last-child { border-bottom: none; }
        details.siswa-detail summary { padding: 11px 0; cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 13px; }
        details.siswa-detail summary::-webkit-details-marker { display: none; }
        .siswa-meta { font-size: 11px; color: var(--color-text-muted); margin-top: 2px; }
        .detail-body { padding: 0 0 12px; font-size: 12.5px; line-height: 1.55; }
        .detail-body b { color: var(--color-primary-dark); font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge-status { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; flex-shrink: 0; }
        .badge-hijau { background: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-kuning { background: #fef3c7; color: #92400e; }
        .badge-abu { background: var(--color-border); color: var(--color-text-muted); }
        .badge-none { background: transparent; border: 1px dashed var(--color-text-muted); color: var(--color-text-muted); }
        .legend { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: var(--color-text-muted); margin-bottom: 12px; }
        .legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_kepsek.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Laporan Akademik</div>
        </div>
    </div>

    <div class="container">

        <?php if (count($daftar_ta) === 0): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">event</span></div>
                <div class="empty-state__text">Belum ada data tahun ajaran.</div>
            </div>
        <?php else: ?>

            <form method="GET" action="laporan_akademik.php" style="margin-bottom:16px;">
                <select name="ta" class="form-input" onchange="this.form.submit()">
                    <?php foreach ($daftar_ta as $ta): ?>
                        <option value="<?php echo (int)$ta['id_tahun_ajaran']; ?>" <?php echo (int)$ta['id_tahun_ajaran'] === $id_ta ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['nama_tahun_ajaran'] . ' - ' . $ta['semester']); ?><?php echo $ta['status_aktif'] ? ' (Aktif)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="stat-grid-4">
                <div class="stat-box-lg"><div class="stat-box-lg__value"><?php echo $persen_terbit; ?>%</div><div class="stat-box-lg__label">E-Rapor Diterbitkan</div></div>
                <div class="stat-box-lg"><div class="stat-box-lg__value"><?php echo $total_siswa; ?></div><div class="stat-box-lg__label">Total Siswa Aktif</div></div>
                <div class="stat-box-lg"><div class="stat-box-lg__value"><?php echo $total_review; ?></div><div class="stat-box-lg__label">Menunggu Review</div></div>
                <div class="stat-box-lg"><div class="stat-box-lg__value"><?php echo $total_belum; ?></div><div class="stat-box-lg__label">Belum Dibuat Guru</div></div>
            </div>

            <div class="card__header"><div class="card__title"><span class="material-symbols-outlined">trending_up</span> Progres E-Rapor per Kelas</div></div>
            <div class="legend">
                <span><i class="seg-terbit"></i>Diterbitkan</span>
                <span><i class="seg-setuju"></i>Disetujui</span>
                <span><i class="seg-review"></i>Review</span>
                <span><i class="seg-draft"></i>Draft</span>
            </div>

            <?php if (count($progres_kelas) > 0): ?>
                <div class="card">
                    <?php foreach ($progres_kelas as $k):
                        $t = (int)$k['total_siswa'];
                        $w = function ($n) use ($t) { return $t > 0 ? round(($n / $t) * 100, 1) : 0; };
                    ?>
                        <div class="kelas-row">
                            <div class="kelas-row__top">
                                <span><?php echo htmlspecialchars($k['nama_kelas']); ?></span>
                                <span class="kelas-row__sub" style="margin:0;"><?php echo (int)$k['terbit']; ?>/<?php echo $t; ?> terbit</span>
                            </div>
                            <div class="progress-track">
                                <div class="seg-terbit" style="width:<?php echo $w((int)$k['terbit']); ?>%"></div>
                                <div class="seg-setuju" style="width:<?php echo $w((int)$k['disetujui']); ?>%"></div>
                                <div class="seg-review" style="width:<?php echo $w((int)$k['review']); ?>%"></div>
                                <div class="seg-draft"  style="width:<?php echo $w((int)$k['draft']); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><div class="empty-state__icon"><span class="material-symbols-outlined">school</span></div><div class="empty-state__text">Belum ada data kelas.</div></div>
            <?php endif; ?>

            <div class="card__header" style="margin-top:20px;"><div class="card__title"><span class="material-symbols-outlined">child_care</span> Perkembangan per Siswa</div></div>

            <?php if (count($siswa_per_kelas) === 0): ?>
                <div class="empty-state"><div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div><div class="empty-state__text">Belum ada siswa aktif yang tercatat di kelas.</div></div>
            <?php endif; ?>

            <?php foreach ($siswa_per_kelas as $nama_kelas => $siswa_list): ?>
                <div class="card">
                    <div class="card__title" style="margin-bottom:6px;"><?php echo htmlspecialchars($nama_kelas); ?></div>
                    <?php foreach ($siswa_list as $s):
                        $persen_hadir = (int)$s['total_absensi'] > 0 ? round(((int)$s['total_hadir'] / (int)$s['total_absensi']) * 100) : null;
                    ?>
                        <details class="siswa-detail">
                            <summary>
                                <div>
                                    <?php echo htmlspecialchars($s['nama_siswa']); ?>
                                    <div class="siswa-meta">Kehadiran: <?php echo $persen_hadir !== null ? $persen_hadir . '%' : 'belum ada data'; ?></div>
                                </div>
                                <?php if ($s['status_rapor']): list($lbl, $cls) = $label_status[$s['status_rapor']]; ?>
                                    <span class="badge-status <?php echo $cls; ?>"><?php echo $lbl; ?></span>
                                <?php else: ?>
                                    <span class="badge-status badge-none">Belum dibuat</span>
                                <?php endif; ?>
                            </summary>
                            <div class="detail-body">
                                <?php if ($s['status_rapor']): ?>
                                    <b>Capaian Pembelajaran</b><br><?php echo nl2br(htmlspecialchars($s['capaian_pembelajaran'] ?? '-')); ?><br><br>
                                    <b>Narasi Perkembangan</b><br><?php echo nl2br(htmlspecialchars($s['narasi_perkembangan'] ?? '-')); ?>
                                <?php else: ?>
                                    E-Rapor semester ini sedang dalam proses penyusunan.
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_kepsek.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="approval_rapor.php" class="bottom-nav__item"><span class="material-symbols-outlined">check_circle</span>E-Rapor</a>
        <a href="monitoring_absensi.php" class="bottom-nav__item"><span class="material-symbols-outlined">calendar_month</span>Absensi</a>
        <a href="laporan_akademik.php" class="bottom-nav__item active"><span class="material-symbols-outlined">trending_up</span>Laporan</a>
    </div>

</body>
</html>
