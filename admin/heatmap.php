<?php
require_once '../config.php';
check_login();
check_role(['admin', 'security']);

$page_title = 'Heatmap Keamanan Kampus';

// Get filter parameters
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// Get all reports with location data for heatmap
$sql = "
    SELECT 
        r.id,
        r.report_number,
        r.title,
        r.status,
        r.priority,
        r.created_at,
        ic.name as category_name,
        ic.icon,
        ic.severity,
        il.building_name,
        il.latitude,
        il.longitude
    FROM reports r
    JOIN incident_locations il ON r.location_id = il.id
    JOIN incident_categories ic ON r.category_id = ic.id
    WHERE il.latitude IS NOT NULL 
      AND il.longitude IS NOT NULL
      AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
";

$params = [$days];

if ($category_id > 0) {
    $sql .= " AND r.category_id = ?";
    $params[] = $category_id;
}

if (!empty($status)) {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_count,
        COUNT(CASE WHEN priority = 'high' OR priority = 'urgent' THEN 1 END) as high_priority,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_week
    FROM reports r
    JOIN incident_locations il ON r.location_id = il.id
    WHERE il.latitude IS NOT NULL 
      AND il.longitude IS NOT NULL
      AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$days]);
$stats = $stmt->fetch();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM incident_categories WHERE is_active = TRUE ORDER BY name")->fetchAll();

// Get hotspot areas (areas with most reports)
$hotspot_sql = "
    SELECT 
        il.building_name,
        il.latitude,
        il.longitude,
        COUNT(*) as report_count,
        COUNT(CASE WHEN r.status = 'new' THEN 1 END) as pending_count
    FROM reports r
    JOIN incident_locations il ON r.location_id = il.id
    WHERE il.latitude IS NOT NULL 
      AND il.longitude IS NOT NULL
      AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY il.building_name, il.latitude, il.longitude
    HAVING report_count >= 2
    ORDER BY report_count DESC
    LIMIT 10
";
$stmt = $pdo->prepare($hotspot_sql);
$stmt->execute([$days]);
$hotspots = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<!-- Leaflet CSS & Heatmap Plugin -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<main>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-fire me-2 text-danger"></i>Heatmap Keamanan Kampus</h2>
                <p class="text-muted">Visualisasi area rawan kejadian keamanan</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="statistics.php" class="btn btn-outline-primary">
                    <i class="fas fa-chart-line me-2"></i>Statistik
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Total Laporan</h6>
                                <h2 class="mb-0"><?= $stats['total'] ?></h2>
                                <small><?= $days ?> hari terakhir</small>
                            </div>
                            <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Belum Ditangani</h6>
                                <h2 class="mb-0"><?= $stats['new_count'] ?></h2>
                                <small>Status: Baru</small>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Prioritas Tinggi</h6>
                                <h2 class="mb-0"><?= $stats['high_priority'] ?></h2>
                                <small>Urgent & High</small>
                            </div>
                            <i class="fas fa-fire fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Minggu Ini</h6>
                                <h2 class="mb-0"><?= $stats['last_week'] ?></h2>
                                <small>7 hari terakhir</small>
                            </div>
                            <i class="fas fa-calendar-week fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Map Column -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-map me-2"></i>Peta Heatmap</h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Filter Controls -->
                        <div class="p-3 bg-light border-bottom">
                            <form method="GET" class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Periode</label>
                                    <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="7" <?= $days == 7 ? 'selected' : '' ?>>7 Hari</option>
                                        <option value="30" <?= $days == 30 ? 'selected' : '' ?>>30 Hari</option>
                                        <option value="90" <?= $days == 90 ? 'selected' : '' ?>>90 Hari</option>
                                        <option value="365" <?= $days == 365 ? 'selected' : '' ?>>1 Tahun</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Kategori</label>
                                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="0">Semua Kategori</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Status</label>
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Semua Status</option>
                                        <option value="new" <?= $status == 'new' ? 'selected' : '' ?>>Baru</option>
                                        <option value="in_progress" <?= $status == 'in_progress' ? 'selected' : '' ?>>Proses</option>
                                        <option value="resolved" <?= $status == 'resolved' ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">&nbsp;</label>
                                    <a href="heatmap.php" class="btn btn-sm btn-secondary w-100">Reset</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Map -->
                        <div id="heatmapCanvas" style="height: 600px; width: 100%;"></div>
                        
                        <!-- Legend -->
                        <div class="p-3 bg-light border-top">
                            <small class="fw-bold">Legenda:</small><br>
                            <small>
                                <span class="badge bg-danger me-2">●</span> Prioritas Tinggi/Mendesak
                                <span class="badge bg-warning text-dark ms-3 me-2">●</span> Prioritas Sedang
                                <span class="badge bg-info ms-3 me-2">●</span> Prioritas Rendah
                                <span class="ms-3">🔥 Area lebih merah = lebih banyak kejadian</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hotspots List -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-fire-alt me-2"></i>Area Rawan (Hotspots)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($hotspots)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted">Tidak ada hotspot terdeteksi dalam periode ini</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($hotspots as $idx => $spot): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <span class="badge bg-danger me-2"><?= $idx + 1 ?></span>
                                                    <?= htmlspecialchars($spot['building_name']) ?>
                                                </h6>
                                                <p class="mb-1 small">
                                                    <i class="fas fa-exclamation-circle text-danger"></i>
                                                    <strong><?= $spot['report_count'] ?></strong> laporan
                                                    <?php if ($spot['pending_count'] > 0): ?>
                                                        <span class="text-warning">
                                                            • <?= $spot['pending_count'] ?> belum ditangani
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <small class="text-muted">
                                                    📍 <?= number_format($spot['latitude'], 6) ?>, 
                                                    <?= number_format($spot['longitude'], 6) ?>
                                                </small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="focusOnLocation(<?= $spot['latitude'] ?>, <?= $spot['longitude'] ?>)">
                                                <i class="fas fa-search-location"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Reports -->
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Laporan Terbaru</h6>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php 
                        $recent = array_slice($reports, 0, 5);
                        foreach ($recent as $report): 
                        ?>
                            <div class="mb-3 pb-2 border-bottom">
                                <small class="text-muted"><?= $report['report_number'] ?></small>
                                <p class="mb-1 small fw-bold"><?= htmlspecialchars(substr($report['title'], 0, 40)) ?>...</p>
                                <small>
                                    <span class="badge bg-<?= $report['priority'] == 'high' ? 'danger' : ($report['priority'] == 'medium' ? 'warning' : 'info') ?>">
                                        <?= ucfirst($report['priority']) ?>
                                    </span>
                                    <span class="text-muted ms-2"><?= $report['building_name'] ?></span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Leaflet JS & Heatmap Plugin -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
// Reports data from PHP
const reportsData = <?= json_encode($reports) ?>;

// Initialize map
const map = L.map('heatmapCanvas').setView([-6.2088, 106.8456], 13);

// Add tile layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
}).addTo(map);

// Prepare heatmap data with intensity based on priority
const heatData = reportsData.map(report => {
    let intensity = 0.3; // Default
    
    switch(report.priority) {
        case 'urgent':
            intensity = 1.0;
            break;
        case 'high':
            intensity = 0.8;
            break;
        case 'medium':
            intensity = 0.5;
            break;
        case 'low':
            intensity = 0.3;
            break;
    }
    
    return [
        parseFloat(report.latitude),
        parseFloat(report.longitude),
        intensity
    ];
});

// Add heatmap layer
const heat = L.heatLayer(heatData, {
    radius: 25,
    blur: 15,
    maxZoom: 17,
    max: 1.0,
    gradient: {
        0.0: 'blue',
        0.3: 'lime',
        0.5: 'yellow',
        0.7: 'orange',
        1.0: 'red'
    }
}).addTo(map);

// Add individual markers for each report
reportsData.forEach(report => {
    const markerColor = report.priority === 'high' || report.priority === 'urgent' ? 'red' : 
                       report.priority === 'medium' ? 'orange' : 'blue';
    
    const marker = L.circleMarker([report.latitude, report.longitude], {
        radius: 6,
        fillColor: markerColor,
        color: '#fff',
        weight: 1,
        opacity: 1,
        fillOpacity: 0.8
    }).addTo(map);
    
    marker.bindPopup(`
        <strong>${report.report_number}</strong><br>
        ${report.title}<br>
        <small class="text-muted">${report.category_name}</small><br>
        <small>📍 ${report.building_name}</small><br>
        <small>Prioritas: <span class="badge bg-${markerColor}">${report.priority}</span></small>
    `);
});

// Focus on location function
function focusOnLocation(lat, lng) {
    map.setView([lat, lng], 16);
}

// Auto-adjust map bounds to fit all markers
if (reportsData.length > 0) {
    const bounds = L.latLngBounds(reportsData.map(r => [r.latitude, r.longitude]));
    map.fitBounds(bounds, {padding: [50, 50]});
}

console.log(`Loaded ${reportsData.length} reports on heatmap`);
</script>

<?php require_once '../includes/footer.php'; ?>