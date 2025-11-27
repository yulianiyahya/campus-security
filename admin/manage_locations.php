<?php
require_once '../config.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Handle Add Location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    try {
        $building_name = clean_input($_POST['building_name']);
        $floor = clean_input($_POST['floor']);
        $room = clean_input($_POST['room']);
        $area = clean_input($_POST['area']);
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $is_high_risk = isset($_POST['is_high_risk']) ? 1 : 0;
        
        // Validasi input
        if (empty($building_name)) {
            $_SESSION['error'] = "Nama gedung wajib diisi!";
            redirect('admin/manage_locations.php');
        }
        
        $sql = "INSERT INTO incident_locations (building_name, floor, room, area, latitude, longitude, is_high_risk) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$building_name, $floor, $room, $area, $latitude, $longitude, $is_high_risk])) {
            $_SESSION['success'] = "Lokasi '{$building_name}' berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan lokasi!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
    
    redirect('admin/manage_locations.php');
}

// Handle Update Location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
    try {
        $id = (int)$_POST['location_id'];
        $building_name = clean_input($_POST['building_name']);
        $floor = clean_input($_POST['floor']);
        $room = clean_input($_POST['room']);
        $area = clean_input($_POST['area']);
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $is_high_risk = isset($_POST['is_high_risk']) ? 1 : 0;
        
        // Validasi input
        if (empty($building_name)) {
            $_SESSION['error'] = "Nama gedung wajib diisi!";
            redirect('admin/manage_locations.php');
        }
        
        $sql = "UPDATE incident_locations 
                SET building_name = ?, floor = ?, room = ?, area = ?, 
                    latitude = ?, longitude = ?, is_high_risk = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$building_name, $floor, $room, $area, $latitude, $longitude, $is_high_risk, $id])) {
            $_SESSION['success'] = "Lokasi '{$building_name}' berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui lokasi!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
    
    redirect('admin/manage_locations.php');
}

// Handle Delete Location
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Check if location is being used
        $check = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE location_id = ?");
        $check->execute([$id]);
        
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus lokasi yang sedang digunakan!";
        } else {
            $sql = "DELETE FROM incident_locations WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$id])) {
                $_SESSION['success'] = "Lokasi berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus lokasi!";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
    
    redirect('admin/manage_locations.php');
}

// Get all locations with report count
$sql = "SELECT il.*, 
        COUNT(r.id) as total_reports
        FROM incident_locations il
        LEFT JOIN reports r ON il.id = r.location_id
        GROUP BY il.id
        ORDER BY il.building_name, il.floor";
$locations = $pdo->query($sql)->fetchAll();

// Get location for edit
$edit_location = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM incident_locations WHERE id = ?");
    $stmt->execute([$id]);
    $edit_location = $stmt->fetch();
}

$page_title = "Kelola Lokasi";
require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>Kelola Lokasi Kampus
                    </h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                        <i class="fas fa-plus me-1"></i>Tambah Lokasi
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
                                    <th>Gedung</th>
                                    <th>Lantai</th>
                                    <th>Ruangan</th>
                                    <th>Area</th>
                                    <th>Koordinat</th>
                                    <th>Total Laporan</th>
                                    <th>Status Risiko</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($locations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Belum ada lokasi. Silakan tambahkan lokasi baru.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($locations as $location): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($location['building_name']) ?></strong></td>
                                            <td><?= $location['floor'] ? htmlspecialchars($location['floor']) : '-' ?></td>
                                            <td><?= $location['room'] ? htmlspecialchars($location['room']) : '-' ?></td>
                                            <td><?= $location['area'] ? htmlspecialchars($location['area']) : '-' ?></td>
                                            <td>
                                                <?php if ($location['latitude'] && $location['longitude']): ?>
                                                    <small class="text-muted">
                                                        <?= number_format((float)$location['latitude'], 6) ?>,
                                                        <?= number_format((float)$location['longitude'], 6) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $location['total_reports'] ?> laporan</span>
                                            </td>
                                            <td>
                                                <?php if ($location['is_high_risk']): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> Rawan
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Aman</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="manage_locations.php?edit=<?= $location['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($location['total_reports'] == 0): ?>
                                                    <a href="manage_locations.php?delete=<?= $location['id'] ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Yakin ingin menghapus lokasi ini?')">
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

<!-- Add Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Tambah Lokasi Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="manage_locations.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Gedung <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="building_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lantai</label>
                            <input type="text" class="form-control" name="floor" placeholder="Contoh: 1, 2, Basement">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ruangan</label>
                            <input type="text" class="form-control" name="room" placeholder="Contoh: R.101, Lab Komputer">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Area/Zona</label>
                            <input type="text" class="form-control" name="area" placeholder="Contoh: Sayap Timur, Parkir">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude (GPS)</label>
                            <input type="text" class="form-control" name="latitude" placeholder="-6.123456">
                            <small class="text-muted">Opsional, untuk peta</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude (GPS)</label>
                            <input type="text" class="form-control" name="longitude" placeholder="106.123456">
                            <small class="text-muted">Opsional, untuk peta</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_high_risk" id="is_high_risk_add">
                            <label class="form-check-label" for="is_high_risk_add">
                                <i class="fas fa-exclamation-triangle text-danger"></i> Tandai sebagai Area Rawan
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <input type="hidden" name="add_location" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Location Modal -->
<?php if ($edit_location): ?>
<div class="modal fade show" id="editLocationModal" tabindex="-1" style="display:block;" aria-modal="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Lokasi</h5>
                <a href="manage_locations.php" class="btn-close"></a>
            </div>
            <form method="POST" action="manage_locations.php">
                <input type="hidden" name="location_id" value="<?= $edit_location['id'] ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Gedung <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="building_name" 
                                   value="<?= htmlspecialchars($edit_location['building_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lantai</label>
                            <input type="text" class="form-control" name="floor" 
                                   value="<?= htmlspecialchars($edit_location['floor'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ruangan</label>
                            <input type="text" class="form-control" name="room" 
                                   value="<?= htmlspecialchars($edit_location['room'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Area/Zona</label>
                            <input type="text" class="form-control" name="area" 
                                   value="<?= htmlspecialchars($edit_location['area'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude (GPS)</label>
                            <input type="text" class="form-control" name="latitude" 
                                   value="<?= $edit_location['latitude'] ?? '' ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude (GPS)</label>
                            <input type="text" class="form-control" name="longitude" 
                                   value="<?= $edit_location['longitude'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_high_risk" id="is_high_risk_edit"
                                   <?= ($edit_location['is_high_risk'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_high_risk_edit">
                                <i class="fas fa-exclamation-triangle text-danger"></i> Area Rawan
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="manage_locations.php" class="btn btn-secondary">Batal</a>
                    <input type="hidden" name="update_location" value="1">
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

<?php require_once '../includes/footer.php'; ?>