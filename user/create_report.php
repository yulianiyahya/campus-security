<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Buat Laporan Baru';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    $category_id = clean_input($_POST['category_id'] ?? '');
    $location_id = clean_input($_POST['location_id'] ?? '');
    $title = clean_input($_POST['title'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $incident_datetime = clean_input($_POST['incident_datetime'] ?? '');
    $priority = clean_input($_POST['priority'] ?? 'low');
    
    $latitude = isset($_POST['latitude']) && !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $detected_address = clean_input($_POST['detected_address'] ?? '');
    
    if (empty($category_id)) $errors[] = 'Kategori harus dipilih';
    if (empty($location_id)) $errors[] = 'Lokasi harus dipilih';
    if (empty($title)) $errors[] = 'Judul laporan harus diisi';
    if (empty($description)) $errors[] = 'Deskripsi harus diisi';
    if (empty($incident_datetime)) $errors[] = 'Waktu kejadian harus diisi';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $report_number = 'REP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("SELECT id FROM reports WHERE report_number = ?");
            $stmt->execute([$report_number]);
            if ($stmt->fetch()) {
                $report_number = 'REP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            if ($latitude && $longitude && $location_id) {
                $stmt = $pdo->prepare("SELECT latitude, longitude FROM incident_locations WHERE id = ?");
                $stmt->execute([$location_id]);
                $location_data = $stmt->fetch();
                
                if ($location_data && ($location_data['latitude'] === null || $location_data['longitude'] === null)) {
                    $stmt = $pdo->prepare("UPDATE incident_locations SET latitude = ?, longitude = ? WHERE id = ?");
                    $stmt->execute([$latitude, $longitude, $location_id]);
                }
            }
            
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
            
            if ($detected_address && $latitude && $longitude) {
                $action_desc = "📍 Lokasi terdeteksi via GPS:\n";
                $action_desc .= "Alamat: " . $detected_address . "\n";
                $action_desc .= "Koordinat: " . $latitude . ", " . $longitude;
                
                $stmt = $pdo->prepare("
                    INSERT INTO report_actions (report_id, officer_id, action_type, action_description, action_date)
                    SELECT ?, id, 'documentation', ?, NOW()
                    FROM security_officers 
                    LIMIT 1
                ");
                $result = $stmt->execute([$report_id, $action_desc]);
                
                if (!$result || $stmt->rowCount() == 0) {
                    $pdo->prepare("UPDATE reports SET resolution_notes = ? WHERE id = ?")
                        ->execute(["Lokasi GPS: " . $detected_address, $report_id]);
                }
            }
            
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../uploads/reports/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                $max_size = 5 * 1024 * 1024;
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] == 0) {
                        $file_name = $_FILES['attachments']['name'][$key];
                        $file_size = $_FILES['attachments']['size'][$key];
                        $file_tmp = $_FILES['attachments']['tmp_name'][$key];
                        
                        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed_extensions)) {
                            $errors[] = "File $file_name: Tipe file tidak diizinkan.";
                            continue;
                        }
                        
                        if ($file_size > $max_size) {
                            $errors[] = "File $file_name: Ukuran terlalu besar (max 5MB)";
                            continue;
                        }
                        
                        $new_filename = $report_number . '_' . time() . '_' . $key . '.' . $ext;
                        $file_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $simple_file_type = in_array($ext, ['jpg', 'jpeg', 'png']) ? 'image' : 'pdf';
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO report_attachments (report_id, file_name, file_path, file_type, file_size)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$report_id, $file_name, $file_path, $simple_file_type, $file_size]);
                        }
                    }
                }
            }
            
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

$stmt = $pdo->query("SELECT * FROM incident_categories WHERE is_active = TRUE ORDER BY name");
$categories = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM incident_locations ORDER BY building_name, floor");
$locations = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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

                        <form method="POST" enctype="multipart/form-data" id="reportForm">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="detected_address" id="detected_address">

                            <!-- ⭐ Interactive Map Section -->
                            <div class="mb-4">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-map-marked-alt me-2"></i>
                                            Lokasi Kejadian (GPS)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info mb-3">
                                            <strong><i class="fas fa-info-circle me-2"></i>Panduan:</strong><br>
                                            • Peta akan otomatis mendeteksi lokasi Anda<br>
                                            • Geser/drag marker 📍 merah untuk menyesuaikan posisi yang tepat<br>
                                            • Klik pada peta untuk memindahkan marker<br>
                                            • Gunakan tombol <i class="fas fa-crosshairs"></i> untuk reset ke lokasi GPS Anda
                                        </div>
                                        
                                        <!-- Auto-detect Status -->
                                        <div id="autoDetectStatus" class="alert alert-warning">
                                            <i class="fas fa-spinner fa-spin me-2"></i>Mendeteksi lokasi Anda...
                                        </div>
                                        
                                        <!-- Map Container -->
                                        <div id="reportMap" style="height: 450px; width: 100%; border-radius: 8px; border: 2px solid #0d6efd;"></div>
                                        
                                        <!-- Location Details -->
                                        <div id="locationDetails" class="mt-3" style="display:none;">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title text-success">
                                                        <i class="fas fa-check-circle me-2"></i>Lokasi Terdeteksi
                                                    </h6>
                                                    <p class="mb-2" id="detectedAddressText">
                                                        <strong>Alamat:</strong> <span id="addressValue">-</span>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-pin me-1"></i>
                                                        <strong>Koordinat:</strong> <span id="coordsDisplay">-</span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Nearby Reports Alert -->
                                        <div id="nearbyReportsAlert" class="alert alert-warning mt-3" style="display:none;">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <span id="nearbyMessage"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-tag me-2"></i>Kategori Kejadian
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                            - <?php echo htmlspecialchars($cat['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Location -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-building me-2"></i>Gedung/Area Kampus
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="location_id" class="form-select" required>
                                    <option value="">-- Pilih Gedung/Area --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>">
                                            <?php echo htmlspecialchars($loc['building_name']); ?>
                                            <?php if ($loc['floor']): ?>
                                                - Lantai <?php echo htmlspecialchars($loc['floor']); ?>
                                            <?php endif; ?>
                                            <?php if ($loc['room']): ?>
                                                - Ruang <?php echo htmlspecialchars($loc['room']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih gedung yang sesuai dengan marker di peta</small>
                            </div>

                            <!-- Title -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-heading me-2"></i>Judul Laporan
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="title" class="form-control" 
                                       placeholder="Contoh: Kehilangan Laptop di Perpustakaan" 
                                       maxlength="200" required>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-align-left me-2"></i>Deskripsi Kejadian
                                    <span class="text-danger">*</span>
                                </label>
                                <textarea name="description" class="form-control" rows="6" 
                                          placeholder="Jelaskan secara detail apa yang terjadi..." 
                                          required></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt me-2"></i>Waktu Kejadian
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="datetime-local" name="incident_datetime" 
                                           class="form-control" 
                                           max="<?php echo date('Y-m-d\TH:i'); ?>" 
                                           required>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Tingkat Prioritas
                                    </label>
                                    <select name="priority" class="form-select">
                                        <option value="low">🟢 Rendah - Tidak Mendesak</option>
                                        <option value="medium" selected>🟡 Sedang - Perlu Perhatian</option>
                                        <option value="high">🔴 Tinggi - Sangat Mendesak</option>
                                    </select>
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
                                    Upload foto/dokumen (JPG, PNG, PDF - Max 5MB)
                                </small>
                                <div id="file-preview" class="mt-2"></div>
                            </div>

                            <!-- Privacy Notice -->
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="fas fa-shield-alt me-2"></i>Informasi Privasi
                                </h6>
                                <p class="mb-0">
                                    Laporan dan lokasi GPS Anda akan ditinjau oleh tim keamanan kampus. 
                                    Data akan dijaga kerahasiaannya sesuai kebijakan privasi kampus.
                                </p>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
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

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let marker;
let circle;
let nearbyMarkers = [];

// Initialize map
function initMap(lat = -6.2088, lng = 106.8456, zoom = 13) {
    console.log('Initializing map with center:', lat, lng);
    
    map = L.map('reportMap').setView([lat, lng], zoom);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add location control button
    addLocationControl();
    
    // Map click handler - for manual positioning
    map.on('click', function(e) {
        console.log('Map clicked at:', e.latlng.lat, e.latlng.lng);
        setMarker(e.latlng.lat, e.latlng.lng);
    });
    
    console.log('Map initialized successfully');
}

// Add custom location control
function addLocationControl() {
    const LocationControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd: function(map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const button = L.DomUtil.create('a', '', container);
            
            button.href = '#';
            button.title = 'Deteksi Ulang Lokasi Saya';
            button.innerHTML = '<i class="fas fa-crosshairs" style="font-size: 18px; line-height: 26px;"></i>';
            button.style.width = '30px';
            button.style.height = '30px';
            button.style.lineHeight = '30px';
            button.style.textAlign = 'center';
            button.style.backgroundColor = 'white';
            
            button.onclick = function(e) {
                e.preventDefault();
                getCurrentLocation();
            };
            
            return container;
        }
    });
    
    map.addControl(new LocationControl());
}

// Set marker on map
function setMarker(lat, lng) {
    // Remove existing
    if (marker) {
        map.removeLayer(marker);
    }
    if (circle) {
        map.removeLayer(circle);
    }
    
    // IMPORTANT: Ensure coordinates are valid numbers
    lat = parseFloat(lat);
    lng = parseFloat(lng);
    
    console.log('Setting marker at:', lat, lng);
    
    // Create draggable marker
    marker = L.marker([lat, lng], {
        draggable: true,
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map);
    
    // Bind popup with coordinates for verification
    marker.bindPopup(`
        <strong>📍 Lokasi Kejadian</strong><br>
        <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small><br>
        <small class="text-muted">Drag marker untuk menyesuaikan</small>
    `).openPopup();
    
    // Accuracy circle at EXACT same coordinates
    circle = L.circle([lat, lng], {
        radius: 50,
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.15,
        weight: 2
    }).addTo(map);
    
    // Drag handler - prevent recursive loop
    marker.off('dragend'); // Remove old handler first
    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        console.log('Marker dragged to:', pos.lat, pos.lng);
        
        // Update without triggering another setMarker
        updateFormFields(pos.lat, pos.lng);
        reverseGeocode(pos.lat, pos.lng);
        checkNearbyReports(pos.lat, pos.lng);
        
        // Update circle position
        if (circle) {
            circle.setLatLng([pos.lat, pos.lng]);
        }
        
        // Update popup
        marker.setPopupContent(`
            <strong>📍 Lokasi Kejadian</strong><br>
            <small>Lat: ${pos.lat.toFixed(6)}<br>Lng: ${pos.lng.toFixed(6)}</small><br>
            <small class="text-muted">Drag marker untuk menyesuaikan</small>
        `);
    });
    
    // Update form and get address
    updateFormFields(lat, lng);
    reverseGeocode(lat, lng);
    checkNearbyReports(lat, lng);
    
    // Show location details
    document.getElementById('locationDetails').style.display = 'block';
    
    // CRITICAL: Center map on exact coordinates with proper zoom
    map.setView([lat, lng], 17, {
        animate: true,
        duration: 1
    });
}

// Update hidden fields
function updateFormFields(lat, lng) {
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('coordsDisplay').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

// Get current location
function getCurrentLocation() {
    const statusEl = document.getElementById('autoDetectStatus');
    statusEl.className = 'alert alert-info';
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mendeteksi lokasi Anda...';
    statusEl.style.display = 'block';
    
    if (!navigator.geolocation) {
        statusEl.className = 'alert alert-danger';
        statusEl.innerHTML = '❌ Browser tidak mendukung Geolocation';
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            console.log('GPS Position:', {
                lat: lat,
                lng: lng,
                accuracy: accuracy
            });
            
            statusEl.className = 'alert alert-success';
            statusEl.innerHTML = `<i class="fas fa-check-circle me-2"></i>Lokasi berhasil terdeteksi! Akurasi: ±${Math.round(accuracy)}m`;
            
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 5000);
            
            // Set marker at detected position
            setMarker(lat, lng);
            
            // Update accuracy circle radius based on GPS accuracy
            if (circle) {
                circle.setRadius(Math.max(accuracy, 50)); // Minimum 50m
            }
        },
        function(error) {
            console.error('Geolocation error:', error);
            
            let msg = '';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    msg = '❌ Akses lokasi ditolak. Mohon izinkan akses lokasi.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    msg = '❌ Lokasi tidak tersedia. Pastikan GPS aktif.';
                    break;
                case error.TIMEOUT:
                    msg = '❌ Request timeout. Silakan coba lagi.';
                    break;
                default:
                    msg = '❌ Error: ' + error.message;
            }
            
            statusEl.className = 'alert alert-danger';
            statusEl.innerHTML = msg + '<br><small>Klik pada peta untuk set lokasi manual</small>';
        },
        {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 0
        }
    );
}

// Reverse geocoding
async function reverseGeocode(lat, lng) {
    const addressEl = document.getElementById('addressValue');
    addressEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari alamat...';
    
    try {
        const response = await fetch(`../api/geocoding.php?lat=${lat}&lng=${lng}`);
        const data = await response.json();
        
        if (data.success && data.location) {
            const address = data.location.full_address || data.location.display_name;
            addressEl.textContent = address;
            document.getElementById('detected_address').value = address;
            
            if (marker) {
                marker.setPopupContent(`
                    <strong>📍 Lokasi Kejadian</strong><br>
                    ${address.substring(0, 50)}...<br>
                    <small>${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
                `);
            }
        } else {
            addressEl.textContent = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        addressEl.textContent = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
}

// Check nearby reports
async function checkNearbyReports(lat, lng, radius = 500) {
    const alertEl = document.getElementById('nearbyReportsAlert');
    const messageEl = document.getElementById('nearbyMessage');
    
    try {
        const response = await fetch(`../api/nearby_reports.php?lat=${lat}&lng=${lng}&radius=${radius}`);
        const data = await response.json();
        
        // Clear old markers
        nearbyMarkers.forEach(m => map.removeLayer(m));
        nearbyMarkers = [];
        
        if (data.success && data.nearby_count > 0) {
            messageEl.innerHTML = `
                <strong>Perhatian:</strong> Ditemukan ${data.nearby_count} laporan lain 
                dalam radius ${radius}m dari lokasi ini.
            `;
            alertEl.style.display = 'block';
            
            // Add markers
            if (data.reports) {
                data.reports.forEach(report => {
                    const m = L.circleMarker([report.latitude, report.longitude], {
                        radius: 8,
                        fillColor: '#ffc107',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.7
                    }).addTo(map);
                    
                    m.bindPopup(`
                        <strong>${report.report_number}</strong><br>
                        ${report.title}<br>
                        <small>${report.category_name}</small><br>
                        <small class="text-muted">Jarak: ${report.distance}m</small>
                    `);
                    
                    nearbyMarkers.push(m);
                });
            }
        } else {
            alertEl.style.display = 'none';
        }
    } catch (error) {
        console.error('Nearby check error:', error);
        alertEl.style.display = 'none';
    }
}

// File preview
document.querySelector('input[type="file"]')?.addEventListener('change', function(e) {
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

// Form validation
document.getElementById('reportForm').addEventListener('submit', function(e) {
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    
    if (!lat || !lng) {
        e.preventDefault();
        alert('⚠️ Mohon set lokasi di peta terlebih dahulu!\n\nKlik tombol 📍 atau klik pada peta untuk menandai lokasi kejadian.');
        return false;
    }
    
    const title = document.querySelector('input[name="title"]').value;
    const description = document.querySelector('textarea[name="description"]').value;
    
    if (title.length < 10) {
        e.preventDefault();
        alert('Judul laporan terlalu pendek! Minimal 10 karakter.');
        return false;
    }
    
    if (description.length < 20) {
        e.preventDefault();
        alert('Deskripsi terlalu pendek! Minimal 20 karakter.');
        return false;
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-set datetime
    const datetimeInput = document.querySelector('input[type="datetime-local"]');
    if (datetimeInput && !datetimeInput.value) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        datetimeInput.value = now.toISOString().slice(0, 16);
    }
    
    // Initialize map
    initMap();
    
    // Auto-detect location on load
    setTimeout(() => {
        getCurrentLocation();
    }, 500);
});
</script>

<style>
/* Custom styling for map controls */
.leaflet-control a {
    cursor: pointer;
    transition: all 0.3s ease;
}

.leaflet-control a:hover {
    background-color: #0d6efd !important;
    color: white !important;
}

/* Marker animation */
@keyframes markerBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes pulsate {
    0% { opacity: 0.3; }
    50% { opacity: 0.8; }
    100% { opacity: 0.3; }
}

.leaflet-marker-icon {
    animation: markerBounce 2s ease-in-out 3;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* Make the accuracy circle more visible */
.leaflet-interactive {
    animation: pulsate 2s ease-in-out infinite;
}

/* Better popup styling */
.leaflet-popup-content {
    margin: 10px 15px;
    line-height: 1.5;
}

.leaflet-popup-content strong {
    color: #dc3545;
    font-size: 14px;
}
</style>

<?php require_once '../includes/footer.php'; ?>