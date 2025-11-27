<?php
require_once __DIR__ . '/../config.php';

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$notif_count = $stmt->fetch()['count'];

// Get pending assignments count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reports WHERE assigned_officer_id = ? AND status NOT IN ('resolved', 'closed')");
$stmt->execute([$_SESSION['user_id']]);
$pending_count = $stmt->fetch()['count'];

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-user-shield"></i> Security Panel
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
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'my_assignments.php' ? 'active' : '' ?>" href="my_assignments.php">
                        <i class="fas fa-tasks"></i> Tugas Saya
                        <?php if ($pending_count > 0): ?>
                            <span class="badge bg-warning"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'all_reports.php' ? 'active' : '' ?>" href="all_reports.php">
                        <i class="fas fa-list"></i> Semua Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'announcements.php' ? 'active' : '' ?>" href="announcements.php">
                        <i class="fas fa-bullhorn"></i> Pengumuman
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
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['nama']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>