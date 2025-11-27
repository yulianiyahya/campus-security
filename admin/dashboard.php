<?php
require_once '../config.php';
check_login();
check_role(['admin']);

$page_title = 'Dashboard Admin';

// Get statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
$stats['total_users'] = $stmt->fetchColumn();

// Total reports
$stmt = $pdo->query("SELECT COUNT(*) FROM reports");
$stats['total_reports'] = $stmt->fetchColumn();

// Reports by status
$stmt = $pdo->query("SELECT 
    COALESCE(SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END), 0) as new_reports,
    COALESCE(SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END), 0) as assigned_reports,
    COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress_reports,
    COALESCE(SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END), 0) as resolved_reports
FROM reports");
$status_stats = $stmt->fetch();

// Pastikan tidak ada nilai NULL
$status_stats['new_reports'] = $status_stats['new_reports'] ?? 0;
$status_stats['assigned_reports'] = $status_stats['assigned_reports'] ?? 0;
$status_stats['in_progress_reports'] = $status_stats['in_progress_reports'] ?? 0;
$status_stats['resolved_reports'] = $status_stats['resolved_reports'] ?? 0;

// Total security officers
$stmt = $pdo->query("SELECT COUNT(*) FROM security_officers");
$stats['total_officers'] = $stmt->fetchColumn();

// Recent reports
$stmt = $pdo->query("
    SELECT r.*, u.nama as reporter_name, ic.name as category_name,
           il.building_name, il.floor
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    JOIN incident_categories ic ON r.category_id = ic.id
    JOIN incident_locations il ON r.location_id = il.id
    ORDER BY r.created_at DESC
    LIMIT 10
");
$recent_reports = $stmt->fetchAll();

// Active announcements - FIXED QUERY
$stmt = $pdo->query("
    SELECT a.*, u.nama as author_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.is_published = 1
    AND a.publish_date <= NOW()
    AND (a.expire_date IS NULL OR a.expire_date > NOW())
    ORDER BY 
        CASE a.priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
        END,
        a.created_at DESC
    LIMIT 5
");
$announcements = $stmt->fetchAll();

// Helper function untuk badge color
function getAnnouncementBadgeColor($type, $priority) {
    // Prioritas lebih tinggi dari type
    if ($priority === 'urgent') return 'danger';
    if ($priority === 'high') return 'warning';
    
    // Jika prioritas normal, lihat dari type
    $type_colors = [
        'emergency' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        'general' => 'primary'
    ];
    return $type_colors[$type] ?? 'info';
}

// Helper function untuk icon
function getAnnouncementIcon($type) {
    $icons = [
        'emergency' => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle',
        'general' => 'bullhorn'
    ];
    return $icons[$type] ?? 'info-circle';
}

require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<!-- Page Header with Logout Button -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>Dashboard Admin</h2>
        <p class="text-muted mb-0">Selamat datang, <?php echo htmlspecialchars($current_user['nama'] ?? 'Admin'); ?>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Pengguna</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_users']); ?></h2>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Laporan</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_reports']); ?></h2>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-file-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Laporan Baru</h6>
                        <h2 class="mb-0"><?php echo number_format((int)$status_stats['new_reports']); ?></h2>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Petugas Aktif</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_officers']); ?></h2>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-user-shield fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Status Chart -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Status Laporan</h5>
            </div>
            <div class="card-body">
                <?php if ($stats['total_reports'] > 0): ?>
                    <canvas id="statusChart"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-pie fa-3x mb-3 opacity-50"></i>
                        <p>Belum ada data laporan untuk ditampilkan</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Pengumuman Aktif</h5>
                <a href="announcements.php" class="btn btn-sm btn-primary">Kelola</a>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($announcements)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-info-circle fa-3x mb-3 opacity-50"></i>
                        <p>Belum ada pengumuman aktif</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="alert alert-<?php echo getAnnouncementBadgeColor($ann['type'], $ann['priority']); ?> mb-3" role="alert">
                            <div class="d-flex align-items-start">
                                <!-- Icon & Title -->
                                <div class="flex-grow-1">
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-<?php echo getAnnouncementIcon($ann['type']); ?> me-2"></i>
                                        <?php echo htmlspecialchars($ann['title']); ?>
                                        
                                        <!-- Priority Badge -->
                                        <?php if ($ann['priority'] === 'urgent' || $ann['priority'] === 'high'): ?>
                                            <span class="badge bg-danger float-end">
                                                <?php echo strtoupper($ann['priority']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h6>
                                    
                                    <!-- Image if exists -->
                                    <?php if (!empty($ann['image_path'])): ?>
                                        <div class="mb-2">
                                            <img src="../<?php echo htmlspecialchars($ann['image_path']); ?>" 
                                                 alt="Announcement" 
                                                 class="img-fluid rounded" 
                                                 style="max-height: 120px; width: auto;">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Content -->
                                    <p class="mb-2 small">
                                        <?php 
                                        $content = htmlspecialchars($ann['content']);
                                        echo strlen($content) > 150 ? substr($content, 0, 150) . '...' : $content;
                                        ?>
                                    </p>
                                    
                                    <!-- Meta Info -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($ann['author_name'] ?? 'Admin'); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo time_ago($ann['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Reports Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Laporan Terbaru</h5>
        <a href="manage_reports.php" class="btn btn-sm btn-primary">Lihat Semua</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No. Laporan</th>
                        <th>Pelapor</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_reports)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                                Belum ada laporan
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_reports as $report): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($report['report_number']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($report['category_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($report['building_name']); ?>
                                    <?php if ($report['floor']): ?>
                                        - Lt. <?php echo htmlspecialchars($report['floor']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $report['priority'] == 'high' ? 'danger' : 
                                            ($report['priority'] == 'medium' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($report['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $report['status'] == 'new' ? 'primary' : 
                                            ($report['status'] == 'assigned' ? 'info' : 
                                            ($report['status'] == 'in_progress' ? 'warning' : 'success')); 
                                    ?>">
                                        <?php 
                                            $status_labels = [
                                                'new' => 'Baru',
                                                'assigned' => 'Ditugaskan',
                                                'in_progress' => 'Diproses',
                                                'resolved' => 'Selesai'
                                            ];
                                            echo $status_labels[$report['status']] ?? $report['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?></small>
                                </td>
                                <td>
                                    <a href="view_report.php?id=<?php echo $report['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($stats['total_reports'] > 0): ?>
<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Status Chart
const ctx = document.getElementById('statusChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Baru', 'Ditugaskan', 'Diproses', 'Selesai'],
        datasets: [{
            data: [
                <?php echo (int)$status_stats['new_reports']; ?>,
                <?php echo (int)$status_stats['assigned_reports']; ?>,
                <?php echo (int)$status_stats['in_progress_reports']; ?>,
                <?php echo (int)$status_stats['resolved_reports']; ?>
            ],
            backgroundColor: [
                '#0d6efd',
                '#0dcaf0',
                '#ffc107',
                '#198754'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>