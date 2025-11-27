<?php
require_once '../config.php';
check_login();
check_role(['security']);

$page_title = 'Pengumuman';

// Get active announcements - SESUAI DENGAN DASHBOARD
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
");
$announcements = $stmt->fetchAll();

// Helper functions - SAMA SEPERTI DI DASHBOARD
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
require_once '../includes/navbar_security.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-bullhorn me-2 text-primary"></i>Pengumuman Keamanan
                    </h2>
                    <p class="text-muted mb-0">Informasi dan pengumuman terkait keamanan kampus</p>
                </div>
                <div>
                    <span class="badge bg-primary fs-6">
                        <i class="fas fa-bell me-1"></i><?php echo count($announcements); ?> Pengumuman Aktif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($announcements)): ?>
        <!-- Empty State -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-info-circle fa-5x text-muted opacity-50 mb-4"></i>
                        <h4 class="text-muted">Tidak Ada Pengumuman</h4>
                        <p class="text-muted mb-0">Tidak ada pengumuman aktif saat ini.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Announcements Grid -->
        <div class="row">
            <?php foreach ($announcements as $ann): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="announcement-card card h-100 border-0 shadow-sm" 
                         style="cursor: pointer; transition: all 0.3s ease;"
                         onclick="showAnnouncementDetail(<?php echo htmlspecialchars(json_encode($ann), ENT_QUOTES); ?>)"
                         onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.15)';"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)';">
                        
                        <!-- Priority Badge - Top Right -->
                        <?php if ($ann['priority'] === 'urgent' || $ann['priority'] === 'high'): ?>
                            <span class="badge bg-<?php echo $ann['priority'] === 'urgent' ? 'danger' : 'warning'; ?> position-absolute top-0 end-0 m-3" style="z-index: 10;">
                                <i class="fas fa-exclamation-circle"></i> 
                                <?php echo strtoupper($ann['priority']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <!-- Image -->
                        <?php if (!empty($ann['image_path'])): ?>
                            <div class="announcement-image" style="height: 200px; overflow: hidden; position: relative;">
                                <img src="<?php echo getImageUrl($ann['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($ann['title']); ?>" 
                                     class="w-100 h-100" 
                                     style="object-fit: cover; transition: transform 0.3s ease;"
                                     onerror="this.parentElement.style.display='none'">
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <!-- Type Badge -->
                            <div class="mb-3">
                                <span class="badge bg-<?php echo getAnnouncementBadgeColor($ann['type'], $ann['priority']); ?>">
                                    <i class="fas fa-<?php echo getAnnouncementIcon($ann['type']); ?> me-1"></i>
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
                            </div>
                            
                            <!-- Title -->
                            <h5 class="card-title mb-3">
                                <i class="fas fa-<?php echo getAnnouncementIcon($ann['type']); ?> me-2 text-<?php echo getAnnouncementBadgeColor($ann['type'], $ann['priority']); ?>"></i>
                                <?php echo htmlspecialchars($ann['title']); ?>
                            </h5>
                            
                            <!-- Content Preview -->
                            <p class="card-text text-muted mb-3">
                                <?php 
                                $content = strip_tags($ann['content']);
                                echo strlen($content) > 150 ? substr($content, 0, 150) . '...' : $content;
                                ?>
                            </p>
                            
                            <!-- Footer -->
                            <div class="border-top pt-3 mt-auto">
                                <div class="row g-2 text-muted small">
                                    <div class="col-12">
                                        <i class="fas fa-user fa-xs me-1"></i>
                                        <?php echo htmlspecialchars($ann['author_name'] ?? 'Admin'); ?>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-calendar fa-xs me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($ann['publish_date'])); ?>
                                    </div>
                                    <div class="col-6 text-end">
                                        <i class="fas fa-clock fa-xs me-1"></i>
                                        <?php echo time_ago($ann['publish_date']); ?>
                                    </div>
                                    <?php if ($ann['expire_date']): ?>
                                        <div class="col-12 text-danger">
                                            <i class="fas fa-hourglass-end fa-xs me-1"></i>
                                            Berlaku sampai: <?php echo date('d/m/Y', strtotime($ann['expire_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Click Hint -->
                            <div class="text-center mt-3 pt-2 border-top">
                                <small class="text-primary fw-bold">
                                    <i class="fas fa-hand-pointer me-1"></i>Klik untuk detail lengkap
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Announcement Detail Modal - SAMA SEPERTI DASHBOARD -->
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
.announcement-card {
    position: relative;
    overflow: hidden;
}

.announcement-card:hover .announcement-image img {
    transform: scale(1.1);
}

.announcement-card .card-body {
    display: flex;
    flex-direction: column;
}

/* Smooth hover effects */
.announcement-card {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.announcement-card:hover {
    box-shadow: 0 12px 24px rgba(0,0,0,0.15) !important;
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Modal animations */
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
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
    
    // Set header color based on priority/type
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