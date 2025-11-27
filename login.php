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
                    INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
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
    
    <!-- Premium Font Stack -->
    <link href="https://fonts.googleapis.com/css2?family=Clash+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Satoshi:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Soft Blue Palette */
            --primary-soft: #6C9BCF;
            --primary-light: #8BB4DB;
            --primary-lighter: #B8D4EC;
            --primary-dark: #5681B3;
            
            /* Premium Gradients */
            --luxury-gradient: linear-gradient(135deg, #6C9BCF 0%, #8BB4DB 50%, #B8D4EC 100%);
            
            /* Backgrounds */
            --body-bg: #f5f9ff;
            --card-bg: #ffffff;
            
            /* Text Colors */
            --text-primary: #2c3e50;
            --text-secondary: #5a6c7d;
            --text-light: #95a5a6;
            
            /* Glass & Shadow */
            --glass-white: rgba(255, 255, 255, 0.95);
            --glass-blue: rgba(108, 155, 207, 0.08);
            --shadow-elegant: 0 10px 40px rgba(108, 155, 207, 0.12);
            --shadow-hover: 0 20px 60px rgba(108, 155, 207, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Satoshi', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--body-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Elegant Floating Orbs Background */
        body::before,
        body::after {
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
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, #6C9BCF, transparent);
            top: -250px;
            right: -250px;
        }

        body::after {
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, #B8D4EC, transparent);
            bottom: -200px;
            left: -200px;
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
            position: relative;
            z-index: 10;
        }
        
        .login-card {
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(108, 155, 207, 0.15);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-elegant);
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--luxury-gradient);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .login-icon {
            width: 90px;
            height: 90px;
            background: var(--luxury-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.2rem;
            box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3);
            position: relative;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 15px 40px rgba(108, 155, 207, 0.4); }
        }
        
        .login-title {
            font-family: 'Clash Grotesk', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
            letter-spacing: -0.8px;
        }
        
        .login-subtitle {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
            letter-spacing: 0.3px;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-control {
            background: white;
            border: 2px solid rgba(108, 155, 207, 0.15);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            height: 60px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary-soft);
            box-shadow: 0 0 0 4px rgba(108, 155, 207, 0.1);
            background: white;
        }

        .form-floating > label {
            color: var(--text-secondary);
            font-family: 'Satoshi', sans-serif;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 1.2rem 1.2rem;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 14px;
            background: var(--luxury-gradient);
            border: none;
            color: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3);
            font-family: 'Satoshi', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-login:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .btn-login:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .btn-login:active {
            transform: translateY(-2px);
        }
        
        .back-link {
            display: inline-block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-soft);
            text-decoration: none;
            font-weight: 600;
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-4px);
        }

        .register-link {
            display: inline-block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-soft);
            text-decoration: none;
            font-weight: 600;
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }
        
        .register-link:hover {
            color: var(--primary-dark);
            transform: translateX(4px);
        }

        .links-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }
        
        .alert {
            border: none;
            border-left: 4px solid;
            border-radius: 14px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: slideInRight 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
            font-family: 'Satoshi', sans-serif;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-danger {
            border-left-color: #e63946;
            background: linear-gradient(135deg, #ffe5e9 0%, #ffccd5 50%);
            color: #8b1e3f;
        }

        .alert-success {
            border-left-color: #52b788;
            background: linear-gradient(135deg, #d8f3dc 0%, #b7e4c7 50%);
            color: #2d6a4f;
        }
        
        .password-toggle {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            z-index: 10;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary-soft);
            transform: translateY(-50%) scale(1.1);
        }

        .form-check {
            margin-bottom: 1.5rem;
        }

        .form-check-input:checked {
            background-color: var(--primary-soft);
            border-color: var(--primary-soft);
        }

        .form-check-input:focus {
            border-color: var(--primary-soft);
            box-shadow: 0 0 0 0.2rem rgba(108, 155, 207, 0.15);
        }

        .form-check-label {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-card {
                padding: 2rem 1.5rem;
            }

            .login-title {
                font-size: 1.6rem;
            }

            .login-subtitle {
                font-size: 0.88rem;
            }

            .login-icon {
                width: 75px;
                height: 75px;
                font-size: 1.8rem;
            }

            .links-container {
                flex-direction: column;
                gap: 1rem;
            }

            .back-link, .register-link {
                margin-top: 0;
            }
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
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nim_nip" name="nim_nip" 
                           placeholder="NIM/NIP" required autofocus>
                    <label for="nim_nip">
                        <i class="fas fa-user me-2"></i>NIM / NIP
                    </label>
                </div>
                
                <div class="form-floating mb-3 position-relative">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Ingat Saya
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <div class="links-container">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Beranda
                </a>
                <a href="register.php" class="register-link">
                    Daftar Akun<i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Password Visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Auto dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.animation = 'slideInRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) reverse';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>