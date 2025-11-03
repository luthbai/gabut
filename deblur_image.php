<?php
// Set header respons sebagai JSON
header('Content-Type: application/json');

// -----------------------------------------------------------------
// MASUKKAN API KEY ANDA DI SINI
// JANGAN PERNAH MENARUH API KEY INI DI FILE HTML ATAU JAVASCRIPT!
$CLIPDROP_API_KEY = "cafb74dadf12a521bbea0f71a52e08e48eff75b64a7e2da82b211badb820c9a673e96af32f78b99ba7235bf28a955b6b"; 
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

// 2. Validasi File
$file = $_FILES['imageFile'];
$maxFileSize = 10 * 1024 * 1024; // 10 MB (sesuaikan dengan batas ClipDrop)
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

// Endpoint ClipDrop untuk upscaling (seringkali memperbaiki blur dan noise)
// Periksa dokumentasi ClipDrop untuk endpoint yang lebih spesifik jika ada.
$clipdropEndpoint = "https://api.clipdrop.co/image-editing/v1/upscale"; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $clipdropEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-api-key: " . $CLIPDROP_API_KEY
]);

// Siapkan data POST multipart/form-data
$postFields = [
    // Gunakan CURLFile untuk mengirim file dengan benar
    'image_file' => new CURLFile($imagePath, $imageMime, $imageName)
];
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
// Set header 'Content-Type: multipart/form-data' akan ditangani otomatis oleh cURL

// 4. Eksekusi cURL
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 5. Tangani Respons dari ClipDrop
if ($httpCode === 200) {
    // Sukses! API mengembalikan data gambar biner.
    // Encode ke Base64 agar aman dikirim melalui JSON.
    $imageDataBase64 = base64_encode($response);
    
    // Kirim respons sukses ke JavaScript
    echo json_encode([
        'success' => true, 
        'imageData' => $imageDataBase64, 
        'mimeType' => 'image/png' // ClipDrop upscale biasanya mengembalikan PNG
    ]);
} else {
    // Gagal
    http_response_code(502); // Bad Gateway (error dari server eksternal)
    
    // Coba decode respons error dari ClipDrop (biasanya JSON)
    $errorDetails = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // Jika responsnya JSON, kirimkan detail errornya
        echo json_encode([
            'error' => 'Gagal memproses gambar dengan ClipDrop API.',
            'details' => $errorDetails['error'] ?? 'Error tidak diketahui dari ClipDrop'
        ]);
    } else {
        // Jika responsnya bukan JSON (misal, teks error)
        echo json_encode([
            'error' => 'Gagal memproses gambar dengan ClipDrop API.',
            'details' => 'Server ClipDrop mengembalikan HTTP Code ' . $httpCode,
            'raw_response' => $response,
            'curl_error' => $curlError
        ]);
    }
}
?>
