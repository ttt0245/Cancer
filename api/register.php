<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'POSTメソッドのみ利用できます。',
    ]);
}

$body = readJsonBody();
$username = isset($body['username']) ? trim((string) $body['username']) : '';
$password = isset($body['password']) ? trim((string) $body['password']) : '';

if ($username === '' || $password === '') {
    jsonResponse(400, [
        'success' => false,
        'message' => 'ユーザー名とパスワードは必須です。',
    ]);
}

if (mb_strlen($username) < 3) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'ユーザー名は3文字以上で入力してください。',
    ]);
}

if (strlen($password) < 6) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'パスワードは6文字以上で入力してください。',
    ]);
}

try {
    $pdo = getPdo();

    $existsStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $existsStmt->execute(['username' => $username]);

    if ($existsStmt->fetch()) {
        jsonResponse(409, [
            'success' => false,
            'message' => 'そのユーザー名は既に使われています。',
        ]);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
    $insertStmt->execute([
        'username' => $username,
        'password_hash' => $hashedPassword,
    ]);

    jsonResponse(201, [
        'success' => true,
        'message' => '登録が完了しました。',
        'user' => [
            'username' => $username,
        ],
    ]);
} catch (PDOException $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'DB接続または登録処理に失敗しました。READMEのDB作成手順を確認してください。',
    ]);
}
