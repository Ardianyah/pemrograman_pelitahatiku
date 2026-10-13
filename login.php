<?php
/* =====================================================================
   PelitaHatiKu - login.php (REVISI)
   Dua alur login:
   1. Staf (Admin/Kepala Sekolah/Guru) -> username & password
   2. Wali Murid (Orang Tua)           -> Kode Akses tunggal (mis. PH-123456)
   ===================================================================== */
session_start();

// -----------------------------------------------------------------
// KONFIGURASI KONEKSI DATABASE (terpusat di db_config.php)
// -----------------------------------------------------------------
require_once __DIR__ . '/db_config.php';

$error_message  = "";
$active_tab     = "staf"; // default tab: staf | wali

// -----------------------------------------------------------------
// PROSES SUBMIT FORM
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login_type = $_POST['login_type'] ?? 'staf';
    $active_tab = ($login_type === 'wali') ? 'wali' : 'staf';

    $conn = get_db_connection(); // otomatis die() dengan pesan aman jika koneksi gagal
    {

        // ===========================================================
        // ALUR 1: LOGIN STAF (Admin / Kepala Sekolah / Guru)
        // ===========================================================
        if ($login_type === 'staf') {

            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($username) || empty($password)) {
                $error_message = "Username dan password wajib diisi.";
            } else {
                $stmt = $conn->prepare("
                    SELECT id_user, username, password, role, nama_lengkap, status_aktif
                    FROM users
                    WHERE username = ? AND role IN ('admin','kepala_sekolah','guru')
                    LIMIT 1
                ");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if ((int)$user['status_aktif'] !== 1) {
                        $error_message = "Akun Anda tidak aktif. Silakan hubungi Admin sekolah.";
                    } elseif (password_verify($password, $user['password'])) {
                        $_SESSION['id_user']      = $user['id_user'];
                        $_SESSION['username']     = $user['username'];
                        $_SESSION['role']         = $user['role'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                        switch ($user['role']) {
                            case 'admin':          header("Location: dashboard_admin.php");  break;
                            case 'kepala_sekolah':  header("Location: dashboard_kepsek.php"); break;
                            case 'guru':            header("Location: dashboard_guru.php");   break;
                        }
                        exit;
                    } else {
                        $error_message = "Password yang Anda masukkan salah.";
                    }
                } else {
                    $error_message = "Username tidak ditemukan.";
                }
                $stmt->close();
            }

        // ===========================================================
        // ALUR 2: LOGIN WALI MURID (Kode Akses)
        // ===========================================================
        } else {

            $kode_akses = trim($_POST['kode_akses'] ?? '');

            if (empty($kode_akses)) {
                $error_message = "Kode Akses wajib diisi.";
            } else {
                $stmt = $conn->prepare("
                    SELECT id_user, kode_akses, role, nama_lengkap, status_aktif
                    FROM users
                    WHERE kode_akses = ? AND role = 'orang_tua'
                    LIMIT 1
                ");
                $stmt->bind_param("s", $kode_akses);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if ((int)$user['status_aktif'] !== 1) {
                        $error_message = "Akun Anda tidak aktif. Silakan hubungi Admin sekolah.";
                    } else {
                        $_SESSION['id_user']      = $user['id_user'];
                        $_SESSION['role']         = $user['role'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['kode_akses']   = $user['kode_akses'];

                        header("Location: dashboard_wali.php");
                        exit;
                    }
                } else {
                    $error_message = "Kode Akses tidak valid atau tidak ditemukan.";
                }
                $stmt->close();
            }
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Login - PelitaHatiKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=family_restroom,person" />
    <link rel="stylesheet" href="icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Tab switch khusus halaman login */
        .login-tabs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            background-color: var(--color-primary-light);
            border-radius: var(--radius-sm);
            padding: 4px;
            margin-bottom: 20px;
        }
        .login-tab-btn {
            text-align: center;
            padding: 10px 6px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--color-primary-dark);
            cursor: pointer;
            border: none;
            background: transparent;
        }
        .login-tab-btn.active {
            background-color: var(--color-surface);
            color: var(--color-primary-dark);
            box-shadow: var(--shadow-soft);
        }
        .login-panel { display: none; }
        .login-panel.active { display: block; }
        .kode-akses-hint {
            font-size: 11.5px;
            color: var(--color-text-muted);
            margin-top: -10px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">

        <div class="login-logo">PH</div>
        <div class="login-title">PelitaHatiKu</div>
        <div class="login-subtitle">Portal Orang Tua &amp; Sistem Informasi Siswa<br>TK Pelita Hati</div>

        <div class="login-card">

            <!-- Tab Switcher -->
            <div class="login-tabs">
                <button type="button" class="login-tab-btn" id="tabBtnStaf" onclick="switchTab('staf')">
                    <span class="material-symbols-outlined">person</span> Masuk sebagai Staf
                </button>
                <button type="button" class="login-tab-btn" id="tabBtnWali" onclick="switchTab('wali')">
                    <span class="material-symbols-outlined">family_restroom</span> Masuk Wali Murid
                </button>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- =========================================================
                 PANEL 1: LOGIN STAF (Username & Password)
            ========================================================== -->
            <div class="login-panel" id="panelStaf">
                <form action="login.php" method="POST" novalidate>
                    <input type="hidden" name="login_type" value="staf">

                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input"
                            placeholder="Masukkan username Anda"
                            required
                            autocomplete="username"
                            value="<?php echo ($active_tab === 'staf' && isset($_POST['username'])) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Masukkan password Anda"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">Masuk sebagai Staf</button>
                </form>

                <p style="text-align:center; font-size:12px; color:#64748b; margin-top:16px;">
                    Lupa password? Hubungi Admin sekolah.
                </p>
            </div>

            <!-- =========================================================
                 PANEL 2: LOGIN WALI MURID (Kode Akses)
            ========================================================== -->
            <div class="login-panel" id="panelWali">
                <form action="login.php" method="POST" novalidate>
                    <input type="hidden" name="login_type" value="wali">

                    <div class="form-group">
                        <label class="form-label" for="kode_akses">Kode Akses</label>
                        <input
                            type="text"
                            id="kode_akses"
                            name="kode_akses"
                            class="form-input"
                            placeholder="Contoh: PH-123456"
                            required
                            autocomplete="off"
                            style="text-transform:uppercase; letter-spacing:1px;"
                            value="<?php echo ($active_tab === 'wali' && isset($_POST['kode_akses'])) ? htmlspecialchars($_POST['kode_akses']) : ''; ?>"
                        >
                    </div>
                    <div class="kode-akses-hint">
                        Kode Akses dapat dilihat pada kartu/surat yang diberikan oleh pihak sekolah.
                    </div>

                    <button type="submit" class="btn btn-primary">Masuk dengan Kode Akses</button>
                </form>

                <p style="text-align:center; font-size:12px; color:#64748b; margin-top:16px;">
                    Belum punya Kode Akses? Hubungi Admin sekolah.
                </p>
            </div>

        </div>

        <p style="font-size:11px; color:#94a3b8; margin-top:24px; text-align:center;">
            &copy; <?php echo date('Y'); ?> TK Pelita Hati — Umi Rohayah
        </p>

    </div>

    <script>
        function switchTab(tab) {
            const panelStaf = document.getElementById('panelStaf');
            const panelWali = document.getElementById('panelWali');
            const btnStaf   = document.getElementById('tabBtnStaf');
            const btnWali   = document.getElementById('tabBtnWali');

            if (tab === 'staf') {
                panelStaf.classList.add('active');
                panelWali.classList.remove('active');
                btnStaf.classList.add('active');
                btnWali.classList.remove('active');
            } else {
                panelWali.classList.add('active');
                panelStaf.classList.remove('active');
                btnWali.classList.add('active');
                btnStaf.classList.remove('active');
            }
        }

        // Tampilkan tab sesuai hasil submit terakhir (jika ada error)
        document.addEventListener('DOMContentLoaded', function () {
            switchTab('<?php echo $active_tab; ?>');
        });
    </script>

</body>
</html>
