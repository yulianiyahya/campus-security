<?php
require_once '../config.php';
check_login();
check_role(['admin']);

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Function to format datetime
function format_datetime($datetime) {
    if (!$datetime) return '-';
    return date('d M Y, H:i', strtotime($datetime));
}

// Get report details
$sql = "SELECT r.*, 
        ic.name as category_name, ic.icon, ic.severity,
        il.building_name, il.floor, il.room, il.area, il.latitude, il.longitude,
        u.nama as reporter_name, u.nim_nip, u.email as reporter_email, u.phone as reporter_phone,
        u.department,
        so.badge_number, u2.nama as officer_name, u2.email as officer_email
        FROM reports r
        JOIN incident_categories ic ON r.category_id = ic.id
        JOIN incident_locations il ON r.location_id = il.id
        JOIN users u ON r.reporter_id = u.id
        LEFT JOIN security_officers so ON r.assigned_officer_id = so.id
        LEFT JOIN users u2 ON so.user_id = u2.id
        WHERE r.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$report_id]);
$report = $stmt->fetch();

if (!$report) {
    $_SESSION['error_message'] = "Laporan tidak ditemukan!";
    header('Location: manage_reports.php');
    exit();
}

// Get attachments
$sql = "SELECT * FROM report_attachments WHERE report_id = ? ORDER BY uploaded_at";
$stmt = $pdo->prepare($sql);
$stmt->execute([$report_id]);
$attachments = $stmt->fetchAll();

// Get action history
$sql = "SELECT ra.*, u.nama as officer_name, so.badge_number
        FROM report_actions ra
        LEFT JOIN security_officers so ON ra.officer_id = so.id
        LEFT JOIN users u ON so.user_id = u.id
        WHERE ra.report_id = ?
        ORDER BY ra.action_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$report_id]);
$actions = $stmt->fetchAll();

$page_title = 'Detail Laporan #' . $report['report_number'];
require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<main>
    <div class="container-fluid py-4">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-file-alt me-2"></i>Detail Laporan</h2>
                        <p class="text-muted mb-0"><?= htmlspecialchars($report['report_number']) ?></p>
                    </div>
                    <a href="manage_reports.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Report Info Card -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Laporan</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Status</h6>
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
                                        $status_text = 'Sedang Ditangani';
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
                                <span class="badge bg-<?= $status_class ?> fs-6"><?= $status_text ?></span>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Prioritas</h6>
                                <?php
                                $priority_class = 'info';
                                $priority_text = 'Rendah';
                                switch($report['priority']) {
                                    case 'low':
                                        $priority_class = 'info';
                                        $priority_text = 'Rendah';
                                        break;
                                    case 'medium':
                                        $priority_class = 'warning';
                                        $priority_text = 'Sedang';
                                        break;
                                    case 'high':
                                        $priority_class = 'danger';
                                        $priority_text = 'Tinggi';
                                        break;
                                    case 'urgent':
                                        $priority_class = 'danger';
                                        $priority_text = 'Mendesak';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?= $priority_class ?> fs-6"><?= $priority_text ?></span>
                            </div>
                        </div>

                        <h4 class="mb-3">
                            <?php if ($report['icon']): ?>
                                <i class="<?= htmlspecialchars($report['icon']) ?> me-2"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($report['title']) ?>
                        </h4>

                        <div class="alert alert-light">
                            <h6 class="mb-2"><i class="fas fa-align-left me-2"></i>Deskripsi:</h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($report['description'])) ?></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted"><i class="fas fa-tag me-2"></i>Kategori</h6>
                                <p><?= htmlspecialchars($report['category_name']) ?> 
                                    <span class="badge bg-secondary"><?= ucfirst($report['severity']) ?></span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Lokasi</h6>
                                <p>
                                    <?= htmlspecialchars($report['building_name']) ?>
                                    <?php if ($report['floor']): ?>
                                        <br>Lantai: <?= htmlspecialchars($report['floor']) ?>
                                    <?php endif; ?>
                                    <?php if ($report['room']): ?>
                                        <br>Ruangan: <?= htmlspecialchars($report['room']) ?>
                                    <?php endif; ?>
                                    <?php if ($report['area']): ?>
                                        <br>Area: <?= htmlspecialchars($report['area']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted"><i class="fas fa-calendar me-2"></i>Waktu Kejadian</h6>
                                <p><?= format_datetime($report['incident_datetime']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted"><i class="fas fa-clock me-2"></i>Dilaporkan</h6>
                                <p><?= format_datetime($report['created_at']) ?></p>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><i class="fas fa-user me-2"></i>Pelapor</h6>
                                <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($report['reporter_name']) ?></p>
                                <p class="mb-1"><strong>NIM/NIP:</strong> <?= htmlspecialchars($report['nim_nip']) ?></p>
                                <?php if ($report['department']): ?>
                                    <p class="mb-1"><strong>Jurusan/Unit:</strong> <?= htmlspecialchars($report['department']) ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><strong>Email:</strong> 
                                    <a href="mailto:<?= htmlspecialchars($report['reporter_email']) ?>"><?= htmlspecialchars($report['reporter_email']) ?></a>
                                </p>
                                <?php if ($report['reporter_phone']): ?>
                                    <p class="mb-0"><strong>Telepon:</strong> 
                                        <a href="tel:<?= htmlspecialchars($report['reporter_phone']) ?>"><?= htmlspecialchars($report['reporter_phone']) ?></a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><i class="fas fa-user-shield me-2"></i>Petugas yang Menangani</h6>
                                <?php if ($report['officer_name']): ?>
                                    <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($report['officer_name']) ?></p>
                                    <p class="mb-1"><strong>Badge:</strong> <?= htmlspecialchars($report['badge_number']) ?></p>
                                    <p class="mb-0"><strong>Email:</strong> 
                                        <a href="mailto:<?= htmlspecialchars($report['officer_email']) ?>"><?= htmlspecialchars($report['officer_email']) ?></a>
                                    </p>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle"></i> Belum ditugaskan ke petugas
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($report['resolution_notes']): ?>
                            <hr>
                            <div class="alert alert-success">
                                <h6 class="mb-2"><i class="fas fa-check-circle me-2"></i>Catatan Penyelesaian:</h6>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($report['resolution_notes'])) ?></p>
                                <?php if ($report['resolved_at']): ?>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-clock me-1"></i>
                                        Diselesaikan pada: <?= format_datetime($report['resolved_at']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attachments -->
                <?php if (!empty($attachments)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>Bukti Lampiran (<?= count($attachments) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($attachments as $file): ?>
                                    <div class="col-md-4 mb-3">
                                        <?php if ($file['file_type'] == 'image'): ?>
                                            <a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank" data-lightbox="report-images">
                                                <img src="../<?= htmlspecialchars($file['file_path']) ?>" 
                                                     class="img-fluid rounded shadow-sm" 
                                                     alt="<?= htmlspecialchars($file['file_name']) ?>"
                                                     style="max-height: 200px; width: 100%; object-fit: cover;">
                                            </a>
                                            <small class="d-block text-muted mt-1 text-center">
                                                <?= htmlspecialchars($file['file_name']) ?>
                                            </small>
                                        <?php else: ?>
                                            <div class="card text-center">
                                                <div class="card-body">
                                                    <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                                    <p class="small mb-2"><?= htmlspecialchars($file['file_name']) ?></p>
                                                    <a href="../<?= htmlspecialchars($file['file_path']) ?>" 
                                                       class="btn btn-sm btn-primary" 
                                                       target="_blank">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action History -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Penanganan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($actions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada riwayat penanganan</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($actions as $action): ?>
                                    <div class="timeline-item mb-4 pb-3" style="border-left: 2px solid #dee2e6; padding-left: 20px; position: relative;">
                                        <div class="timeline-marker bg-primary rounded-circle" 
                                             style="width: 12px; height: 12px; position: absolute; left: -7px; top: 5px;"></div>
                                        <div class="card shadow-sm">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0">
                                                        <span class="badge bg-info">
                                                            <?= ucfirst(str_replace('_', ' ', $action['action_type'])) ?>
                                                        </span>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= format_datetime($action['action_date']) ?>
                                                    </small>
                                                </div>
                                                <p class="mb-2"><?= nl2br(htmlspecialchars($action['action_description'])) ?></p>
                                                <?php if ($action['notes']): ?>
                                                    <div class="alert alert-light mb-2 py-2">
                                                        <small>
                                                            <i class="fas fa-sticky-note me-1"></i>
                                                            <strong>Catatan:</strong> <?= nl2br(htmlspecialchars($action['notes'])) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($action['officer_name']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-shield me-1"></i>
                                                        <?= htmlspecialchars($action['officer_name']) ?> 
                                                        (<?= htmlspecialchars($action['badge_number']) ?>)
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Actions & Stats -->
            <div class="col-lg-4">
                <!-- Quick Info -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span><i class="fas fa-tasks me-2 text-primary"></i>Total Aksi:</span>
                            <strong class="badge bg-primary"><?= count($actions) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span><i class="fas fa-paperclip me-2 text-info"></i>Lampiran:</span>
                            <strong class="badge bg-info"><?= count($attachments) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="fas fa-hourglass-half me-2 text-warning"></i>Waktu Penanganan:</span>
                            <strong>
                                <?php
                                if ($report['resolved_at']) {
                                    $start = new DateTime($report['created_at']);
                                    $end = new DateTime($report['resolved_at']);
                                    $diff = $start->diff($end);
                                    if ($diff->days > 0) {
                                        echo $diff->days . ' hari ';
                                    }
                                    if ($diff->h > 0) {
                                        echo $diff->h . ' jam';
                                    }
                                    if ($diff->days == 0 && $diff->h == 0) {
                                        echo $diff->i . ' menit';
                                    }
                                } else {
                                    echo '<span class="badge bg-warning">Belum selesai</span>';
                                }
                                ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <?php if ($report['latitude'] && $report['longitude']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i>Lokasi Kejadian</h5>
                        </div>
                        <div class="card-body p-0">
                            <iframe 
                                width="100%" 
                                height="300" 
                                frameborder="0" 
                                scrolling="no" 
                                marginheight="0" 
                                marginwidth="0" 
                                src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $report['longitude']-0.01 ?>,<?= $report['latitude']-0.01 ?>,<?= $report['longitude']+0.01 ?>,<?= $report['latitude']+0.01 ?>&layer=mapnik&marker=<?= $report['latitude'] ?>,<?= $report['longitude'] ?>">
                            </iframe>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Aksi Cepat</h5>
                    </div>
                    <div class="card-body">
                        <a href="manage_reports.php" class="btn btn-secondary w-100 mb-2">
                            <i class="fas fa-list me-1"></i> Lihat Semua Laporan
                        </a>
                        <a href="statistics.php" class="btn btn-info w-100 mb-2">
                            <i class="fas fa-chart-line me-1"></i> Lihat Statistik
                        </a>
                        <button onclick="window.print()" class="btn btn-primary w-100">
                            <i class="fas fa-print me-1"></i> Cetak Laporan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
@media print {
    .btn, .card-header, nav, aside {
        display: none !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>