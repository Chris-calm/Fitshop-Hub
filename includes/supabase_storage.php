<?php
require_once __DIR__ . '/env.php';

function supabase_base_url() {
    $raw = trim(getenv('SUPABASE_URL') ?: '');
    if ($raw === '') {
        return '';
    }
    $raw = rtrim($raw, '/');
    // If user pasted a service endpoint (e.g. .../storage/v1 or .../rest/v1), strip it back to origin
    $raw = preg_replace('#/(storage|rest)/v1.*$#', '', $raw);
    // If still has a path, reduce to scheme+host
    $parts = parse_url($raw);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $raw;
    }
    return $parts['scheme'] . '://' . $parts['host'];
}

function supabase_storage_public_url($bucket, $path) {
    $base = supabase_base_url();
    if (!$base) {
        return null;
    }
    return $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));
}

function supabase_storage_upload_tmpfile($bucket, $path, $tmpFilePath, $contentType) {
    $base = supabase_base_url();
    $serviceKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';

    if (!$base || !$serviceKey) {
        throw new RuntimeException('Supabase Storage not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.');
    }

    $url = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));
    $payload = file_get_contents($tmpFilePath);

    $doRequest = function ($method) use ($url, $serviceKey, $contentType, $payload) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $serviceKey,
            'apikey: ' . $serviceKey,
            'Content-Type: ' . ($contentType ?: 'application/octet-stream'),
            'x-upsert: true',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ch = null;
        return [$resp, $err, $code];
    };

    [$resp, $err, $code] = $doRequest('PUT');
    if ($code === 405) {
        // Some environments only accept POST for object uploads
        [$resp, $err, $code] = $doRequest('POST');
    }

    if ($resp === false) {
        throw new RuntimeException('Supabase upload failed: ' . $err . ' | url=' . $url);
    }

    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Supabase upload failed (HTTP ' . $code . '): ' . $resp . ' | url=' . $url);
    }

    return supabase_storage_public_url($bucket, $path);
}
