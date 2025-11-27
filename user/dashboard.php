<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Dashboard';

// Get my reports statistics - FIXED: Ganti SUM jadi COUNT
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_reports,
        COUNT(CASE WHEN status = 'assigned' OR status = 'in_progress' THEN 1 END) as ongoing,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved
    FROM reports
    WHERE reporter_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$my_stats = $stmt->fetch();

// Get my recent reports
$stmt = $pdo->prepare("
    SELECT r.*, ic.name as category_name, ic.icon,
           il.building_name, il.floor
    FROM reports r
    JOIN incident_categories ic ON r.category_id = ic.id
    JOIN incident_locations il ON r.location_id = il.id
    WHERE r.reporter_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_reports = $stmt->fetchAll();

// Active announcements - SAMA SEPERTI SECURITY
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

// Unread notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND is_read = FALSE
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$unread_notifications = $stmt->fetchAll();

// Helper functions - SAMA SEPERTI SECURITY
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

function getAnnouncementIcon($type) {
    $icons = [
        'emergency' => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle',
        'general' => 'bullhorn'
    ];
    return $icons[$type] ?? 'info-circle';
}

function getImageUrl($image_path) {
    if (empty($image_path)) return '';
    
    if (strpos($image_path, 'uploads/') === 0) {
        return '../' . $image_path;
    }
    
    if (strpos($image_path, '/') === false) {
        return '../uploads/announcements/' . $image_path;
    }
    
    return '../' . $image_path;
}

require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<!-- Welcome Banner -->
<div class="card bg-gradient-primary text-white mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="mb-2">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h3>
                <p class="mb-3">Laporkan setiap kejadian keamanan yang Anda temui untuk menjaga kampus tetap aman.</p>
                <a href="create_report.php" class="btn btn-light">
                    <i class="fas fa-plus-circle me-2"></i>Buat Laporan Baru
                </a>
            </div>
            <div class="col-md-4 text-center d-none d-md-block">
                <i class="fas fa-shield-alt fa-5x opacity-50"></i>
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
                        <h6 class="text-muted mb-2">Total Laporan</h6>
                        <h2 class="mb-0"><?php echo number_format($my_stats['total']); ?></h2>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-file-alt fa-3x opacity-50"></i>
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
                        <h6 class="text-muted mb-2">Laporan Baru</h6>
                        <h2 class="mb-0"><?php echo number_format($my_stats['new_reports']); ?></h2>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-clock fa-3x opacity-50"></i>
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
                        <h2 class="mb-0"><?php echo number_format($my_stats['ongoing']); ?></h2>
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
    <!-- My Recent Reports -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Laporan Saya</h5>
                <a href="my_reports.php" class="btn btn-sm btn-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_reports)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-4x mb-3"></i>
                        <h5>Belum Ada Laporan</h5>
                        <p>Anda belum membuat laporan apapun</p>
                        <a href="create_report.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus-circle me-2"></i>Buat Laporan Pertama
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_reports as $report): ?>
                            <a href="report_detail.php?id=<?php echo $report['id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="me-2"><?php echo $report['icon'] ?? '📋'; ?></span>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($report['title']); ?></h6>
                                        </div>
                                        <p class="mb-1 text-muted small">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($report['category_name']); ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($report['building_name']); ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo time_ago($report['created_at']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end ms-3">
                                        <span class="badge bg-<?php 
                                            echo $report['status'] == 'new' ? 'primary' : 
                                                ($report['status'] == 'assigned' ? 'info' : 
                                                ($report['status'] == 'in_progress' ? 'warning' : 'success')); 
                                        ?> mb-2">
                                            <?php 
                                                $status_labels = [
                                                    'new' => 'Baru',
                                                    'assigned' => 'Ditugaskan',
                                                    'in_progress' => 'Diproses',
                                                    'resolved' => 'Selesai'
                                                ];
                                                echo $status_labels[$report['status']];
                                            ?>
                                        </span>
                                        <br>
                                        <span class="badge bg-<?php 
                                            echo $report['priority'] == 'high' ? 'danger' : 
                                                ($report['priority'] == 'medium' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($report['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($unread_notifications)): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notifikasi Terbaru</h5>
                <a href="notifications.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($unread_notifications as $notif): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0">
                                    <span class="badge bg-<?php 
                                        echo $notif['type'] == 'report_update' ? 'primary' : 
                                            ($notif['type'] == 'announcement' ? 'info' : 'secondary'); 
                                    ?> rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-<?php 
                                            echo $notif['type'] == 'report_update' ? 'file-alt' : 
                                                ($notif['type'] == 'announcement' ? 'bullhorn' : 'bell'); 
                                        ?>"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo time_ago($notif['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Aksi Cepat</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="create_report.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Buat Laporan Baru
                    </a>
                    <a href="my_reports.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt me-2"></i>Lihat Semua Laporan
                    </a>
                    <a href="announcements.php" class="btn btn-outline-info">
                        <i class="fas fa-bullhorn me-2"></i>Pengumuman
                    </a>
                    <a href="profile.php" class="btn btn-outline-secondary">
                        <i class="fas fa-user me-2"></i>Edit Profil
                    </a>
                </div>
            </div>
        </div>

        <!-- Announcements - ENHANCED DENGAN GAMBAR -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Pengumuman</h5>
                <a href="announcements.php" class="btn btn-sm btn-outline-primary">Semua</a>
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
                                
                                <!-- Image Thumbnail -->
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

        <!-- Help Card -->
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Butuh Bantuan?</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">Jika Anda mengalami situasi darurat, segera hubungi:</p>
                <div class="d-grid gap-2">
                    <a href="tel:112" class="btn btn-danger">
                        <i class="fas fa-phone me-2"></i>Emergency: 112
                    </a>
                    <a href="tel:021-xxx-xxxx" class="btn btn-outline-primary">
                        <i class="fas fa-phone me-2"></i>Satpam Kampus
                    </a>
                </div>
                <hr>
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Untuk laporan non-darurat, gunakan form laporan di atas.
                </small>
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

/* Custom scrollbar */
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
    
    // Set modal title
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
    
    // Set image
    if (announcement.image_path) {
        let imagePath = announcement.image_path;
        
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
    
    // Set meta
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