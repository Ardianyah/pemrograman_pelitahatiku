<?php
/* =====================================================================
   PelitaHatiKu - approval_rapor.php
   Kepala Sekolah: review, setujui/kembalikan, dan terbitkan E-Rapor
   Alur status: draft -> menunggu_review -> disetujui -> diterbitkan
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'kepala_sekolah') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_kepsek    = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

// -----------------------------------------------------------------
// PROSES AKSI (POST): setujui / kembalikan / terbitkan
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi     = $_POST['aksi'] ?? '';
    $id_rapor = (int)($_POST['id_rapor'] ?? 0);

    if ($aksi === 'setujui') {
        $stmt = $conn->prepare("UPDATE rapor SET status = 'disetujui', id_kepsek_approve = ? WHERE id_rapor = ? AND status = 'menunggu_review'");
        $stmt->bind_param("ii", $id_kepsek, $id_rapor);
        $stmt->execute();
        $pesan_sukses = $stmt->affected_rows > 0 ? "E-Rapor berhasil disetujui." : "";
        $pesan_error  = $stmt->affected_rows > 0 ? "" : "E-Rapor tidak ditemukan atau statusnya sudah berubah.";
        $stmt->close();

    } elseif ($aksi === 'kembalikan') {
        $stmt = $conn->prepare("UPDATE rapor SET status = 'draft', id_kepsek_approve = NULL WHERE id_rapor = ? AND status = 'menunggu_review'");
        $stmt->bind_param("i", $id_rapor);
        $stmt->execute();
        $pesan_sukses = $stmt->affected_rows > 0 ? "E-Rapor dikembalikan ke Guru sebagai draft untuk direvisi." : "";
        $pesan_error  = $stmt->affected_rows > 0 ? "" : "E-Rapor tidak ditemukan atau statusnya sudah berubah.";
        $stmt->close();

    } elseif ($aksi === 'terbitkan') {
        $stmt = $conn->prepare("UPDATE rapor SET status = 'diterbitkan', tanggal_terbit = CURDATE() WHERE id_rapor = ? AND status = 'disetujui'");
        $stmt->bind_param("i", $id_rapor);
        $stmt->execute();
        $pesan_sukses = $stmt->affected_rows > 0 ? "E-Rapor berhasil diterbitkan dan kini dapat dilihat orang tua." : "";
        $pesan_error  = $stmt->affected_rows > 0 ? "" : "E-Rapor harus berstatus 'Disetujui' sebelum diterbitkan.";
        $stmt->close();
    }
}

// -----------------------------------------------------------------
// FILTER STATUS (tab)
// -----------------------------------------------------------------
$status_valid  = ['menunggu_review', 'disetujui', 'diterbitkan', 'draft'];
$filter_status = $_GET['status'] ?? 'menunggu_review';
if (!in_array($filter_status, $status_valid, true)) { $filter_status = 'menunggu_review'; }

$label_status = [
    'menunggu_review' => 'Menunggu Review',
    'disetujui'       => 'Disetujui',
    'diterbitkan'     => 'Diterbitkan',
    'draft'           => 'Draft',
];

// Hitung jumlah per status untuk badge tab
$jumlah_status = array_fill_keys($status_valid, 0);
$res_count = $conn->query("SELECT status, COUNT(*) AS jumlah FROM rapor GROUP BY status");
while ($row = $res_count->fetch_assoc()) { $jumlah_status[$row['status']] = (int)$row['jumlah']; }

// -----------------------------------------------------------------
// DAFTAR RAPOR SESUAI FILTER
// -----------------------------------------------------------------
$daftar_rapor = [];
$stmt2 = $conn->prepare("
    SELECT r.id_rapor, r.capaian_pembelajaran, r.narasi_perkembangan, r.status, r.tanggal_terbit, r.updated_at,
           s.nama_siswa, s.nis, k.nama_kelas,
           t.nama_tahun_ajaran, t.semester,
           g.nama_lengkap AS nama_guru
    FROM rapor r
    JOIN siswa s ON s.id_siswa = r.id_siswa
    LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
    JOIN tahun_ajaran t ON t.id_tahun_ajaran = r.id_tahun_ajaran
    LEFT JOIN users g ON g.id_user = r.id_guru_input
    WHERE r.status = ?
    ORDER BY r.updated_at DESC
");
$stmt2->bind_param("s", $filter_status);
$stmt2->execute();
$res = $stmt2->get_result();
while ($row = $res->fetch_assoc()) { $daftar_rapor[] = $row; }
$stmt2->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Approval E-Rapor - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=assignment,calendar_month,check_circle,home,trending_up,undo,upload" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-chip-row { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; padding-bottom: 4px; }
        .filter-chip {
            flex-shrink: 0; padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background-color: var(--color-surface); border: 1.5px solid var(--color-border); color: var(--color-text-muted);
        }
        .filter-chip.active { background-color: var(--color-primary); border-color: var(--color-primary); color: #fff; }
        .filter-chip__count {
            display: inline-block; margin-left: 4px; font-size: 10.5px; font-weight: 700;
            background-color: rgba(0,0,0,0.08); border-radius: 20px; padding: 1px 7px;
        }
        .filter-chip.active .filter-chip__count { background-color: rgba(255,255,255,0.25); }
        .rapor-card {
            background-color: var(--color-surface); border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft); padding: 16px; margin-bottom: 12px;
        }
        .rapor-card__name { font-size: 14.5px; font-weight: 700; }
        .rapor-card__meta { font-size: 12px; color: var(--color-text-muted); margin-top: 3px; }
        .rapor-section-title { font-size: 11.5px; font-weight: 700; color: var(--color-primary-dark); margin: 14px 0 4px; text-transform: uppercase; letter-spacing: 0.3px; }
        .rapor-text { font-size: 13px; line-height: 1.55; }
        .action-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px; }
        .btn-danger-outline { background: transparent; color: var(--color-danger); border: 1.5px solid var(--color-danger); }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_kepsek.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Approval E-Rapor</div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <div class="filter-chip-row">
            <?php foreach ($label_status as $key => $label): ?>
                <a href="approval_rapor.php?status=<?php echo $key; ?>" class="filter-chip <?php echo $filter_status === $key ? 'active' : ''; ?>">
                    <?php echo $label; ?><span class="filter-chip__count"><?php echo $jumlah_status[$key]; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (count($daftar_rapor) === 0): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">assignment</span></div>
                <div class="empty-state__text">
                    <?php echo $filter_status === 'menunggu_review'
                        ? 'Tidak ada E-Rapor yang menunggu review saat ini.'
                        : 'Belum ada E-Rapor dengan status ' . htmlspecialchars($label_status[$filter_status]) . '.'; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($daftar_rapor as $r): ?>
            <div class="rapor-card">
                <div class="rapor-card__name"><?php echo htmlspecialchars($r['nama_siswa']); ?></div>
                <div class="rapor-card__meta">
                    NIS <?php echo htmlspecialchars($r['nis']); ?> · <?php echo htmlspecialchars($r['nama_kelas'] ?? '-'); ?> ·
                    <?php echo htmlspecialchars($r['nama_tahun_ajaran'] . ' ' . $r['semester']); ?>
                </div>
                <div class="rapor-card__meta">Diisi oleh: <?php echo htmlspecialchars($r['nama_guru'] ?? '-'); ?></div>
                <?php if ($r['tanggal_terbit']): ?>
                    <div class="rapor-card__meta">Diterbitkan: <?php echo date('d M Y', strtotime($r['tanggal_terbit'])); ?></div>
                <?php endif; ?>

                <div class="rapor-section-title">Capaian Pembelajaran</div>
                <div class="rapor-text"><?php echo nl2br(htmlspecialchars($r['capaian_pembelajaran'] ?? '-')); ?></div>

                <div class="rapor-section-title">Narasi Perkembangan</div>
                <div class="rapor-text"><?php echo nl2br(htmlspecialchars($r['narasi_perkembangan'] ?? '-')); ?></div>

                <?php if ($r['status'] === 'menunggu_review'): ?>
                    <div class="action-row">
                        <form method="POST" action="approval_rapor.php?status=menunggu_review"
                              onsubmit="return confirm('Kembalikan E-Rapor ini ke Guru untuk direvisi?');">
                            <input type="hidden" name="aksi" value="kembalikan">
                            <input type="hidden" name="id_rapor" value="<?php echo (int)$r['id_rapor']; ?>">
                            <button type="submit" class="btn btn-danger-outline"><span class="material-symbols-outlined">undo</span> Kembalikan</button>
                        </form>
                        <form method="POST" action="approval_rapor.php?status=menunggu_review">
                            <input type="hidden" name="aksi" value="setujui">
                            <input type="hidden" name="id_rapor" value="<?php echo (int)$r['id_rapor']; ?>">
                            <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">check_circle</span> Setujui</button>
                        </form>
                    </div>
                <?php elseif ($r['status'] === 'disetujui'): ?>
                    <form method="POST" action="approval_rapor.php?status=disetujui" style="margin-top:16px;"
                          onsubmit="return confirm('Terbitkan E-Rapor ini? Orang tua akan langsung dapat melihat dan mengunduhnya.');">
                        <input type="hidden" name="aksi" value="terbitkan">
                        <input type="hidden" name="id_rapor" value="<?php echo (int)$r['id_rapor']; ?>">
                        <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">upload</span> Terbitkan ke Orang Tua</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_kepsek.php" class="bottom-nav__item"><span class="material-symbols-outlined">home</span>Beranda</a>
        <a href="approval_rapor.php" class="bottom-nav__item active"><span class="material-symbols-outlined">check_circle</span>E-Rapor</a>
        <a href="monitoring_absensi.php" class="bottom-nav__item"><span class="material-symbols-outlined">calendar_month</span>Absensi</a>
        <a href="laporan_akademik.php" class="bottom-nav__item"><span class="material-symbols-outlined">trending_up</span>Laporan</a>
    </div>

</body>
</html>
