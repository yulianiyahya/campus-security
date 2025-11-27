<?php
require_once '../config.php';
check_login();
check_role(['security', 'admin']); // Allow both security and admin

$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    $_SESSION['success_message'] = 'Notifikasi ditandai sebagai dibaca';
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $_SESSION['success_message'] = 'Semua notifikasi ditandai sebagai dibaca';
    header('Location: notifications.php');
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    $_SESSION['success_message'] = 'Notifikasi berhasil dihapus';
    header('Location: notifications.php');
    exit();
}

// Get all notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch()['count'];

$page_title = 'Notifikasi';

include '../includes/header.php';

// Include appropriate navbar based on role
if ($_SESSION['role'] === 'admin') {
    include '../includes/navbar_admin.php';
} else {
    include '../includes/navbar_security.php';
}
?>

<main>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bell"></i> Notifikasi
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?= $unread_count ?> baru</span>
                            <?php endif; ?>
                        </h5>
                        <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read" class="btn btn-sm btn-light" onclick="return confirm('Tandai semua notifikasi sebagai dibaca?')">
                            <i class="fas fa-check-double"></i> Tandai Semua Dibaca
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($notifications) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notif): ?>
                                <div class="list-group-item <?= $notif['is_read'] ? '' : 'list-group-item-info' ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-start">
                                                <div class="me-3 mt-1">
                                                    <?php
                                                    $icon_class = 'fa-bell';
                                                    $icon_color = 'text-primary';
                                                    switch($notif['type']) {
                                                        case 'success':
                                                            $icon_class = 'fa-check-circle';
                                                            $icon_color = 'text-success';
                                                            break;
                                                        case 'warning':
                                                            $icon_class = 'fa-exclamation-triangle';
                                                            $icon_color = 'text-warning';
                                                            break;
                                                        case 'danger':
                                                            $icon_class = 'fa-exclamation-circle';
                                                            $icon_color = 'text-danger';
                                                            break;
                                                        case 'info':
                                                            $icon_class = 'fa-info-circle';
                                                            $icon_color = 'text-info';
                                                            break;
                                                        case 'assignment':
                                                            $icon_class = 'fa-user-check';
                                                            $icon_color = 'text-primary';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="fas <?= $icon_class ?> <?= $icon_color ?> fa-2x"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?= htmlspecialchars($notif['title']) ?>
                                                        <?php if (!$notif['is_read']): ?>
                                                            <span class="badge bg-primary">Baru</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-2"><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
                                                    
                                                    <?php if ($notif['reference_id'] && $notif['type'] == 'assignment'): ?>
                                                        <a href="laporan_detail.php?id=<?= $notif['reference_id'] ?>" class="btn btn-sm btn-outline-primary mb-2">
                                                            <i class="fas fa-eye"></i> Lihat Laporan
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock"></i> 
                                                            <?= date('d M Y H:i', strtotime($notif['created_at'])) ?>
                                                        </small>
                                                        <?php if ($notif['is_read'] && isset($notif['read_at']) && $notif['read_at']): ?>
                                                            <small class="text-muted ms-3">
                                                                <i class="fas fa-check"></i> 
                                                                Dibaca: <?= date('d M Y H:i', strtotime($notif['read_at'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="btn-group-vertical ms-2">
                                            <?php if (!$notif['is_read']): ?>
                                            <a href="?mark_read=<?= $notif['id'] ?>" 
                                               class="btn btn-sm btn-outline-success" 
                                               title="Tandai dibaca">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?= $notif['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Yakin ingin menghapus notifikasi ini?')"
                                               title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada notifikasi</h5>
                                <p class="text-muted">Anda akan menerima notifikasi di sini ketika ada penugasan laporan baru</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($notifications) > 0): ?>
                    <div class="card-footer text-muted">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            Menampilkan <?= count($notifications) ?> notifikasi terakhir
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.list-group-item-info {
    background-color: #e8f4f8;
    border-left: 4px solid #0dcaf0;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<?php include '../includes/footer.php'; ?>