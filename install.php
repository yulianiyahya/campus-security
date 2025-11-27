<?php
/**
 * =====================================================
 * INSTALL.PHP - Database Installer
 * File ini untuk membuat database dan tabel pertama kali
 * Jalankan sekali saja: http://localhost/campus-security/install.php
 * =====================================================
 */

// Koneksi ke MySQL tanpa database
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'campus_security_system';

try {
    // Koneksi tanpa memilih database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Database Installer</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 50px 0; }
            .install-card { background: white; border-radius: 15px; padding: 40px; max-width: 800px; margin: 0 auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
            .success { color: #28a745; }
            .error { color: #dc3545; }
            .step { padding: 15px; margin: 10px 0; border-left: 4px solid #667eea; background: #f8f9fa; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='install-card'>
                <h1 class='mb-4 text-center'>📦 Database Installer</h1>
                <h4 class='mb-4'>Sistem Informasi Keamanan Kampus</h4>";
    
    // Step 1: Create Database
    echo "<div class='step'><strong>Step 1:</strong> Membuat Database...</div>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p class='success'>✓ Database '$dbname' berhasil dibuat!</p>";
    
    // Step 2: Pilih database
    $pdo->exec("USE $dbname");
    echo "<div class='step'><strong>Step 2:</strong> Memilih Database...</div>";
    echo "<p class='success'>✓ Database dipilih!</p>";
    
    // Step 3: Create Tables
    echo "<div class='step'><strong>Step 3:</strong> Membuat Tabel...</div>";
    
    // Tabel Users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nim_nip VARCHAR(50) UNIQUE NOT NULL,
            nama VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'security', 'user') DEFAULT 'user',
            phone VARCHAR(20),
            department VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'users' dibuat</p>";
    
    // Tabel Security Officers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_officers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            badge_number VARCHAR(50) UNIQUE NOT NULL,
            shift VARCHAR(50),
            area_responsibility TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'security_officers' dibuat</p>";
    
    // Tabel Incident Categories
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incident_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            icon VARCHAR(100),
            severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'incident_categories' dibuat</p>";
    
    // Tabel Incident Locations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incident_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            building_name VARCHAR(255) NOT NULL,
            floor VARCHAR(50),
            room VARCHAR(100),
            area VARCHAR(255),
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            is_high_risk BOOLEAN DEFAULT FALSE,
            INDEX idx_high_risk (is_high_risk)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'incident_locations' dibuat</p>";
    
    // Tabel Reports
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_number VARCHAR(50) UNIQUE NOT NULL,
            reporter_id INT NOT NULL,
            category_id INT NOT NULL,
            location_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            incident_datetime DATETIME NOT NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
            assigned_officer_id INT,
            resolution_notes TEXT,
            resolved_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id),
            FOREIGN KEY (category_id) REFERENCES incident_categories(id),
            FOREIGN KEY (location_id) REFERENCES incident_locations(id),
            FOREIGN KEY (assigned_officer_id) REFERENCES security_officers(id),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at),
            INDEX idx_reporter (reporter_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'reports' dibuat</p>";
    
    // Tabel Report Attachments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS report_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type ENUM('image', 'video', 'document') NOT NULL,
            file_size INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'report_attachments' dibuat</p>";
    
    // Tabel Report Actions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS report_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            officer_id INT NOT NULL,
            action_type ENUM('investigation', 'patrol', 'follow_up', 'resolution') NOT NULL,
            action_description TEXT NOT NULL,
            notes TEXT,
            action_date DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
            FOREIGN KEY (officer_id) REFERENCES security_officers(id),
            INDEX idx_action_date (action_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'report_actions' dibuat</p>";
    
    // Tabel Announcements
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_by INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            type ENUM('emergency', 'warning', 'info', 'general') DEFAULT 'general',
            priority ENUM('normal', 'high', 'urgent') DEFAULT 'normal',
            is_published BOOLEAN DEFAULT FALSE,
            publish_date DATETIME,
            expire_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id),
            INDEX idx_published (is_published),
            INDEX idx_dates (publish_date, expire_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'announcements' dibuat</p>";
    
    // Tabel Notifications
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('report_update', 'announcement', 'system') NOT NULL,
            reference_id INT,
            is_read BOOLEAN DEFAULT FALSE,
            read_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'notifications' dibuat</p>";
    
    // Tabel User Sessions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_at DATETIME,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (session_token),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✓ Tabel 'user_sessions' dibuat</p>";
    
    // Step 4: Insert Sample Data
    echo "<div class='step'><strong>Step 4:</strong> Memasukkan Data Sample...</div>";
    
    // Insert Users (Password: admin123, security123, user123)
    $pdo->exec("
        INSERT INTO users (nim_nip, nama, email, password, role, phone, department) VALUES
        ('A001', 'Administrator', 'admin@kampus.ac.id', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', '08123456789', 'IT Security'),
        ('S001', 'Bambang Sutrisno', 'bambang@kampus.ac.id', '" . password_hash('security123', PASSWORD_DEFAULT) . "', 'security', '08123456790', 'Security'),
        ('S002', 'Andi Wijaya', 'andi.security@kampus.ac.id', '" . password_hash('security123', PASSWORD_DEFAULT) . "', 'security', '08123456791', 'Security'),
        ('2024001', 'Budi Santoso', 'budi@student.kampus.ac.id', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user', '08123456792', 'Teknik Informatika'),
        ('2024002', 'Siti Nurhaliza', 'siti@student.kampus.ac.id', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user', '08123456793', 'Manajemen'),
        ('P001', 'Dr. Ahmad Dahlan', 'ahmad@kampus.ac.id', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user', '08123456794', 'Dosen Teknik')
    ");
    echo "<p class='success'>✓ Data users berhasil ditambahkan</p>";
    
    // Insert Security Officers
    $pdo->exec("
        INSERT INTO security_officers (user_id, badge_number, shift, area_responsibility) VALUES
        (2, 'SEC-001', 'Pagi', 'Gedung A, B, Area Parkir Utara'),
        (3, 'SEC-002', 'Siang', 'Gedung C, Perpustakaan, Area Olahraga')
    ");
    echo "<p class='success'>✓ Data security officers berhasil ditambahkan</p>";
    
    // Insert Categories
    $pdo->exec("
        INSERT INTO incident_categories (name, description, severity) VALUES
        ('Kehilangan Barang', 'Barang pribadi hilang di area kampus', 'medium'),
        ('Pencurian', 'Tindakan pencurian atau pengambilan barang tanpa izin', 'high'),
        ('Pelanggaran Tata Tertib', 'Pelanggaran aturan kampus', 'low'),
        ('Tindakan Mencurigakan', 'Aktivitas atau orang yang mencurigakan', 'medium'),
        ('Kerusakan Fasilitas', 'Kerusakan properti atau fasilitas kampus', 'medium'),
        ('Darurat/Emergency', 'Situasi darurat yang memerlukan penanganan segera', 'high'),
        ('Parkir Illegal', 'Parkir tidak pada tempatnya', 'low'),
        ('Akses Tidak Sah', 'Masuk ke area terlarang tanpa izin', 'high')
    ");
    echo "<p class='success'>✓ Data kategori insiden berhasil ditambahkan</p>";
    
    // Insert Locations
    $pdo->exec("
        INSERT INTO incident_locations (building_name, area, is_high_risk) VALUES
        ('Gedung A (Rektorat)', 'Area Utara', FALSE),
        ('Gedung B (Fakultas Teknik)', 'Area Timur', FALSE),
        ('Gedung C (Fakultas Ekonomi)', 'Area Selatan', FALSE),
        ('Perpustakaan Pusat', 'Area Tengah', FALSE),
        ('Gedung Olahraga', 'Area Barat', TRUE),
        ('Area Parkir Mahasiswa', 'Area Timur', TRUE),
        ('Kantin/Food Court', 'Area Tengah', TRUE),
        ('Laboratorium Komputer', 'Gedung B Lt.3', FALSE)
    ");
    echo "<p class='success'>✓ Data lokasi berhasil ditambahkan</p>";
    
    // Create Upload Directory
    $uploadDir = _DIR_ . '/uploads/reports';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "<p class='success'>✓ Folder uploads dibuat</p>";
    }
    
    echo "<hr>";
    echo "<div class='alert alert-success mt-4'>
            <h4><i class='bi bi-check-circle'></i> Instalasi Berhasil!</h4>
            <p>Database dan semua tabel berhasil dibuat. Sistem siap digunakan!</p>
            <p><strong>Akun Demo:</strong></p>
            <ul>
                <li>Admin: <code>A001</code> / <code>admin123</code></li>
                <li>Security: <code>S001</code> / <code>security123</code></li>
                <li>User: <code>2024001</code> / <code>user123</code></li>
            </ul>
            <a href='login.php' class='btn btn-primary mt-3'>Login Sekarang</a>
            <hr class='my-3'>
            <p class='mb-0'><strong>PENTING:</strong> Hapus file <code>install.php</code> setelah instalasi untuk keamanan!</p>
          </div>";
    
    echo "</div></div></body></html>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>