<?php

require_once __DIR__ . '/env.php';

function fh_is_https_request(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    return true;
  }
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    return true;
  }
  return false;
}

function fh_boot_session(): void {
  if (session_status() !== PHP_SESSION_NONE) {
    return;
  }

  $cookieParams = [
    'httponly' => true,
    'secure' => fh_is_https_request(),
    'samesite' => 'Lax',
    'path' => '/',
  ];

  if (defined('IS_VERCEL') && IS_VERCEL) {
    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl) {
      fh_use_pgsql_session_handler($databaseUrl);
    }
  }

  session_name('fh_session');
  session_set_cookie_params($cookieParams);
  session_start();
}

function fh_use_pgsql_session_handler(string $databaseUrl): void {
  $parts = parse_url($databaseUrl);
  $scheme = $parts['scheme'] ?? '';
  if ($scheme !== 'postgres' && $scheme !== 'postgresql') {
    return;
  }

  $host = $parts['host'] ?? '';
  $port = $parts['port'] ?? 5432;
  $db = isset($parts['path']) ? ltrim((string)$parts['path'], '/') : '';
  $user = $parts['user'] ?? '';
  $pass = $parts['pass'] ?? '';

  parse_str($parts['query'] ?? '', $query);
  $sslmode = $query['sslmode'] ?? 'require';

  $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, (string)$port, $db, $sslmode);

  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS public.php_sessions (\n      id text PRIMARY KEY,\n      data text NOT NULL,\n      expires_at timestamptz NOT NULL\n    )");

    $handler = new class($pdo) implements SessionHandlerInterface {
      private PDO $pdo;
      public function __construct(PDO $pdo) { $this->pdo = $pdo; }
      public function open(string $path, string $name): bool { return true; }
      public function close(): bool { return true; }
      public function read(string $id): string|false {
        $stmt = $this->pdo->prepare('SELECT data FROM public.php_sessions WHERE id = ? AND expires_at > NOW()');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return '';
        return (string)$row['data'];
      }
      public function write(string $id, string $data): bool {
        $ttl = (int)ini_get('session.gc_maxlifetime');
        if ($ttl <= 0) { $ttl = 1440; }
        $expiresAt = date('c', time() + $ttl);
        $stmt = $this->pdo->prepare('INSERT INTO public.php_sessions(id,data,expires_at) VALUES (?,?,?) ON CONFLICT (id) DO UPDATE SET data=EXCLUDED.data, expires_at=EXCLUDED.expires_at');
        return $stmt->execute([$id, $data, $expiresAt]);
      }
      public function destroy(string $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM public.php_sessions WHERE id = ?');
        $stmt->execute([$id]);
        return true;
      }
      public function gc(int $max_lifetime): int|false {
        $stmt = $this->pdo->prepare('DELETE FROM public.php_sessions WHERE expires_at <= NOW()');
        $stmt->execute();
        return $stmt->rowCount();
      }
    };

    session_set_save_handler($handler, true);
  } catch (Throwable $e) {
    // If session DB fails, fall back to default handler
    error_log('Session DB handler init failed: ' . $e->getMessage());
  }
}
