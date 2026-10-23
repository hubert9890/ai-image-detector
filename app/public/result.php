<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const MAX_FILE_SIZE = 10 * 1024 * 1024;

function fail(string $message, int $statusCode = 400): never {
    http_response_code($statusCode);
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Error</title>';
    echo '</head>';
    echo '<body>';
    echo '<h1>Error</h1>';
    echo '<p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p>';
    echo '<p><a href="index.php">Try again</a></p>';
    echo '</body>';
    echo '</html>';

    exit;
}

function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.', 405);
}

if (!isset($_FILES['image'])) {
    fail('No image was uploaded.');
}

$file = $_FILES['image'];

if (!is_array($file)) {
    fail('Invalid upload data.');
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'The file exceeds upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'The temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'PHP could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];

    $errorCode = (int) $file['error'];

    fail(
        $uploadErrors[$errorCode] ?? 'Unknown upload error. Code: ' . $errorCode,
        400
    );
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

if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true)) {
    fail('Could not create the upload directory.', 500);
}

$storedPath = $uploadDirectory . '/' . $storedFilename;

if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
    fail('Could not store the uploaded image.', 500);
}

try {
    $curl = curl_init(detectorUrl());

    if ($curl === false) {
        throw new RuntimeException('Could not initialize detector request.');
    }

    $curlFile = new CURLFile(
        $storedPath,
        $mimeType,
        basename((string) $file['name'])
    );

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => $curlFile,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
    ]);

    $responseBody = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);

    curl_close($curl);

    if ($responseBody === false || $curlError !== '') {
        throw new RuntimeException(
            'Detector service is unavailable. ' . $curlError
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            'Detector returned an error.' . $httpCode . '.'
        );
    }

    $predictionResults = json_decode(
        $responseBody, 
        true, 
        512, 
        JSON_THROW_ON_ERROR
    );

    // Expected payload from Detector Backend
    // [
    //     [
    //         'label' => 'human',
    //         'score' => 0.9975
    //     ],
    //     [
    //         'label' => 'artificial',
    //         'score' => 0.0024
    //     ]
    // ]

    if ( !is_array($predictionResults) || $predictionResults === [] ) {
        throw new RuntimeException(
            'Invalid detector response.'
        );
    }

    foreach ($predictionResults as $result) {
        if (
            !is_array($result) ||
            !isset($result['label']) ||
            !isset($result['score']) ||
            !is_string($result['label']) ||
            !is_numeric($result['score'])
        ) {
            throw new RuntimeException(
                'Detector returned an invalid prediction item.'
            );
        }
    }

    $bestPrediction = $predictionResults[0];

    foreach ($predictionResults as $result) {
        if ((float) $result['score'] > (float) $bestPrediction['score']) {
            $bestPrediction = $result;
        }
    }

    $predictedLabel = (string) $bestPrediction['label'];
    $isAiGenerated = strtolower($predictedLabel) === 'artificial';
    
    $confidence = (float) $bestPrediction['score'];
    $percentage = number_format (
        $confidence * 100, 2
    );

    
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'INSERT INTO analysis (
            original_filename, 
            stored_filename, 
            mime_type, 
            file_size,
            predicted_label, 
            is_ai_generated, 
            confidence
        )
        VALUES(
            :original_filename, 
            :stored_filename, 
            :mime_type, 
            :file_size,
            :predicted_label, 
            :is_ai_generated, 
            :confidence
        )'
    );

    $statement->execute([
        ':original_filename' => basename((string) $file['name']),
        ':stored_filename' => $storedFilename,
        ':mime_type' => $mimeType,
        ':file_size' => $file['size'],
        ':predicted_label' => $predictedLabel,
        ':is_ai_generated' => (int) $isAiGenerated,
        ':confidence' => (float) $confidence,
    ]);


    echo '<!DOCTYPE html>';
    echo '<html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Analysis Result</title>';
    echo '<style>';
    echo 'body {';
    echo '    font-family: Arial, sans-serif;';
    echo '    max-width: 800px;';
    echo '    margin: 40px auto;';
    echo '    padding: 0 20px;';
    echo '    line-height: 1.5;';
    echo '}';
    echo '.summary, .payload {';
    echo '    border: 1px solid #ddd;';
    echo '    border-radius: 8px;';
    echo '    padding: 20px;';
    echo '    margin-top: 20px;';
    echo '}';
    echo '.summary {';
    echo '    background: #f5f8ff;';
    echo '}';
    echo 'table {';
    echo '    width: 100%;';
    echo '    border-collapse: collapse;';
    echo '    margin-top: 10px;';
    echo '}';
    echo 'th, td {';
    echo '    border: 1px solid #ddd;';
    echo '    padding: 10px;';
    echo '    text-align: left;';
    echo '}';
    echo 'th {';
    echo '    background: #f0f0f0;';
    echo '}';
    echo '.human { color: green; }';
    echo '.artificial { color: darkred; }';
    echo '</style>';

    echo '</head>';
    echo '<body>';

    echo '<h1>Analysis Result</h1>';

    echo '<section class="summary">';
    echo '<h2>Primary Result</h2>';

    echo '<p><strong>Result:</strong> '
        . escape($predictedLabel)
        . '</p>';

    echo '<p><strong>Confidence:</strong> '
        . escape($percentage)
        . '%</p>';

    echo '<p><strong>AI generated:</strong> '
        . ($isAiGenerated ? 'Yes' : 'No')
        . '</p>';

    echo '</section>';

    echo '<section class="payload">';
    echo '<h2>Complete Detector Results</h2>';

    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Label</th>';
    echo '<th>Score</th>';
    echo '<th>Percentage</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($predictionResults as $result) {
        $label = (string) $result['label'];
        $score = (float) $result['score'];
        $scorePercentage = number_format($score * 100, 2);
        $className = strtolower($label);

        echo '<tr>';
        echo '<td class="' . escape($className) . '">'
            . escape($label)
            . '</td>';
        echo '<td>' . escape((string) $score) . '</td>';
        echo '<td>' . escape($scorePercentage) . '%</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '</section>';

    echo '<p><a href="index.php">Analyze another image</a></p>';

    echo '</body>';
    echo '</html>';

} catch (JsonException $exception) {
    if (is_file($storedPath)) {
        unlink($storedPath);
    }

    fail('Detector returned invalid JSON.' , 500);
}

catch (Throwable $exception) {
    if (file_exists($storedPath)) {
        unlink($storedPath);
    }

    fail($exception->getMessage(), 500);
}

