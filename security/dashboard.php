<?php
require_once '../config.php';
check_login();
check_role(['security']);

$page_title = 'Dashboard Petugas';

// Get officer info
$stmt = $pdo->prepare("SELECT * FROM security_officers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$officer = $stmt->fetch();

// Get my assignments statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'assigned' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved
    FROM reports
    WHERE assigned_officer_id = ?
");
$stmt->execute([$officer['id']]);
$my_stats = $stmt->fetch();

// Get my current assignments (not resolved)
$stmt = $pdo->prepare("
    SELECT r.*, u.nama as reporter_name, ic.name as category_name,
           il.building_name, il.floor
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    JOIN incident_categories ic ON r.category_id = ic.id
    JOIN incident_locations il ON r.location_id = il.id
    WHERE r.assigned_officer_id = ? 
    AND r.status != 'resolved'
    ORDER BY 
        CASE r.priority 
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END,
        r.created_at ASC
");
$stmt->execute([$officer['id']]);
$my_assignments = $stmt->fetchAll();

// Get recent activities
$stmt = $pdo->prepare("
    SELECT ra.*, r.report_number, r.title
    FROM report_actions ra
    JOIN reports r ON ra.report_id = r.id
    WHERE ra.officer_id = ?
    ORDER BY ra.action_date DESC
    LIMIT 10
");
$stmt->execute([$officer['id']]);
$recent_activities = $stmt->fetchAll();

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
    if ($priority === 'urgent') return 'danger';
    if ($priority === 'high') return 'warning';
    
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

// Helper function untuk fix image path - INI YANG PENTING!
function getImageUrl($image_path) {
    if (empty($image_path)) return '';
    
    // Jika path sudah lengkap dengan uploads/, langsung gunakan
    if (strpos($image_path, 'uploads/') === 0) {
        return '../' . $image_path;
    }
    
    // Jika hanya nama file, tambahkan path lengkap
    if (strpos($image_path, '/') === false) {
        return '../uploads/announcements/' . $image_path;
    }
    
    // Default: anggap path relatif dari root
    return '../' . $image_path;
}

require_once '../includes/header.php';
require_once '../includes/navbar_security.php';
?>

<!-- Officer Info Card -->
<div class="card bg-gradient-primary text-white mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-2">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h4>
                <p class="mb-0">
                    <i class="fas fa-id-badge me-2"></i>Badge Number: <strong><?php echo htmlspecialchars($officer['badge_number']); ?></strong>
                </p>
                <p class="mb-0">
                    <i class="fas fa-clock me-2"></i>Shift: <strong><?php echo ucfirst($officer['shift']); ?></strong>
                </p>
                <p class="mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>Area: <strong><?php echo htmlspecialchars($officer['area_responsibility']); ?></strong>
                </p>
            </div>
            <div class="col-md-4 text-center">
                <i class="fas fa-user-shield fa-5x opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Tugas</h6>
                        <h2 class="mb-0"><?php echo number_format($my_stats['total']); ?></h2>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-tasks fa-3x opacity-50"></i>
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
                        <h6 class="text-muted mb-2">Menunggu</h6>
                        <h2 class="mb-0"><?php echo number_format($my_stats['pending']); ?></h2>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-hourglass-half fa-3x opacity-50"></i>
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
                        <h6 class="text-muted mb-2">Dalam Proses</h6>
                        <h2 class="mb-0"><?php echo number_format($my_stats['in_progress']); ?></h2>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-spinner fa-3x opacity-50"></i>
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
                        <h6 class="text-muted mb-2">Selesai</h6>
                        <h2 class="mb-0"><?php echo number_format($my_stats['resolved']); ?></h2>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- My Assignments -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Tugas Saya</h5>
                <a href="my_assignments.php" class="btn btn-sm btn-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <?php if (empty($my_assignments)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-clipboard-check fa-4x mb-3"></i>
                        <h5>Tidak Ada Tugas Aktif</h5>
                        <p>Anda tidak memiliki tugas yang perlu ditangani saat ini</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No. Laporan</th>
                                    <th>Judul</th>
                                    <th>Lokasi</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_assignments as $report): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($report['report_number']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($report['title'], 0, 50)); ?>...</td>
                                        <td>
                                            <?php echo htmlspecialchars($report['building_name']); ?>
                                            <?php if ($report['floor']): ?>
                                                - Lt. <?php echo $report['floor']; ?>
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
                                                echo $report['status'] == 'assigned' ? 'info' : 'warning'; 
                                            ?>">
                                                <?php echo $report['status'] == 'assigned' ? 'Ditugaskan' : 'Diproses'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="handle_report.php?id=<?php echo $report['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> Tangani
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terakhir</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center text-muted py-4">
                        <p>Belum ada aktivitas</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <div class="timeline-marker bg-primary rounded-circle" style="width: 12px; height: 12px; margin-top: 5px;"></div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($activity['action_type']); ?></strong>
                                            <small class="text-muted">
                                                <?php echo time_ago($activity['action_date']); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1 text-muted">
                                            Laporan: <?php echo htmlspecialchars($activity['report_number']); ?>
                                        </p>
                                        <p class="mb-0 small">
                                            <?php echo htmlspecialchars($activity['action_description']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Announcements -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Pengumuman</h5>
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($announcements)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-info-circle fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0">Tidak ada pengumuman</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="announcement-card card mb-3 border-0 shadow-sm" 
                             style="cursor: pointer; transition: all 0.3s ease;"
                             onclick="showAnnouncementDetail(<?php echo htmlspecialchars(json_encode($ann), ENT_QUOTES); ?>)"
                             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 16px rgba(0,0,0,0.15)';"
                             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)';">
                            
                            <div class="card-body p-3">
                                <!-- Priority Badge - Top Right -->
                                <?php if ($ann['priority'] === 'urgent' || $ann['priority'] === 'high'): ?>
                                    <span class="badge bg-<?php echo $ann['priority'] === 'urgent' ? 'danger' : 'warning'; ?> position-absolute top-0 end-0 m-2">
                                        <i class="fas fa-exclamation-circle"></i> 
                                        <?php echo strtoupper($ann['priority']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <!-- Image Thumbnail - FIXED PATH -->
                                <?php if (!empty($ann['image_path'])): ?>
                                    <div class="mb-3 text-center">
                                        <img src="<?php echo getImageUrl($ann['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($ann['title']); ?>" 
                                             class="img-fluid rounded shadow-sm" 
                                             style="max-height: 150px; width: 100%; object-fit: cover;"
                                             onerror="this.style.display='none'">
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Title with Icon -->
                                <h6 class="card-title mb-2">
                                    <i class="fas fa-<?php echo getAnnouncementIcon($ann['type']); ?> me-2 text-<?php echo getAnnouncementBadgeColor($ann['type'], $ann['priority']); ?>"></i>
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </h6>
                                
                                <!-- Type Badge -->
                                <span class="badge bg-<?php echo getAnnouncementBadgeColor($ann['type'], $ann['priority']); ?> mb-2">
                                    <?php 
                                    $type_labels = [
                                        'emergency' => 'Darurat',
                                        'warning' => 'Peringatan',
                                        'info' => 'Informasi',
                                        'general' => 'Umum'
                                    ];
                                    echo $type_labels[$ann['type']] ?? ucfirst($ann['type']);
                                    ?>
                                </span>
                                
                                <!-- Content Preview -->
                                <p class="card-text small text-muted mb-2">
                                    <?php 
                                    $content = strip_tags($ann['content']);
                                    echo strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content;
                                    ?>
                                </p>
                                
                                <!-- Footer -->
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                    <small class="text-muted">
                                        <i class="fas fa-user fa-xs me-1"></i>
                                        <?php echo htmlspecialchars($ann['author_name'] ?? 'Admin'); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-clock fa-xs me-1"></i>
                                        <?php echo time_ago($ann['publish_date']); ?>
                                    </small>
                                </div>
                                
                                <!-- Click to view hint -->
                                <div class="text-center mt-2">
                                    <small class="text-primary">
                                        <i class="fas fa-hand-pointer me-1"></i>Klik untuk detail lengkap
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Aksi Cepat</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="my_assignments.php" class="btn btn-primary">
                        <i class="fas fa-tasks me-2"></i>Lihat Semua Tugas
                    </a>
                    <a href="all_reports.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt me-2"></i>Semua Laporan
                    </a>
                    <a href="profile.php" class="btn btn-outline-secondary">
                        <i class="fas fa-user me-2"></i>Edit Profil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Announcement Detail Modal -->
<div class="modal fade" id="announcementDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Priority & Type Badges -->
                <div class="mb-3" id="modalBadges"></div>
                
                <!-- Image -->
                <div id="modalImageContainer" class="mb-3"></div>
                
                <!-- Content -->
                <div id="modalContent" class="mb-3" style="white-space: pre-line;"></div>
                
                <!-- Meta Information -->
                <hr>
                <div class="row text-muted small">
                    <div class="col-md-6">
                        <p class="mb-2">
                            <i class="fas fa-user me-2"></i><strong>Dibuat oleh:</strong> 
                            <span id="modalAuthor"></span>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-calendar me-2"></i><strong>Tanggal Publish:</strong> 
                            <span id="modalPublishDate"></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2">
                            <i class="fas fa-tag me-2"></i><strong>Tipe:</strong> 
                            <span id="modalType"></span>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-hourglass-end me-2"></i><strong>Kadaluarsa:</strong> 
                            <span id="modalExpireDate"></span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.announcement-card {
    position: relative;
    overflow: hidden;
}

.announcement-card:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.15) !important;
}

.announcement-card img {
    transition: transform 0.3s ease;
}

.announcement-card:hover img {
    transform: scale(1.05);
}

/* Custom scrollbar untuk announcement section */
.card-body::-webkit-scrollbar {
    width: 8px;
}

.card-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<script>
function showAnnouncementDetail(announcement) {
    // Type mapping
    const typeIcons = {
        'emergency': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle',
        'general': 'bullhorn'
    };
    
    const typeColors = {
        'emergency': 'danger',
        'warning': 'warning',
        'info': 'info',
        'general': 'primary'
    };
    
    const typeLabels = {
        'emergency': 'Darurat',
        'warning': 'Peringatan',
        'info': 'Informasi',
        'general': 'Umum'
    };
    
    const priorityLabels = {
        'urgent': 'Mendesak',
        'high': 'Tinggi',
        'normal': 'Normal'
    };
    
    // Set modal title with icon
    const icon = typeIcons[announcement.type] || 'info-circle';
    document.getElementById('modalTitle').innerHTML = 
        `<i class="fas fa-${icon} me-2"></i>${announcement.title}`;
    
    // Set header color
    const headerColor = announcement.priority === 'urgent' || announcement.priority === 'high' ? 
        (announcement.priority === 'urgent' ? 'danger' : 'warning') : 
        (typeColors[announcement.type] || 'info');
    
    document.getElementById('modalHeader').className = `modal-header bg-${headerColor} text-white`;
    
    // Set badges
    let badgesHtml = '';
    if (announcement.priority === 'urgent' || announcement.priority === 'high') {
        badgesHtml += `<span class="badge bg-${announcement.priority === 'urgent' ? 'danger' : 'warning'} me-2 fs-6">
            <i class="fas fa-exclamation-circle"></i> ${priorityLabels[announcement.priority]}
        </span>`;
    }
    badgesHtml += `<span class="badge bg-${typeColors[announcement.type] || 'info'} fs-6">
        <i class="fas fa-${icon}"></i> ${typeLabels[announcement.type]}
    </span>`;
    document.getElementById('modalBadges').innerHTML = badgesHtml;
    
    // Set image if exists - FIXED PATH
    if (announcement.image_path) {
        let imagePath = announcement.image_path;
        
        // Fix path untuk modal
        if (imagePath.indexOf('uploads/') === 0) {
            imagePath = '../' + imagePath;
        } else if (imagePath.indexOf('/') === -1) {
            imagePath = '../uploads/announcements/' + imagePath;
        } else {
            imagePath = '../' + imagePath;
        }
        
        document.getElementById('modalImageContainer').innerHTML = `
            <div class="text-center">
                <img src="${imagePath}" 
                     alt="Announcement Image" 
                     class="img-fluid rounded shadow"
                     style="max-height: 500px; width: auto; max-width: 100%;"
                     onerror="this.parentElement.style.display='none'">
            </div>
        `;
    } else {
        document.getElementById('modalImageContainer').innerHTML = '';
    }
    
    // Set content
    document.getElementById('modalContent').textContent = announcement.content;
    
    // Set meta information
    document.getElementById('modalAuthor').textContent = announcement.author_name || 'Admin';
    document.getElementById('modalType').textContent = typeLabels[announcement.type] || announcement.type;
    
    // Format dates
    const publishDate = new Date(announcement.publish_date);
    document.getElementById('modalPublishDate').textContent = 
        publishDate.toLocaleDateString('id-ID', { 
            day: 'numeric', 
            month: 'long', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    
    if (announcement.expire_date) {
        const expireDate = new Date(announcement.expire_date);
        document.getElementById('modalExpireDate').textContent = 
            expireDate.toLocaleDateString('id-ID', { 
                day: 'numeric', 
                month: 'long', 
                year: 'numeric'
            });
    } else {
        document.getElementById('modalExpireDate').textContent = 'Tidak ada batas waktu';
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('announcementDetailModal'));
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>