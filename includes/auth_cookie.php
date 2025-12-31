<?php
require_once __DIR__ . '/env.php';

function fh_is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function fh_auth_secret() {
    $secret = getenv('FH_AUTH_SECRET') ?: '';
    if (!$secret && defined('IS_LOCAL') && IS_LOCAL) {
        $secret = 'dev-secret-change-me';
    }
    return $secret;
}

function fh_auth_sign($data) {
    $secret = fh_auth_secret();
    if (!$secret) {
        return null;
    }
    return hash_hmac('sha256', $data, $secret);
}

function fh_set_auth_cookie($user) {
    $payload = json_encode([
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? null,
        'email' => $user['email'] ?? null,
        'photo_url' => $user['photo_url'] ?? null,
        'iat' => time(),
    ]);

    if (!$payload) {
        return false;
    }

    $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sig = fh_auth_sign($b64);
    if (!$sig) {
        return false;
    }

    $value = $b64 . '.' . $sig;

    return setcookie('fh_user', $value, [
        'expires' => time() + 60 * 60 * 24 * 7,
        'path' => '/',
        'secure' => fh_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function fh_clear_auth_cookie() {
    return setcookie('fh_user', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => fh_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function fh_restore_user_from_cookie() {
    if (!empty($_SESSION['user'])) {
        return true;
    }

    $raw = $_COOKIE['fh_user'] ?? '';
    if (!$raw || strpos($raw, '.') === false) {
        return false;
    }

    [$b64, $sig] = explode('.', $raw, 2);
    $expected = fh_auth_sign($b64);
    if (!$expected || !hash_equals($expected, $sig)) {
        return false;
    }

    $json = base64_decode(strtr($b64, '-_', '+/'), true);
    if (!$json) {
        return false;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['id'])) {
        return false;
    }

    $_SESSION['user'] = [
        'id' => $data['id'],
        'name' => $data['name'] ?? '',
        'email' => $data['email'] ?? '',
        'photo_url' => $data['photo_url'] ?? null,
    ];

    return true;
}
