<?php
require_once __DIR__ . '/env.php';

function supabase_storage_public_url($bucket, $path) {
    $base = rtrim(getenv('SUPABASE_URL') ?: '', '/');
    if (!$base) {
        return null;
    }
    return $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));
}

function supabase_storage_upload_tmpfile($bucket, $path, $tmpFilePath, $contentType) {
    $base = rtrim(getenv('SUPABASE_URL') ?: '', '/');
    $serviceKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';

    if (!$base || !$serviceKey) {
        throw new RuntimeException('Supabase Storage not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.');
    }

    $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $serviceKey,
        'apikey: ' . $serviceKey,
        'Content-Type: ' . ($contentType ?: 'application/octet-stream'),
        'x-upsert: true',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tmpFilePath));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ch = null;

    if ($resp === false) {
        throw new RuntimeException('Supabase upload failed: ' . $err);
    }

    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Supabase upload failed (HTTP ' . $code . '): ' . $resp);
    }

    return supabase_storage_public_url($bucket, $path);
}
