<?php
// Make sure $pdo is available
if (!isset($pdo)) {
    die('Database connection not available');
}

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$notif_count = $stmt->fetch()['count'];

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-shield-alt"></i> Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['manage_reports.php', 'view_report.php']) ? 'active' : '' ?>" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-exclamation-triangle"></i> Laporan
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="manage_reports.php"><i class="fas fa-list"></i> Kelola Laporan</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['manage_user.php', 'manage_officer.php']) ? 'active' : '' ?>" href="#" id="usersDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-users"></i> Pengguna
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="manage_user.php"><i class="fas fa-user"></i> Kelola Users</a></li>
                        <li><a class="dropdown-item" href="manage_officer.php"><i class="fas fa-user-shield"></i> Kelola Petugas</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['manage_categories.php', 'manage_locations.php']) ? 'active' : '' ?>" href="#" id="masterDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-database"></i> Master Data
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="manage_categories.php"><i class="fas fa-tags"></i> Kategori</a></li>
                        <li><a class="dropdown-item" href="manage_locations.php"><i class="fas fa-map-marker-alt"></i> Lokasi</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'announcements.php' ? 'active' : '' ?>" href="announcements.php">
                        <i class="fas fa-bullhorn"></i> Pengumuman
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'statistics.php' ? 'active' : '' ?>" href="statistics.php">
                        <i class="fas fa-chart-bar"></i> Statistik
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'activity_logs.php' ? 'active' : '' ?>" href="activity_logs.php">
                        <i class="fas fa-history"></i> Log Aktivitas
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
                        <i class="fas fa-bell"></i>
                        <?php if ($notif_count > 0): ?>
                            <span class="badge bg-danger"><?= $notif_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>