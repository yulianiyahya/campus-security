<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Laporan Saya';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Build query
$where = ["r.reporter_id = ?"];
$params = [$_SESSION['user_id']];

if ($status_filter) {
    $where[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[] = "(r.title LIKE ? OR r.description LIKE ? OR r.report_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where);

// Get total records
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reports r WHERE $where_clause");
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get reports
$stmt = $pdo->prepare("
    SELECT r.*, ic.name as category_name, ic.icon,
           il.building_name, il.floor, il.room,
           u.nama as officer_name
    FROM reports r
    JOIN incident_categories ic ON r.category_id = ic.id
    JOIN incident_locations il ON r.location_id = il.id
    LEFT JOIN security_officers so ON r.assigned_officer_id = so.id
    LEFT JOIN users u ON so.user_id = u.id
    WHERE $where_clause
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
    FROM reports
    WHERE reporter_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                <p class="text-muted mb-0">Total Laporan</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <h3 class="mb-1 text-primary"><?php echo $stats['new_count']; ?></h3>
                <p class="text-muted mb-0">Baru</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <h3 class="mb-1 text-warning"><?php echo $stats['in_progress_count']; ?></h3>
                <p class="text-muted mb-0">Diproses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <h3 class="mb-1 text-success"><?php echo $stats['resolved_count']; ?></h3>
                <p class="text-muted mb-0">Selesai</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cari Laporan</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Cari berdasarkan judul, nomor laporan..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>Baru</option>
                    <option value="assigned" <?php echo $status_filter == 'assigned' ? 'selected' : ''; ?>>Ditugaskan</option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>Diproses</option>
                    <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Selesai</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Cari
                </button>
                <a href="my_reports.php" class="btn btn-secondary">
                    <i class="fas fa-redo me-2"></i>Reset
                </a>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="create_report.php" class="btn btn-success w-100">
                    <i class="fas fa-plus-circle me-2"></i>Buat Laporan
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Reports List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Daftar Laporan Saya
            <span class="badge bg-primary ms-2"><?php echo $total_records; ?> Laporan</span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($reports)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Tidak Ada Laporan</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter): ?>
                        Tidak ditemukan laporan dengan kriteria pencarian tersebut.
                    <?php else: ?>
                        Anda belum membuat laporan apapun.
                    <?php endif; ?>
                </p>
                <a href="create_report.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus-circle me-2"></i>Buat Laporan Pertama
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 15%">No. Laporan</th>
                            <th style="width: 25%">Judul &amp; Kategori</th>
                            <th style="width: 15%">Lokasi</th>
                            <th style="width: 10%">Prioritas</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 10%">Petugas</th>
                            <th style="width: 10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $index => $report): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <strong class="text-primary">
                                        <?php echo htmlspecialchars($report['report_number']); ?>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-start">
                                        <span class="me-2"><?php echo htmlspecialchars($report['icon'] ?? '📋'); ?></span>
                                        <div>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($report['title']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i>
                                                <?php echo htmlspecialchars($report['category_name']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-building me-1"></i>
                                        <?php echo htmlspecialchars($report['building_name']); ?>
                                        <?php if ($report['floor']): ?>
                                            <br>Lt. <?php echo htmlspecialchars($report['floor']); ?>
                                        <?php endif; ?>
                                        <?php if ($report['room']): ?>
                                            - <?php echo htmlspecialchars($report['room']); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $priority_config = [
                                        'high' => ['class' => 'danger', 'label' => '🔴 Tinggi'],
                                        'medium' => ['class' => 'warning', 'label' => '🟡 Sedang'],
                                        'low' => ['class' => 'info', 'label' => '🟢 Rendah']
                                    ];
                                    $priority = $priority_config[$report['priority']] ?? $priority_config['low'];
                                    ?>
                                    <span class="badge bg-<?php echo $priority['class']; ?>">
                                        <?php echo $priority['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_config = [
                                        'new' => ['class' => 'primary', 'label' => 'Baru'],
                                        'assigned' => ['class' => 'info', 'label' => 'Ditugaskan'],
                                        'in_progress' => ['class' => 'warning', 'label' => 'Diproses'],
                                        'resolved' => ['class' => 'success', 'label' => 'Selesai']
                                    ];
                                    $status = $status_config[$report['status']] ?? $status_config['new'];
                                    ?>
                                    <span class="badge bg-<?php echo $status['class']; ?>">
                                        <?php echo $status['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($report['officer_name']): ?>
                                        <small>
                                            <i class="fas fa-user-shield me-1"></i>
                                            <?php echo htmlspecialchars($report['officer_name']); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">Belum ditugaskan</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="report_detail.php?id=<?php echo $report['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary"
                                       title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&amp;status=<?php echo $status_filter; ?>&amp;search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&amp;status=<?php echo $status_filter; ?>&amp;search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif (abs($i - $page) == 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&amp;status=<?php echo $status_filter; ?>&amp;search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>