<?php
/* =====================================================================
   PelitaHatiKu - profil.php
   Orang Tua: memperbarui data kontak & informasi pribadi (+ foto profil)
   Validasi upload: maksimal 2MB, format JPG/PNG/PDF (sesuai spesifikasi)
   ===================================================================== */
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'orang_tua') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_user_login = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2MB
const EXT_DIIZINKAN    = ['jpg', 'jpeg', 'png', 'pdf'];
const UPLOAD_DIR       = __DIR__ . '/uploads/profil/';

// -----------------------------------------------------------------
// PROSES: UPDATE PROFIL
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_profil') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');

    if (empty($nama_lengkap)) {
        $pesan_error = "Nama lengkap wajib diisi.";
    } else {
        $foto_path_baru = null;

        // ---------------------------------------------------------
        // VALIDASI & UPLOAD FOTO PROFIL (opsional)
        // ---------------------------------------------------------
        if (!empty($_FILES['foto_profil']['name'])) {
            $file = $_FILES['foto_profil'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $pesan_error = "Gagal mengunggah berkas (kode error: {$file['error']}).";
            } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
                $pesan_error = "Ukuran berkas melebihi 2MB. Silakan kompres atau pilih berkas lain.";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, EXT_DIIZINKAN, true)) {
                    $pesan_error = "Format berkas tidak didukung. Gunakan JPG, PNG, atau PDF.";
                } else {
                    if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0755, true); }
                    $nama_file_baru = 'wali_' . $id_user_login . '_' . time() . '.' . $ext;
                    $tujuan = UPLOAD_DIR . $nama_file_baru;
                    if (move_uploaded_file($file['tmp_name'], $tujuan)) {
                        $foto_path_baru = 'uploads/profil/' . $nama_file_baru;
                    } else {
                        $pesan_error = "Gagal menyimpan berkas ke server.";
                    }
                }
            }
        }

        if (empty($pesan_error)) {
            $email_val = $email !== '' ? $email : null;
            if ($foto_path_baru) {
                $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, foto_profil = ? WHERE id_user = ?");
                $stmt->bind_param("ssssi", $nama_lengkap, $email_val, $no_hp, $foto_path_baru, $id_user_login);
            } else {
                $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ? WHERE id_user = ?");
                $stmt->bind_param("sssi", $nama_lengkap, $email_val, $no_hp, $id_user_login);
            }

            if ($stmt->execute()) {
                $pesan_sukses = "Profil berhasil diperbarui.";
                $_SESSION['nama_lengkap'] = $nama_lengkap; // sinkronkan session
            } else {
                $pesan_error = "Gagal menyimpan perubahan: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// -----------------------------------------------------------------
// AMBIL DATA PROFIL TERKINI
// -----------------------------------------------------------------
$stmt2 = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt2->bind_param("i", $id_user_login);
$stmt2->execute();
$profil = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

// -----------------------------------------------------------------
// DATA ANAK YANG TERHUBUNG (read-only, untuk konteks)
// -----------------------------------------------------------------
$daftar_anak = [];
$stmt3 = $conn->prepare("
    SELECT s.nama_siswa, s.nis, k.nama_kelas, ots.hubungan
    FROM orang_tua_siswa ots
    JOIN siswa s ON s.id_siswa = ots.id_siswa
    LEFT JOIN kelas k ON k.id_kelas = s.id_kelas
    WHERE ots.id_user = ?
");
$stmt3->bind_param("i", $id_user_login);
$stmt3->execute();
$res3 = $stmt3->get_result();
while ($row = $res3->fetch_assoc()) { $daftar_anak[] = $row; }
$stmt3->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Profil Saya - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-header { display: flex; flex-direction: column; align-items: center; margin: 8px 0 20px; }
        .profile-photo {
            width: 88px; height: 88px; border-radius: 50%;
            background-color: var(--color-primary-light);
            border: 3px solid var(--color-primary);
            object-fit: cover; margin-bottom: 10px;
        }
        .profile-name { font-size: 16px; font-weight: 800; }
        .profile-kode { font-family: monospace; font-size: 12px; color: var(--color-text-muted); margin-top: 2px; }
        .anak-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--color-border); font-size: 13px; }
        .anak-item:last-child { border-bottom: none; }
        .anak-item__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 2px; }
        .file-hint { font-size: 11px; color: var(--color-text-muted); margin-top: 6px; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="dashboard_wali.php" class="back-link" style="color:#fff; opacity:0.9;">&larr; Beranda</a>
            <div class="topbar__title">Profil Saya</div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?><div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div><?php endif; ?>

        <div class="profile-header">
            <img class="profile-photo"
                 src="<?php echo !empty($profil['foto_profil']) ? htmlspecialchars($profil['foto_profil']) : 'assets/img/default-avatar.png'; ?>"
                 alt="Foto Profil">
            <div class="profile-name"><?php echo htmlspecialchars($profil['nama_lengkap']); ?></div>
            <div class="profile-kode">Kode Akses: <?php echo htmlspecialchars($profil['kode_akses']); ?></div>
        </div>

        <!-- ============================================================
             FORM UPDATE PROFIL
        ============================================================= -->
        <div class="card">
            <div class="card__title" style="margin-bottom:12px;">âœï¸ Perbarui Data</div>
            <form method="POST" action="profil.php" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="update_profil">

                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-input" required
                           value="<?php echo htmlspecialchars($profil['nama_lengkap']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input"
                           value="<?php echo htmlspecialchars($profil['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_hp" class="form-input"
                           value="<?php echo htmlspecialchars($profil['no_hp'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Foto Profil / Dokumen Pendukung</label>
                    <input type="file" name="foto_profil" class="form-input" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="file-hint">Maksimal 2MB, format JPG/PNG/PDF.</div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </form>
        </div>

        <!-- ============================================================
             DATA ANAK TERHUBUNG (read-only)
        ============================================================= -->
        <div class="card__header" style="margin-top:20px;">
            <div class="card__title">ðŸ‘¶ Anak Terhubung</div>
        </div>

        <?php if (count($daftar_anak) > 0): ?>
            <div class="card">
                <?php foreach ($daftar_anak as $a): ?>
                    <div class="anak-item">
                        <div>
                            <?php echo htmlspecialchars($a['nama_siswa']); ?>
                            <div class="anak-item__meta">NIS <?php echo htmlspecialchars($a['nis']); ?> Â· <?php echo htmlspecialchars($a['nama_kelas'] ?? '-'); ?></div>
                        </div>
                        <span style="font-size:11.5px; color:var(--color-text-muted);"><?php echo htmlspecialchars($a['hubungan']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon">ðŸ‘¶</div>
                <div class="empty-state__text">Belum ada data anak yang terhubung ke akun ini. Hubungi Admin sekolah.</div>
            </div>
        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="dashboard_wali.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ </span>Beranda</a>
        <a href="absensi_anak.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ—“ï¸</span>Presensi</a>
        <a href="rapor_anak.php" class="bottom-nav__item"><span class="bottom-nav__icon">ðŸ“„</span>E-Rapor</a>
        <a href="profil.php" class="bottom-nav__item active"><span class="bottom-nav__icon">ðŸ‘¤</span>Profil</a>
    </div>

</body>
</html>
