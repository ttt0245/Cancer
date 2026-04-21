<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    jsonResponse(401, [
        'success' => false,
        'message' => '未ログインです。',
    ]);
}

jsonResponse(200, [
    'success' => true,
    'user' => [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
    ],
]);
