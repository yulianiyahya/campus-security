<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get security officer data
$stmt = $pdo->prepare("
    SELECT so.* 
    FROM security_officers so 
    WHERE so.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$officer = $stmt->fetch();

// Menu items
$menu_items = [
    [
        'icon' => 'fas fa-tachometer-alt',
        'title' => 'Dashboard',
        'url' => 'dashboard.php',
        'page' => 'dashboard.php'
    ],
    [
        'icon' => 'fas fa-tasks',
        'title' => 'Tugas Saya',
        'url' => 'my_tasks.php',
        'page' => 'my_tasks.php',
        'badge' => [
            'query' => "SELECT COUNT(*) FROM reports WHERE assigned_officer_id = " . ($officer['id'] ?? 0) . " AND status IN ('assigned', 'in_progress')",
            'class' => 'bg-warning'
        ]
    ],
    [
        'icon' => 'fas fa-file-alt',
        'title' => 'Semua Laporan',
        'url' => 'reports.php',
        'page' => 'reports.php'
    ],
    [
        'icon' => 'fas fa-check-circle',
        'title' => 'Laporan Selesai',
        'url' => 'completed_reports.php',
        'page' => 'completed_reports.php'
    ],
    [
        'icon' => 'fas fa-map-marked-alt',
        'title' => 'Patroli',
        'url' => 'patrols.php',
        'page' => 'patrols.php'
    ],
    [
        'icon' => 'fas fa-clipboard-list',
        'title' => 'Riwayat Tugas',
        'url' => 'task_history.php',
        'page' => 'task_history.php'
    ],
    [
        'icon' => 'fas fa-bullhorn',
        'title' => 'Pengumuman',
        'url' => 'announcements.php',
        'page' => 'announcements.php'
    ]
];
?>

<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <!-- Officer Info Card -->
        <div class="card mb-3 mx-2 border-success">
            <div class="card-body p-2">
                <div class="d-flex align-items-center">
                    <div class="avatar bg-success text-white me-2" 
                         style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Petugas</small>
                        <strong style="font-size: 0.85rem;">
                            <?php echo htmlspecialchars($officer['badge_number'] ?? 'N/A'); ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav flex-column">
            <?php foreach ($menu_items as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == $item['page'] ? 'active' : ''; ?>" 
                       href="<?php echo $item['url']; ?>">
                        <i class="<?php echo $item['icon']; ?> me-2"></i>
                        <?php echo $item['title']; ?>
                        
                        <?php if (isset($item['badge'])): ?>
                            <?php
                            try {
                                $stmt = $pdo->query($item['badge']['query']);
                                $count = $stmt->fetchColumn();
                                if ($count > 0):
                            ?>
                                <span class="badge <?php echo $item['badge']['class']; ?> ms-2">
                                    <?php echo $count; ?>
                                </span>
                            <?php 
                                endif;
                            } catch (Exception $e) {
                                // Ignore error if query fails
                            }
                            ?>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Quick Stats -->
        <div class="card mx-2 mt-3 border-info">
            <div class="card-header bg-info text-white py-2">
                <h6 class="mb-0 small">
                    <i class="fas fa-chart-line me-2"></i>
                    Status Tugas Hari Ini
                </h6>
            </div>
            <div class="card-body p-2">
                <?php
                if ($officer):
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                            SUM(CASE WHEN status = 'resolved' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) as today_completed
                        FROM reports 
                        WHERE assigned_officer_id = ?
                    ");
                    $stmt->execute([$officer['id']]);
                    $task_stats = $stmt->fetch();
                ?>
                    <div class="d-flex justify-content-between mb-1">
                        <small>Sedang Diproses:</small>
                        <strong class="text-warning"><?php echo $task_stats['in_progress']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small>Selesai Hari Ini:</small>
                        <strong class="text-success"><?php echo $task_stats['today_completed']; ?></strong>
                    </div>
                <?php else: ?>
                    <small class="text-muted">Data tidak tersedia</small>
                <?php endif; ?>
            </div>
        </div>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Akun</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" 
                   href="profile.php">
                    <i class="fas fa-user me-2"></i>
                    Profil Saya
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="../logout.php" 
                   onclick="return confirm('Yakin ingin logout?')">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.sidebar {
    position: fixed;
    top: 56px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    overflow-y: auto;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: 0.75rem 1rem;
    border-radius: 0.25rem;
    margin: 0.25rem 0.5rem;
}

.sidebar .nav-link:hover {
    background-color: #e9ecef;
    color: #0d6efd;
}

.sidebar .nav-link.active {
    background-color: #0d6efd;
    color: white;
}

.sidebar .nav-link i {
    width: 20px;
}

.sidebar-heading {
    font-size: .75rem;
    text-transform: uppercase;
}

@media (max-width: 767.98px) {
    .sidebar {
        top: 0;
    }
}
</style>