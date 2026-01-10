<?php

function fh_user_selected_programs(PDO $pdo, int $userId): array {
  $stmt = $pdo->prepare('SELECT plan_json FROM users WHERE id=?');
  $stmt->execute([$userId]);
  $plan = json_decode($stmt->fetchColumn() ?: 'null', true);

  $selected = [];
  if (is_array($plan) && !empty($plan['programs']) && is_array($plan['programs'])) {
    foreach ($plan['programs'] as $pid) {
      $pid = (string)$pid;
      if ($pid !== '') {
        $selected[] = $pid;
      }
    }
  }

  $selected = array_values(array_unique($selected));
  return $selected;
}

function fh_program_enabled(array $selected, string $programId): bool {
  if (empty($selected)) {
    return true;
  }
  return in_array($programId, $selected, true);
}

function fh_require_any_program(array $selected, array $allowedProgramIds): void {
  if (empty($selected)) {
    return;
  }

  foreach ($allowedProgramIds as $pid) {
    $pid = (string)$pid;
    if ($pid !== '' && in_array($pid, $selected, true)) {
      return;
    }
  }

  header('Location: index.php?page=customize', true, 302);
  exit;
}
