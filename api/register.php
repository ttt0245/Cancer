<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'message' => 'POSTメソッドのみ利用できます。',
    ]);
}

enforceSameOriginForStateChange();

$body = readJsonBody();
$username = isset($body['username']) ? trim((string) $body['username']) : '';
$password = isset($body['password']) ? (string) $body['password'] : '';

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

if (!preg_match('/\A[a-zA-Z0-9_]{3,50}\z/', $username)) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'ユーザー名は3〜50文字の半角英数字とアンダースコアのみ使用できます。',
    ]);
}

if (strlen($password) < 8 || strlen($password) > 128) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'パスワードは8〜128文字で入力してください。',
    ]);
}

if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    jsonResponse(400, [
        'success' => false,
        'message' => 'パスワードは英字と数字をそれぞれ1文字以上含めてください。',
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
