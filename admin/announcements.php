<?php
require_once '../config.php';

// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create debug log file
$debug_log = __DIR__ . '/announcement_debug.log';
file_put_contents($debug_log, "\n\n" . date('Y-m-d H:i:s') . " - Page Loaded\n", FILE_APPEND);

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Debug log
file_put_contents($debug_log, "User ID: $user_id\n", FILE_APPEND);
file_put_contents($debug_log, "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents($debug_log, "POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Handle Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_announcement']) && !isset($_GET['delete'])) {
    file_put_contents($debug_log, "\n=== CREATE ANNOUNCEMENT TRIGGERED ===\n", FILE_APPEND);
    
    // Get form data
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $type = isset($_POST['type']) ? trim($_POST['type']) : 'general';
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'normal';
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d H:i:s');
    $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
    
    file_put_contents($debug_log, "Title: $title\n", FILE_APPEND);
    file_put_contents($debug_log, "Content: $content\n", FILE_APPEND);
    
    // Validate required fields
    if (empty($title) || empty($content)) {
        $_SESSION['flash_message'] = 'Judul dan konten harus diisi!';
        $_SESSION['flash_type'] = 'danger';
        file_put_contents($debug_log, "ERROR: Empty title or content\n", FILE_APPEND);
        header('Location: announcements.php');
        exit;
    }
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/announcements/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['announcement_image']['tmp_name'];
        $file_name = $_FILES['announcement_image']['name'];
        $file_size = $_FILES['announcement_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_ext)) {
            $_SESSION['flash_message'] = 'Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: announcements.php');
            exit;
        }
        
        // Validate file size (max 5MB)
        if ($file_size > 5 * 1024 * 1024) {
            $_SESSION['flash_message'] = 'Ukuran file terlalu besar! Maksimal 5MB.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: announcements.php');
            exit;
        }
        
        // Generate unique filename
        $new_filename = 'announcement_' . time() . '_' . uniqid() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $destination)) {
            $image_path = 'uploads/announcements/' . $new_filename;
            file_put_contents($debug_log, "Image uploaded: $image_path\n", FILE_APPEND);
        } else {
            file_put_contents($debug_log, "ERROR: Failed to move uploaded file\n", FILE_APPEND);
        }
    }
    
    try {
        $sql = "INSERT INTO announcements (created_by, title, content, type, priority, is_published, publish_date, expire_date, image_path, created_at)
                VALUES (:created_by, :title, :content, :type, :priority, :is_published, :publish_date, :expire_date, :image_path, NOW())";
        $stmt = $pdo->prepare($sql);
        
        file_put_contents($debug_log, "SQL Prepared\n", FILE_APPEND);
        
        // Bind parameters
        $stmt->bindParam(':created_by', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':priority', $priority, PDO::PARAM_STR);
        $stmt->bindParam(':is_published', $is_published, PDO::PARAM_INT);
        $stmt->bindParam(':publish_date', $publish_date, PDO::PARAM_STR);
        $stmt->bindParam(':expire_date', $expire_date, PDO::PARAM_STR);
        $stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR);
        
        file_put_contents($debug_log, "Parameters bound\n", FILE_APPEND);
        
        $result = $stmt->execute();
        
        file_put_contents($debug_log, "Execute result: " . ($result ? 'TRUE' : 'FALSE') . "\n", FILE_APPEND);
        file_put_contents($debug_log, "Last Insert ID: " . $pdo->lastInsertId() . "\n", FILE_APPEND);
        
        if ($result && $stmt->rowCount() > 0) {
            $lastId = $pdo->lastInsertId();
            $_SESSION['flash_message'] = 'Pengumuman berhasil dibuat! ID: ' . $lastId;
            $_SESSION['flash_type'] = 'success';
            file_put_contents($debug_log, "SUCCESS - Announcement ID: $lastId\n", FILE_APPEND);
        } else {
            $_SESSION['flash_message'] = 'Gagal membuat pengumuman!';
            $_SESSION['flash_type'] = 'danger';
            file_put_contents($debug_log, "FAILED - No rows affected\n", FILE_APPEND);
        }
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        file_put_contents($debug_log, "PDO ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    header('Location: announcements.php');
    exit;
}

// Handle Update Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    file_put_contents($debug_log, "UPDATE ANNOUNCEMENT TRIGGERED\n", FILE_APPEND);
    
    $id = (int)$_POST['announcement_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type = trim($_POST['type']);
    $priority = trim($_POST['priority']);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $publish_date = $_POST['publish_date'];
    $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
    
    // Get existing announcement data
    $stmt = $pdo->prepare("SELECT image_path FROM announcements WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $existing = $stmt->fetch();
    $image_path = $existing['image_path'];
    
    // Handle image upload for update
    if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/announcements/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['announcement_image']['tmp_name'];
        $file_name = $_FILES['announcement_image']['name'];
        $file_size = $_FILES['announcement_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_ext) && $file_size <= 5 * 1024 * 1024) {
            // Delete old image if exists
            if ($image_path && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
            
            $new_filename = 'announcement_' . time() . '_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_path = 'uploads/announcements/' . $new_filename;
            }
        }
    }
    
    // Handle remove image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if ($image_path && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        $image_path = null;
    }
    
    try {
        $sql = "UPDATE announcements 
                SET title = :title, content = :content, type = :type, priority = :priority, 
                    is_published = :is_published, publish_date = :publish_date, 
                    expire_date = :expire_date, image_path = :image_path, updated_at = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':priority', $priority, PDO::PARAM_STR);
        $stmt->bindParam(':is_published', $is_published, PDO::PARAM_INT);
        $stmt->bindParam(':publish_date', $publish_date, PDO::PARAM_STR);
        $stmt->bindParam(':expire_date', $expire_date, PDO::PARAM_STR);
        $stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = 'Pengumuman berhasil diperbarui!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Gagal memperbarui pengumuman!';
            $_SESSION['flash_type'] = 'danger';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        file_put_contents($debug_log, "UPDATE ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    header('Location: announcements.php');
    exit;
}

// Handle Delete Announcement
if (isset($_GET['delete'])) {
    file_put_contents($debug_log, "DELETE ANNOUNCEMENT TRIGGERED\n", FILE_APPEND);
    $id = (int)$_GET['delete'];
    
    try {
        // Get image path before delete
        $stmt = $pdo->prepare("SELECT image_path FROM announcements WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $announcement = $stmt->fetch();
        
        // Delete the announcement
        $sql = "DELETE FROM announcements WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Delete image file if exists
            if ($announcement && $announcement['image_path'] && file_exists('../' . $announcement['image_path'])) {
                unlink('../' . $announcement['image_path']);
            }
            
            $_SESSION['flash_message'] = 'Pengumuman berhasil dihapus!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Gagal menghapus pengumuman!';
            $_SESSION['flash_type'] = 'danger';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        file_put_contents($debug_log, "DELETE ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    header('Location: announcements.php');
    exit;
}

// Get all announcements
try {
    $sql = "SELECT a.*, u.nama as author_name
            FROM announcements a
            JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC";
    $announcements = $pdo->query($sql)->fetchAll();
    file_put_contents($debug_log, "Total announcements found: " . count($announcements) . "\n", FILE_APPEND);
} catch (PDOException $e) {
    $announcements = [];
    $_SESSION['flash_message'] = 'Error mengambil data: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'danger';
    file_put_contents($debug_log, "FETCH ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Get announcement for edit
$edit_announcement = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $edit_announcement = $stmt->fetch();
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

$page_title = "Kelola Pengumuman";
require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <?php
            // Display flash messages
            if (isset($_SESSION['flash_message'])):
                $message = $_SESSION['flash_message'];
                $type = $_SESSION['flash_type'] ?? 'info';
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            ?>
                <div class="alert alert-<?= $type ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-bullhorn me-2"></i>Kelola Pengumuman
                    </h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                        <i class="fas fa-plus me-1"></i>Buat Pengumuman
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-bullhorn fa-3x mb-3 opacity-50"></i>
                            <p>Belum ada pengumuman. Klik "Buat Pengumuman" untuk membuat yang pertama.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="col-md-12 mb-3">
                                    <div class="card border-start border-5 <?= ($announcement['priority'] === 'high' || $announcement['priority'] === 'urgent') ? 'border-danger' : 'border-primary' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="flex-grow-1">
                                                    <h5 class="card-title mb-1">
                                                        <?php
                                                        $type_icons = [
                                                            'info' => 'info-circle text-primary',
                                                            'warning' => 'exclamation-triangle text-warning',
                                                            'emergency' => 'exclamation-circle text-danger',
                                                            'general' => 'bullhorn text-secondary'
                                                        ];
                                                        ?>
                                                        <i class="fas fa-<?= $type_icons[$announcement['type']] ?? 'info-circle' ?> me-2"></i>
                                                        <?= htmlspecialchars($announcement['title']) ?>
                                                    </h5>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($announcement['author_name']) ?>
                                                        <i class="fas fa-clock ms-2 me-1"></i><?= date('d M Y H:i', strtotime($announcement['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <?php if ($announcement['is_published']): ?>
                                                        <span class="badge bg-success me-1">
                                                            <i class="fas fa-check"></i> Published
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary me-1">Draft</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($announcement['priority'] === 'high' || $announcement['priority'] === 'urgent'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-star"></i> Prioritas <?= ucfirst($announcement['priority']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($announcement['image_path'])): ?>
                                                <div class="mb-3">
                                                    <img src="../<?= htmlspecialchars($announcement['image_path']) ?>" 
                                                         alt="Announcement Image" 
                                                         class="img-fluid rounded" 
                                                         style="max-height: 300px; object-fit: cover;">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <p class="card-text mb-2"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        Publish: <?= date('d M Y', strtotime($announcement['publish_date'])) ?>
                                                        <?php if ($announcement['expire_date']): ?>
                                                            | Expired: <?= date('d M Y', strtotime($announcement['expire_date'])) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <a href="?edit=<?= $announcement['id'] ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="?delete=<?= $announcement['id'] ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Yakin ingin menghapus pengumuman ini?')">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Pengumuman Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="announcements.php" id="createAnnouncementForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Judul Pengumuman *</label>
                        <input type="text" class="form-control" name="title" required placeholder="Masukkan judul pengumuman">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konten *</label>
                        <textarea class="form-control" name="content" rows="6" required placeholder="Masukkan konten pengumuman"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gambar Pengumuman (Opsional)</label>
                        <input type="file" class="form-control" name="announcement_image" accept="image/*" id="createImageInput">
                        <small class="text-muted">Format: JPG, PNG, GIF, WEBP. Maksimal 5MB</small>
                        <div id="createImagePreview" class="mt-2"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe Pengumuman *</label>
                            <select class="form-select" name="type" required>
                                <option value="general" selected>Umum</option>
                                <option value="info">Info</option>
                                <option value="warning">Peringatan</option>
                                <option value="emergency">Darurat</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prioritas *</label>
                            <select class="form-select" name="priority" required>
                                <option value="normal" selected>Normal</option>
                                <option value="high">Tinggi</option>
                                <option value="urgent">Mendesak</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Publish</label>
                            <input type="datetime-local" class="form-control" name="publish_date"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Kadaluarsa (Opsional)</label>
                            <input type="datetime-local" class="form-control" name="expire_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_published" checked value="1">
                            <label class="form-check-label">
                                <i class="fas fa-eye"></i> Publish Sekarang
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bullhorn me-1"></i>Publikasikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<?php if ($edit_announcement): ?>
<div class="modal fade show" id="editAnnouncementModal" tabindex="-1" style="display:block;" aria-modal="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Pengumuman</h5>
                <a href="announcements.php" class="btn-close"></a>
            </div>
            <form method="POST" action="announcements.php" enctype="multipart/form-data" id="editAnnouncementForm">
                <input type="hidden" name="announcement_id" value="<?= $edit_announcement['id'] ?>">
                <input type="hidden" name="remove_image" id="removeImageFlag" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Judul Pengumuman *</label>
                        <input type="text" class="form-control" name="title" 
                               value="<?= htmlspecialchars($edit_announcement['title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konten *</label>
                        <textarea class="form-control" name="content" rows="6" required><?= htmlspecialchars($edit_announcement['content']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gambar Pengumuman</label>
                        <?php if (!empty($edit_announcement['image_path'])): ?>
                            <div id="currentImageContainer" class="mb-2">
                                <img src="../<?= htmlspecialchars($edit_announcement['image_path']) ?>" 
                                     alt="Current Image" 
                                     class="img-fluid rounded mb-2" 
                                     style="max-height: 200px;">
                                <div>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeCurrentImage()">
                                        <i class="fas fa-trash"></i> Hapus Gambar
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="announcement_image" accept="image/*" id="editImageInput">
                        <small class="text-muted">Format: JPG, PNG, GIF, WEBP. Maksimal 5MB. Kosongkan jika tidak ingin mengubah.</small>
                        <div id="editImagePreview" class="mt-2"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe Pengumuman *</label>
                            <select class="form-select" name="type" required>
                                <option value="general" <?= $edit_announcement['type'] === 'general' ? 'selected' : '' ?>>Umum</option>
                                <option value="info" <?= $edit_announcement['type'] === 'info' ? 'selected' : '' ?>>Info</option>
                                <option value="warning" <?= $edit_announcement['type'] === 'warning' ? 'selected' : '' ?>>Peringatan</option>
                                <option value="emergency" <?= $edit_announcement['type'] === 'emergency' ? 'selected' : '' ?>>Darurat</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prioritas *</label>
                            <select class="form-select" name="priority" required>
                                <option value="normal" <?= $edit_announcement['priority'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="high" <?= $edit_announcement['priority'] === 'high' ? 'selected' : '' ?>>Tinggi</option>
                                <option value="urgent" <?= $edit_announcement['priority'] === 'urgent' ? 'selected' : '' ?>>Mendesak</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Publish</label>
                            <input type="datetime-local" class="form-control" name="publish_date"
                                   value="<?= date('Y-m-d\TH:i', strtotime($edit_announcement['publish_date'])) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Kadaluarsa (Opsional)</label>
                            <input type="datetime-local" class="form-control" name="expire_date"
                                   value="<?= $edit_announcement['expire_date'] ? date('Y-m-d\TH:i', strtotime($edit_announcement['expire_date'])) : '' ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_published" 
                                   value="1" <?= $edit_announcement['is_published'] ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                <i class="fas fa-eye"></i> Publish Sekarang
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="announcements.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="update_announcement" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script>
// Image preview for create form
document.getElementById('createImageInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('createImagePreview');
    
    if (file) {
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file terlalu besar! Maksimal 5MB.');
            this.value = '';
            preview.innerHTML = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.');
            this.value = '';
            preview.innerHTML = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="position-relative d-inline-block">
                    <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" 
                            onclick="document.getElementById('createImageInput').value=''; document.getElementById('createImagePreview').innerHTML='';">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});

// Image preview for edit form
document.getElementById('editImageInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('editImagePreview');
    
    if (file) {
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file terlalu besar! Maksimal 5MB.');
            this.value = '';
            preview.innerHTML = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.');
            this.value = '';
            preview.innerHTML = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="position-relative d-inline-block">
                    <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" 
                            onclick="document.getElementById('editImageInput').value=''; document.getElementById('editImagePreview').innerHTML='';">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});

// Function to remove current image in edit form
function removeCurrentImage() {
    if (confirm('Yakin ingin menghapus gambar ini?')) {
        document.getElementById('currentImageContainer').style.display = 'none';
        document.getElementById('removeImageFlag').value = '1';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>