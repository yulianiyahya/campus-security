<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Pengumuman';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9; // Changed to 9 for 3x3 grid
$offset = ($page - 1) * $per_page;

// Filter
$type_filter = isset($_GET['type']) ? clean_input($_GET['type']) : '';

// Build query
$where = "is_published = TRUE AND publish_date <= NOW() AND (expire_date IS NULL OR expire_date > NOW())";
$params = [];

if ($type_filter) {
    $where .= " AND type = ?";
    $params[] = $type_filter;
}

// Get total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE $where");
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get announcements
$stmt = $pdo->prepare("
    SELECT a.*, u.nama as author_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    WHERE $where
    ORDER BY 
        CASE a.priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
        END,
        a.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Helper functions
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

<!-- Header -->
<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h4 class="mb-1">
            <i class="fas fa-bullhorn me-2"></i>Pengumuman Kampus
        </h4>
        <p class="text-muted mb-0">
            Menampilkan <strong><?php echo $total_records; ?></strong> pengumuman aktif
        </p>
    </div>
    <div class="col-md-4">
        <form method="GET">
            <select name="type" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Tipe</option>
                <option value="emergency" <?php echo $type_filter == 'emergency' ? 'selected' : ''; ?>>
                    🚨 Darurat
                </option>
                <option value="warning" <?php echo $type_filter == 'warning' ? 'selected' : ''; ?>>
                    ⚠️ Peringatan
                </option>
                <option value="info" <?php echo $type_filter == 'info' ? 'selected' : ''; ?>>
                    ℹ️ Informasi
                </option>
                <option value="general" <?php echo $type_filter == 'general' ? 'selected' : ''; ?>>
                    📢 Umum
                </option>
            </select>
        </form>
    </div>
</div>

<!-- Announcements Grid -->
<?php if (empty($announcements)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-inbox fa-4x text-muted mb-3 opacity-50"></i>
            <h5 class="text-muted">Tidak Ada Pengumuman</h5>
            <p class="text-muted mb-0">
                Saat ini tidak ada pengumuman aktif
                <?php if ($type_filter): ?>
                    dengan tipe "<?php 
                        $type_labels = [
                            'emergency' => 'Darurat',
                            'warning' => 'Peringatan',
                            'info' => 'Informasi',
                            'general' => 'Umum'
                        ];
                        echo $type_labels[$type_filter] ?? ucfirst($type_filter);
                    ?>"
                <?php endif; ?>
            </p>
            <?php if ($type_filter): ?>
                <a href="announcements.php" class="btn btn-primary mt-3">
                    <i class="fas fa-list me-2"></i>Lihat Semua Pengumuman
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4 mb-4">
        <?php foreach ($announcements as $ann): ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm announcement-card" 
                     style="cursor: pointer; transition: all 0.3s ease;"
                     onclick="showAnnouncementDetail(<?php echo htmlspecialchars(json_encode($ann), ENT_QUOTES); ?>)"
                     onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.15)';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)';">
                    
                    <!-- Priority Badge - Top Right -->
                    <?php if ($ann['priority'] === 'urgent' || $ann['priority'] === 'high'): ?>
                        <span class="badge bg-<?php echo $ann['priority'] === 'urgent' ? 'danger' : 'warning'; ?> position-absolute top-0 end-0 m-3 z-index-1" style="z-index: 10;">
                            <i class="fas fa-exclamation-circle"></i> 
                            <?php echo strtoupper($ann['priority']); ?>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Image -->
                    <?php if (!empty($ann['image_path'])): ?>
                        <div class="position-relative overflow-hidden" style="height: 200px;">
                            <img src="<?php echo getImageUrl($ann['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($ann['title']); ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover; transition: transform 0.3s ease;"
                                 onerror="this.parentElement.style.display='none'; this.parentElement.nextElementSibling.style.paddingTop='1.5rem';">
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <!-- Type Badge -->
                        <div class="mb-2">
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
                        <h5 class="card-title mb-2">
                            <?php echo htmlspecialchars($ann['title']); ?>
                        </h5>
                        
                        <!-- Content Preview -->
                        <p class="card-text text-muted small mb-3" style="min-height: 60px;">
                            <?php 
                            $content = strip_tags($ann['content']);
                            echo strlen($content) > 120 ? substr($content, 0, 120) . '...' : $content;
                            ?>
                        </p>
                        
                        <!-- Footer -->
                        <div class="border-top pt-3">
                            <div class="row text-muted small">
                                <div class="col-6">
                                    <i class="fas fa-user fa-xs me-1"></i>
                                    <?php echo htmlspecialchars($ann['author_name'] ?? 'Admin'); ?>
                                </div>
                                <div class="col-6 text-end">
                                    <i class="fas fa-calendar fa-xs me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($ann['publish_date'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($ann['expire_date']): ?>
                                <div class="mt-2 text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-hourglass-end fa-xs me-1"></i>
                                        Berlaku hingga <?php echo date('d/m/Y', strtotime($ann['expire_date'])); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light border-0">
                        <div class="text-center">
                            <small class="text-primary">
                                <i class="fas fa-hand-pointer me-1"></i>Klik untuk detail lengkap
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type_filter); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type_filter); ?>">
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
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type_filter); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

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
                <div id="modalContent" class="mb-3" style="white-space: pre-line; line-height: 1.8;"></div>
                
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

.announcement-card img {
    transition: transform 0.3s ease;
}

.announcement-card:hover img {
    transform: scale(1.1);
}

/* Responsive grid */
@media (max-width: 768px) {
    .col-md-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

@media (min-width: 769px) and (max-width: 991px) {
    .col-md-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
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