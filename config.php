<?php
/**
 * =====================================================
 * CONFIG.PHP - Database Configuration
 * Sistem Informasi Keamanan Kampus
 * =====================================================
 */

// Pengaturan Error Reporting (untuk development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone Indonesia
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// DATABASE CONFIGURATION
// =====================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'campus_security_system');

// =====================================================
// SITE CONFIGURATION
// =====================================================
define('SITE_NAME', 'Sistem Keamanan Kampus');
define('SITE_URL', 'http://localhost/campus-security/');
define('BASE_PATH', __DIR__);

// =====================================================
// UPLOAD CONFIGURATION
// =====================================================
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// =====================================================
// SESSION CONFIGURATION
// =====================================================
define('SESSION_LIFETIME', 3600 * 24); // 24 jam
define('SESSION_NAME', 'campus_security_session');

// =====================================================
// KONEKSI DATABASE MENGGUNAKAN PDO
// =====================================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// =====================================================
// START SESSION (pindahkan ke atas sebelum fungsi)
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// =====================================================
// AUTO LOGOUT JIKA SESSION EXPIRED
// =====================================================
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . 'login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// =====================================================
// AUTHENTICATION FUNCTIONS
// =====================================================

/**
 * Cek apakah user sudah login - REDIRECT jika belum
 */
if (!function_exists('check_login')) {
    function check_login() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            header('Location: ' . SITE_URL . 'login.php');
            exit();
        }
    }
}

/**
 * Cek role user - REDIRECT jika tidak sesuai
 */
if (!function_exists('check_role')) {
    function check_role($allowed_roles = []) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            header('Location: ' . SITE_URL . 'login.php');
            exit();
        }
        
        if (!in_array($_SESSION['role'], $allowed_roles)) {
            header('Location: ' . SITE_URL . 'unauthorized.php');
            exit();
        }
    }
}

/**
 * Get current user data dari database
 */
if (!function_exists('get_current_user')) {
    function get_current_user() {
        global $pdo;
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}

/**
 * Cek apakah user sudah login (return boolean)
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Redirect ke halaman lain
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        // Jika URL sudah lengkap dengan http/https, gunakan langsung
        if (strpos($url, 'http') === 0) {
            header("Location: " . $url);
        } else {
            header("Location: " . SITE_URL . $url);
        }
        exit();
    }
}

/**
 * Sanitasi input dari user
 */
if (!function_exists('clean_input')) {
    function clean_input($data) {
        if (is_array($data)) {
            return array_map('clean_input', $data);
        }
        
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

/**
 * Generate nomor laporan otomatis
 */
if (!function_exists('generate_report_number')) {
    function generate_report_number($pdo) {
        $date = date('Ymd');
        $prefix = "REP-{$date}-";
        
        $stmt = $pdo->prepare("SELECT report_number FROM reports WHERE report_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();
        
        if ($result) {
            $last_number = (int) substr($result['report_number'], -4);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }
}

// =====================================================
// DATE & TIME FORMATTING FUNCTIONS
// =====================================================

/**
 * Format datetime - FUNGSI YANG HILANG INI PENYEBAB ERROR!
 */
if (!function_exists('format_datetime')) {
    function format_datetime($datetime) {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
            return '-';
        }
        
        $timestamp = strtotime($datetime);
        return date('d/m/Y H:i', $timestamp);
    }
}

/**
 * Format tanggal Indonesia
 */
if (!function_exists('format_tanggal')) {
    function format_tanggal($date) {
        if (empty($date) || $date == '0000-00-00 00:00:00') {
            return '-';
        }
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $hari = date('d', $timestamp);
        $bulan_num = date('n', $timestamp);
        $tahun = date('Y', $timestamp);
        $waktu = date('H:i', $timestamp);
        
        return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun . ' ' . $waktu;
    }
}

/**
 * Format date only (tanpa waktu)
 */
if (!function_exists('format_date')) {
    function format_date($date) {
        if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
            return '-';
        }
        
        $bulan = [
            1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
        ];
        
        $timestamp = strtotime($date);
        $hari = date('d', $timestamp);
        $bulan_num = date('n', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
    }
}

/**
 * Format time only
 */
if (!function_exists('format_time')) {
    function format_time($time) {
        if (empty($time)) {
            return '-';
        }
        
        $timestamp = strtotime($time);
        return date('H:i', $timestamp);
    }
}

/**
 * Time ago function
 */
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if (empty($datetime)) {
            return '-';
        }
        
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;
        
        if ($difference < 60) {
            return $difference . ' detik yang lalu';
        } elseif ($difference < 3600) {
            $minutes = floor($difference / 60);
            return $minutes . ' menit yang lalu';
        } elseif ($difference < 86400) {
            $hours = floor($difference / 3600);
            return $hours . ' jam yang lalu';
        } elseif ($difference < 604800) {
            $days = floor($difference / 86400);
            return $days . ' hari yang lalu';
        } elseif ($difference < 2592000) {
            $weeks = floor($difference / 604800);
            return $weeks . ' minggu yang lalu';
        } else {
            return date('d M Y', $timestamp);
        }
    }
}

/**
 * Get Indonesian day name
 */
if (!function_exists('get_indonesian_day')) {
    function get_indonesian_day($datetime) {
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];
        
        $timestamp = strtotime($datetime);
        $day = date('l', $timestamp);
        
        return $days[$day] ?? $day;
    }
}

/**
 * Format datetime in Indonesian (lengkap)
 */
if (!function_exists('format_datetime_indonesian')) {
    function format_datetime_indonesian($datetime) {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
            return '-';
        }
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($datetime);
        $day = get_indonesian_day($datetime);
        $day_num = date('d', $timestamp);
        $month = $bulan[(int)date('m', $timestamp)];
        $year = date('Y', $timestamp);
        $time = date('H:i', $timestamp);
        
        return "$day, $day_num $month $year - $time WIB";
    }
}

// =====================================================
// STATUS & PRIORITY FUNCTIONS
// =====================================================

/**
 * Get status badge HTML
 */
if (!function_exists('get_status_badge')) {
    function get_status_badge($status) {
        $badges = [
            'new' => '<span class="badge bg-warning">Baru</span>',
            'in_progress' => '<span class="badge bg-info">Dalam Proses</span>',
            'resolved' => '<span class="badge bg-success">Selesai</span>',
            'closed' => '<span class="badge bg-secondary">Ditutup</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
}

/**
 * Get status text
 */
if (!function_exists('get_status_text')) {
    function get_status_text($status) {
        $texts = [
            'new' => 'Baru',
            'in_progress' => 'Dalam Proses',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup'
        ];
        
        return $texts[$status] ?? ucfirst($status);
    }
}

/**
 * Get status badge class (Bootstrap)
 */
if (!function_exists('get_status_badge_class')) {
    function get_status_badge_class($status) {
        $badges = [
            'new' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            'closed' => 'secondary'
        ];
        
        return $badges[$status] ?? 'secondary';
    }
}

/**
 * Get priority badge HTML
 */
if (!function_exists('get_priority_badge')) {
    function get_priority_badge($priority) {
        $badges = [
            'low' => '<span class="badge bg-info">Rendah</span>',
            'medium' => '<span class="badge bg-warning">Sedang</span>',
            'high' => '<span class="badge bg-danger">Tinggi</span>',
            'urgent' => '<span class="badge bg-danger">Mendesak</span>',
        ];
        
        return $badges[$priority] ?? '<span class="badge bg-secondary">-</span>';
    }
}

/**
 * Get priority text
 */
if (!function_exists('get_priority_text')) {
    function get_priority_text($priority) {
        $texts = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak'
        ];
        
        return $texts[$priority] ?? ucfirst($priority);
    }
}

/**
 * Get priority badge class (Bootstrap)
 */
if (!function_exists('get_priority_badge_class')) {
    function get_priority_badge_class($priority) {
        $badges = [
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'danger',
            'urgent' => 'danger'
        ];
        
        return $badges[$priority] ?? 'secondary';
    }
}

// =====================================================
// FILE UPLOAD FUNCTIONS
// =====================================================

/**
 * Format file size
 */
if (!function_exists('format_filesize')) {
    function format_filesize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}

/**
 * Sanitize filename
 */
if (!function_exists('sanitize_filename')) {
    function sanitize_filename($filename) {
        // Remove any non-alphanumeric characters except dots, dashes and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return $filename;
    }
}

/**
 * Check if file is image
 */
if (!function_exists('is_image')) {
    function is_image($file_type) {
        $image_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        return in_array(strtolower($file_type), $image_types);
    }
}

// =====================================================
// STRING & VALIDATION FUNCTIONS
// =====================================================

/**
 * Truncate text
 */
if (!function_exists('truncate')) {
    function truncate($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
}

/**
 * Validate email
 */
if (!function_exists('is_valid_email')) {
    function is_valid_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Validate phone number (Indonesian format)
 */
if (!function_exists('is_valid_phone')) {
    function is_valid_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it starts with 08 or 628 and has 10-13 digits
        if (preg_match('/^(08|628)[0-9]{8,11}$/', $phone)) {
            return true;
        }
        
        return false;
    }
}

/**
 * Format phone number
 */
if (!function_exists('format_phone')) {
    function format_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) >= 10) {
            return preg_replace('/(\d{4})(\d{4})(\d+)/', '$1-$2-$3', $phone);
        }
        
        return $phone;
    }
}

/**
 * Generate random string
 */
if (!function_exists('generate_random_string')) {
    function generate_random_string($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
}

// =====================================================
// FLASH MESSAGE FUNCTIONS
// =====================================================

/**
 * Set flash message
 */
if (!function_exists('set_flash')) {
    function set_flash($type, $message) {
        $_SESSION['flash_type'] = $type;
        $_SESSION['flash_message'] = $message;
    }
}

/**
 * Get flash message
 */
if (!function_exists('get_flash')) {
    function get_flash() {
        if (isset($_SESSION['flash_message'])) {
            $message = [
                'type' => $_SESSION['flash_type'],
                'message' => $_SESSION['flash_message']
            ];
            unset($_SESSION['flash_type']);
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
}

/**
 * Display flash message (auto-generate HTML)
 */
if (!function_exists('display_flash')) {
    function display_flash() {
        $flash = get_flash();
        if ($flash) {
            $alert_class = $flash['type'] === 'error' ? 'danger' : $flash['type'];
            echo '<div class="alert alert-' . $alert_class . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($flash['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
        }
    }
}

// =====================================================
// CSRF TOKEN FUNCTIONS
// =====================================================

/**
 * Generate CSRF token
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify CSRF token
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * CSRF token input field (HTML)
 */
if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = generate_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

// =====================================================
// NOTIFICATION FUNCTIONS
// =====================================================

/**
 * Get unread notification count for user
 */
if (!function_exists('get_unread_notification_count')) {
    function get_unread_notification_count($user_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

?>