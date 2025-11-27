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

if (!$officer) {
    $_SESSION['error_message'] = "Data petugas keamanan tidak ditemukan!";
    redirect('security/dashboard.php');
}

$officer_id = $officer['id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$sql = "SELECT r.*, 
        ic.name as category_name, ic.icon,
        il.building_name,
        u.nama as reporter_name,
        so.badge_number, u2.nama as officer_name
        FROM reports r
        JOIN incident_categories ic ON r.category_id = ic.id
        JOIN incident_locations il ON r.location_id = il.id
        JOIN users u ON r.reporter_id = u.id
        LEFT JOIN security_officers so ON r.assigned_officer_id = so.id
        LEFT JOIN users u2 ON so.user_id = u2.id
        WHERE 1=1";

$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $sql .= " AND r.priority = ?";
    $params[] = $priority_filter;
}

if ($category_filter !== 'all') {
    $sql .= " AND r.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $sql .= " AND (r.report_number LIKE ? OR r.title LIKE ? OR r.description LIKE ? OR u.nama LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Count total
$count_sql = preg_replace('/SELECT.*FROM/', 'SELECT COUNT(*) FROM', $sql);
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get paginated results
$sql .= " ORDER BY r.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get categories for filter
$sql = "SELECT * FROM incident_categories WHERE is_active = TRUE ORDER BY name";
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              COUNT(CASE WHEN status = 'new' THEN 1 END) as new_count,
              COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
              COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count
              FROM reports";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

include_once '../includes/header.php';
include_once '../includes/navbar_security.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-list me-2"></i>Semua Laporan Keamanan</h2>
            <p class="text-muted">Daftar lengkap laporan keamanan kampus</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Laporan</h6>
                            <h2 class="mb-0"><?= $stats['total'] ?></h2>
                        </div>
                        <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
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
                            <h6 class="mb-0">Dalam Proses</h6>
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
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua</option>
                        <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>Baru</option>
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>Dalam Proses</option>
                        <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Selesai</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Prioritas</label>
                    <select name="priority" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $priority_filter === 'all' ? 'selected' : '' ?>>Semua</option>
                        <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>Tinggi</option>
                        <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Sedang</option>
                        <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Rendah</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Cari</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari laporan..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search) || $status_filter !== 'all' || $priority_filter !== 'all' || $category_filter !== 'all'): ?>
                            <a href="all_reports.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No. Laporan</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Pelapor</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Petugas</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">Tidak ada laporan ditemukan</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>
                                        <strong><?= $report['report_number'] ?></strong>
                                    </td>
                                    <td>
                                        <i class="<?= $report['icon'] ?> me-1"></i>
                                        <?= htmlspecialchars(substr($report['title'], 0, 40)) ?>
                                        <?= strlen($report['title']) > 40 ? '...' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($report['category_name']) ?></td>
                                    <td><?= htmlspecialchars($report['building_name']) ?></td>
                                    <td><?= htmlspecialchars($report['reporter_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : 'info') ?>">
                                            <?= ucfirst($report['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
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
                                                $status_text = 'Proses';
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
                                    </td>
                                    <td>
                                        <?php if ($report['officer_name']): ?>
                                            <small><?= htmlspecialchars($report['officer_name']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($report['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <a href="handle_report.php?id=<?= $report['id'] ?>" class="btn btn-sm btn-primary" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $status_filter ?>&priority=<?= $priority_filter ?>&category=<?= $category_filter ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?>&priority=<?= $priority_filter ?>&category=<?= $category_filter ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $status_filter ?>&priority=<?= $priority_filter ?>&category=<?= $category_filter ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <p class="text-center text-muted small">
                        Menampilkan <?= ($offset + 1) ?> - <?= min($offset + $per_page, $total_records) ?> dari <?= $total_records ?> laporan
                    </p>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>