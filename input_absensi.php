<?php
/* =====================================================================
   PelitaHatiKu - input_absensi.php
   Guru: Input/edit presensi harian siswa di kelas yang diampu
   Aturan: 1 siswa hanya boleh 1 baris absensi per tanggal (upsert)
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
    // Tidak render halaman lebih jauh jika guru belum punya kelas
    header("Location: dashboard_guru.php");
    exit;
}
$id_kelas = $kelas_data['id_kelas'];

// -----------------------------------------------------------------
// TANGGAL YANG DIPILIH (default hari ini, tidak boleh di masa depan)
// -----------------------------------------------------------------
$tanggal_pilih = $_GET['tanggal'] ?? date('Y-m-d');
if ($tanggal_pilih > date('Y-m-d')) {
    $tanggal_pilih = date('Y-m-d');
}

// -----------------------------------------------------------------
// PROSES: SIMPAN ABSENSI (upsert per siswa)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_absensi') {
    $tanggal_input = $_POST['tanggal'] ?? date('Y-m-d');
    if ($tanggal_input > date('Y-m-d')) {
        $pesan_error = "Tidak bisa mengisi absensi untuk tanggal yang belum terjadi.";
    } else {
        $status_list      = $_POST['status'] ?? [];      // [id_siswa => status]
        $keterangan_list  = $_POST['keterangan'] ?? [];   // [id_siswa => keterangan]

        $stmt_upsert = $conn->prepare("
            INSERT INTO absensi (id_siswa, tanggal, status, keterangan, id_guru_input)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), id_guru_input = VALUES(id_guru_input)
        ");

        $berhasil = 0;
        foreach ($status_list as $id_siswa => $status) {
            $id_siswa = (int)$id_siswa;
            if (!in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpa'], true)) { continue; }
            $ket = trim($keterangan_list[$id_siswa] ?? '');
            $ket_val = $ket !== '' ? $ket : null;
            $stmt_upsert->bind_param("isssi", $id_siswa, $tanggal_input, $status, $ket_val, $id_guru);
            if ($stmt_upsert->execute()) { $berhasil++; }
        }
        $stmt_upsert->close();

        $pesan_sukses = "Absensi tanggal " . date('d M Y', strtotime($tanggal_input)) . " berhasil disimpan untuk $berhasil siswa.";
        $tanggal_pilih = $tanggal_input;
    }
}

// -----------------------------------------------------------------
// AMBIL DAFTAR SISWA + STATUS ABSENSI PADA TANGGAL TERPILIH
// -----------------------------------------------------------------
$daftar_siswa = [];
$stmt2 = $conn->prepare("
    SELECT s.id_siswa, s.nama_siswa, s.nis,
           a.status AS status_terisi, a.keterangan AS keterangan_terisi
    FROM siswa s
    LEFT JOIN absensi a ON a.id_siswa = s.id_siswa AND a.tanggal = ?
    WHERE s.id_kelas = ? AND s.status_aktif = 1
    ORDER BY s.nama_siswa ASC
");
$stmt2->bind_param("si", $tanggal_pilih, $id_kelas);
$stmt2->execute();
$res_siswa = $stmt2->get_result();
while ($row = $res_siswa->fetch_assoc()) { $daftar_siswa[] = $row; }
$stmt2->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Input Absensi - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=calendar_month,campaign,child_care,edit_note,home,save" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .absen-item {
            background-color: var(--color-surface);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 10px;
        }
        .absen-item__name { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .absen-item__nis { font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 10px; }
        .status-toggle {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin-bottom: 8px;
        }
        .status-toggle input[type="radio"] { display: none; }
        .status-toggle label {
            text-align: center;
            padding: 8px 4px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            border: 1.5px solid var(--color-border);
            color: var(--color-text-muted);
            cursor: pointer;
        }
        .status-toggle input[value="Hadir"]:checked + label { background-color: var(--color-primary-light); border-color: var(--color-primary); color: var(--color-primary-dark); }
        .status-toggle input[value="Izin"]:checked + label { background-color: #dbeafe; border-color: #3b82f6; color: #1d4ed8; }
        .status-toggle input[value="Sakit"]:checked + label { background-color: #fef3c7; border-color: #d97706; color: #92400e; }
        .status-toggle input[value="Alpa"]:checked + label { background-color: #fee2e2; border-color: var(--color-danger); color: var(--color-danger); }
        .ket-input { font-size: 12px; padding: 8px 10px; }
        .date-picker-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 16px;
        }
        .back-link { font-size: 12.5px; }
        .sticky-save {
            position: sticky;
            bottom: 76px;
            padding: 10px 0;
            background: linear-gradient(180deg, transparent 0%, var(--color-bg) 30%);
        }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_guru.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Input Absensi</div>
            <div class="topbar__subtitle"><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <!-- ============================================================
             PEMILIH TANGGAL
        ============================================================= -->
        <form method="GET" action="input_absensi.php" class="date-picker-row">
            <input type="date" name="tanggal" class="form-input" max="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo htmlspecialchars($tanggal_pilih); ?>" onchange="this.form.submit()">
            <span style="font-size:12px; color:var(--color-text-muted); white-space:nowrap;">
                <?php echo count($daftar_siswa); ?> siswa
            </span>
        </form>

        <?php if (count($daftar_siswa) === 0): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">child_care</span></div>
                <div class="empty-state__text">Belum ada data presensi untuk hari ini. Belum ada siswa aktif di kelas ini.</div>
            </div>
        <?php else: ?>

            <form method="POST" action="input_absensi.php">
                <input type="hidden" name="aksi" value="simpan_absensi">
                <input type="hidden" name="tanggal" value="<?php echo htmlspecialchars($tanggal_pilih); ?>">

                <?php foreach ($daftar_siswa as $s):
                    $status_saat_ini = $s['status_terisi'] ?? 'Hadir'; // default pilihan: Hadir
                ?>
                    <div class="absen-item">
                        <div class="absen-item__name"><?php echo htmlspecialchars($s['nama_siswa']); ?></div>
                        <div class="absen-item__nis">NIS <?php echo htmlspecialchars($s['nis']); ?></div>

                        <div class="status-toggle">
                            <?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $opsi): ?>
                                <input type="radio"
                                       name="status[<?php echo (int)$s['id_siswa']; ?>]"
                                       id="st_<?php echo (int)$s['id_siswa']; ?>_<?php echo $opsi; ?>"
                                       value="<?php echo $opsi; ?>"
                                       <?php echo $status_saat_ini === $opsi ? 'checked' : ''; ?>>
                                <label for="st_<?php echo (int)$s['id_siswa']; ?>_<?php echo $opsi; ?>"><?php echo $opsi; ?></label>
                            <?php endforeach; ?>
                        </div>

                        <input type="text" class="form-input ket-input"
                               name="keterangan[<?php echo (int)$s['id_siswa']; ?>]"
                               placeholder="Keterangan (opsional)"
                               value="<?php echo htmlspecialchars($s['keterangan_terisi'] ?? ''); ?>">
                    </div>
                <?php endforeach; ?>

                <div class="sticky-save">
                    <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">save</span> Simpan Absensi Hari Ini</button>
                </div>
            </form>

        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_guru.php" class="bottom-nav__item">
            <span class="material-symbols-outlined">home</span>
            Beranda
        </a>
        <a href="input_absensi.php" class="bottom-nav__item active">
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
