<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
startSecureSession();

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
        'message' => 'ユーザー名とパスワードを入力してください。',
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

$rateLimitKey = buildLoginRateLimitKey($username);
$rateLimit = checkLoginRateLimit($rateLimitKey);
if (!$rateLimit['allowed']) {
    jsonResponse(429, [
        'success' => false,
        'message' => 'ログイン試行回数が上限に達しました。しばらく待ってから再試行してください。',
        'retry_after' => (int) $rateLimit['retry_after'],
    ]);
}

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    $hashForVerify = $user ? (string) $user['password_hash'] : getPasswordVerifyDummyHash();
    $isValidPassword = password_verify($password, $hashForVerify);

    if (!$user || !$isValidPassword) {
        registerLoginFailure($rateLimitKey);
        jsonResponse(401, [
            'success' => false,
            'message' => 'ユーザー名またはパスワードが正しくありません。',
        ]);
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $rehashStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $rehashStmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);
    }

    clearLoginFailures($rateLimitKey);

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
