<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Menu items
$menu_items = [
    [
        'icon' => 'fas fa-tachometer-alt',
        'title' => 'Dashboard',
        'url' => 'dashboard.php',
        'page' => 'dashboard.php'
    ],
    [
        'icon' => 'fas fa-file-alt',
        'title' => 'Kelola Laporan',
        'url' => 'reports.php',
        'page' => 'reports.php',
        'badge' => [
            'query' => "SELECT COUNT(*) FROM reports WHERE status = 'new'",
            'class' => 'bg-danger'
        ]
    ],
    [
        'icon' => 'fas fa-user-shield',
        'title' => 'Petugas Keamanan',
        'url' => 'officers.php',
        'page' => 'officers.php'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'Pengguna',
        'url' => 'users.php',
        'page' => 'users.php'
    ],
    [
        'icon' => 'fas fa-tags',
        'title' => 'Kategori Insiden',
        'url' => 'categories.php',
        'page' => 'categories.php'
    ],
    [
        'icon' => 'fas fa-map-marker-alt',
        'title' => 'Lokasi',
        'url' => 'locations.php',
        'page' => 'locations.php'
    ],
    [
        'icon' => 'fas fa-bullhorn',
        'title' => 'Pengumuman',
        'url' => 'announcements.php',
        'page' => 'announcements.php'
    ],
    [
        'icon' => 'fas fa-chart-bar',
        'title' => 'Laporan Statistik',
        'url' => 'statistics.php',
        'page' => 'statistics.php'
    ],
    [
        'icon' => 'fas fa-cog',
        'title' => 'Pengaturan',
        'url' => 'settings.php',
        'page' => 'settings.php'
    ]
];
?>

<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <?php foreach ($menu_items as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == $item['page'] ? 'active' : ''; ?>" 
                       href="<?php echo $item['url']; ?>">
                        <i class="<?php echo $item['icon']; ?> me-2"></i>
                        <?php echo $item['title']; ?>
                        
                        <?php if (isset($item['badge'])): ?>
                            <?php
                            $stmt = $pdo->query($item['badge']['query']);
                            $count = $stmt->fetchColumn();
                            if ($count > 0):
                            ?>
                                <span class="badge <?php echo $item['badge']['class']; ?> ms-2">
                                    <?php echo $count; ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Lainnya</span>
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