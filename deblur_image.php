<?php
// TAMBAHKAN 3 BARIS INI UNTUK MELIHAT ERROR PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header respons sebagai JSON
header('Content-Type: application/json');

// -----------------------------------------------------------------
// MASUKKAN API KEY CLIPDROP ANDA DI SINI
$CLIPDROP_API_KEY = "fe30029d9068f23de3233bd0033397f19d41816931efa5ac083b0e243b83fee20bc2d26bdb97570e47a00663a4aef9d5"; 
// -----------------------------------------------------------------

// 1. Validasi Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Metode request harus POST.']);
    exit;
}

if (!isset($_FILES['imageFile'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Tidak ada file gambar yang diterima. Pastikan key-nya adalah "imageFile".']);
    exit;
}

// 2. Validasi File (Anda bisa sesuaikan batasannya)
$file = $_FILES['imageFile'];
$maxFileSize = 10 * 1024 * 1024; // 10 MB
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Terjadi kesalahan saat mengunggah file.', 'upload_error' => $file['error']]);
    exit;
}
if ($file['size'] > $maxFileSize) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['error' => 'Ukuran file terlalu besar (maksimal 10MB).']);
    exit;
}
if (!in_array($file['type'], $allowedMimeTypes)) {
    http_response_code(415); // Unsupported Media Type
    echo json_encode(['error' => 'Tipe file tidak didukung. Hanya JPG, PNG, GIF, WebP.']);
    exit;
}

// 3. Persiapan cURL untuk memanggil ClipDrop API
$imagePath = $file['tmp_name'];
$imageMime = $file['type'];
$imageName = $file['name'];

// --- PERUBAHAN UTAMA DI SINI ---
// Kita ganti endpoint dari 'upscale' ke 'unblur'
$clipdropEndpoint = "https://api.clipdrop.co/image-editing/v1/unblur"; 
// ------------------------------

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $clipdropEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-api-key: " . $CLIPDROP_API_KEY
]);

$postFields = [
    'image_file' => new CURLFile($imagePath, $imageMime, $imageName)
];
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

// 4. Eksekusi cURL
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. Tangani Respons dari ClipDrop
if ($httpCode === 200) {
    // Sukses!
    $imageDataBase64 = base64_encode($response);
    echo json_encode([
        'success' => true, 
        'imageData' => $imageDataBase64, 
        'mimeType' => 'image/png' // ClipDrop biasanya mengembalikan PNG
    ]);
} else {
    // Gagal
    http_response_code(502); // Bad Gateway
    $errorDetails = json_decode($response, true);
    
    echo json_encode([
        'error' => 'Gagal memproses gambar dengan ClipDrop API.',
        'details' => $errorDetails['error'] ?? 'Error tidak diketahui dari ClipDrop',
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'raw_response' => $response
    ]);
}
?>
