<?php
require_once '../config.php';
check_login();
check_role(['admin']);

// Handle assign officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_officer'])) {
    $report_id = (int)$_POST['report_id'];
    $officer_id = (int)$_POST['officer_id'];
    
    // Debug: Log received data
    error_log("Assign Officer - Report ID: $report_id, Officer ID: $officer_id");
    
    // Validasi input
    if (empty($officer_id) || $officer_id <= 0) {
        set_flash('danger', 'Silakan pilih petugas terlebih dahulu!');
        header('Location: manage_reports.php');
        exit;
    }
    
    if (empty($report_id) || $report_id <= 0) {
        set_flash('danger', 'ID Laporan tidak valid!');
        header('Location: manage_reports.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Cek apakah report exists
        $sql = "SELECT id, report_number, status FROM reports WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report_id]);
        $existing_report = $stmt->fetch();
        
        if (!$existing_report) {
            throw new Exception("Laporan tidak ditemukan");
        }
        
        error_log("Report found: " . $existing_report['report_number']);
        
        // Cek apakah officer exists
        $sql = "SELECT so.id, u.nama FROM security_officers so JOIN users u ON so.user_id = u.id WHERE so.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$officer_id]);
        $officer_check = $stmt->fetch();
        
        if (!$officer_check) {
            throw new Exception("Petugas tidak ditemukan");
        }
        
        error_log("Officer found: " . $officer_check['nama']);
        
        // Update report - set status to in_progress when assigning
        $sql = "UPDATE reports SET assigned_officer_id = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$officer_id, $report_id]);
        $rows_affected = $stmt->rowCount();
        
        error_log("Update result - Success: " . ($success ? 'Yes' : 'No') . ", Rows: $rows_affected");
        
        if (!$success || $rows_affected === 0) {
            throw new Exception("Gagal mengupdate laporan. Rows affected: $rows_affected");
        }
        
        // Get officer user info for notification
        $sql = "SELECT u.id as user_id, u.nama FROM security_officers so JOIN users u ON so.user_id = u.id WHERE so.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$officer_id]);
        $officer_user = $stmt->fetch();
        
        // Send notification to officer
        if ($officer_user) {
            $sql = "INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
                    VALUES (?, ?, ?, 'report_update', ?, NOW())";
            $notif_title = "Tugas Baru";
            $notif_message = "Anda telah ditugaskan untuk menangani laporan {$existing_report['report_number']}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$officer_user['user_id'], $notif_title, $notif_message, $report_id]);
            
            error_log("Notification sent to user: " . $officer_user['user_id']);
        }
        
        $pdo->commit();
        
        error_log("Transaction committed successfully");
        
        set_flash('success', 'Petugas ' . htmlspecialchars($officer_user['nama']) . ' berhasil ditugaskan untuk laporan ' . $existing_report['report_number'] . '!');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in assign officer: " . $e->getMessage());
        set_flash('danger', 'Gagal menugaskan petugas: ' . $e->getMessage());
    }
    
    header('Location: manage_reports.php');
    exit;
}

// Handle delete report
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    
    try {
        // Delete related data first
        $pdo->exec("DELETE FROM report_actions WHERE report_id = $report_id");
        $pdo->exec("DELETE FROM report_attachments WHERE report_id = $report_id");
        $pdo->exec("DELETE FROM notifications WHERE reference_id = $report_id AND type = 'report_update'");
        $pdo->exec("DELETE FROM reports WHERE id = $report_id");
        
        set_flash('success', 'Laporan berhasil dihapus!');
    } catch (PDOException $e) {
        set_flash('danger', 'Gagal menghapus laporan: ' . $e->getMessage());
    }
    
    header('Location: manage_reports.php');
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$sql = "SELECT r.*, 
        ic.name as category_name, ic.icon,
        il.building_name,
        u.nama as reporter_name, u.nim_nip,
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

if (!empty($search)) {
    $sql .= " AND (r.report_number LIKE ? OR r.title LIKE ? OR u.nama LIKE ?)";
    $search_param = "%$search%";
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

// Get available officers
$sql = "SELECT so.id, so.badge_number, u.nama, so.shift, so.area_responsibility
        FROM security_officers so
        JOIN users u ON so.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY u.nama";
$stmt = $pdo->query($sql);
$officers = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
              SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
              SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
              SUM(CASE WHEN assigned_officer_id IS NULL THEN 1 ELSE 0 END) as unassigned_count
              FROM reports";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

$page_title = "Manajemen Laporan";
include_once '../includes/header.php';
include_once '../includes/navbar_admin.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-clipboard-list me-2"></i>Manajemen Laporan</h2>
        <p class="text-muted">Kelola semua laporan keamanan dan tugaskan ke petugas</p>
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
                        <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                    </div>
                    <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Belum Ditugaskan</h6>
                        <h2 class="mb-0"><?php echo $stats['unassigned_count']; ?></h2>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
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
                        <h2 class="mb-0"><?php echo $stats['in_progress_count']; ?></h2>
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
                        <h2 class="mb-0"><?php echo $stats['resolved_count']; ?></h2>
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
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>Baru</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>Dalam Proses</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Ditutup</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Prioritas</label>
                <select name="priority" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>Semua Prioritas</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>Tinggi</option>
                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Sedang</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Rendah</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Cari</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari nomor laporan, judul, atau nama..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Cari</button>
                    <?php if (!empty($search) || $status_filter !== 'all' || $priority_filter !== 'all'): ?>
                        <a href="manage_reports.php" class="btn btn-secondary">Reset</a>
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
                <thead class="table-light">
                    <tr>
                        <th>No. Laporan</th>
                        <th>Judul</th>
                        <th>Pelapor</th>
                        <th>Lokasi</th>
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
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">Tidak ada laporan ditemukan</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $report['report_number']; ?></strong>
                                </td>
                                <td>
                                    <i class="<?php echo $report['icon']; ?> me-1"></i>
                                    <?php echo htmlspecialchars(substr($report['title'], 0, 30)); ?>
                                    <?php echo strlen($report['title']) > 30 ? '...' : ''; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($report['reporter_name']); ?><br>
                                    <small class="text-muted"><?php echo $report['nim_nip']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($report['building_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $report['priority'] === 'high' ? 'danger' : ($report['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($report['priority']); ?>
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
                                        case 'closed':
                                            $status_class = 'secondary';
                                            $status_text = 'Ditutup';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <?php if ($report['officer_name']): ?>
                                        <small><?php echo htmlspecialchars($report['officer_name']); ?></small>
                                    <?php else: ?>
                                        <span class="text-danger"><small>Belum ditugaskan</small></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y', strtotime($report['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-info" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (!$report['assigned_officer_id']): ?>
                                            <button class="btn btn-success" onclick="assignOfficer(<?php echo $report['id']; ?>, '<?php echo $report['report_number']; ?>')" title="Tugaskan Petugas">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="?delete=1&id=<?php echo $report['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus laporan ini?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
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
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <p class="text-center text-muted small">
                    Menampilkan <?php echo ($offset + 1); ?> - <?php echo min($offset + $per_page, $total_records); ?> dari <?php echo $total_records; ?> laporan
                </p>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Officer Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="assignForm" action="">
                <input type="hidden" name="assign_officer" value="1">
                <input type="hidden" name="report_id" id="modal_report_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tugaskan Petugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tugaskan petugas untuk menangani laporan: <strong id="modal_report_number"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Petugas Keamanan *</label>
                        <select name="officer_id" id="officer_id" class="form-select" required>
                            <option value="">-- Pilih Petugas --</option>
                            <?php foreach ($officers as $officer): ?>
                                <option value="<?php echo $officer['id']; ?>">
                                    <?php echo htmlspecialchars($officer['nama']); ?> (<?php echo $officer['badge_number']; ?>)
                                    - <?php echo ucfirst($officer['shift']); ?> Shift
                                    <?php echo $officer['area_responsibility'] ? ' - ' . $officer['area_responsibility'] : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Silakan pilih petugas</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnAssign">
                        <i class="fas fa-check me-1"></i> Tugaskan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function assignOfficer(reportId, reportNumber) {
    document.getElementById('modal_report_id').value = reportId;
    document.getElementById('modal_report_number').textContent = reportNumber;
    document.getElementById('officer_id').value = ''; // Reset selection
    
    // Remove previous validation states
    document.getElementById('officer_id').classList.remove('is-invalid');
    
    var modal = new bootstrap.Modal(document.getElementById('assignModal'));
    modal.show();
}

// Form validation
document.getElementById('assignForm').addEventListener('submit', function(e) {
    var officerId = document.getElementById('officer_id').value;
    
    if (!officerId || officerId === '') {
        e.preventDefault();
        document.getElementById('officer_id').classList.add('is-invalid');
        return false;
    }
    
    // Show loading state
    var btnAssign = document.getElementById('btnAssign');
    btnAssign.disabled = true;
    btnAssign.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...';
    
    return true;
});

// Remove invalid class on change
document.getElementById('officer_id').addEventListener('change', function() {
    this.classList.remove('is-invalid');
});
</script>

<?php include_once '../includes/footer.php'; ?>