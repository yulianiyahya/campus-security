<?php
require_once '../config.php';

// Check if user is logged in and is security officer
if (!is_logged_in() || $_SESSION['role'] !== 'security') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get security officer ID
$sql = "SELECT id FROM security_officers WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$officer = $stmt->fetch();
$officer_id = $officer['id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$sql = "SELECT r.*, 
        ic.name as category_name, ic.icon, ic.severity,
        il.building_name, il.floor, il.room,
        u.nama as reporter_name, u.nim_nip,
        TIMESTAMPDIFF(HOUR, r.incident_datetime, NOW()) as hours_ago
        FROM reports r
        JOIN incident_categories ic ON r.category_id = ic.id
        JOIN incident_locations il ON r.location_id = il.id
        JOIN users u ON r.reporter_id = u.id
        WHERE r.assigned_officer_id = ?";

$params = [$officer_id];

if ($status_filter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $sql .= " AND r.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($search)) {
    $sql .= " AND (r.report_number LIKE ? OR r.title LIKE ? OR r.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY 
          CASE r.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
          END,
          r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              COUNT(CASE WHEN status = 'new' THEN 1 END) as new_count,
              COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
              COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count,
              COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_count
              FROM reports 
              WHERE assigned_officer_id = ?";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$officer_id]);
$stats = $stmt->fetch();

include_once '../includes/header.php';
include_once '../includes/navbar_security.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-clipboard-list me-2"></i>Tugas Saya</h2>
            <p class="text-muted">Daftar laporan yang ditugaskan kepada Anda</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Tugas</h6>
                            <h2 class="mb-0"><?= $stats['total'] ?></h2>
                        </div>
                        <i class="fas fa-tasks fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Baru</h6>
                            <h2 class="mb-0"><?= $stats['new_count'] ?></h2>
                        </div>
                        <i class="fas fa-bell fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Sedang Ditangani</h6>
                            <h2 class="mb-0"><?= $stats['in_progress_count'] ?></h2>
                        </div>
                        <i class="fas fa-spinner fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Selesai</h6>
                            <h2 class="mb-0"><?= $stats['resolved_count'] ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>Baru</option>
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>Sedang Ditangani</option>
                        <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prioritas</label>
                    <select name="priority" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $priority_filter === 'all' ? 'selected' : '' ?>>Semua Prioritas</option>
                        <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>Tinggi</option>
                        <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Sedang</option>
                        <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Rendah</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cari</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari nomor laporan, judul, atau deskripsi..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Cari</button>
                        <?php if (!empty($search) || $status_filter !== 'all' || $priority_filter !== 'all'): ?>
                            <a href="my_assignments.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reports List -->
    <div class="row">
        <?php if (empty($assignments)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Tidak ada tugas yang ditugaskan kepada Anda saat ini.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($assignments as $report): ?>
                <div class="col-md-6 mb-3">
                    <div class="card h-100 border-start border-4 <?php
                        echo $report['priority'] === 'high' ? 'border-danger' : 
                        ($report['priority'] === 'medium' ? 'border-warning' : 'border-info');
                    ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <i class="<?= $report['icon'] ?> me-2"></i>
                                        <?= htmlspecialchars($report['title']) ?>
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        <strong><?= $report['report_number'] ?></strong>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($report['status']) {
                                        case 'new':
                                            $status_class = 'warning';
                                            $status_text = 'Baru';
                                            break;
                                        case 'in_progress':
                                            $status_class = 'info';
                                            $status_text = 'Ditangani';
                                            break;
                                        case 'resolved':
                                            $status_class = 'success';
                                            $status_text = 'Selesai';
                                            break;
                                        case 'rejected':
                                            $status_class = 'danger';
                                            $status_text = 'Ditolak';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                    <br>
                                    <span class="badge bg-<?= $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : 'info') ?> mt-1">
                                        <?= ucfirst($report['priority']) ?>
                                    </span>
                                </div>
                            </div>

                            <p class="card-text small text-muted mb-2">
                                <?= nl2br(htmlspecialchars(substr($report['description'], 0, 100))) ?>
                                <?= strlen($report['description']) > 100 ? '...' : '' ?>
                            </p>

                            <div class="row small text-muted mb-3">
                                <div class="col-6">
                                    <i class="fas fa-user me-1"></i>
                                    <?= htmlspecialchars($report['reporter_name']) ?>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-id-card me-1"></i>
                                    <?= htmlspecialchars($report['nim_nip']) ?>
                                </div>
                                <div class="col-6 mt-1">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars($report['building_name']) ?>
                                    <?php if ($report['floor']): ?>
                                        , Lt. <?= $report['floor'] ?>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 mt-1">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= $report['hours_ago'] ?> jam lalu
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="handle_report.php?id=<?= $report['id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                                    <i class="fas fa-eye me-1"></i> Detail & Tangani
                                </a>
                                <?php if ($report['status'] === 'new'): ?>
                                    <button class="btn btn-success btn-sm" onclick="quickAccept(<?= $report['id'] ?>)">
                                        <i class="fas fa-check"></i> Terima
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function quickAccept(reportId) {
    if (confirm('Terima tugas ini dan mulai penanganan?')) {
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'report_id=' + reportId + '&status=in_progress'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Tugas berhasil diterima!');
                location.reload();
            } else {
                alert('Gagal: ' + data.message);
            }
        });
    }
}
</script>

<?php include_once '../includes/footer.php'; ?>