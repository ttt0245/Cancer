<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
session_start();

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
        'message' => 'ユーザー名とパスワードを入力してください。',
    ]);
}

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        jsonResponse(401, [
            'success' => false,
            'message' => 'ユーザー名またはパスワードが正しくありません。',
        ]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];

    jsonResponse(200, [
        'success' => true,
        'message' => 'ログイン成功',
        'user' => [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
        ],
    ]);
} catch (PDOException $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'DB接続またはログイン処理に失敗しました。READMEのDB作成手順を確認してください。',
    ]);
}
