<?php
require_once '../config.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Handle Add Officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_officer'])) {
    $user_id = (int)$_POST['user_id'];
    $badge_number = clean_input($_POST['badge_number']);
    $shift = clean_input($_POST['shift']);
    $area_responsibility = clean_input($_POST['area_responsibility']);
    
    // Check if user is already an officer
    $sql = "SELECT id FROM security_officers WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "User ini sudah terdaftar sebagai petugas keamanan!";
    } else {
        try {
            $sql = "INSERT INTO security_officers (user_id, badge_number, shift, area_responsibility, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $badge_number, $shift, $area_responsibility]);
            
            $_SESSION['success_message'] = "Petugas keamanan berhasil ditambahkan!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Gagal menambahkan petugas: " . $e->getMessage();
        }
    }
    header('Location: manage_officer.php');
    exit();
}

// Handle Edit Officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_officer'])) {
    $officer_id = (int)$_POST['officer_id'];
    $badge_number = clean_input($_POST['badge_number']);
    $shift = clean_input($_POST['shift']);
    $area_responsibility = clean_input($_POST['area_responsibility']);
    
    try {
        $sql = "UPDATE security_officers SET badge_number = ?, shift = ?, area_responsibility = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$badge_number, $shift, $area_responsibility, $officer_id]);
        
        $_SESSION['success_message'] = "Data petugas berhasil diupdate!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Gagal update petugas: " . $e->getMessage();
    }
    header('Location: manage_officer.php');
    exit();
}

// Handle Delete Officer
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $officer_id = (int)$_GET['id'];
    
    try {
        $sql = "DELETE FROM security_officers WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$officer_id]);
        
        $_SESSION['success_message'] = "Petugas berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Gagal menghapus petugas: " . $e->getMessage();
    }
    header('Location: manage_officer.php');
    exit();
}

// Get all officers with user info
$sql = "SELECT so.*, u.nim_nip, u.nama, u.email, u.phone, u.status,
        COUNT(DISTINCT r.id) as total_handled
        FROM security_officers so
        JOIN users u ON so.user_id = u.id
        LEFT JOIN reports r ON r.assigned_officer_id = so.id
        GROUP BY so.id
        ORDER BY u.nama";
$stmt = $pdo->query($sql);
$officers = $stmt->fetchAll();

// Get available security users (not yet officers)
$sql = "SELECT u.* FROM users u
        LEFT JOIN security_officers so ON u.id = so.user_id
        WHERE u.role = 'security' AND so.id IS NULL
        ORDER BY u.nama";
$stmt = $pdo->query($sql);
$available_users = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
              COUNT(DISTINCT so.id) as total_officers,
              COUNT(DISTINCT CASE WHEN so.shift = 'morning' THEN so.id END) as morning_shift,
              COUNT(DISTINCT CASE WHEN so.shift = 'afternoon' THEN so.id END) as afternoon_shift,
              COUNT(DISTINCT CASE WHEN so.shift = 'night' THEN so.id END) as night_shift,
              COUNT(DISTINCT r.id) as total_assignments
              FROM security_officers so
              JOIN users u ON so.user_id = u.id
              LEFT JOIN reports r ON r.assigned_officer_id = so.id
              WHERE u.status = 'active'";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

$page_title = "Manajemen Petugas Keamanan";
include_once '../includes/header.php';
include_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-user-shield me-2"></i>Manajemen Petugas Keamanan</h2>
                    <p class="text-muted mb-0">Kelola data petugas keamanan kampus</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOfficerModal">
                    <i class="fas fa-plus me-1"></i> Tambah Petugas
                </button>
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
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Petugas</h6>
                            <h2 class="mb-0"><?= $stats['total_officers'] ?></h2>
                        </div>
                        <i class="fas fa-user-shield fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Shift Pagi</h6>
                            <h2 class="mb-0"><?= $stats['morning_shift'] ?></h2>
                        </div>
                        <i class="fas fa-sun fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Shift Sore</h6>
                            <h2 class="mb-0"><?= $stats['afternoon_shift'] ?></h2>
                        </div>
                        <i class="fas fa-cloud-sun fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Shift Malam</h6>
                            <h2 class="mb-0"><?= $stats['night_shift'] ?></h2>
                        </div>
                        <i class="fas fa-moon fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Officers Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Badge Number</th>
                            <th>Nama Petugas</th>
                            <th>NIP</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Shift</th>
                            <th>Area</th>
                            <th>Tugas Ditangani</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($officers)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-user-slash fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">Belum ada petugas keamanan terdaftar</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($officers as $officer): ?>
                                <tr>
                                    <td><strong class="text-primary"><?= htmlspecialchars($officer['badge_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($officer['nama']) ?></td>
                                    <td><?= htmlspecialchars($officer['nim_nip']) ?></td>
                                    <td><?= htmlspecialchars($officer['email']) ?></td>
                                    <td><?= htmlspecialchars($officer['phone']) ?></td>
                                    <td>
                                        <?php
                                        $shift_badge = '';
                                        $shift_text = '';
                                        switch($officer['shift']) {
                                            case 'morning':
                                                $shift_badge = 'warning';
                                                $shift_text = 'Pagi';
                                                break;
                                            case 'afternoon':
                                                $shift_badge = 'info';
                                                $shift_text = 'Sore';
                                                break;
                                            case 'night':
                                                $shift_badge = 'dark';
                                                $shift_text = 'Malam';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $shift_badge ?>"><?= $shift_text ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($officer['area_responsibility']) ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $officer['total_handled'] ?> Laporan</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $officer['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $officer['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-warning" onclick='editOfficer(<?= json_encode($officer) ?>)' title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete=1&id=<?= $officer['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus petugas ini?')" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
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

<!-- Add Officer Modal -->
<div class="modal fade" id="addOfficerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_officer" value="1">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tambah Petugas Keamanan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($available_users)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Tidak ada user dengan role Security yang tersedia. Silakan tambah user dengan role Security terlebih dahulu di menu Manajemen Pengguna.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Pilih User Security *</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">-- Pilih User --</option>
                                <?php foreach ($available_users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['nama']) ?> (<?= htmlspecialchars($user['nim_nip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Badge Number *</label>
                                <input type="text" name="badge_number" class="form-control" required placeholder="Contoh: SEC001">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift *</label>
                                <select name="shift" class="form-select" required>
                                    <option value="">-- Pilih Shift --</option>
                                    <option value="morning">Pagi (07:00 - 15:00)</option>
                                    <option value="afternoon">Sore (15:00 - 23:00)</option>
                                    <option value="night">Malam (23:00 - 07:00)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Area Tanggung Jawab</label>
                            <input type="text" name="area_responsibility" class="form-control" placeholder="Contoh: Gedung A dan B">
                            <small class="text-muted">Area atau gedung yang menjadi tanggung jawab petugas</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <?php if (!empty($available_users)): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Officer Modal -->
<div class="modal fade" id="editOfficerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="edit_officer" value="1">
                <input type="hidden" name="officer_id" id="edit_officer_id">
                
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Data Petugas</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Petugas</label>
                        <input type="text" id="edit_officer_name" class="form-control" disabled>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Badge Number *</label>
                            <input type="text" name="badge_number" id="edit_badge_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shift *</label>
                            <select name="shift" id="edit_shift" class="form-select" required>
                                <option value="morning">Pagi (07:00 - 15:00)</option>
                                <option value="afternoon">Sore (15:00 - 23:00)</option>
                                <option value="night">Malam (23:00 - 07:00)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Area Tanggung Jawab</label>
                        <input type="text" name="area_responsibility" id="edit_area" class="form-control">
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

<script>
function editOfficer(officer) {
    document.getElementById('edit_officer_id').value = officer.id;
    document.getElementById('edit_officer_name').value = officer.nama;
    document.getElementById('edit_badge_number').value = officer.badge_number;
    document.getElementById('edit_shift').value = officer.shift;
    document.getElementById('edit_area').value = officer.area_responsibility || '';
    
    new bootstrap.Modal(document.getElementById('editOfficerModal')).show();
}
</script>

<?php include_once '../includes/footer.php'; ?>