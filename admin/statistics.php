<?php
require_once '../config.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Total statistics
$sql = "SELECT 
        COUNT(*) as total_reports,
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_reports,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
        COUNT(CASE WHEN priority = 'low' THEN 1 END) as `low_priority`,
        COUNT(CASE WHEN priority = 'medium' THEN 1 END) as `medium_priority`,
        COUNT(CASE WHEN priority = 'high' THEN 1 END) as `high_priority`,
        COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as `urgent_priority`
        FROM reports
        WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$stats = $stmt->fetch();

// Reports by category
$sql = "SELECT ic.name, ic.icon, COUNT(r.id) as total
        FROM incident_categories ic
        LEFT JOIN reports r ON ic.id = r.category_id 
            AND DATE(r.created_at) BETWEEN ? AND ?
        GROUP BY ic.id
        ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$categories_stats = $stmt->fetchAll();

// Reports by location
$sql = "SELECT il.building_name, il.floor, COUNT(r.id) as total
        FROM incident_locations il
        LEFT JOIN reports r ON il.id = r.location_id 
            AND DATE(r.created_at) BETWEEN ? AND ?
        GROUP BY il.id
        HAVING total > 0
        ORDER BY total DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$locations_stats = $stmt->fetchAll();

// Reports by month (last 6 months)
$sql = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        DATE_FORMAT(created_at, '%b %Y') as month_name,
        COUNT(*) as total
        FROM reports
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month, month_name
        ORDER BY month ASC";
$monthly_stats = $pdo->query($sql)->fetchAll();

// Officer performance
$sql = "SELECT 
        u.nama as officer_name,
        so.badge_number,
        COUNT(r.id) as total_handled,
        COUNT(CASE WHEN r.status = 'resolved' THEN 1 END) as resolved,
        COUNT(CASE WHEN r.status = 'in_progress' THEN 1 END) as in_progress
        FROM security_officers so
        JOIN users u ON so.user_id = u.id
        LEFT JOIN reports r ON so.id = r.assigned_officer_id
            AND DATE(r.created_at) BETWEEN ? AND ?
        WHERE u.status = 'active'
        GROUP BY so.id
        ORDER BY total_handled DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$officer_stats = $stmt->fetchAll();

// Average response time
$sql = "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_hours
        FROM reports
        WHERE status IN ('in_progress', 'resolved')
        AND DATE(created_at) BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$avg_response = $stmt->fetch();

$page_title = "Statistik & Laporan";
require_once '../includes/header.php';
require_once '../includes/navbar_admin.php';
?>

<div class="container-fluid py-4">
    <!-- Filter Date Range -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="statistics.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Laporan</h6>
                            <h2 class="mb-0"><?= $stats['total_reports'] ?></h2>
                        </div>
                        <i class="fas fa-file-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Laporan Baru</h6>
                            <h2 class="mb-0"><?= $stats['new_reports'] ?></h2>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Sedang Ditangani</h6>
                            <h2 class="mb-0"><?= $stats['in_progress'] ?></h2>
                        </div>
                        <i class="fas fa-spinner fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Selesai</h6>
                            <h2 class="mb-0"><?= $stats['resolved'] ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Reports by Category -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Laporan per Kategori</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart"></canvas>
                    <div class="mt-3">
                        <?php foreach ($categories_stats as $cat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="fas fa-<?= $cat['icon'] ?> me-2"></i>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </span>
                                <span class="badge bg-primary"><?= $cat['total'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports by Priority -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Laporan per Prioritas</h5>
                </div>
                <div class="card-body">
                    <canvas id="priorityChart"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-success me-2"></i>Rendah</span>
                            <span class="badge bg-success"><?= $stats['low_priority'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-warning me-2"></i>Sedang</span>
                            <span class="badge bg-warning"><?= $stats['medium_priority'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-circle text-danger me-2"></i>Tinggi</span>
                            <span class="badge bg-danger"><?= $stats['high_priority'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle text-dark me-2"></i>Darurat</span>
                            <span class="badge bg-dark"><?= $stats['urgent_priority'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trend -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Tren Laporan (6 Bulan Terakhir)</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row">
        <!-- Top Locations -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Top 10 Lokasi Kejadian</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Lokasi</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($locations_stats as $loc): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($loc['building_name']) ?></strong><br>
                                            <small class="text-muted">Lantai <?= $loc['floor'] ? htmlspecialchars($loc['floor']) : '-' ?></small>
                                        </td>
                                        <td><span class="badge bg-danger"><?= $loc['total'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Officer Performance -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Kinerja Petugas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Petugas</th>
                                    <th>Total</th>
                                    <th>Selesai</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($officer_stats as $officer): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($officer['officer_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($officer['badge_number']) ?></small>
                                        </td>
                                        <td><span class="badge bg-primary"><?= $officer['total_handled'] ?></span></td>
                                        <td><span class="badge bg-success"><?= $officer['resolved'] ?></span></td>
                                        <td><span class="badge bg-info"><?= $officer['in_progress'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Average Response Time -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm bg-light">
                <div class="card-body text-center">
                    <h5 class="mb-3"><i class="fas fa-stopwatch me-2"></i>Rata-rata Waktu Respon</h5>
                    <h2 class="text-primary">
                        <?php 
                        $hours = round($avg_response['avg_response_hours'] ?? 0, 1);
                        echo $hours . ' jam';
                        ?>
                    </h2>
                    <p class="text-muted mb-0">Dari laporan masuk hingga ditangani</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Category Chart
const ctxCategory = document.getElementById('categoryChart').getContext('2d');
new Chart(ctxCategory, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($categories_stats, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($categories_stats, 'total')) ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Priority Chart
const ctxPriority = document.getElementById('priorityChart').getContext('2d');
new Chart(ctxPriority, {
    type: 'bar',
    data: {
        labels: ['Rendah', 'Sedang', 'Tinggi', 'Darurat'],
        datasets: [{
            label: 'Jumlah Laporan',
            data: [
                <?= $stats['low_priority'] ?>,
                <?= $stats['medium_priority'] ?>,
                <?= $stats['high_priority'] ?>,
                <?= $stats['urgent_priority'] ?>
            ],
            backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#343a40']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Monthly Trend Chart
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctxMonthly, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthly_stats, 'month_name')) ?>,
        datasets: [{
            label: 'Jumlah Laporan',
            data: <?= json_encode(array_column($monthly_stats, 'total')) ?>,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>