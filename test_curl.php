<?php
/**
 * Test Script untuk Diagnosa Masalah Geocoding
 * Letakkan file ini di root project, akses via browser
 */

echo "<h1>🔧 Diagnosa Geocoding API</h1>";

// Test 1: Check if cURL is installed
echo "<h2>Test 1: cURL Extension</h2>";
if (function_exists('curl_version')) {
    $curl_info = curl_version();
    echo "✅ <strong>cURL AKTIF</strong><br>";
    echo "Version: " . $curl_info['version'] . "<br>";
    echo "SSL Version: " . $curl_info['ssl_version'] . "<br><br>";
} else {
    echo "❌ <strong>cURL TIDAK AKTIF!</strong><br>";
    echo "Solusi: Aktifkan extension=curl di php.ini<br><br>";
    exit;
}

// Test 2: Test API call directly
echo "<h2>Test 2: Test API Call ke Nominatim</h2>";
$test_lat = -6.356992;
$test_lng = 107.282432;

echo "Koordinat Test: $test_lat, $test_lng (lokasi Anda)<br><br>";

$url = "https://nominatim.openstreetmap.org/reverse";
$params = [
    'format' => 'json',
    'lat' => $test_lat,
    'lon' => $test_lng,
    'zoom' => 18,
    'addressdetails' => 1,
    'accept-language' => 'id'
];

$full_url = $url . '?' . http_build_query($params);

echo "<strong>URL yang dipanggil:</strong><br>";
echo "<code style='background:#f5f5f5;padding:10px;display:block;word-break:break-all;'>";
echo htmlspecialchars($full_url);
echo "</code><br><br>";

// Initialize cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $full_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Campus Security System/1.0 (Test Script)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true
]);

echo "<strong>Mengirim request...</strong><br>";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: <strong>$http_code</strong><br>";

if ($curl_error) {
    echo "❌ <strong>cURL Error:</strong> $curl_error<br><br>";
} else {
    echo "✅ <strong>Request berhasil!</strong><br><br>";
}

// Test 3: Parse response
echo "<h2>Test 3: Response dari API</h2>";

if ($response) {
    echo "<strong>Raw Response:</strong><br>";
    echo "<pre style='background:#f5f5f5;padding:10px;max-height:300px;overflow:auto;'>";
    echo htmlspecialchars($response);
    echo "</pre><br>";
    
    $data = json_decode($response, true);
    
    if ($data && !isset($data['error'])) {
        echo "✅ <strong>JSON Valid & Berhasil!</strong><br><br>";
        
        echo "<h3>📍 Hasil Geocoding:</h3>";
        echo "<strong>Display Name:</strong><br>";
        echo htmlspecialchars($data['display_name'] ?? 'N/A') . "<br><br>";
        
        if (isset($data['address'])) {
            echo "<strong>Address Details:</strong><br>";
            echo "<pre>";
            print_r($data['address']);
            echo "</pre>";
        }
        
        // Format Indonesian style address
        $address = $data['address'] ?? [];
        $addressParts = array_filter([
            $address['road'] ?? null,
            $address['suburb'] ?? $address['neighbourhood'] ?? null,
            $address['village'] ?? null,
            $address['city'] ?? $address['town'] ?? $address['county'] ?? null,
            $address['state'] ?? null
        ]);
        
        $formatted = implode(', ', $addressParts);
        
        echo "<h3>✨ Alamat Terformat:</h3>";
        echo "<div style='background:#d4edda;padding:15px;border-radius:5px;'>";
        echo "<strong style='color:#155724;font-size:18px;'>";
        echo htmlspecialchars($formatted ?: $data['display_name']);
        echo "</strong>";
        echo "</div>";
        
    } else {
        echo "❌ <strong>Response Error:</strong><br>";
        echo isset($data['error']) ? htmlspecialchars($data['error']) : "Unknown error";
    }
} else {
    echo "❌ <strong>Tidak ada response dari API</strong><br>";
}

// Test 4: Recommendations
echo "<hr><h2>💡 Rekomendasi</h2>";

if ($http_code == 200 && $response && !$curl_error) {
    echo "✅ <strong>Semua test PASSED!</strong><br>";
    echo "Geocoding API berjalan dengan baik. Jika masih error di form, check:<br>";
    echo "1. Console browser (F12) untuk error JavaScript<br>";
    echo "2. Path API di JavaScript: <code>../api/geocoding.php</code><br>";
    echo "3. Network tab di browser untuk lihat request gagal<br>";
} else {
    echo "⚠️ <strong>Ada masalah dengan koneksi API:</strong><br>";
    if ($curl_error) {
        echo "- cURL Error: $curl_error<br>";
    }
    if ($http_code != 200) {
        echo "- HTTP Code: $http_code (seharusnya 200)<br>";
    }
    echo "<br><strong>Solusi:</strong><br>";
    echo "1. Pastikan server bisa akses internet<br>";
    echo "2. Check firewall/antivirus yang block HTTPS<br>";
    echo "3. Coba ganti DNS ke 8.8.8.8 (Google DNS)<br>";
}

echo "<hr>";
echo "<p><small>Script ini aman untuk dihapus setelah testing selesai.</small></p>";
?>