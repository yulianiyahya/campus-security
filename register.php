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

// Proses Registrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nim_nip = clean_input($_POST['nim_nip'] ?? '');
    $nama = clean_input($_POST['nama'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $no_hp = clean_input($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'mahasiswa';
    
    // PERBAIKAN: Ambil department dari select yang aktif
    if ($user_type === 'mahasiswa') {
        $department = clean_input($_POST['department_mahasiswa'] ?? '');
    } else {
        $department = clean_input($_POST['department_dosen_staff'] ?? '');
    }
    
    // Validasi input
    if (empty($nim_nip) || empty($nama) || empty($email) || empty($password) || empty($department)) {
        $error = 'Semua field wajib diisi!';
        
        // Debug spesifik untuk development (HAPUS di production!)
        if (empty($department)) {
            $error .= ' Department tidak boleh kosong.';
        }
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } else {
        try {
            // Cek apakah NIM/NIP sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE nim_nip = ?");
            $stmt->execute([$nim_nip]);
            if ($stmt->fetch()) {
                $error = 'NIM/NIP sudah terdaftar!';
            } else {
                // Cek apakah email sudah terdaftar
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email sudah terdaftar!';
                } else {
                    // Hash password dengan benar
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user baru
                    $stmt = $pdo->prepare("
                        INSERT INTO users (nim_nip, nama, email, phone, password, role, department, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'user', ?, 'active', NOW())
                    ");
                    
                    $result = $stmt->execute([$nim_nip, $nama, $email, $no_hp, $hashed_password, $department]);
                    
                    if ($result) {
                        redirect('login.php?registered=1');
                    } else {
                        $error = 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}


// Daftar jurusan/program studi untuk mahasiswa
$mahasiswa_departments = [
    'Teknik Informatika',
    'Teknik Elektro',
    'Teknik Sipil',
    'Teknik Mesin',
    'Teknik Industri',
    'Manajemen',
    'Akuntansi',
    'Ekonomi Pembangunan',
    'Ilmu Hukum',
    'Kedokteran',
    'Keperawatan',
    'Farmasi',
    'Matematika',
    'Fisika',
    'Kimia',
    'Biologi',
    'Ilmu Komunikasi',
    'Ilmu Politik',
    'Sosiologi',
    'Sastra Indonesia',
    'Sastra Inggris',
    'Sastra Jepang'
];

// Daftar unit kerja untuk dosen/staff
$dosen_staff_departments = [
    'Fakultas Teknik',
    'Fakultas Ekonomi dan Bisnis',
    'Fakultas Hukum',
    'Fakultas Kedokteran',
    'Fakultas MIPA',
    'Fakultas Ilmu Sosial dan Politik',
    'Fakultas Sastra',
    'Rektorat',
    'Administrasi Umum',
    'Sarana & Prasarana',
    'Kemahasiswaan',
    'Teknologi Informasi',
    'Perpustakaan',
    'Laboratorium',
    'Keuangan'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Font Stack -->
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
            padding: 40px 0;
            position: relative;
            overflow-x: hidden;
            color: var(--text-primary);
        }

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
        
        .register-container {
            width: 100%;
            max-width: 550px;
            padding: 20px;
            position: relative;
            z-index: 10;
        }
        
        .register-card {
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

        .register-card::before {
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
        
        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .register-icon {
            width: 80px;
            height: 80px;
            background: var(--luxury-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .register-title {
            font-family: 'Clash Grotesk', serif;
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
            letter-spacing: -0.8px;
        }
        
        .register-subtitle {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .form-floating {
            margin-bottom: 1.3rem;
            position: relative;
        }
        
        .form-control, .form-select {
            background: white;
            border: 2px solid rgba(108, 155, 207, 0.15);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            height: 58px;
            transition: all 0.3s ease;
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-soft);
            box-shadow: 0 0 0 4px rgba(108, 155, 207, 0.1);
            background: white;
        }

        .form-floating > label {
            color: var(--text-secondary);
            font-family: 'Satoshi', sans-serif;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 1.15rem 1.2rem;
        }

        .user-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-type-option {
            position: relative;
        }

        .user-type-option input[type="radio"] {
            display: none;
        }

        .user-type-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem 1rem;
            border: 2px solid rgba(108, 155, 207, 0.15);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            text-align: center;
        }

        .user-type-label i {
            font-size: 2rem;
            color: var(--primary-soft);
            margin-bottom: 0.5rem;
        }

        .user-type-label span {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .user-type-option input[type="radio"]:checked + .user-type-label {
            border-color: var(--primary-soft);
            background: linear-gradient(135deg, rgba(108, 155, 207, 0.05) 0%, rgba(184, 212, 236, 0.1) 100%);
        }

        .user-type-option input[type="radio"]:checked + .user-type-label::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--primary-soft);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .department-field {
            display: none;
        }

        .department-field.active {
            display: block;
        }
        
        .btn-register {
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
            transition: all 0.4s ease;
            box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3);
            font-family: 'Satoshi', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .btn-register::before {
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

        .btn-register:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .btn-register:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        
        .login-link {
            display: inline-block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-soft);
            text-decoration: none;
            font-weight: 600;
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .login-link:hover {
            color: var(--primary-dark);
            transform: translateX(-4px);
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
            animation: slideInRight 0.5s ease;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
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

        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .password-strength-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: 'Satoshi', sans-serif;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .register-card {
                padding: 2rem 1.5rem;
            }

            .register-title {
                font-size: 1.6rem;
            }

            .register-icon {
                width: 70px;
                height: 70px;
                font-size: 1.7rem;
            }

            .links-container {
                flex-direction: column;
                gap: 1rem;
            }

            .user-type-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="register-title">Daftar Akun</h1>
                <p class="register-subtitle">Buat akun baru untuk melaporkan kejadian keamanan</p>
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
            
            <form method="POST" action="" id="registerForm">
                <!-- User Type Selector -->
                <div class="user-type-selector">
                    <div class="user-type-option">
                        <input type="radio" id="type_mahasiswa" name="user_type" value="mahasiswa" checked>
                        <label for="type_mahasiswa" class="user-type-label">
                            <i class="fas fa-user-graduate"></i>
                            <span>Mahasiswa</span>
                        </label>
                    </div>
                    <div class="user-type-option">
                        <input type="radio" id="type_dosen_staff" name="user_type" value="dosen_staff">
                        <label for="type_dosen_staff" class="user-type-label">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Dosen / Staff</span>
                        </label>
                    </div>
                </div>

                <div class="form-floating">
                    <input type="text" class="form-control" id="nim_nip" name="nim_nip" 
                           placeholder="NIM/NIP" required autofocus>
                    <label for="nim_nip">
                        <i class="fas fa-id-card me-2"></i><span id="nim_nip_label">NIM</span>
                    </label>
                </div>
                
                <div class="form-floating">
                    <input type="text" class="form-control" id="nama" name="nama" 
                           placeholder="Nama Lengkap" required>
                    <label for="nama">
                        <i class="fas fa-user me-2"></i>Nama Lengkap
                    </label>
                </div>
                
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Email" required>
                    <label for="email">
                        <i class="fas fa-envelope me-2"></i>Email
                    </label>
                </div>
                
                <div class="form-floating">
                    <input type="tel" class="form-control" id="no_hp" name="no_hp" 
                           placeholder="No. HP">
                    <label for="no_hp">
                        <i class="fas fa-phone me-2"></i>No. HP (Opsional)
                    </label>
                </div>

                <!-- Department untuk Mahasiswa -->
                <div class="form-floating department-field active" id="department_mahasiswa">
                    <select class="form-select" name="department_mahasiswa" id="department_mahasiswa_select" required>
                        <option value="" selected>Pilih Jurusan/Program Studi</option>
                        <?php foreach ($mahasiswa_departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>">
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="department_mahasiswa_select">
                        <i class="fas fa-graduation-cap me-2"></i>Jurusan/Program Studi
                    </label>
                </div>

                <!-- Department untuk Dosen/Staff -->
                <div class="form-floating department-field" id="department_dosen_staff">
                    <select class="form-select" name="department_dosen_staff" id="department_dosen_staff_select">
                        <option value="" selected>Pilih Unit Kerja</option>
                        <?php foreach ($dosen_staff_departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>">
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="department_dosen_staff_select">
                        <i class="fas fa-building me-2"></i>Unit Kerja
                    </label>
                </div>
                
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </span>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
                <div class="password-strength-text" id="strengthText">Minimal 6 karakter</div>
                
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           placeholder="Konfirmasi Password" required>
                    <label for="confirm_password">
                        <i class="fas fa-lock me-2"></i>Konfirmasi Password
                    </label>
                    <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </span>
                </div>
                
                <button type="submit" class="btn btn-register mt-3">
                    <i class="fas fa-user-plus me-2"></i>Daftar
                </button>
            </form>
            
            <div class="links-container">
                <a href="index.php" class="login-link">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Beranda
                </a>
                <a href="login.php" class="login-link">
                    Sudah punya akun?<i class="fas fa-sign-in-alt ms-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle User Type
        document.querySelectorAll('input[name="user_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const mahasiswaField = document.getElementById('department_mahasiswa');
                const dosenStaffField = document.getElementById('department_dosen_staff');
                const mahasiswaSelect = document.getElementById('department_mahasiswa_select');
                const dosenStaffSelect = document.getElementById('department_dosen_staff_select');
                const nimNipLabel = document.getElementById('nim_nip_label');
                
                if (this.value === 'mahasiswa') {
                    mahasiswaField.classList.add('active');
                    dosenStaffField.classList.remove('active');
                    mahasiswaSelect.required = true;
                    dosenStaffSelect.required = false;
                    dosenStaffSelect.value = '';
                    nimNipLabel.textContent = 'NIM';
                } else {
                    dosenStaffField.classList.add('active');
                    mahasiswaField.classList.remove('active');
                    dosenStaffSelect.required = true;
                    mahasiswaSelect.required = false;
                    mahasiswaSelect.value = '';
                    nimNipLabel.textContent = 'NIP';
                }
            });
        });

        // Toggle Password Visibility
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
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

        // Password Strength Checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Password lemah';
                    color = '#e63946';
                    break;
                case 2:
                case 3:
                    text = 'Password sedang';
                    color = '#f77f00';
                    break;
                case 4:
                case 5:
                    text = 'Password kuat';
                    color = '#52b788';
                    break;
            }
            
            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.background = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
        });

        // Form Validation - PERBAIKAN DI SINI!
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const userType = document.querySelector('input[name="user_type"]:checked').value;
            const departmentHidden = document.getElementById('department');
            let department = '';
            
            // Ambil nilai department dari dropdown yang aktif
            if (userType === 'mahasiswa') {
                department = document.getElementById('department_mahasiswa_select').value;
            } else {
                department = document.getElementById('department_dosen_staff_select').value;
            }
            
            // SET NILAI KE HIDDEN FIELD - INI YANG PENTING!
            departmentHidden.value = department;
            
            // Validasi
            if (!department) {
                e.preventDefault();
                alert('Silakan pilih jurusan/unit kerja!');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }
            
            // Form akan di-submit dengan department yang sudah terisi
        });

        // Auto dismiss alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.animation = 'slideInRight 0.5s ease reverse';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>