<?php
require_once '../config.php';

// Check if user is logged in and is security officer
if (!is_logged_in() || $_SESSION['role'] !== 'security') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get user and officer data
$sql = "SELECT u.*, so.badge_number, so.shift, so.area_responsibility
        FROM users u
        LEFT JOIN security_officers so ON u.id = so.user_id
        WHERE u.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama = clean_input($_POST['nama']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $department = clean_input($_POST['department']);
    
    try {
        $sql = "UPDATE users SET nama = ?, email = ?, phone = ?, department = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $email, $phone, $department, $user_id]);
        
        $_SESSION['success_message'] = "Profil berhasil diupdate!";
        redirect('security/profile.php');
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Gagal update profil: " . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error_message'] = "Password lama tidak sesuai!";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = "Password baru tidak cocok!";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error_message'] = "Password minimal 6 karakter!";
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hashed, $user_id]);
            
            $_SESSION['success_message'] = "Password berhasil diubah!";
            redirect('security/profile.php');
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Gagal ubah password: " . $e->getMessage();
        }
    }
}

$page_title = "Profil Petugas";
include_once '../includes/header.php';
include_once '../includes/navbar_security.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-user-circle me-2"></i>Profil Petugas</h2>
            <p class="text-muted">Kelola informasi profil dan keamanan akun Anda</p>
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

    <div class="row">
        <!-- Left Column - Profile Info -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                    </div>
                    <h4 class="mb-1"><?= htmlspecialchars($user['nama']) ?></h4>
                    <p class="text-muted mb-2">Petugas Keamanan</p>
                    <span class="badge bg-primary mb-3"><?= htmlspecialchars($user['badge_number']) ?></span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p class="mb-2">
                            <i class="fas fa-id-card text-muted me-2"></i>
                            <strong>NIP:</strong> <?= htmlspecialchars($user['nim_nip']) ?>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-envelope text-muted me-2"></i>
                            <strong>Email:</strong><br>
                            <small><?= htmlspecialchars($user['email']) ?></small>
                        </p>
                        <?php if ($user['phone']): ?>
                            <p class="mb-2">
                                <i class="fas fa-phone text-muted me-2"></i>
                                <strong>Telepon:</strong> <?= htmlspecialchars($user['phone']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($user['shift']): ?>
                            <p class="mb-2">
                                <i class="fas fa-clock text-muted me-2"></i>
                                <strong>Shift:</strong> <?= ucfirst($user['shift']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($user['area_responsibility']): ?>
                            <p class="mb-2">
                                <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                <strong>Area:</strong> <?= htmlspecialchars($user['area_responsibility']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="mb-0">
                            <i class="fas fa-calendar text-muted me-2"></i>
                            <strong>Bergabung:</strong><br>
                            <small><?= format_date($user['created_at']) ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Forms -->
        <div class="col-md-8">
            <!-- Edit Profile Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profil</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP *</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['nim_nip']) ?>" disabled>
                                <small class="text-muted">NIP tidak dapat diubah</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Badge *</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['badge_number']) ?>" disabled>
                                <small class="text-muted">Badge number tidak dapat diubah</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Departemen</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>" placeholder="Keamanan Kampus">
                        </div>

                        <input type="hidden" name="update_profile" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password Form -->
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Ubah Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <div class="mb-3">
                            <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>

                        <input type="hidden" name="change_password" value="1">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-lock me-1"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>