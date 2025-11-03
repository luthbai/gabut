<?php
header('Content-Type: application/json'); // Respons selalu JSON

// JANGAN PERNAH MENARUH API KEY DI JAVASCRIPT FRONT-END!
// Ganti dengan API Key ClipDrop Anda yang sebenarnya.
// Idealnya, ini harus diambil dari environment variable (e.g., getenv('CLIPDROP_API_KEY'))
// untuk keamanan yang lebih tinggi di lingkungan produksi.
$CLIPDROP_API_KEY = "fe30029d9068f23de3233bd0033397f19d41816931efa5ac083b0e243b83fee20bc2d26bdb97570e47a00663a4aef9d5"; 

// Pastikan request adalah POST dan ada file yang diunggah
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['imageFile'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Metode tidak diizinkan atau tidak ada file gambar yang diterima.']);
    exit;
}

// Validasi ukuran dan tipe file sederhana
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

if ($_FILES['imageFile']['size'] > $maxFileSize) {
    http_response_code(413);
    echo json_encode(['error' => 'Ukuran file terlalu besar (maksimal 5MB).']);
    exit;
}

if (!in_array($_FILES['imageFile']['type'], $allowedMimeTypes)) {
    http_response_code(415);
    echo json_encode(['error' => 'Tipe file tidak didukung. Hanya JPG, PNG, GIF yang diperbolehkan.']);
    exit;
}

$imagePath = $_FILES['imageFile']['tmp_name'];
$imageMime = $_FILES['imageFile']['type'];
$imageName = $_FILES['imageFile']['name'];

// Inisialisasi cURL untuk request ke ClipDrop API
$ch = curl_init();

// Endpoint ClipDrop untuk upscaling (seringkali juga deblurring ringan)
// Periksa dokumentasi ClipDrop untuk endpoint deblur/denoise yang lebih spesifik jika ada.
$clipdropEndpoint = "https://api.clipdrop.co/image-editing/v1/upscale"; 

curl_setopt($ch, CURLOPT_URL, $clipdropEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-api-key: " . $CLIPDROP_API_KEY,
    // Content-Type akan diatur secara otomatis oleh CURLFile untuk multipart/form-data
]);

// Siapkan data POST multipart/form-data
$postFields = [
    'image_file' => new CURLFile($imagePath, $imageMime, $imageName)
];
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

// Eksekusi request cURL ke ClipDrop API
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Tangani respons dari ClipDrop API
if ($httpCode === 200) {
    // API mengembalikan gambar biner. Kita encode ke Base64 agar bisa dikirim melalui JSON.
    $imageDataBase64 = base64_encode($response);
    echo json_encode(['success' => true, 'imageData' => $imageDataBase64, 'mimeType' => 'image/png']); // ClipDrop upscale seringkali mengeluarkan PNG
} else {
    // Tangani error dari ClipDrop API atau error cURL
    http_response_code(500); // Internal Server Error
    error_log("ClipDrop API Error (HTTP Code: $httpCode): " . $response);
    error_log("cURL Error: " . $error);
    echo json_encode([
        'error' => 'Gagal memproses gambar dengan ClipDrop API.',
        'details' => $response, // Detail respons dari ClipDrop
        'curl_error' => $error // Error dari cURL jika ada
    ]);
}
?>
