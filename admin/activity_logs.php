<?php
require_once '../config.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get filter
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where = [];
$params = [];

if ($user_filter > 0) {
    $where[] = "us.user_id = ?";
    $params[] = $user_filter;
}

if ($date_filter) {
    $where[] = "DATE(us.login_at) = ?";
    $params[] = $date_filter;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM user_sessions us $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get activity logs
$sql = "SELECT us.*, u.nama, u.nim_nip, u.role
        FROM user_sessions us
        JOIN users u ON us.user_id = u.id
        $where_clause
        ORDER BY us.login_at DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get all users for filter
$users = $pdo->query("SELECT id, nama, nim_nip, role FROM users ORDER BY nama")->fetchAll();

// Get today's stats
$today_sql = "SELECT 
              COUNT(*) as total_logins,
              COUNT(DISTINCT user_id) as unique_users
              FROM user_sessions
              WHERE DATE(login_at) = CURDATE()";
$today_stats = $pdo->query($today_sql)->fetch();

$page_title = "Log Aktivitas Sistem";
require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Login Hari Ini</h6>
                            <h2 class="mb-0"><?= $today_stats['total_logins'] ?></h2>
                        </div>
                        <i class="fas fa-sign-in-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card bg-success text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">User Aktif Hari Ini</h6>
                            <h2 class="mb-0"><?= $today_stats['unique_users'] ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Logs Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>Log Aktivitas Sistem
            </h5>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label">Filter User</label>
                    <select class="form-select" name="user">
                        <option value="0">-- Semua User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['nama']) ?> (<?= htmlspecialchars($user['nim_nip']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Filter Tanggal</label>
                    <input type="date" class="form-control" name="date" value="<?= $date_filter ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="activity_logs.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu Login</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>IP Address</th>
                            <th>Browser/Device</th>
                            <th>Waktu Logout</th>
                            <th>Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d M Y', strtotime($activity['login_at'])) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($activity['login_at'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($activity['nama']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($activity['nim_nip']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $role_badges = [
                                        'admin' => 'danger',
                                        'security' => 'warning',
                                        'user' => 'primary'
                                    ];
                                    $role_names = [
                                        'admin' => 'Admin',
                                        'security' => 'Petugas',
                                        'user' => 'User'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $role_badges[$activity['role']] ?>">
                                        <?= $role_names[$activity['role']] ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($activity['ip_address']) ?></code>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php
                                        // Parse user agent untuk tampilan lebih baik
                                        $ua = $activity['user_agent'];
                                        if (strpos($ua, 'Chrome') !== false) {
                                            echo '<i class="fab fa-chrome text-warning"></i> Chrome';
                                        } elseif (strpos($ua, 'Firefox') !== false) {
                                            echo '<i class="fab fa-firefox text-danger"></i> Firefox';
                                        } elseif (strpos($ua, 'Safari') !== false) {
                                            echo '<i class="fab fa-safari text-primary"></i> Safari';
                                        } elseif (strpos($ua, 'Edge') !== false) {
                                            echo '<i class="fab fa-edge text-info"></i> Edge';
                                        } else {
                                            echo '<i class="fas fa-globe"></i> Unknown';
                                        }
                                        
                                        // Detect OS
                                        if (strpos($ua, 'Windows') !== false) {
                                            echo ' / <i class="fab fa-windows"></i> Windows';
                                        } elseif (strpos($ua, 'Mac') !== false) {
                                            echo ' / <i class="fab fa-apple"></i> Mac';
                                        } elseif (strpos($ua, 'Linux') !== false) {
                                            echo ' / <i class="fab fa-linux"></i> Linux';
                                        } elseif (strpos($ua, 'Android') !== false) {
                                            echo ' / <i class="fab fa-android"></i> Android';
                                        } elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) {
                                            echo ' / <i class="fab fa-apple"></i> iOS';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($activity['logout_at']): ?>
                                        <?= date('H:i:s', strtotime($activity['logout_at'])) ?>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-circle"></i> Online
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($activity['logout_at']) {
                                        $login = strtotime($activity['login_at']);
                                        $logout = strtotime($activity['logout_at']);
                                        $duration = $logout - $login;
                                        
                                        $hours = floor($duration / 3600);
                                        $minutes = floor(($duration % 3600) / 60);
                                        $seconds = $duration % 60;
                                        
                                        if ($hours > 0) {
                                            echo "{$hours}j {$minutes}m";
                                        } elseif ($minutes > 0) {
                                            echo "{$minutes}m {$seconds}s";
                                        } else {
                                            echo "{$seconds}s";
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&user=<?= $user_filter ?>&date=<?= $date_filter ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&user=<?= $user_filter ?>&date=<?= $date_filter ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&user=<?= $user_filter ?>&date=<?= $date_filter ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <p class="text-center text-muted">
                    Halaman <?= $page ?> dari <?= $total_pages ?> 
                    (Total: <?= $total_records ?> records)
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>