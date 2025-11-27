<?php
require_once '../config.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $severity = trim($_POST['severity']);
        
        // Validasi input
        if (empty($name) || empty($icon) || empty($severity)) {
            $_SESSION['error'] = "Nama, Icon, dan Tingkat Bahaya wajib diisi!";
            redirect('admin/manage_categories.php');
        }
        
        $sql = "INSERT INTO incident_categories (name, description, icon, severity, is_active) 
                VALUES (?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$name, $description, $icon, $severity])) {
            $_SESSION['success'] = "Kategori '{$name}' berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan kategori!";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
    
    redirect('admin/manage_categories.php');
}

// Handle Update Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    try {
        $id = intval($_POST['category_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $severity = trim($_POST['severity']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validasi input
        if (empty($name) || empty($icon) || empty($severity)) {
            $_SESSION['error'] = "Nama, Icon, dan Tingkat Bahaya wajib diisi!";
            redirect('admin/manage_categories.php');
        }
        
        $sql = "UPDATE incident_categories 
                SET name = ?, description = ?, icon = ?, severity = ?, is_active = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$name, $description, $icon, $severity, $is_active, $id])) {
            $_SESSION['success'] = "Kategori '{$name}' berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui kategori!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
    
    redirect('admin/manage_categories.php');
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        // Check if category is being used
        $check = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE category_id = ?");
        $check->execute([$id]);
        
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus kategori yang sedang digunakan!";
        } else {
            $sql = "DELETE FROM incident_categories WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$id])) {
                $_SESSION['success'] = "Kategori berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus kategori!";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
    
    redirect('admin/manage_categories.php');
}

// Get all categories
$sql = "SELECT ic.*, 
        COUNT(r.id) as total_reports
        FROM incident_categories ic
        LEFT JOIN reports r ON ic.id = r.category_id
        GROUP BY ic.id
        ORDER BY ic.name ASC";
$categories = $pdo->query($sql)->fetchAll();

// Get category for edit
$edit_category = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM incident_categories WHERE id = ?");
    $stmt->execute([$id]);
    $edit_category = $stmt->fetch();
}

// Icon options
$icon_options = [
    'exclamation-triangle' => 'Peringatan',
    'fire' => 'Kebakaran',
    'shield-alt' => 'Keamanan',
    'bolt' => 'Darurat',
    'car-crash' => 'Kecelakaan',
    'building' => 'Fasilitas',
    'user-shield' => 'Keamanan Personal',
    'tools' => 'Pemeliharaan',
    'medkit' => 'Medis',
    'exclamation-circle' => 'Perhatian',
    'bell' => 'Notifikasi',
    'ban' => 'Larangan',
    'radiation' => 'Bahaya',
    'skull-crossbones' => 'Berbahaya',
    'biohazard' => 'Biohazard'
];

$page_title = "Kelola Kategori Kejadian";
require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2"></i>Kelola Kategori Kejadian
                    </h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus me-1"></i>Tambah Kategori
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Icon</th>
                                    <th>Nama Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Tingkat Bahaya</th>
                                    <th>Total Laporan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Belum ada kategori. Silakan tambahkan kategori baru.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-<?= htmlspecialchars($category['icon'] ?? 'question') ?> fa-2x text-primary"></i>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($category['name'] ?? '') ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($category['description'] ?? '') ?></td>
                                            <td>
                                                <?php
                                                $badge_class = [
                                                    'low' => 'success',
                                                    'medium' => 'warning',
                                                    'high' => 'danger'
                                                ];
                                                $severity_text = [
                                                    'low' => 'Rendah',
                                                    'medium' => 'Sedang',
                                                    'high' => 'Tinggi'
                                                ];
                                                $sev = $category['severity'] ?? 'low';
                                                ?>
                                                <span class="badge bg-<?= $badge_class[$sev] ?? 'secondary' ?>">
                                                    <?= $severity_text[$sev] ?? 'Unknown' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $category['total_reports'] ?? 0 ?> laporan</span>
                                            </td>
                                            <td>
                                                <?php if ($category['is_active']): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Nonaktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="manage_categories.php?edit=<?= $category['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($category['total_reports'] == 0): ?>
                                                    <a href="manage_categories.php?delete=<?= $category['id'] ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Yakin ingin menghapus kategori ini?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_categories.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Kategori Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon <span class="text-danger">*</span></label>
                        <select class="form-select" name="icon" id="icon_select_add" required>
                            <option value="">-- Pilih Icon --</option>
                            <?php foreach ($icon_options as $icon => $label): ?>
                                <option value="<?= $icon ?>">
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2 text-center" id="icon_preview_add" style="display:none;">
                            <i class="fas fa-question fa-3x text-primary"></i>
                            <p class="small text-muted mt-1">Preview Icon</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tingkat Bahaya <span class="text-danger">*</span></label>
                        <select class="form-select" name="severity" required>
                            <option value="">-- Pilih Tingkat Bahaya --</option>
                            <option value="low">Rendah</option>
                            <option value="medium">Sedang</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <input type="hidden" name="add_category" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<?php if ($edit_category): ?>
<div class="modal fade show" id="editCategoryModal" tabindex="-1" style="display:block;" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Kategori</h5>
                <a href="manage_categories.php" class="btn-close"></a>
            </div>
            <form method="POST" action="manage_categories.php">
                <input type="hidden" name="category_id" value="<?= $edit_category['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" 
                               value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon <span class="text-danger">*</span></label>
                        <select class="form-select" name="icon" id="icon_select_edit" required>
                            <option value="">-- Pilih Icon --</option>
                            <?php foreach ($icon_options as $icon => $label): ?>
                                <option value="<?= $icon ?>" <?= ($edit_category['icon'] ?? '') == $icon ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2 text-center" id="icon_preview_edit">
                            <i class="fas fa-<?= htmlspecialchars($edit_category['icon'] ?? 'question') ?> fa-3x text-primary"></i>
                            <p class="small text-muted mt-1">Preview Icon</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tingkat Bahaya <span class="text-danger">*</span></label>
                        <select class="form-select" name="severity" required>
                            <option value="">-- Pilih Tingkat Bahaya --</option>
                            <option value="low" <?= ($edit_category['severity'] ?? '') == 'low' ? 'selected' : '' ?>>Rendah</option>
                            <option value="medium" <?= ($edit_category['severity'] ?? '') == 'medium' ? 'selected' : '' ?>>Sedang</option>
                            <option value="high" <?= ($edit_category['severity'] ?? '') == 'high' ? 'selected' : '' ?>>Tinggi</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                   <?= ($edit_category['is_active'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Status Aktif</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="manage_categories.php" class="btn btn-secondary">Batal</a>
                    <input type="hidden" name="update_category" value="1">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script>
// Icon preview for add modal
document.getElementById('icon_select_add')?.addEventListener('change', function() {
    const preview = document.getElementById('icon_preview_add');
    const icon = this.value;
    if (icon) {
        preview.style.display = 'block';
        preview.querySelector('i').className = `fas fa-${icon} fa-3x text-primary`;
    } else {
        preview.style.display = 'none';
    }
});

// Icon preview for edit modal
document.getElementById('icon_select_edit')?.addEventListener('change', function() {
    const preview = document.getElementById('icon_preview_edit');
    const icon = this.value;
    if (icon) {
        preview.querySelector('i').className = `fas fa-${icon} fa-3x text-primary`;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>