<?php

function fh_supabase_env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v === false || $v === null) {
        return $default;
    }
    return (string)$v;
}

function fh_supabase_storage_public_url(string $bucket, string $objectPath): string {
    $base = rtrim(fh_supabase_env('SUPABASE_URL'), '/');
    return $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($objectPath));
}

function fh_supabase_storage_upload(
    string $bucket,
    string $objectPath,
    string $localTmpFile,
    ?string $contentType = null
): string {
    $supabaseUrl = rtrim(fh_supabase_env('SUPABASE_URL'), '/');
    $serviceKey = fh_supabase_env('SUPABASE_SERVICE_ROLE_KEY');

    if (!$supabaseUrl || !$serviceKey) {
        throw new RuntimeException('Supabase Storage is not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.');
    }

    if (!is_file($localTmpFile)) {
        throw new RuntimeException('Upload temp file not found.');
    }

    $data = file_get_contents($localTmpFile);
    if ($data === false) {
        throw new RuntimeException('Failed to read upload temp file.');
    }

    $contentType = $contentType ?: 'application/octet-stream';

    $url = $supabaseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($objectPath));

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Failed to initialize upload client.');
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $serviceKey,
        'apikey: ' . $serviceKey,
        'Content-Type: ' . $contentType,
        'x-upsert: true'
    ]);

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException('Upload failed: ' . $err);
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Upload failed (HTTP ' . $status . '): ' . (string)$resp);
    }

    return fh_supabase_storage_public_url($bucket, $objectPath);
}
