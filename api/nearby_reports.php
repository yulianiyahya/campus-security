<?php
/**
 * Nearby Reports API
 * Find reports within specified radius using Haversine formula
 * Usage: GET /api/nearby_reports.php?lat=-6.2088&lng=106.8456&radius=500
 */

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Get parameters
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius = isset($_GET['radius']) ? intval($_GET['radius']) : 500; // meters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

// Validate input
if (!$lat || !$lng) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parameter lat dan lng harus diisi'
    ]);
    exit;
}

try {
    /**
     * Haversine Formula for distance calculation
     * Calculate distance in meters between two GPS coordinates
     * 
     * Formula: d = 2 * R * asin(sqrt(sin²((lat2-lat1)/2) + cos(lat1) * cos(lat2) * sin²((lng2-lng1)/2)))
     * Where R = Earth's radius in meters (6371000)
     */
    
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
            il.building_name,
            il.latitude,
            il.longitude,
            u.nama as reporter_name,
            (
                6371000 * 2 * ASIN(
                    SQRT(
                        POWER(SIN((RADIANS(il.latitude) - RADIANS(?)) / 2), 2) +
                        COS(RADIANS(?)) * COS(RADIANS(il.latitude)) *
                        POWER(SIN((RADIANS(il.longitude) - RADIANS(?)) / 2), 2)
                    )
                )
            ) AS distance
        FROM reports r
        JOIN incident_locations il ON r.location_id = il.id
        JOIN incident_categories ic ON r.category_id = ic.id
        JOIN users u ON r.reporter_id = u.id
        WHERE il.latitude IS NOT NULL 
          AND il.longitude IS NOT NULL
        HAVING distance <= ?
        ORDER BY distance ASC, r.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lat, $lat, $lng, $radius, $limit]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates and add status badges
    foreach ($reports as &$report) {
        $report['distance'] = round($report['distance'], 1); // Round to 1 decimal
        $report['created_at'] = date('d M Y H:i', strtotime($report['created_at']));
        
        // Add status text
        $statusMap = [
            'new' => 'Baru',
            'in_progress' => 'Dalam Proses',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup'
        ];
        $report['status_text'] = $statusMap[$report['status']] ?? $report['status'];
        
        // Add priority text
        $priorityMap = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak'
        ];
        $report['priority_text'] = $priorityMap[$report['priority']] ?? $report['priority'];
    }
    
    // Response
    echo json_encode([
        'success' => true,
        'search_location' => [
            'latitude' => $lat,
            'longitude' => $lng,
            'radius' => $radius
        ],
        'nearby_count' => count($reports),
        'reports' => $reports
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>