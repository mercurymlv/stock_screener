<?php

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=stocks_dev;charset=utf8mb4',
        'mavaldez',
        'Gogo125!',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage()); // log real error
    http_response_code(500);
    echo "<h1>Temporary database issue</h1>";
    echo "<p>Please try again later.</p>";
    exit;
}