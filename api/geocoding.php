<?php
/**
 * Reverse Geocoding API with Enhanced Error Handling
 * Converts latitude/longitude to human-readable address
 * Fallback: Returns coordinates as formatted string if geocoding fails
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Get input
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

// Validate input
if (!$lat || !$lng) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parameter lat dan lng harus diisi',
        'example' => 'GET /api/geocoding.php?lat=-6.2088&lng=106.8456'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate range
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Koordinat tidak valid'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if cURL is available
if (!function_exists('curl_init')) {
    // Fallback: Return formatted coordinates
    echo json_encode([
        'success' => true,
        'coordinates' => [
            'latitude' => $lat,
            'longitude' => $lng
        ],
        'location' => [
            'full_address' => sprintf('Lokasi: %.6f, %.6f', $lat, $lng),
            'display_name' => sprintf('Koordinat GPS: %.6f°, %.6f°', $lat, $lng),
            'details' => [
                'note' => 'Geocoding tidak tersedia (cURL disabled)'
            ]
        ],
        'fallback' => true,
        'message' => 'Koordinat tersimpan. Alamat akan diupdate manual.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Build Nominatim API URL
    $url = "https://nominatim.openstreetmap.org/reverse";
    $params = [
        'format' => 'json',
        'lat' => $lat,
        'lon' => $lng,
        'zoom' => 18,
        'addressdetails' => 1,
        'accept-language' => 'id'
    ];
    
    $full_url = $url . '?' . http_build_query($params);
    
    // Initialize cURL with extended timeout
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $full_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Campus Security System/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '', // Accept any encoding
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    // Handle cURL errors - Fallback to coordinates
    if ($curl_error || $curl_errno !== 0) {
        throw new Exception("Connection issue: " . ($curl_error ?: "Error code $curl_errno"));
    }
    
    // Handle HTTP errors - Fallback
    if ($http_code !== 200) {
        throw new Exception("API returned HTTP $http_code");
    }
    
    // Parse JSON
    $data = @json_decode($response, true);
    
    if (!$data) {
        throw new Exception("Invalid JSON response");
    }
    
    // Check for API error
    if (isset($data['error'])) {
        throw new Exception($data['error']);
    }
    
    // Parse address
    $address = $data['address'] ?? [];
    
    // Build Indonesian-style formatted address
    $addressParts = array_filter([
        $address['road'] ?? $address['pedestrian'] ?? $address['footway'] ?? null,
        $address['suburb'] ?? $address['neighbourhood'] ?? $address['hamlet'] ?? null,
        $address['village'] ?? $address['city_district'] ?? null,
        $address['city'] ?? $address['town'] ?? $address['municipality'] ?? $address['county'] ?? null,
        $address['state'] ?? null
    ]);
    
    $formattedAddress = implode(', ', $addressParts);
    
    // Fallback to display_name if no formatted address
    if (empty($formattedAddress)) {
        $formattedAddress = $data['display_name'] ?? sprintf('Koordinat: %.6f, %.6f', $lat, $lng);
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'coordinates' => [
            'latitude' => $lat,
            'longitude' => $lng
        ],
        'location' => [
            'full_address' => $formattedAddress,
            'display_name' => $data['display_name'] ?? $formattedAddress,
            'details' => [
                'road' => $address['road'] ?? null,
                'building' => $address['building'] ?? null,
                'suburb' => $address['suburb'] ?? $address['neighbourhood'] ?? null,
                'village' => $address['village'] ?? null,
                'city' => $address['city'] ?? $address['town'] ?? $address['county'] ?? null,
                'state' => $address['state'] ?? null,
                'postcode' => $address['postcode'] ?? null,
                'country' => $address['country'] ?? 'Indonesia'
            ]
        ],
        'place_type' => $data['type'] ?? 'unknown',
        'osm_id' => $data['osm_id'] ?? null,
        'fallback' => false
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // FALLBACK: Return formatted coordinates as "address"
    echo json_encode([
        'success' => true, // Still success because coordinates are valid
        'coordinates' => [
            'latitude' => $lat,
            'longitude' => $lng
        ],
        'location' => [
            'full_address' => sprintf('Lokasi GPS: %.6f, %.6f', $lat, $lng),
            'display_name' => sprintf('Koordinat: %.6f° Lintang, %.6f° Bujur', $lat, $lng),
            'details' => [
                'note' => 'Alamat tidak dapat dideteksi. Koordinat GPS tersimpan.',
                'reason' => $e->getMessage()
            ]
        ],
        'fallback' => true,
        'fallback_reason' => $e->getMessage(),
        'message' => 'Koordinat tersimpan dengan sukses. Petugas akan memverifikasi lokasi.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>