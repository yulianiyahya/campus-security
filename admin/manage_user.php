<?php
require_once '../config.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nim_nip = clean_input($_POST['nim_nip']);
    $nama = clean_input($_POST['nama']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $role = clean_input($_POST['role']);
    $phone = clean_input($_POST['phone']);
    $department = clean_input($_POST['department']);
    
    // Check if NIM/NIP already exists
    $sql = "SELECT id FROM users WHERE nim_nip = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nim_nip]);
    
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "NIM/NIP sudah terdaftar!";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (nim_nip, nama, email, password, role, phone, department, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nim_nip, $nama, $email, $hashed_password, $role, $phone, $department]);
            
            $_SESSION['success_message'] = "User berhasil ditambahkan!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Gagal menambahkan user: " . $e->getMessage();
        }
    }
    header('Location: manage_user.php');
    exit();
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $nama = clean_input($_POST['nama']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $department = clean_input($_POST['department']);
    $status = clean_input($_POST['status']);
    
    try {
        $sql = "UPDATE users SET nama = ?, email = ?, phone = ?, department = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $email, $phone, $department, $status, $user_id]);
        
        $_SESSION['success_message'] = "User berhasil diupdate!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Gagal update user: " . $e->getMessage();
    }
    header('Location: manage_user.php');
    exit();
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hashed_password, $user_id]);
        
        $_SESSION['success_message'] = "Password berhasil direset!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Gagal reset password: " . $e->getMessage();
    }
    header('Location: manage_user.php');
    exit();
}

// Handle Soft Delete User
if (isset($_GET['soft_delete']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Tidak boleh hapus akun sendiri
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Tidak dapat menghapus akun sendiri!";
    } else {
        try {
            // Soft delete → ubah status menjadi inactive + isi deleted_at
            $sql = "UPDATE users 
                    SET status = 'inactive', deleted_at = NOW() 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);

            $_SESSION['success_message'] = "User berhasil dihapus (soft delete). User dapat dipulihkan kembali.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Gagal melakukan soft delete: " . $e->getMessage();
        }
    }

    header('Location: manage_user.php');
    exit();
}

// Handle Restore User
if (isset($_GET['restore']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    try {
        $sql = "UPDATE users 
                SET status = 'active', deleted_at = NULL 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);

        $_SESSION['success_message'] = "User berhasil dipulihkan!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Gagal memulihkan user: " . $e->getMessage();
    }

    header('Location: manage_user.php');
    exit();
}

// Handle Hard Delete User (Permanent)
if (isset($_GET['hard_delete']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Tidak boleh hapus akun sendiri
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Tidak dapat menghapus akun sendiri!";
    } else {
        try {
            // Hard delete → hapus permanen dari database
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);

            $_SESSION['success_message'] = "User berhasil dihapus permanen (hard delete)!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Gagal melakukan hard delete: " . $e->getMessage();
        }
    }

    header('Location: manage_user.php');
    exit();
}

// Get filter
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$show_deleted = isset($_GET['show_deleted']) ? true : false;

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

// Filter untuk menampilkan user yang dihapus atau tidak
if ($show_deleted) {
    $sql .= " AND deleted_at IS NOT NULL";
} else {
    $sql .= " AND deleted_at IS NULL";
}

if ($role_filter !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (nim_nip LIKE ? OR nama LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
              SUM(CASE WHEN role = 'security' THEN 1 ELSE 0 END) as security_count,
              SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
              SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
              SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_count
              FROM users";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

$page_title = "Manajemen Pengguna";
include_once '../includes/header.php';
include_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-users me-2"></i>Manajemen Pengguna</h2>
                    <p class="text-muted mb-0">Kelola semua pengguna sistem</p>
                </div>
                <div>
                    <?php if ($show_deleted): ?>
                        <a href="manage_user.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Kembali ke User Aktif
                        </a>
                    <?php else: ?>
                        <a href="?show_deleted=1" class="btn btn-secondary me-2">
                            <i class="fas fa-trash-restore me-1"></i> Lihat User Terhapus (<?= $stats['deleted_count'] ?>)
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-1"></i> Tambah User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total User</h6>
                            <h2 class="mb-0"><?= $stats['total'] ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Admin</h6>
                            <h2 class="mb-0"><?= $stats['admin_count'] ?></h2>
                        </div>
                        <i class="fas fa-user-shield fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Security</h6>
                            <h2 class="mb-0"><?= $stats['security_count'] ?></h2>
                        </div>
                        <i class="fas fa-user-tie fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">User Aktif</h6>
                            <h2 class="mb-0"><?= $stats['active_count'] ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Terhapus</h6>
                            <h2 class="mb-0"><?= $stats['deleted_count'] ?></h2>
                        </div>
                        <i class="fas fa-trash fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($show_deleted): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            Menampilkan user yang telah dihapus (soft delete). Anda dapat memulihkan atau menghapus permanen.
        </div>
    <?php endif; ?>

    <!-- Filter & Search -->
    <?php if (!$show_deleted): ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>Semua Role</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="security" <?= $role_filter === 'security' ? 'selected' : '' ?>>Security</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cari</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari NIM/NIP, nama, email..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                            <a href="manage_user.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>NIM/NIP</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Departemen/Fakultas</th>
                            <th>Status</th>
                            <?php if ($show_deleted): ?>
                                <th>Dihapus Pada</th>
                            <?php else: ?>
                                <th>Terdaftar</th>
                            <?php endif; ?>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-users-slash fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">
                                        <?= $show_deleted ? 'Tidak ada user yang dihapus' : 'Tidak ada user ditemukan' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($user['nim_nip']) ?></strong></td>
                                    <td><?= htmlspecialchars($user['nama']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php
                                        $role_badge = '';
                                        switch($user['role']) {
                                            case 'admin':
                                                $role_badge = 'danger';
                                                break;
                                            case 'security':
                                                $role_badge = 'warning';
                                                break;
                                            case 'user':
                                                $role_badge = 'info';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $role_badge ?>"><?= ucfirst($user['role']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($user['department']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $user['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php 
                                            if ($show_deleted && $user['deleted_at']) {
                                                echo date('d M Y H:i', strtotime($user['deleted_at']));
                                            } else {
                                                echo date('d M Y H:i', strtotime($user['created_at']));
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($show_deleted): ?>
                                            <!-- Tombol untuk user yang dihapus -->
                                            <div class="btn-group btn-group-sm">
                                                <a href="?restore=1&id=<?= $user['id'] ?>" 
                                                   class="btn btn-success" 
                                                   onclick="return confirm('Yakin ingin memulihkan user ini?')" 
                                                   title="Pulihkan User">
                                                    <i class="fas fa-undo"></i> Pulihkan
                                                </a>
                                                <button class="btn btn-danger" 
                                                        onclick="confirmHardDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['nama']) ?>')" 
                                                        title="Hapus Permanen">
                                                    <i class="fas fa-trash-alt"></i> Hapus Permanen
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <!-- Tombol untuk user aktif -->
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-warning" onclick='editUser(<?= json_encode($user) ?>)' title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-info" onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['nama']) ?>')" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="?soft_delete=1&id=<?= $user['id'] ?>" 
                                                       class="btn btn-danger" 
                                                       onclick="return confirm('Yakin ingin menghapus user ini? (Soft Delete - dapat dipulihkan)')" 
                                                       title="Hapus (Soft Delete)">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tambah User Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIM/NIP *</label>
                            <input type="text" name="nim_nip" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="">-- Pilih Role --</option>
                                <option value="admin">Admin</option>
                                <option value="security">Security Officer</option>
                                <option value="user">User (Mahasiswa/Dosen)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" minlength="6" required>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departemen/Fakultas</label>
                            <input type="text" name="department" class="form-control" placeholder="Nama Departemen">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NIM/NIP</label>
                        <input type="text" id="edit_nim_nip" class="form-control" disabled>
                        <small class="text-muted">NIM/NIP tidak dapat diubah</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="nama" id="edit_nama" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departemen</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password untuk: <strong id="reset_user_name"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Password Baru *</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-key me-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hard Delete Confirmation Modal -->
<div class="modal fade" id="hardDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus Permanen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>PERINGATAN!</strong> Tindakan ini tidak dapat dibatalkan!
                </div>
                <p class="mb-3">Anda akan menghapus permanen user:</p>
                <p class="text-center">
                    <strong class="fs-5" id="hard_delete_user_name"></strong>
                </p>
                <p class="text-muted small">Data user akan dihapus selamanya dari database dan tidak dapat dipulihkan kembali.</p>
                
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="confirmHardDelete" required>
                    <label class="form-check-label text-danger" for="confirmHardDelete">
                        Saya memahami bahwa tindakan ini permanen dan tidak dapat dibatalkan
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="hardDeleteBtn" class="btn btn-danger disabled">
                    <i class="fas fa-trash-alt me-1"></i> Hapus Permanen
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styling for 5 column grid */
.col-md-2-4 {
    flex: 0 0 auto;
    width: 20%;
}

@media (max-width: 768px) {
    .col-md-2-4 {
        width: 100%;
        margin-bottom: 1rem;
    }
}
</style>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_nim_nip').value = user.nim_nip;
    document.getElementById('edit_nama').value = user.nama;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_department').value = user.department || '';
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function resetPassword(userId, userName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
    
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

function confirmHardDelete(userId, userName) {
    document.getElementById('hard_delete_user_name').textContent = userName;
    
    const checkbox = document.getElementById('confirmHardDelete');
    const deleteBtn = document.getElementById('hardDeleteBtn');
    
    // Reset checkbox
    checkbox.checked = false;
    deleteBtn.classList.add('disabled');
    deleteBtn.href = '#';
    
    // Enable delete button when checkbox is checked
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            deleteBtn.classList.remove('disabled');
            deleteBtn.href = '?hard_delete=1&id=' + userId;
        } else {
            deleteBtn.classList.add('disabled');
            deleteBtn.href = '#';
        }
    });
    
    new bootstrap.Modal(document.getElementById('hardDeleteModal')).show();
}
</script>

<?php include_once '../includes/footer.php'; ?>