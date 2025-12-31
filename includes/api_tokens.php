<?php

function fh_api_tokens_table_name() {
    return 'api_tokens';
}

function fh_token_hash($plain) {
    return hash('sha256', $plain);
}

function fh_base64url_encode($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function fh_generate_api_token_plain() {
    return 'fh_' . fh_base64url_encode(random_bytes(32));
}

function fh_get_authorization_header_value() {
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['Authorization'] ?? '',
    ];

    foreach ($candidates as $v) {
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (is_string($k) && strcasecmp($k, 'Authorization') === 0 && is_string($v) && trim($v) !== '') {
                    return trim($v);
                }
            }
        }
    }

    return '';
}

function fh_get_bearer_token() {
    $h = fh_get_authorization_header_value();
    if (!$h) {
        return '';
    }
    if (stripos($h, 'Bearer ') !== 0) {
        return '';
    }
    return trim(substr($h, 7));
}

function fh_issue_api_token(PDO $pdo, $userId, $name = null) {
    $plain = fh_generate_api_token_plain();
    $hash = fh_token_hash($plain);

    $table = fh_api_tokens_table_name();

    $tokenId = null;
    if (defined('IS_VERCEL') && IS_VERCEL) {
        $stmt = $pdo->prepare("INSERT INTO {$table}(user_id, token_hash, name, created_at) VALUES (?,?,?, NOW()) RETURNING id");
        $stmt->execute([(int)$userId, $hash, $name]);
        $tokenId = (int)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("INSERT INTO {$table}(user_id, token_hash, name, created_at) VALUES (?,?,?, NOW())");
        $stmt->execute([(int)$userId, $hash, $name]);
        $tokenId = (int)$pdo->lastInsertId();
    }

    return [$plain, $tokenId];
}

function fh_revoke_api_token(PDO $pdo, $userId, $tokenId) {
    $table = fh_api_tokens_table_name();
    $stmt = $pdo->prepare("UPDATE {$table} SET revoked_at = NOW() WHERE id=? AND user_id=?");
    return $stmt->execute([(int)$tokenId, (int)$userId]);
}

function fh_list_api_tokens(PDO $pdo, $userId) {
    $table = fh_api_tokens_table_name();
    $stmt = $pdo->prepare("SELECT id, name, created_at, last_used_at, revoked_at FROM {$table} WHERE user_id=? ORDER BY id DESC");
    $stmt->execute([(int)$userId]);
    return $stmt->fetchAll();
}

function fh_user_from_api_token(PDO $pdo, $plainToken) {
    if (!$plainToken) {
        return null;
    }

    $hash = fh_token_hash($plainToken);
    $table = fh_api_tokens_table_name();
    $stmt = $pdo->prepare("SELECT id, user_id FROM {$table} WHERE token_hash=? AND revoked_at IS NULL LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $tokenId = (int)($row['id'] ?? 0);
    $userId = (int)($row['user_id'] ?? 0);
    if ($tokenId <= 0 || $userId <= 0) {
        return null;
    }

    $pdo->prepare("UPDATE {$table} SET last_used_at = NOW() WHERE id=?")->execute([$tokenId]);

    return ['token_id' => $tokenId, 'user_id' => $userId];
}
