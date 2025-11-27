<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Profil Saya';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    if (isset($_POST['update_profile'])) {
        // Update profile
        $nama = clean_input($_POST['nama']);
        $email = clean_input($_POST['email']);
        $phone = clean_input($_POST['phone']);
        $department = clean_input($_POST['department']);
        
        if (empty($nama)) $errors[] = 'Nama harus diisi';
        if (empty($email)) $errors[] = 'Email harus diisi';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid';
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET nama = ?, email = ?, phone = ?, department = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $email, $phone, $department, $_SESSION['user_id']]);
                
                // Update session data
                $_SESSION['nama'] = $nama;
                $_SESSION['email'] = $email;
                
                $_SESSION['success_message'] = 'Profil berhasil diperbarui';
                redirect('user/profile.php');
            } catch (Exception $e) {
                $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password)) $errors[] = 'Password lama harus diisi';
        if (empty($new_password)) $errors[] = 'Password baru harus diisi';
        if (strlen($new_password) < 6) $errors[] = 'Password baru minimal 6 karakter';
        if ($new_password !== $confirm_password) $errors[] = 'Konfirmasi password tidak cocok';
        
        if (empty($errors)) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Password lama tidak sesuai';
            } else {
                try {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed, $_SESSION['user_id']]);
                    
                    $_SESSION['success_message'] = 'Password berhasil diubah';
                    redirect('user/profile.php');
                } catch (Exception $e) {
                    $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reports,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports
    FROM reports
    WHERE reporter_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<div class="container-fluid py-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Terjadi Kesalahan:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Overview -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="user-avatar bg-primary text-white mx-auto mb-3" 
                         style="width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['nama']); ?></h4>
                    <p class="text-muted mb-2">
                        <i class="fas fa-id-card me-1"></i>
                        <?php echo htmlspecialchars($user['nim_nip']); ?>
                    </p>
                    <span class="badge bg-primary mb-3">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    
                    <div class="border-top pt-3 mt-3">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="mb-0"><?php echo $stats['total_reports']; ?></h4>
                                <small class="text-muted">Total Laporan</small>
                            </div>
                            <div class="col-6">
                                <h4 class="mb-0"><?php echo $stats['resolved_reports']; ?></h4>
                                <small class="text-muted">Diselesaikan</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Info -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Informasi Akun
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Status Akun</small>
                        <?php
                        $status_class = $user['status'] == 'active' ? 'success' : 'danger';
                        $status_label = $user['status'] == 'active' ? 'Aktif' : 'Nonaktif';
                        ?>
                        <span class="badge bg-<?php echo $status_class; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Terdaftar Sejak</small>
                        <strong><?php echo date('d F Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                    <div>
                        <small class="text-muted d-block">Terakhir Diupdate</small>
                        <strong>
                            <?php 
                            if (!empty($user['updated_at']) && $user['updated_at'] != '0000-00-00 00:00:00') {
                                echo time_ago($user['updated_at']); 
                            } else {
                                echo 'Belum pernah diupdate';
                            }
                            ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Forms -->
        <div class="col-lg-8">
            <!-- Edit Profile -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>Edit Profil
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIM/NIP</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['nim_nip']); ?>" 
                                       disabled>
                                <small class="text-muted">NIM/NIP tidak dapat diubah</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="08xxxxxxxxxx">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fakultas/Departemen</label>
                            <input type="text" name="department" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>"
                                   placeholder="Contoh: Fakultas Teknik">
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <input type="hidden" name="update_profile" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-key me-2"></i>Ubah Password
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php" id="changePasswordForm">
                        <div class="mb-3">
                            <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="currentPassword" 
                                       class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('currentPassword')">
                                    <i class="fas fa-eye" id="currentPassword-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPassword" 
                                       class="form-control" minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('newPassword')">
                                    <i class="fas fa-eye" id="newPassword-icon"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirmPassword" 
                                       class="form-control" minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('confirmPassword')">
                                    <i class="fas fa-eye" id="confirmPassword-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Setelah mengubah password, Anda akan tetap login di sesi ini. 
                            Pastikan Anda mengingat password baru Anda.
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </button>
                            <input type="hidden" name="change_password" value="1">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Ubah Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password confirmation validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Konfirmasi password tidak cocok!');
        return false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>