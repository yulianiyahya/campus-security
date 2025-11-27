<?php
require_once '../config.php';

// Check if user is logged in and is security officer
if (!is_logged_in() || $_SESSION['role'] !== 'security') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Get report details
$sql = "SELECT r.*, 
        ic.name as category_name, ic.icon, ic.severity,
        il.building_name, il.floor, il.room, il.area, il.latitude, il.longitude,
        u.nama as reporter_name, u.nim_nip, u.email as reporter_email, u.phone as reporter_phone,
        TIMESTAMPDIFF(HOUR, r.incident_datetime, NOW()) as hours_ago,
        so.badge_number, u2.nama as officer_name
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
    set_flash('danger', 'Laporan tidak ditemukan!');
    redirect('security/all_reports.php');
}

// Check if this report is assigned to current officer
$is_assigned = ($report['assigned_officer_id'] != null);
$is_my_assignment = ($report['assigned_officer_id'] == $officer_id);
$can_handle = $is_my_assignment; // Hanya petugas yang ditugaskan yang bisa handle

// Handle update status submission - HANYA jika petugas ditugaskan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $can_handle) {
    $status = clean_input($_POST['status']);
    $resolution_notes = clean_input($_POST['resolution_notes']);
    
    try {
        // Update report status
        if ($status === 'resolved' || $status === 'closed') {
            $sql = "UPDATE reports 
                    SET status = ?, 
                        resolution_notes = ?, 
                        resolved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND assigned_officer_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $resolution_notes, $report_id, $officer_id]);
        } else {
            $sql = "UPDATE reports 
                    SET status = ?, 
                        resolution_notes = ?,
                        updated_at = NOW()
                    WHERE id = ? AND assigned_officer_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $resolution_notes, $report_id, $officer_id]);
        }
        
        // Add action history
        $action_description = "Status diubah menjadi: " . get_status_text($status);
        if ($resolution_notes) {
            $action_description .= "\nCatatan: " . $resolution_notes;
        }
        
        $sql = "INSERT INTO report_actions (report_id, officer_id, action_type, action_description, action_date) 
                VALUES (?, ?, 'resolution', ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report_id, $officer_id, $action_description]);
        
        // Create notification for reporter
        $sql = "SELECT reporter_id FROM reports WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report_id]);
        $report_data = $stmt->fetch();
        
        if ($report_data) {
            $notification_message = "Status laporan Anda telah diperbarui menjadi: " . get_status_text($status);
            $sql = "INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
                    VALUES (?, 'Update Status Laporan', ?, 'report_update', ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$report_data['reporter_id'], $notification_message, $report_id]);
        }
        
        set_flash('success', 'Status laporan berhasil diperbarui!');
        redirect("security/handle_report.php?id=$report_id");
        
    } catch (PDOException $e) {
        set_flash('danger', 'Gagal memperbarui status: ' . $e->getMessage());
    }
}

// Handle action submission - HANYA jika petugas ditugaskan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_action']) && $can_handle) {
    $action_type = clean_input($_POST['action_type']);
    $action_description = clean_input($_POST['action_description']);
    $notes = clean_input($_POST['notes']);
    
    try {
        $sql = "INSERT INTO report_actions (report_id, officer_id, action_type, action_description, notes, action_date) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report_id, $officer_id, $action_type, $action_description, $notes]);
        
        set_flash('success', 'Aksi berhasil ditambahkan!');
        redirect("security/handle_report.php?id=$report_id");
    } catch (PDOException $e) {
        set_flash('danger', 'Gagal menambahkan aksi: ' . $e->getMessage());
    }
}

// Get attachments
$sql = "SELECT * FROM report_attachments WHERE report_id = ? ORDER BY uploaded_at";
$stmt = $pdo->prepare($sql);
$stmt->execute([$report_id]);
$attachments = $stmt->fetchAll();

// Get action history
$sql = "SELECT ra.*, u.nama as officer_name, so.badge_number
        FROM report_actions ra
        JOIN security_officers so ON ra.officer_id = so.id
        JOIN users u ON so.user_id = u.id
        WHERE ra.report_id = ?
        ORDER BY ra.action_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$report_id]);
$actions = $stmt->fetchAll();

$page_title = "Detail Laporan - " . $report['report_number'];
include_once '../includes/header.php';
include_once '../includes/navbar_security.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-file-alt me-2"></i>Detail Laporan</h2>
                    <p class="text-muted mb-0"><?= htmlspecialchars($report['report_number']) ?></p>
                </div>
                <a href="<?= $is_my_assignment ? 'my_assignments.php' : 'all_reports.php' ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <?php
    // Display flash messages
    $flash = get_flash();
    if ($flash):
        $alert_class = $flash['type'] === 'danger' ? 'danger' : $flash['type'];
    ?>
        <div class="alert alert-<?= $alert_class ?> alert-dismissible fade show">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$is_assigned): ?>
        <!-- Alert jika belum ada petugas ditugaskan -->
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Belum Ditugaskan:</strong> Laporan ini belum ditugaskan ke petugas manapun. Anda hanya dapat melihat informasi laporan.
        </div>
    <?php elseif (!$is_my_assignment): ?>
        <!-- Alert jika ditugaskan ke petugas lain -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Informasi:</strong> Laporan ini ditugaskan ke petugas lain (<?= htmlspecialchars($report['officer_name']) ?>). Anda hanya dapat melihat informasi laporan.
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column - Report Details -->
        <div class="col-lg-<?= $can_handle ? '8' : '12' ?>">
            <!-- Report Info Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Laporan</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2">Status</h6>
                            <span class="badge bg-<?= get_status_badge_class($report['status']) ?> fs-6">
                                <?= get_status_text($report['status']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2">Prioritas</h6>
                            <span class="badge bg-<?= get_priority_badge_class($report['priority']) ?> fs-6">
                                <?= get_priority_text($report['priority']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2">Petugas Ditugaskan</h6>
                            <?php if ($is_assigned): ?>
                                <p class="mb-0">
                                    <i class="fas fa-user-shield me-1"></i>
                                    <?= htmlspecialchars($report['officer_name']) ?>
                                    <small class="text-muted">(<?= htmlspecialchars($report['badge_number']) ?>)</small>
                                </p>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum Ditugaskan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h4 class="mb-3">
                        <i class="<?= htmlspecialchars($report['icon']) ?> me-2"></i>
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
                            <small class="text-muted">(<?= $report['hours_ago'] ?> jam yang lalu)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted"><i class="fas fa-clock me-2"></i>Dilaporkan</h6>
                            <p><?= format_datetime($report['created_at']) ?></p>
                        </div>
                    </div>

                    <hr>

                    <h6 class="text-muted mb-2"><i class="fas fa-user me-2"></i>Pelapor</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($report['reporter_name']) ?></p>
                            <p class="mb-1"><strong>NIM/NIP:</strong> <?= htmlspecialchars($report['nim_nip']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Email:</strong> 
                                <a href="mailto:<?= htmlspecialchars($report['reporter_email']) ?>"><?= htmlspecialchars($report['reporter_email']) ?></a>
                            </p>
                            <?php if ($report['reporter_phone']): ?>
                                <p class="mb-1"><strong>Telepon:</strong> 
                                    <a href="tel:<?= htmlspecialchars($report['reporter_phone']) ?>"><?= htmlspecialchars($report['reporter_phone']) ?></a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>Bukti Lampiran</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($attachments as $file): ?>
                                <div class="col-md-<?= $can_handle ? '4' : '3' ?> mb-3">
                                    <?php if (is_image($file['file_type'])): ?>
                                        <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank" data-lightbox="evidence">
                                            <img src="<?= htmlspecialchars($file['file_path']) ?>" class="img-fluid rounded shadow-sm" alt="Evidence" style="cursor: pointer;">
                                        </a>
                                        <p class="small text-muted mt-2 mb-0"><?= htmlspecialchars($file['file_name']) ?></p>
                                    <?php else: ?>
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-file fa-3x text-secondary mb-2"></i>
                                                <p class="small mb-2"><?= htmlspecialchars($file['file_name']) ?></p>
                                                <small class="text-muted d-block mb-2"><?= format_filesize($file['file_size']) ?></small>
                                                <a href="<?= htmlspecialchars($file['file_path']) ?>" class="btn btn-sm btn-primary" download>
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
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Penanganan</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($actions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada riwayat penanganan</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($actions as $action): ?>
                                <div class="timeline-item mb-4">
                                    <div class="d-flex">
                                        <div class="timeline-marker bg-primary rounded-circle me-3" style="width: 12px; height: 12px; margin-top: 6px;"></div>
                                        <div class="flex-grow-1">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-0">
                                                            <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $action['action_type'])) ?></span>
                                                        </h6>
                                                        <small class="text-muted"><?= format_datetime($action['action_date']) ?></small>
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
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?= htmlspecialchars($action['officer_name']) ?> (<?= htmlspecialchars($action['badge_number']) ?>)
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Map jika tidak ada sidebar aksi -->
            <?php if (!$can_handle && $report['latitude'] && $report['longitude']): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-map me-2"></i>Lokasi Kejadian</h5>
                    </div>
                    <div class="card-body p-0">
                        <iframe 
                            width="100%" 
                            height="400" 
                            frameborder="0" 
                            scrolling="no" 
                            marginheight="0" 
                            marginwidth="0" 
                            src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $report['longitude']-0.01 ?>,<?= $report['latitude']-0.01 ?>,<?= $report['longitude']+0.01 ?>,<?= $report['latitude']+0.01 ?>&layer=mapnik&marker=<?= $report['latitude'] ?>,<?= $report['longitude'] ?>">
                        </iframe>
                        <div class="p-2 text-center bg-light">
                            <small class="text-muted">
                                <i class="fas fa-map-pin me-1"></i>
                                Koordinat: <?= $report['latitude'] ?>, <?= $report['longitude'] ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Actions (HANYA TAMPIL JIKA PETUGAS DITUGASKAN) -->
        <?php if ($can_handle): ?>
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Aksi Cepat</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Ubah Status</label>
                            <select name="status" class="form-select" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="in_progress" <?= $report['status'] === 'in_progress' ? 'selected' : '' ?>>Sedang Ditangani</option>
                                <option value="resolved" <?= $report['status'] === 'resolved' ? 'selected' : '' ?>>Selesai</option>
                                <option value="closed" <?= $report['status'] === 'closed' ? 'selected' : '' ?>>Ditutup</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan Penyelesaian (Opsional)</label>
                            <textarea name="resolution_notes" class="form-control" rows="3" placeholder="Jelaskan tindakan yang telah dilakukan..."><?= htmlspecialchars($report['resolution_notes'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save me-1"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Add Action -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Tambah Aksi</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="add_action" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipe Aksi *</label>
                            <select name="action_type" class="form-select" required>
                                <option value="">-- Pilih Tipe --</option>
                                <option value="investigation">Investigasi</option>
                                <option value="patrol">Patroli</option>
                                <option value="interview">Wawancara</option>
                                <option value="coordination">Koordinasi</option>
                                <option value="follow_up">Tindak Lanjut</option>
                                <option value="documentation">Dokumentasi</option>
                                <option value="resolution">Penyelesaian</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi Aksi *</label>
                            <textarea name="action_description" class="form-control" rows="3" required placeholder="Jelaskan tindakan yang dilakukan..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan Tambahan</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Catatan internal (opsional)"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i> Tambah Aksi
                        </button>
                    </form>
                </div>
            </div>

            <!-- Map (if location has coordinates) -->
            <?php if ($report['latitude'] && $report['longitude']): ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-map me-2"></i>Lokasi Kejadian</h5>
                    </div>
                    <div class="card-body p-0">
                        <iframe 
                            width="100%" 
                            height="250" 
                            frameborder="0" 
                            scrolling="no" 
                            marginheight="0" 
                            marginwidth="0" 
                            src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $report['longitude']-0.01 ?>,<?= $report['latitude']-0.01 ?>,<?= $report['longitude']+0.01 ?>,<?= $report['latitude']+0.01 ?>&layer=mapnik&marker=<?= $report['latitude'] ?>,<?= $report['longitude'] ?>">
                        </iframe>
                        <div class="p-2 text-center bg-light">
                            <small class="text-muted">
                                <i class="fas fa-map-pin me-1"></i>
                                Koordinat: <?= $report['latitude'] ?>, <?= $report['longitude'] ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>