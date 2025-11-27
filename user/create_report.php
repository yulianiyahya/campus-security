<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Buat Laporan Baru';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Validate inputs
    $category_id = clean_input($_POST['category_id'] ?? '');
    $location_id = clean_input($_POST['location_id'] ?? '');
    $title = clean_input($_POST['title'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $incident_datetime = clean_input($_POST['incident_datetime'] ?? '');
    $priority = clean_input($_POST['priority'] ?? 'low');
    
    if (empty($category_id)) $errors[] = 'Kategori harus dipilih';
    if (empty($location_id)) $errors[] = 'Lokasi harus dipilih';
    if (empty($title)) $errors[] = 'Judul laporan harus diisi';
    if (empty($description)) $errors[] = 'Deskripsi harus diisi';
    if (empty($incident_datetime)) $errors[] = 'Waktu kejadian harus diisi';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Generate report number
            $report_number = 'REP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if report number exists
            $stmt = $pdo->prepare("SELECT id FROM reports WHERE report_number = ?");
            $stmt->execute([$report_number]);
            if ($stmt->fetch()) {
                $report_number = 'REP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Insert report
            $stmt = $pdo->prepare("
                INSERT INTO reports (report_number, reporter_id, category_id, location_id,
                                   title, description, incident_datetime, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')
            ");
            $stmt->execute([
                $report_number,
                $_SESSION['user_id'],
                $category_id,
                $location_id,
                $title,
                $description,
                $incident_datetime,
                $priority
            ]);
            
            $report_id = $pdo->lastInsertId();
            
            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../uploads/reports/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] == 0) {
                        $file_name = $_FILES['attachments']['name'][$key];
                        $file_size = $_FILES['attachments']['size'][$key];
                        $file_tmp = $_FILES['attachments']['tmp_name'][$key];
                        
                        // Get file extension
                        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        // Validate extension
                        if (!in_array($ext, $allowed_extensions)) {
                            $errors[] = "File $file_name: Tipe file tidak diizinkan. Hanya JPG, PNG, PDF.";
                            continue;
                        }
                        
                        // Validate size
                        if ($file_size > $max_size) {
                            $errors[] = "File $file_name: Ukuran terlalu besar (max 5MB)";
                            continue;
                        }
                        
                        // Generate unique filename
                        $new_filename = $report_number . '_' . time() . '_' . $key . '.' . $ext;
                        $file_path = $upload_dir . $new_filename;
                        
                        // Move file
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Determine simple file type for database - FIXED HERE
                            $simple_file_type = '';
                            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                $simple_file_type = 'image';
                            } elseif ($ext === 'pdf') {
                                $simple_file_type = 'pdf';
                            }
                            
                            // Insert to database with simplified file_type
                            $stmt = $pdo->prepare("
                                INSERT INTO report_attachments (report_id, file_name, file_path, file_type, file_size)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $report_id,
                                $file_name,
                                $file_path,
                                $simple_file_type,  // Use simplified type instead of MIME type
                                $file_size
                            ]);
                        } else {
                            $errors[] = "File $file_name: Gagal diupload";
                        }
                    }
                }
            }
            
            // Create notification for admin
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, reference_id)
                SELECT id, 'Laporan Baru', CONCAT('Laporan baru dari ', ?), 'report_update', ?
                FROM users WHERE role = 'admin'
            ");
            $stmt->execute([$_SESSION['nama'], $report_id]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Laporan berhasil dibuat dengan nomor: $report_number";
            header('Location: my_reports.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get categories
$stmt = $pdo->query("SELECT * FROM incident_categories WHERE is_active = TRUE ORDER BY name");
$categories = $stmt->fetchAll();

// Get locations
$stmt = $pdo->query("SELECT * FROM incident_locations ORDER BY building_name, floor");
$locations = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<main>
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Buat Laporan Keamanan
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Terjadi Kesalahan:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="reportForm">
                            <!-- Category -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-tag me-2"></i>Kategori Kejadian
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['icon'] ?? '📋'); ?> 
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                            - <?php echo htmlspecialchars($cat['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih kategori yang paling sesuai dengan kejadian</small>
                            </div>

                            <!-- Location -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-map-marker-alt me-2"></i>Lokasi Kejadian
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="location_id" class="form-select" required>
                                    <option value="">-- Pilih Lokasi --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>"
                                                <?php echo (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['building_name']); ?>
                                            <?php if ($loc['floor']): ?>
                                                - Lantai <?php echo htmlspecialchars($loc['floor']); ?>
                                            <?php endif; ?>
                                            <?php if ($loc['room']): ?>
                                                - Ruang <?php echo htmlspecialchars($loc['room']); ?>
                                            <?php endif; ?>
                                            <?php if ($loc['area']): ?>
                                                (<?php echo htmlspecialchars($loc['area']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih lokasi tempat kejadian</small>
                            </div>

                            <!-- Title -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-heading me-2"></i>Judul Laporan
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="title" class="form-control" 
                                       placeholder="Contoh: Kehilangan Laptop di Perpustakaan" 
                                       maxlength="200" required
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                <small class="text-muted">Judul singkat dan jelas (max 200 karakter)</small>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-align-left me-2"></i>Deskripsi Kejadian
                                    <span class="text-danger">*</span>
                                </label>
                                <textarea name="description" class="form-control" rows="6" 
                                          placeholder="Jelaskan secara detail apa yang terjadi, siapa yang terlibat, dan informasi penting lainnya..." 
                                          required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <small class="text-muted">Berikan detail selengkap mungkin untuk mempermudah penanganan</small>
                            </div>

                            <div class="row">
                                <!-- Incident DateTime -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt me-2"></i>Waktu Kejadian
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="datetime-local" name="incident_datetime" 
                                           class="form-control" 
                                           max="<?php echo date('Y-m-d\TH:i'); ?>" 
                                           value="<?php echo isset($_POST['incident_datetime']) ? htmlspecialchars($_POST['incident_datetime']) : ''; ?>"
                                           required>
                                    <small class="text-muted">Kapan kejadian ini terjadi?</small>
                                </div>

                                <!-- Priority -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Tingkat Prioritas
                                    </label>
                                    <select name="priority" class="form-select">
                                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>
                                            🟢 Rendah - Tidak Mendesak
                                        </option>
                                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>
                                            🟡 Sedang - Perlu Perhatian
                                        </option>
                                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>
                                            🔴 Tinggi - Sangat Mendesak
                                        </option>
                                    </select>
                                    <small class="text-muted">Seberapa mendesak laporan ini?</small>
                                </div>
                            </div>

                            <!-- File Attachments -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-paperclip me-2"></i>Lampiran (Opsional)
                                </label>
                                <input type="file" name="attachments[]" class="form-control" 
                                       accept=".jpg,.jpeg,.png,.pdf" multiple>
                                <small class="text-muted">
                                    Upload foto/dokumen pendukung (JPG, PNG, PDF - Max 5MB per file, max 5 files)
                                </small>
                                <div id="file-preview" class="mt-2"></div>
                            </div>

                            <!-- Privacy Notice -->
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="fas fa-info-circle me-2"></i>Informasi Privasi
                                </h6>
                                <p class="mb-0">
                                    Laporan Anda akan ditinjau oleh tim keamanan kampus. 
                                    Identitas Anda akan dijaga kerahasiaannya sesuai kebijakan privasi kampus.
                                </p>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Laporan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// File preview
document.querySelector('input[type="file"]').addEventListener('change', function(e) {
    const preview = document.getElementById('file-preview');
    preview.innerHTML = '';
    
    if (this.files.length > 0) {
        preview.innerHTML = '<div class="mt-2"><strong>File terpilih:</strong></div>';
        Array.from(this.files).forEach(file => {
            const div = document.createElement('div');
            div.className = 'badge bg-secondary me-2 mb-2';
            div.textContent = file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
            preview.appendChild(div);
        });
    }
});

// Auto-set incident datetime to now
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.querySelector('input[type="datetime-local"]').value = now.toISOString().slice(0, 16);
});

// Form validation
document.getElementById('reportForm').addEventListener('submit', function(e) {
    const title = document.querySelector('input[name="title"]').value;
    const description = document.querySelector('textarea[name="description"]').value;
    
    if (title.length < 10) {
        e.preventDefault();
        alert('Judul laporan terlalu pendek! Minimal 10 karakter.');
        return false;
    }
    
    if (description.length < 20) {
        e.preventDefault();
        alert('Deskripsi terlalu pendek! Minimal 20 karakter untuk penjelasan yang jelas.');
        return false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>