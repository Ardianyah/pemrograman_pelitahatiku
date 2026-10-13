<?php
/* =====================================================================
   PelitaHatiKu - input_rapor.php
   Guru: Input/edit capaian pembelajaran & narasi perkembangan siswa,
   lalu ajukan ke Kepala Sekolah untuk direview.
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'guru') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_guru = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

// -----------------------------------------------------------------
// PASTIKAN GURU MEMANG WALI KELAS
// -----------------------------------------------------------------
$stmt = $conn->prepare("SELECT id_kelas, nama_kelas FROM kelas WHERE id_guru = ? LIMIT 1");
$stmt->bind_param("i", $id_guru);
$stmt->execute();
$res_kelas = $stmt->get_result();
$kelas_data = $res_kelas->num_rows > 0 ? $res_kelas->fetch_assoc() : null;
$stmt->close();

if (!$kelas_data) {
    header("Location: dashboard_guru.php");
    exit;
}
$id_kelas = $kelas_data['id_kelas'];

// -----------------------------------------------------------------
// TAHUN AJARAN AKTIF
// -----------------------------------------------------------------
$res_ta = $conn->query("SELECT id_tahun_ajaran, nama_tahun_ajaran, semester FROM tahun_ajaran WHERE status_aktif = 1 LIMIT 1");
$tahun_ajaran_aktif = $res_ta->num_rows > 0 ? $res_ta->fetch_assoc() : null;

if (!$tahun_ajaran_aktif) {
    $pesan_error = "Belum ada Tahun Ajaran aktif. Hubungi Admin untuk mengaktifkan tahun ajaran terlebih dahulu.";
}
$id_tahun_ajaran = $tahun_ajaran_aktif['id_tahun_ajaran'] ?? 0;

// -----------------------------------------------------------------
// PROSES: SIMPAN / UPDATE RAPOR (draft) UNTUK SATU SISWA
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_rapor') {
    $id_siswa             = (int)($_POST['id_siswa'] ?? 0);
    $capaian_pembelajaran = trim($_POST['capaian_pembelajaran'] ?? '');
    $narasi_perkembangan  = trim($_POST['narasi_perkembangan'] ?? '');
    $ajukan_review        = isset($_POST['ajukan_review']);

    if (empty($capaian_pembelajaran) || empty($narasi_perkembangan)) {
        $pesan_error = "Capaian pembelajaran dan narasi perkembangan wajib diisi.";
    } else {
        $status_baru = $ajukan_review ? 'menunggu_review' : 'draft';

        $cek = $conn->prepare("SELECT id_rapor, status FROM rapor WHERE id_siswa = ? AND id_tahun_ajaran = ?");
        $cek->bind_param("ii", $id_siswa, $id_tahun_ajaran);
        $cek->execute();
        $existing = $cek->get_result()->fetch_assoc();
        $cek->close();

        if ($existing && in_array($existing['status'], ['disetujui', 'diterbitkan'], true)) {
            $pesan_error = "E-Rapor sudah disetujui/diterbitkan dan tidak bisa diubah lagi. Hubungi Kepala Sekolah jika perlu revisi.";
        } elseif ($existing) {
            $stmt = $conn->prepare("
                UPDATE rapor SET capaian_pembelajaran = ?, narasi_perkembangan = ?, status = ?, id_guru_input = ?
                WHERE id_rapor = ?
            ");
            $stmt->bind_param("sssii", $capaian_pembelajaran, $narasi_perkembangan, $status_baru, $id_guru, $existing['id_rapor']);
            $stmt->execute();
            $stmt->close();
            $pesan_sukses = $ajukan_review ? "E-Rapor berhasil diajukan untuk review Kepala Sekolah." : "E-Rapor berhasil disimpan sebagai draft.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO rapor (id_siswa, id_tahun_ajaran, capaian_pembelajaran, narasi_perkembangan, status, id_guru_input)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisssi", $id_siswa, $id_tahun_ajaran, $capaian_pembelajaran, $narasi_perkembangan, $status_baru, $id_guru);
            $stmt->execute();
            $stmt->close();
            $pesan_sukses = $ajukan_review ? "E-Rapor berhasil diajukan untuk review Kepala Sekolah." : "E-Rapor berhasil disimpan sebagai draft.";
        }
    }
}

// -----------------------------------------------------------------
// DAFTAR SISWA + STATUS RAPOR TAHUN AJARAN AKTIF
// -----------------------------------------------------------------
$daftar_siswa = [];
if ($id_tahun_ajaran) {
    $stmt2 = $conn->prepare("
        SELECT s.id_siswa, s.nama_siswa, s.nis,
               r.id_rapor, r.capaian_pembelajaran, r.narasi_perkembangan, r.status
        FROM siswa s
        LEFT JOIN rapor r ON r.id_siswa = s.id_siswa AND r.id_tahun_ajaran = ?
        WHERE s.id_kelas = ? AND s.status_aktif = 1
        ORDER BY s.nama_siswa ASC
    ");
    $stmt2->bind_param("ii", $id_tahun_ajaran, $id_kelas);
    $stmt2->execute();
    $res_siswa = $stmt2->get_result();
    while ($row = $res_siswa->fetch_assoc()) { $daftar_siswa[] = $row; }
    $stmt2->close();
}

$conn->close();

$label_status = [
    'draft'            => ['Draft', 'badge-nonaktif'],
    'menunggu_review'  => ['Menunggu Review', 'badge-warning'],
    'disetujui'        => ['Disetujui', 'badge-aktif'],
    'diterbitkan'      => ['Diterbitkan', 'badge-aktif'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Input E-Rapor - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=calendar_month,campaign,check_circle,child_care,edit_note,home,lock,save" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        details.form-panel {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 12px;
            overflow: hidden;
        }
        details.form-panel summary {
            padding: 14px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; list-style: none;
            display: flex; justify-content: space-between; align-items: center;
        }
        details.form-panel summary::-webkit-details-marker { display: none; }
        details.form-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .form-panel-body { padding: 16px; }
        textarea.form-input { min-height: 90px; resize: vertical; line-height: 1.4; }
        .badge-status { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; }
        .badge-aktif { background-color: var(--color-primary-light); color: var(--color-primary-dark); }
        .badge-nonaktif { background-color: var(--color-border); color: var(--color-text-muted); }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .back-link { font-size: 12.5px; }
        .locked-note { font-size: 11.5px; color: var(--color-text-muted); margin-top: 8px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_guru.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Input E-Rapor</div>
            <div class="topbar__subtitle">
                <?php echo htmlspecialchars($kelas_data['nama_kelas']); ?>
                <?php if ($tahun_ajaran_aktif): ?>
                    · <?php echo htmlspecialchars($tahun_ajaran_aktif['nama_tahun_ajaran'] . ' ' . $tahun_ajaran_aktif['semester']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <?php if (count($daftar_siswa) === 0 && $tahun_ajaran_aktif): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div>
                <div class="empty-state__text">Belum ada siswa aktif di kelas ini.</div>
            </div>
        <?php endif; ?>

        <?php foreach ($daftar_siswa as $s):
            $status = $s['status'] ?? 'draft';
            $terkunci = in_array($status, ['disetujui', 'diterbitkan'], true);
            list($label, $badge_class) = $label_status[$status];
        ?>
            <details class="form-panel">
                <summary>
                    <span><span class="material-symbols-outlined">edit_note</span> <?php echo htmlspecialchars($s['nama_siswa']); ?></span>
                    <span class="badge-status <?php echo $badge_class; ?>"><?php echo $label; ?></span>
                </summary>
                <div class="form-panel-body">
                    <?php if ($terkunci): ?>
                        <p style="font-size:13px;">
                            <?php echo htmlspecialchars($s['capaian_pembelajaran']); ?>
                        </p>
                        <p style="font-size:13px; margin-top:8px;">
                            <?php echo htmlspecialchars($s['narasi_perkembangan']); ?>
                        </p>
                        <div class="locked-note"><span class="material-symbols-outlined">lock</span> E-Rapor ini sudah <?php echo strtolower($label); ?> dan tidak bisa diubah dari sini.</div>
                    <?php else: ?>
                        <form method="POST" action="input_rapor.php">
                            <input type="hidden" name="aksi" value="simpan_rapor">
                            <input type="hidden" name="id_siswa" value="<?php echo (int)$s['id_siswa']; ?>">

                            <div class="form-group">
                                <label class="form-label">Capaian Pembelajaran</label>
                                <textarea name="capaian_pembelajaran" class="form-input" required
                                          placeholder="contoh: Anak mampu mengenal huruf A-Z dan berhitung 1-20."><?php echo htmlspecialchars($s['capaian_pembelajaran'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Narasi Perkembangan</label>
                                <textarea name="narasi_perkembangan" class="form-input" required
                                          placeholder="contoh: Ananda menunjukkan perkembangan motorik halus yang baik..."><?php echo htmlspecialchars($s['narasi_perkembangan'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" name="ajukan_review" value="0" class="btn btn-outline" style="margin-bottom:8px;">
                                <span class="material-symbols-outlined">save</span> Simpan sebagai Draft
                            </button>
                            <button type="submit" name="ajukan_review" value="1" class="btn btn-primary"
                                    onclick="return confirm('Ajukan E-Rapor ini untuk direview Kepala Sekolah? Pastikan isinya sudah lengkap.');">
                                <span class="material-symbols-outlined">check_circle</span> Ajukan untuk Review
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_guru.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">home</span>
            Beranda
        </a>
        <a href="input_absensi.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">calendar_month</span>
            Absensi
        </a>
        <a href="input_rapor.php" class="bottom-nav__item active">
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
