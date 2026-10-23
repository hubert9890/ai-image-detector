<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO {
    $host = getenv('DB_HOST') ?: 'db';
    $database = getenv('DB_DATABASE') ?: 'ai_detector';
    $username = getenv('DB_USERNAME') ?: 'protector';
    $password = getenv('DB_PASSWORD') ?: 'bisanda2077';

    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function detectorUrl(): string
{
    return getenv('DETECTOR_URL') ?: 'http://detector:5000/predict';
}

