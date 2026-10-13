<?php
/* =====================================================================
   PelitaHatiKu - input_galeri_guru.php
   Guru (kelas yang diampu) & Admin (pilih kelas): unggah & kelola foto
   kegiatan. Validasi: hanya JPG/PNG, maksimal 2MB, cek MIME asli.
   ===================================================================== */
session_start();

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['id_user']) || !in_array($role, ['guru', 'admin'], true)) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db_config.php';
$conn = get_db_connection();

$id_user      = $_SESSION['id_user'];
$pesan_sukses = "";
$pesan_error  = "";

const MAX_FOTO_BYTES = 2 * 1024 * 1024;               // 2MB
const EXT_FOTO       = ['jpg', 'jpeg', 'png'];
const MIME_FOTO      = ['image/jpeg', 'image/png'];
const FOLDER_GALERI  = __DIR__ . '/uploads/galeri/';

$kembali_url = ($role === 'admin') ? 'dashboard_admin.php' : 'dashboard_guru.php';

// -----------------------------------------------------------------
// TENTUKAN KELAS YANG BOLEH DIKELOLA
// -----------------------------------------------------------------
$daftar_kelas = [];
if ($role === 'guru') {
    $stmt = $conn->prepare("SELECT id_kelas, nama_kelas FROM kelas WHERE id_guru = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $daftar_kelas[] = $row; }
    $stmt->close();

    if (count($daftar_kelas) === 0) {
        header("Location: dashboard_guru.php");
        exit;
    }
} else {
    $res = $conn->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
    while ($row = $res->fetch_assoc()) { $daftar_kelas[] = $row; }
}
$id_kelas_boleh = array_map('intval', array_column($daftar_kelas, 'id_kelas'));

// -----------------------------------------------------------------
// SIAPKAN FOLDER UPLOAD (+ blokir eksekusi script di dalamnya)
// -----------------------------------------------------------------
if (!is_dir(FOLDER_GALERI)) { mkdir(FOLDER_GALERI, 0755, true); }
$htaccess = FOLDER_GALERI . '.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi)$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
}

// -----------------------------------------------------------------
// PROSES: UNGGAH FOTO
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'unggah') {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tanggal   = $_POST['tanggal_kegiatan'] ?? date('Y-m-d');
    $id_kelas  = (int)($_POST['id_kelas'] ?? 0);

    if ($role === 'guru' && count($id_kelas_boleh) === 1) { $id_kelas = $id_kelas_boleh[0]; }

    if ($judul === '' || $tanggal === '') {
        $pesan_error = "Judul dan tanggal kegiatan wajib diisi.";
    } elseif (!in_array($id_kelas, $id_kelas_boleh, true)) {
        $pesan_error = "Kelas tidak valid.";
    } elseif (mb_strlen($deskripsi) > 500) {
        $pesan_error = "Deskripsi maksimal 500 karakter.";
    } elseif (empty($_FILES['foto']['name'])) {
        $pesan_error = "Silakan pilih foto yang akan diunggah.";
    } else {
        $file = $_FILES['foto'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAX_FOTO_BYTES) {
            $pesan_error = "Ukuran foto melebihi 2MB. Silakan kompres terlebih dahulu.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $pesan_error = "Gagal mengunggah foto (kode error: {$file['error']}).";
        } elseif (!in_array($ext, EXT_FOTO, true)) {
            $pesan_error = "Format foto tidak didukung. Gunakan JPG atau PNG.";
        } else {
            // Validasi isi file sebenarnya (bukan sekadar ekstensi)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, MIME_FOTO, true) || @getimagesize($file['tmp_name']) === false) {
                $pesan_error = "Berkas bukan gambar JPG/PNG yang valid.";
            } else {
                $ext_simpan = ($mime === 'image/png') ? 'png' : 'jpg';
                $nama_baru  = 'galeri_' . $id_kelas . '_' . bin2hex(random_bytes(8)) . '.' . $ext_simpan;

                if (move_uploaded_file($file['tmp_name'], FOLDER_GALERI . $nama_baru)) {
                    $desk_val = $deskripsi !== '' ? $deskripsi : null;
                    $stmt = $conn->prepare("
                        INSERT INTO galeri_kegiatan (id_kelas, id_pengunggah, judul, deskripsi, nama_file, tanggal_kegiatan)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iissss", $id_kelas, $id_user, $judul, $desk_val, $nama_baru, $tanggal);
                    if ($stmt->execute()) {
                        $pesan_sukses = "Foto \"$judul\" berhasil diunggah.";
                    } else {
                        @unlink(FOLDER_GALERI . $nama_baru);
                        $pesan_error = "Gagal menyimpan ke database: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $pesan_error = "Gagal menyimpan berkas ke server. Pastikan folder uploads/galeri dapat ditulis.";
                }
            }
        }
    }
}

// -----------------------------------------------------------------
// PROSES: HAPUS FOTO (hanya kelas yang boleh dikelola)
// -----------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $stmt = $conn->prepare("SELECT id_galeri, id_kelas, nama_file FROM galeri_kegiatan WHERE id_galeri = ?");
    $stmt->bind_param("i", $id_hapus);
    $stmt->execute();
    $foto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($foto && in_array((int)$foto['id_kelas'], $id_kelas_boleh, true)) {
        $stmt = $conn->prepare("DELETE FROM galeri_kegiatan WHERE id_galeri = ?");
        $stmt->bind_param("i", $id_hapus);
        $stmt->execute();
        $stmt->close();
        $path = FOLDER_GALERI . basename($foto['nama_file']);
        if (is_file($path)) { @unlink($path); }
        $pesan_sukses = "Foto berhasil dihapus.";
    } else {
        $pesan_error = "Foto tidak ditemukan atau bukan bagian dari kelas Anda.";
    }
}

// -----------------------------------------------------------------
// DAFTAR FOTO YANG SUDAH DIUNGGAH
// -----------------------------------------------------------------
$daftar_foto = [];
if (count($id_kelas_boleh) > 0) {
    $in  = implode(',', $id_kelas_boleh); // sudah di-cast int di atas
    $res = $conn->query("
        SELECT g.*, k.nama_kelas, u.nama_lengkap AS pengunggah
        FROM galeri_kegiatan g
        JOIN kelas k ON k.id_kelas = g.id_kelas
        JOIN users u ON u.id_user = g.id_pengunggah
        WHERE g.id_kelas IN ($in)
        ORDER BY g.tanggal_kegiatan DESC, g.id_galeri DESC
    ");
    while ($row = $res->fetch_assoc()) { $daftar_foto[] = $row; }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Unggah Galeri Kegiatan - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=add_a_photo,block,delete,photo_library,upload" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        details.form-panel { background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 16px; overflow: hidden; }
        details.form-panel summary { padding: 14px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; list-style: none; }
        details.form-panel summary::-webkit-details-marker { display: none; }
        details.form-panel[open] summary { border-bottom: 1px solid var(--color-border); }
        .form-panel-body { padding: 16px; }
        textarea.form-input { min-height: 80px; resize: vertical; line-height: 1.4; }
        .file-hint { font-size: 11px; color: var(--color-text-muted); margin-top: 6px; }
        .preview-box { margin-top: 10px; display: none; }
        .preview-box img { width: 100%; max-height: 220px; object-fit: cover; border-radius: var(--radius-sm); }
        .foto-item { display: flex; gap: 12px; background: var(--color-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); padding: 10px; margin-bottom: 10px; }
        .foto-item__thumb { width: 78px; height: 78px; border-radius: var(--radius-sm); object-fit: cover; flex-shrink: 0; background: var(--color-primary-light); }
        .foto-item__thumb--empty { display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .foto-item__title { font-size: 13.5px; font-weight: 700; }
        .foto-item__meta { font-size: 11px; color: var(--color-text-muted); margin-top: 3px; }
        .icon-btn { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; margin-left: auto; display: flex; align-items: center; justify-content: center; font-size: 14px; border: 1px solid var(--color-border); background: var(--color-bg); text-decoration: none; }
        .back-link { font-size: 12.5px; }
    </style>
</head>
<body>
    <!-- PEMANGGILAN BACKGROUND BOLA-BOLA -->
    <?php include "background.php"; ?>

    <div class="topbar">
        <div>
            <a href="<?php echo $kembali_url; ?>" class="back-link" style="color:#fff; opacity:0.9;">&larr; Dashboard</a>
            <div class="topbar__title">Galeri Kegiatan</div>
        </div>
    </div>

    <div class="container">

        <?php if ($pesan_sukses): ?><div class="alert alert-success"><?php echo htmlspecialchars($pesan_sukses); ?></div><?php endif; ?>
        <?php if ($pesan_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($pesan_error); ?></div><?php endif; ?>

        <!-- ============================================================
             FORM UNGGAH FOTO
        ============================================================= -->
        <details class="form-panel" open>
            <summary><span class="material-symbols-outlined">add_a_photo</span> Unggah Foto Kegiatan</summary>
            <div class="form-panel-body">
                <form method="POST" action="input_galeri_guru.php" enctype="multipart/form-data" id="formGaleri">
                    <input type="hidden" name="aksi" value="unggah">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FOTO_BYTES; ?>">

                    <?php if (count($daftar_kelas) > 1): ?>
                        <div class="form-group">
                            <label class="form-label">Kelas</label>
                            <select name="id_kelas" class="form-input" required>
                                <option value="">Pilih kelas</option>
                                <?php foreach ($daftar_kelas as $k): ?>
                                    <option value="<?php echo (int)$k['id_kelas']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="id_kelas" value="<?php echo (int)($daftar_kelas[0]['id_kelas'] ?? 0); ?>">
                        <div class="file-hint" style="margin:0 0 12px;">Foto akan tampil untuk orang tua kelas <b><?php echo htmlspecialchars($daftar_kelas[0]['nama_kelas'] ?? '-'); ?></b>.</div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Judul Kegiatan</label>
                        <input type="text" name="judul" class="form-input" maxlength="150" placeholder="contoh: Belajar Mewarnai" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deskripsi Singkat</label>
                        <textarea name="deskripsi" class="form-input" maxlength="500" placeholder="Ceritakan singkat kegiatannya (opsional)"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Kegiatan</label>
                        <input type="date" name="tanggal_kegiatan" class="form-input" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Foto</label>
                        <input type="file" name="foto" id="inputFoto" class="form-input" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                        <div class="file-hint">Format JPG/PNG, maksimal 2MB.</div>
                        <div class="preview-box" id="previewBox"><img id="previewImg" src="" alt="Pratinjau"></div>
                    </div>

                    <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">upload</span> Unggah Foto</button>
                </form>
            </div>
        </details>

        <!-- ============================================================
             DAFTAR FOTO
        ============================================================= -->
        <div class="card__header"><div class="card__title"><span class="material-symbols-outlined">photo_library</span> Foto Terunggah (<?php echo count($daftar_foto); ?>)</div></div>

        <?php if (count($daftar_foto) > 0): ?>
            <?php foreach ($daftar_foto as $g):
                $path = 'uploads/galeri/' . $g['nama_file'];
                $ada  = is_file(__DIR__ . '/' . $path);
            ?>
                <div class="foto-item">
                    <?php if ($ada): ?>
                        <img class="foto-item__thumb" src="<?php echo htmlspecialchars($path); ?>" alt="<?php echo htmlspecialchars($g['judul']); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="foto-item__thumb foto-item__thumb--empty"><span class="material-symbols-outlined">photo_library</span></div>
                    <?php endif; ?>
                    <div>
                        <div class="foto-item__title"><?php echo htmlspecialchars($g['judul']); ?></div>
                        <div class="foto-item__meta"><?php echo htmlspecialchars($g['nama_kelas']); ?> · <?php echo date('d M Y', strtotime($g['tanggal_kegiatan'])); ?></div>
                        <div class="foto-item__meta">oleh <?php echo htmlspecialchars($g['pengunggah']); ?></div>
                    </div>
                    <a href="input_galeri_guru.php?hapus=<?php echo (int)$g['id_galeri']; ?>" class="icon-btn" title="Hapus"
                       onclick="return confirm('Hapus foto \'<?php echo htmlspecialchars($g['judul'], ENT_QUOTES); ?>\'? Foto juga akan hilang dari galeri orang tua.');"><span class="material-symbols-outlined">delete</span></a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><span class="material-symbols-outlined">photo_library</span></div>
                <div class="empty-state__text">Belum ada foto kegiatan yang diunggah.</div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Validasi sisi klien (server tetap memvalidasi ulang)
        var inputFoto = document.getElementById('inputFoto');
        inputFoto.addEventListener('change', function () {
            var f = this.files[0];
            var box = document.getElementById('previewBox');
            if (!f) { box.style.display = 'none'; return; }
            var okTipe = ['image/jpeg', 'image/png'].indexOf(f.type) !== -1;
            if (!okTipe) { alert('Format foto harus JPG atau PNG.'); this.value = ''; box.style.display = 'none'; return; }
            if (f.size > 2 * 1024 * 1024) { alert('Ukuran foto melebihi 2MB.'); this.value = ''; box.style.display = 'none'; return; }
            document.getElementById('previewImg').src = URL.createObjectURL(f);
            box.style.display = 'block';
        });
    </script>

</body>
</html>
