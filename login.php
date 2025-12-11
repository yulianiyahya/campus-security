<?php 
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (is_logged_in()) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('admin/dashboard.php');
            break;
        case 'security':
            redirect('security/dashboard.php');
            break;
        case 'user':
            redirect('user/dashboard.php');
            break;
    }
}

$error = '';
$success = '';

// Cek jika ada pesan timeout
if (isset($_GET['timeout'])) {
    $error = 'Sesi Anda telah berakhir. Silakan login kembali.';
}

// Cek jika ada pesan registrasi berhasil
if (isset($_GET['registered'])) {
    $success = 'Registrasi berhasil! Silakan login dengan akun Anda.';
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim_nip = clean_input($_POST['nim_nip']);
    $password = $_POST['password'];

    if (empty($nim_nip) || empty($password)) {
        $error = 'NIM/NIP dan Password harus diisi!';
    } else {
        try {
            // Cari user berdasarkan NIM/NIP
            $stmt = $pdo->prepare("SELECT * FROM users WHERE nim_nip = ? AND status = 'active'");
            $stmt->execute([$nim_nip]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {

                // Login berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nim_nip'] = $user['nim_nip'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                // Simpan session ke database
                $session_token = bin2hex(random_bytes(32));
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

                $stmt = $pdo->prepare("
                    INSERT INTO user_sessions 
                    (user_id, session_token, ip_address, user_agent, expires_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user['id'], $session_token, $ip_address, $user_agent, $expires_at]);

                // Redirect sesuai role
                switch ($user['role']) {
                    case 'admin':
                        redirect('admin/dashboard.php');
                        break;
                    case 'security':
                        redirect('security/dashboard.php');
                        break;
                    case 'user':
                        redirect('user/dashboard.php');
                        break;
                }

            } else {
                $error = 'NIM/NIP atau Password salah!';
            }

        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Premium Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Clash+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Satoshi:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-soft: #6C9BCF;
            --primary-light: #8BB4DB;
            --primary-lighter: #B8D4EC;
            --primary-dark: #5681B3;

            --luxury-gradient: linear-gradient(135deg, #6C9BCF 0%, #8BB4DB 50%, #B8D4EC 100%);

            --body-bg: #f5f9ff;
            --card-bg: #ffffff;

            --text-primary: #2c3e50;
            --text-secondary: #5a6c7d;
            --text-light: #95a5a6;

            --glass-white: rgba(255, 255, 255, 0.95);
            --glass-blue: rgba(108, 155, 207, 0.08);

            --shadow-elegant: 0 10px 40px rgba(108, 155, 207, 0.12);
            --shadow-hover: 0 20px 60px rgba(108, 155, 207, 0.2);
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }

        body {
            font-family: 'Satoshi', sans-serif;
            background: var(--body-bg);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
            color: var(--text-primary);
            line-height: 1.6;
        }

        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.12;
            z-index: 0;
            animation: floatOrb 25s ease-in-out infinite;
            pointer-events: none;
        }

        body::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #6C9BCF, transparent);
            top: -250px; right: -250px;
        }

        body::after {
            width: 450px; height: 450px;
            background: radial-gradient(circle, #B8D4EC, transparent);
            bottom: -200px; left: -200px;
            animation-delay: -12s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
            position: relative; z-index: 10;
        }

        .login-card {
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(108, 155, 207, 0.15);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-elegant);
            animation: slideUp 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--luxury-gradient);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header { text-align: center; margin-bottom: 2.5rem; }

        .login-icon {
            width: 90px; height: 90px;
            background: var(--luxury-gradient);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.2rem;
            box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .login-title {
            font-family: 'Clash Grotesk', serif;
            font-size: 2rem; font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
        }

        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form-floating { margin-bottom: 1.5rem; }

        .form-control {
            background: white;
            border: 2px solid rgba(108, 155, 207, 0.15);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            height: 60px;
            transition: .3s;
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        .form-control:focus {
            border-color: var(--primary-soft);
            box-shadow: 0 0 0 4px rgba(108,155,207,0.1);
        }

        .form-floating label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .password-toggle {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            font-size: 1.1rem;
            z-index: 10;
        }

        .password-toggle:hover { color: var(--primary-soft); }

        .btn-login {
            width: 100%;
            padding: 1rem;
            border-radius: 14px;
            background: var(--luxury-gradient);
            border: none;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: .3s;
            box-shadow: 0 10px 30px rgba(108,155,207,0.3);
        }

        .btn-login:hover { transform: translateY(-4px); }

        .links-container {
            display: flex; justify-content: space-between;
            margin-top: 1.5rem;
        }

        .back-link, .register-link {
            font-weight: 600;
            color: var(--primary-soft);
            text-decoration: none;
            font-size: 0.95rem;
        }

        .back-link:hover, .register-link:hover { color: var(--primary-dark); }

        .alert {
            border: none;
            border-left: 4px solid;
            border-radius: 14px;
            padding: 1.2rem 1.5rem;
            animation: slideInRight .5s;
            backdrop-filter: blur(10px);
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-danger {
            border-left-color: #e63946;
            background: linear-gradient(135deg, #ffe5e9, #ffccd5);
            color: #8b1e3f;
        }

        .alert-success {
            border-left-color: #52b788;
            background: linear-gradient(135deg, #d8f3dc, #b7e4c7);
            color: #2d6a4f;
        }
    </style>

</head>
<body>

<div class="login-container">
    <div class="login-card">

        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">Login</h1>
            <p class="login-subtitle">Sistem Informasi Keamanan Kampus</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="nim_nip" name="nim_nip" placeholder="NIM/NIP" required autofocus>
                <label for="nim_nip"><i class="fas fa-user me-2"></i>NIM / NIP</label>
            </div>

            <div class="form-floating mb-3 position-relative">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </span>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label" for="remember"> Ingat Saya </label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>

        </form>

        <div class="text-center mt-3">
             <a href="forgot_password.php" class="register-link">
                 <i class="fas fa-key me-2"></i> Lupa Password?
            </a>
        </div>

        <div class="links-container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Beranda
            </a>

            <a href="register.php" class="register-link">
                Daftar Akun <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>

    </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>

</body>
</html>
