<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const MAX_FILE_SIZE = 10 * 1024 * 1024;

function fail(string $message, int $statusCode = 400): never {
    http_response_code($statusCode);
    echo '<h1>Error</h1>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="index.php">Try again</a></p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.', 405);
}

if (!isset($_FILES['image'])) {
    fail('No image was uploaded.');
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    fail('Upload failed.');
}

if ($file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) {
    fail('Image must be between 1 byte and 10 MB.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowedMimeTypes = [
	'image/jpeg' => 'jpg',
	'image/jpg' => 'jpg',
    	'image/png' => 'png',
	'image/webp' => 'webp',
];

if (!array_key_exists($mimeType, $allowedMimeTypes)) {
    fail('Only JPEG, PNG, and WebP images are allowed.');
}

$imageInfo = @getimagesize($file['tmp_name']);

if ($imageInfo === false) {
    fail('The uploaded file is not a valid image.');
}

$storedFilename = bin2hex(random_bytes(24))
    . '.'
    . $allowedMimeTypes[$mimeType];

$uploadDirectory = __DIR__ . '/uploads';

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0750, true);
}

$storedPath = $uploadDirectory . '/' . $storedFilename;

if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
    fail('Could not store the uploaded image.', 500);
}

try {
    $curl = curl_init(detectorUrl());

    $curlFile = new CURLFile(
        $storedPath,
        $mimeType,
        basename($file['name'])
    );

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => $curlFile,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);

    $responseBody = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);

    curl_close($curl);

    if ($responseBody === false || $curlError !== '') {
        throw new RuntimeException('Detector service is unavailable.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Detector returned an error.');
    }

    $prediction = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

    if (
        !isset($prediction['label']) ||
        !isset($prediction['is_ai_generated']) ||
        !isset($prediction['confidence'])
    ) {
        throw new RuntimeException('Invalid detector response.');
    }

    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'INSERT INTO analyses
        (original_filename, stored_filename, mime_type, file_size,
         predicted_label, is_ai_generated, confidence)
        VALUES
        (:original_filename, :stored_filename, :mime_type, :file_size,
         :predicted_label, :is_ai_generated, :confidence)'
    );

    $statement->execute([
        ':original_filename' => basename($file['name']),
        ':stored_filename' => $storedFilename,
        ':mime_type' => $mimeType,
        ':file_size' => $file['size'],
        ':predicted_label' => $prediction['label'],
        ':is_ai_generated' => (int) $prediction['is_ai_generated'],
        ':confidence' => (float) $prediction['confidence'],
    ]);

    $percentage = number_format(
        ((float) $prediction['confidence']) * 100,
        2
    );

    echo '<!DOCTYPE html>';
    echo '<html lang="en"><head><meta charset="UTF-8">';
    echo '<title>Analysis Result</title></head><body>';
    echo '<h1>Analysis Result</h1>';
    echo '<p><strong>Result:</strong> '
        . htmlspecialchars((string) $prediction['label'], ENT_QUOTES, 'UTF-8')
        . '</p>';
    echo '<p><strong>Confidence:</strong> '
        . htmlspecialchars($percentage, ENT_QUOTES, 'UTF-8')
        . '%</p>';
    echo '<p><a href="index.php">Analyze another image</a></p>';
    echo '</body></html>';

    // Delete the image after analysis if permanent storage is unnecessary.
    unlink($storedPath);

} catch (Throwable $exception) {
    if (file_exists($storedPath)) {
        unlink($storedPath);
    }

    fail($exception->getMessage(), 500);
}

