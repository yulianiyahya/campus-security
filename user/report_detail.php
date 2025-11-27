<?php
require_once '../config.php';
check_login();
check_role(['user']);

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get report details with related data
$stmt = $pdo->prepare("
    SELECT r.*, 
           c.name as category_name, c.icon as category_icon,
           l.building_name, l.floor, l.room, l.area,
           u.nama as reporter_name, u.email,
           so.badge_number, u2.nama as officer_name
    FROM reports r
    LEFT JOIN incident_categories c ON r.category_id = c.id
    LEFT JOIN incident_locations l ON r.location_id = l.id
    LEFT JOIN users u ON r.reporter_id = u.id
    LEFT JOIN security_officers so ON r.assigned_officer_id = so.id
    LEFT JOIN users u2 ON so.user_id = u2.id
    WHERE r.id = ? AND r.reporter_id = ?
");
$stmt->execute([$report_id, $_SESSION['user_id']]);
$report = $stmt->fetch();

if (!$report) {
    $_SESSION['error_message'] = 'Laporan tidak ditemukan atau Anda tidak memiliki akses.';
    header('Location: my_reports.php');
    exit();
}

// Get attachments
$stmt = $pdo->prepare("
    SELECT * FROM report_attachments 
    WHERE report_id = ? 
    ORDER BY uploaded_at ASC
");
$stmt->execute([$report_id]);
$attachments = $stmt->fetchAll();

// Get report actions (timeline)
$stmt = $pdo->prepare("
    SELECT ra.*, 
           u.nama as officer_name,
           so.badge_number
    FROM report_actions ra
    LEFT JOIN security_officers so ON ra.officer_id = so.id
    LEFT JOIN users u ON so.user_id = u.id
    WHERE ra.report_id = ?
    ORDER BY ra.action_date DESC
");
$stmt->execute([$report_id]);
$actions = $stmt->fetchAll();

$page_title = 'Detail Laporan #' . $report['report_number'];
require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<main>
    <div class="container-fluid mt-4">
        <!-- Back button -->
        <div class="mb-3">
            <a href="my_reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Laporan
            </a>
        </div>

        <div class="row">
            <!-- Report Details -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt"></i> Detail Laporan
                            <span class="float-end">#<?= htmlspecialchars($report['report_number']) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Status and Priority -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="text-muted mb-2">Status:</h6>
                                <?php
                                $status_badges = [
                                    'new' => 'badge bg-primary',
                                    'in_progress' => 'badge bg-warning text-dark',
                                    'resolved' => 'badge bg-success',
                                    'closed' => 'badge bg-secondary'
                                ];
                                $status_labels = [
                                    'new' => 'Baru',
                                    'in_progress' => 'Dalam Proses',
                                    'resolved' => 'Selesai',
                                    'closed' => 'Ditutup'
                                ];
                                ?>
                                <span class="<?= $status_badges[$report['status']] ?? 'badge bg-secondary' ?> fs-6">
                                    <?= $status_labels[$report['status']] ?? strtoupper($report['status']) ?>
                                </span>
                            </div>
                            <div>
                                <h6 class="text-muted mb-2">Prioritas:</h6>
                                <?php
                                $priority_badges = [
                                    'low' => 'badge bg-success',
                                    'medium' => 'badge bg-warning text-dark',
                                    'high' => 'badge bg-danger',
                                    'urgent' => 'badge bg-danger'
                                ];
                                $priority_labels = [
                                    'low' => '🟢 Rendah',
                                    'medium' => '🟡 Sedang',
                                    'high' => '🔴 Tinggi',
                                    'urgent' => '🔴 Mendesak'
                                ];
                                ?>
                                <span class="<?= $priority_badges[$report['priority']] ?? 'badge bg-secondary' ?> fs-6">
                                    <?= $priority_labels[$report['priority']] ?? strtoupper($report['priority']) ?>
                                </span>
                            </div>
                        </div>

                        <hr>

                        <!-- Report Info -->
                        <div class="mb-3">
                            <h6 class="text-muted">Judul Laporan:</h6>
                            <h5><?= htmlspecialchars($report['title']) ?></h5>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-muted">Deskripsi:</h6>
                            <p class="text-justify"><?= nl2br(htmlspecialchars($report['description'])) ?></p>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-muted">Kategori:</h6>
                                <p>
                                    <i class="fas fa-tag"></i> 
                                    <?= htmlspecialchars($report['category_icon'] ?? '📋') ?> 
                                    <?= htmlspecialchars($report['category_name']) ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Lokasi:</h6>
                                <p>
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?= htmlspecialchars($report['building_name']) ?>
                                    <?php if ($report['floor']): ?>
                                        , Lantai <?= htmlspecialchars($report['floor']) ?>
                                    <?php endif; ?>
                                    <?php if ($report['room']): ?>
                                        , Ruang <?= htmlspecialchars($report['room']) ?>
                                    <?php endif; ?>
                                    <?php if ($report['area']): ?>
                                        (<?= htmlspecialchars($report['area']) ?>)
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-muted">Waktu Kejadian:</h6>
                                <p><i class="fas fa-calendar"></i> <?= date('d M Y H:i', strtotime($report['incident_datetime'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Dilaporkan:</h6>
                                <p><i class="fas fa-clock"></i> <?= date('d M Y H:i', strtotime($report['created_at'])) ?></p>
                            </div>
                        </div>

                        <?php if (count($attachments) > 0): ?>
                        <div class="mb-3">
                            <h6 class="text-muted">Lampiran:</h6>
                            <div class="row">
                                <?php foreach ($attachments as $att): ?>
                                <div class="col-md-4 mb-2">
                                    <?php if ($att['file_type'] == 'image'): ?>
                                        <a href="../<?= htmlspecialchars($att['file_path']) ?>" target="_blank" data-lightbox="report-images">
                                            <img src="../<?= htmlspecialchars($att['file_path']) ?>" 
                                                 class="img-thumbnail" 
                                                 alt="<?= htmlspecialchars($att['file_name']) ?>"
                                                 style="max-height: 150px; width: 100%; object-fit: cover;">
                                        </a>
                                        <small class="d-block text-muted text-center mt-1">
                                            <?= htmlspecialchars($att['file_name']) ?>
                                        </small>
                                    <?php else: ?>
                                        <a href="../<?= htmlspecialchars($att['file_path']) ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-danger w-100">
                                            <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($att['file_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($report['officer_name']): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-user-shield"></i>
                            <strong>Petugas yang Menangani:</strong><br>
                            <?= htmlspecialchars($report['officer_name']) ?> 
                            (Badge: <?= htmlspecialchars($report['badge_number']) ?>)
                        </div>
                        <?php endif; ?>

                        <?php if ($report['resolution_notes'] && $report['status'] == 'resolved'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Catatan Penyelesaian:</strong><br>
                            <?= nl2br(htmlspecialchars($report['resolution_notes'])) ?>
                            <?php if ($report['resolved_at']): ?>
                                <br><small class="text-muted">Diselesaikan pada: <?= date('d M Y H:i', strtotime($report['resolved_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Timeline & Info -->
            <div class="col-lg-4">
                <!-- Reporter Info -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-user"></i> Informasi Pelapor</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Nama:</strong><br><?= htmlspecialchars($report['reporter_name']) ?></p>
                        <p class="mb-0"><strong>Email:</strong><br><?= htmlspecialchars($report['email']) ?></p>
                    </div>
                </div>

                <!-- Timeline Actions -->
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-history"></i> Timeline Penanganan</h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($actions) > 0): ?>
                            <div class="timeline">
                                <?php foreach ($actions as $action): ?>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="timeline-icon">
                                            <?php
                                            $icon = 'fa-circle';
                                            $color = 'text-secondary';
                                            switch($action['action_type']) {
                                                case 'investigation':
                                                    $icon = 'fa-search';
                                                    $color = 'text-info';
                                                    break;
                                                case 'patrol':
                                                    $icon = 'fa-route';
                                                    $color = 'text-primary';
                                                    break;
                                                case 'follow_up':
                                                    $icon = 'fa-tasks';
                                                    $color = 'text-warning';
                                                    break;
                                                case 'resolution':
                                                    $icon = 'fa-check-circle';
                                                    $color = 'text-success';
                                                    break;
                                            }
                                            ?>
                                            <i class="fas <?= $icon ?> <?= $color ?>"></i>
                                        </div>
                                        <div class="ms-2 flex-grow-1">
                                            <strong><?= ucfirst(str_replace('_', ' ', $action['action_type'])) ?></strong>
                                            <p class="mb-0 small"><?= htmlspecialchars($action['action_description']) ?></p>
                                            <?php if ($action['notes']): ?>
                                                <p class="mb-0 small text-muted fst-italic">
                                                    <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($action['notes']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($action['officer_name']): ?>
                                                <small class="text-muted">oleh: <?= htmlspecialchars($action['officer_name']) ?> (<?= htmlspecialchars($action['badge_number']) ?>)</small><br>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> <?= date('d M Y H:i', strtotime($action['action_date'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">
                                <i class="fas fa-info-circle"></i><br>
                                Belum ada tindakan penanganan
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.timeline-item {
    border-left: 2px solid #dee2e6;
    padding-left: 15px;
    position: relative;
}
.timeline-icon {
    position: absolute;
    left: -10px;
    background: white;
}
.text-justify {
    text-align: justify;
}
</style>

<?php require_once '../includes/footer.php'; ?>