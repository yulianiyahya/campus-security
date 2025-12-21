<?php
require_once '../config.php';

// Check if user is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

// Get parameters
$type = $_GET['type'] ?? 'summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="statistik_' . $type . '_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 (untuk Excel compatibility)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Export based on type
switch ($type) {
    case 'summary':
        exportSummary($pdo, $output, $start_date, $end_date);
        break;
    
    case 'category':
        exportCategory($pdo, $output, $start_date, $end_date);
        break;
    
    case 'priority':
        exportPriority($pdo, $output, $start_date, $end_date);
        break;
    
    case 'location':
        exportLocation($pdo, $output, $start_date, $end_date);
        break;
    
    case 'officer':
        exportOfficer($pdo, $output, $start_date, $end_date);
        break;
    
    case 'monthly':
        exportMonthly($pdo, $output);
        break;
    
    case 'all':
        exportAll($pdo, $output, $start_date, $end_date);
        break;
    
    default:
        exportSummary($pdo, $output, $start_date, $end_date);
}

fclose($output);
exit;

// ==================== EXPORT FUNCTIONS ====================

function exportSummary($pdo, $output, $start_date, $end_date) {
    // Header
    fputcsv($output, ['RINGKASAN STATISTIK LAPORAN']);
    fputcsv($output, ['Periode: ' . $start_date . ' s/d ' . $end_date]);
    fputcsv($output, ['Tanggal Export: ' . date('d-m-Y H:i:s')]);
    fputcsv($output, []);
    
    // Get statistics
    $sql = "SELECT 
            COUNT(*) as total_reports,
            COUNT(CASE WHEN status = 'new' THEN 1 END) as new_reports,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
            COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority,
            COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
            COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
            COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_priority
            FROM reports
            WHERE DATE(created_at) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $stats = $stmt->fetch();
    
    // Status Section
    fputcsv($output, ['STATISTIK BERDASARKAN STATUS']);
    fputcsv($output, ['Status', 'Jumlah']);
    fputcsv($output, ['Total Laporan', $stats['total_reports']]);
    fputcsv($output, ['Laporan Baru', $stats['new_reports']]);
    fputcsv($output, ['Sedang Ditangani', $stats['in_progress']]);
    fputcsv($output, ['Selesai', $stats['resolved']]);
    fputcsv($output, []);
    
    // Priority Section
    fputcsv($output, ['STATISTIK BERDASARKAN PRIORITAS']);
    fputcsv($output, ['Prioritas', 'Jumlah']);
    fputcsv($output, ['Rendah', $stats['low_priority']]);
    fputcsv($output, ['Sedang', $stats['medium_priority']]);
    fputcsv($output, ['Tinggi', $stats['high_priority']]);
    fputcsv($output, ['Darurat', $stats['urgent_priority']]);
    fputcsv($output, []);
    
    // Average response time
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_hours
            FROM reports
            WHERE status IN ('in_progress', 'resolved')
            AND DATE(created_at) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $avg = $stmt->fetch();
    
    fputcsv($output, ['RATA-RATA WAKTU RESPON']);
    fputcsv($output, ['Metric', 'Nilai']);
    fputcsv($output, ['Rata-rata Waktu Respon (jam)', round($avg['avg_response_hours'] ?? 0, 2)]);
}

function exportCategory($pdo, $output, $start_date, $end_date) {
    fputcsv($output, ['LAPORAN PER KATEGORI']);
    fputcsv($output, ['Periode: ' . $start_date . ' s/d ' . $end_date]);
    fputcsv($output, []);
    fputcsv($output, ['No', 'Kategori', 'Total Laporan']);
    
    $sql = "SELECT ic.name, COUNT(r.id) as total
            FROM incident_categories ic
            LEFT JOIN reports r ON ic.id = r.category_id 
                AND DATE(r.created_at) BETWEEN ? AND ?
            GROUP BY ic.id
            ORDER BY total DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    
    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$no++, $row['name'], $row['total']]);
    }
}

function exportPriority($pdo, $output, $start_date, $end_date) {
    fputcsv($output, ['LAPORAN PER PRIORITAS']);
    fputcsv($output, ['Periode: ' . $start_date . ' s/d ' . $end_date]);
    fputcsv($output, []);
    fputcsv($output, ['Prioritas', 'Jumlah Laporan']);
    
    $sql = "SELECT 
            COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority,
            COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
            COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
            COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_priority
            FROM reports
            WHERE DATE(created_at) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $stats = $stmt->fetch();
    
    fputcsv($output, ['Rendah', $stats['low_priority']]);
    fputcsv($output, ['Sedang', $stats['medium_priority']]);
    fputcsv($output, ['Tinggi', $stats['high_priority']]);
    fputcsv($output, ['Darurat', $stats['urgent_priority']]);
}

function exportLocation($pdo, $output, $start_date, $end_date) {
    fputcsv($output, ['TOP LOKASI KEJADIAN']);
    fputcsv($output, ['Periode: ' . $start_date . ' s/d ' . $end_date]);
    fputcsv($output, []);
    fputcsv($output, ['No', 'Nama Gedung', 'Lantai', 'Total Laporan']);
    
    $sql = "SELECT il.building_name, il.floor, COUNT(r.id) as total
            FROM incident_locations il
            LEFT JOIN reports r ON il.id = r.location_id 
                AND DATE(r.created_at) BETWEEN ? AND ?
            GROUP BY il.id
            HAVING total > 0
            ORDER BY total DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    
    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++, 
            $row['building_name'], 
            $row['floor'] ?? '-', 
            $row['total']
        ]);
    }
}

function exportOfficer($pdo, $output, $start_date, $end_date) {
    fputcsv($output, ['KINERJA PETUGAS KEAMANAN']);
    fputcsv($output, ['Periode: ' . $start_date . ' s/d ' . $end_date]);
    fputcsv($output, []);
    fputcsv($output, ['No', 'Nama Petugas', 'Badge Number', 'Total Ditangani', 'Selesai', 'Dalam Progress']);
    
    $sql = "SELECT 
            u.nama as officer_name,
            so.badge_number,
            COUNT(r.id) as total_handled,
            COUNT(CASE WHEN r.status = 'resolved' THEN 1 END) as resolved,
            COUNT(CASE WHEN r.status = 'in_progress' THEN 1 END) as in_progress
            FROM security_officers so
            JOIN users u ON so.user_id = u.id
            LEFT JOIN reports r ON so.id = r.assigned_officer_id
                AND DATE(r.created_at) BETWEEN ? AND ?
            WHERE u.status = 'active'
            GROUP BY so.id
            ORDER BY total_handled DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    
    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++,
            $row['officer_name'],
            $row['badge_number'],
            $row['total_handled'],
            $row['resolved'],
            $row['in_progress']
        ]);
    }
}

function exportMonthly($pdo, $output) {
    fputcsv($output, ['TREN LAPORAN BULANAN (6 BULAN TERAKHIR)']);
    fputcsv($output, []);
    fputcsv($output, ['Bulan', 'Total Laporan']);
    
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%b %Y') as month_name,
            COUNT(*) as total
            FROM reports
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month, month_name
            ORDER BY month ASC";
    $result = $pdo->query($sql);
    
    while ($row = $result->fetch()) {
        fputcsv($output, [$row['month_name'], $row['total']]);
    }
}

function exportAll($pdo, $output, $start_date, $end_date) {
    fputcsv($output, ['LAPORAN LENGKAP SEMUA DATA']);
    fputcsv($output, ['Periode: ' . $start_date . ' s/d ' . $end_date]);
    fputcsv($output, ['Tanggal Export: ' . date('d-m-Y H:i:s')]);
    fputcsv($output, []);
    
    fputcsv($output, [
        'No',
        'ID Laporan',
        'Tanggal',
        'Pelapor',
        'Kategori',
        'Lokasi',
        'Lantai',
        'Prioritas',
        'Status',
        'Petugas',
        'Deskripsi'
    ]);
    
    $sql = "SELECT 
            r.id,
            r.report_number,
            r.created_at,
            u.nama as reporter_name,
            ic.name as category_name,
            il.building_name,
            il.floor,
            r.priority,
            r.status,
            uo.nama as officer_name,
            r.description
            FROM reports r
            LEFT JOIN users u ON r.reporter_id = u.id
            LEFT JOIN incident_categories ic ON r.category_id = ic.id
            LEFT JOIN incident_locations il ON r.location_id = il.id
            LEFT JOIN security_officers so ON r.assigned_officer_id = so.id
            LEFT JOIN users uo ON so.user_id = uo.id
            WHERE DATE(r.created_at) BETWEEN ? AND ?
            ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    
    $no = 1;
    while ($row = $stmt->fetch()) {
        // Status mapping
        $status_map = [
            'new' => 'Baru',
            'in_progress' => 'Dalam Proses',
            'resolved' => 'Selesai'
        ];
        
        // Priority mapping
        $priority_map = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Darurat'
        ];
        
        fputcsv($output, [
            $no++,
            $row['report_number'],
            date('d-m-Y H:i', strtotime($row['created_at'])),
            $row['reporter_name'] ?? 'Unknown',
            $row['category_name'] ?? '-',
            $row['building_name'] ?? '-',
            $row['floor'] ?? '-',
            $priority_map[$row['priority']] ?? $row['priority'],
            $status_map[$row['status']] ?? $row['status'],
            $row['officer_name'] ?? 'Belum ditugaskan',
            strip_tags($row['description'])
        ]);
    }
}
?>