<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
require_login();
$u = $_SESSION['user'];

function fh_detect_upload_mime(array $file): string {
  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp !== '' && function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $m = finfo_file($fi, $tmp);
      $fi = null;
      if (is_string($m) && $m !== '') {
        return $m;
      }
    }
  }

  $m = (string)($file['type'] ?? '');
  if ($m !== '') {
    return $m;
  }

  $name = strtolower((string)($file['name'] ?? ''));
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
  if ($ext === 'png') return 'image/png';
  if ($ext === 'webp') return 'image/webp';
  return '';
}

$err = '';
$warn = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  error_log('FOOD_SCAN post:start user=' . (string)($u['id'] ?? '')); 
  $title = trim($_POST['title'] ?? '');
  $ingredients = trim($_POST['ingredients_text'] ?? '');
  $servingSize = trim($_POST['serving_size'] ?? '');
  $useSupabaseStorage = false;
  try {
    $useSupabaseStorage = (supabase_base_url() !== '') && ((getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '') !== '');
  } catch (Throwable $e) {
    $useSupabaseStorage = false;
    error_log('Food upload config invalid: ' . $e->getMessage());
  }
  // Default macros to 0 if empty; ensure non-negative
  $cal = isset($_POST['calories']) && $_POST['calories'] !== '' ? intval($_POST['calories']) : 0;
  $protein = isset($_POST['protein_g']) && $_POST['protein_g'] !== '' ? floatval($_POST['protein_g']) : 0.0;
  $carbs = isset($_POST['carbs_g']) && $_POST['carbs_g'] !== '' ? floatval($_POST['carbs_g']) : 0.0;
  $fat = isset($_POST['fat_g']) && $_POST['fat_g'] !== '' ? floatval($_POST['fat_g']) : 0.0;
  if ($cal < 0 || $protein < 0 || $carbs < 0 || $fat < 0) {
    $err = 'Macros must be non-negative.';
  }

  // Supabase schema uses numeric(6,2) for macros => max abs value is 9999.99
  $maxMacro = 9999.99;
  if (!$err) {
    if ($protein > $maxMacro || $carbs > $maxMacro || $fat > $maxMacro) {
      $warn = 'Some detected macros were too large and were clamped to 9999.99g. Please review before saving.';
    }
  }

  // Normalize for numeric(6,2)
  $protein = round(min($maxMacro, max(0.0, $protein)), 2);
  $carbs = round(min($maxMacro, max(0.0, $carbs)), 2);
  $fat = round(min($maxMacro, max(0.0, $fat)), 2);
  $photoPath = null;

  if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp'=>'.webp'];
    $mime = fh_detect_upload_mime($_FILES['photo']);
    $ext = $allowed[$mime] ?? null;
    if (!$ext) {
      $err = 'Unsupported image type.';
    } else {
      if ($useSupabaseStorage) {
        try {
          $bucket = getenv('SUPABASE_FOOD_BUCKET') ?: 'food';
          $key = 'food_' . $u['id'] . '_' . time() . '_' . bin2hex(random_bytes(6)) . $ext;
          $photoPath = supabase_storage_upload_tmpfile($bucket, $key, $_FILES['photo']['tmp_name'], $mime ?: 'application/octet-stream');
        } catch (Throwable $e) {
          $warn = 'Photo upload failed. Your meal can still be saved without a photo.';
          error_log('Food upload failed: ' . $e->getMessage());
          $photoPath = null;
        }
      } else {
        $dir = __DIR__ . '/../uploads/food';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $fname = 'food_' . $u['id'] . '_' . time() . $ext;
        $dest = $dir . '/' . $fname;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
          $photoPath = rtrim(BASE_URL, '/') . '/uploads/food/' . $fname;
        } else {
          $warn = 'Photo upload failed. Your meal can still be saved without a photo.';
          $photoPath = null;
        }
      }
    }
  }

  if (!$err) {
    try {
      error_log('FOOD_SCAN insert:start user=' . (string)($u['id'] ?? '') . ' has_photo=' . (!empty($photoPath) ? '1' : '0'));
      $stmt = $pdo->prepare('INSERT INTO food_logs (user_id, title, photo_path, ingredients_text, serving_size, calories, protein_g, carbs_g, fat_g) VALUES (?,?,?,?,?,?,?,?,?)');
      $stmt->execute([$u['id'], $title ?: 'Meal', $photoPath, $ingredients ?: null, $servingSize ?: null, $cal, $protein, $carbs, $fat]);
    } catch (Throwable $e) {
      // Backward-compatible insert if DB schema is not migrated yet
      $stmt = $pdo->prepare('INSERT INTO food_logs (user_id, title, photo_path, calories, protein_g, carbs_g, fat_g) VALUES (?,?,?,?,?,?,?)');
      $stmt->execute([$u['id'], $title ?: 'Meal', $photoPath, $cal, $protein, $carbs, $fat]);
    }
    header('Location: index.php?page=food_history');
    exit;
  }
}
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Food Scan</h2>
  <p class="text-neutral-400 mb-4">Capture a meal photo and log basic nutrition. On phones, this will open the camera.</p>
  <?php if (!empty($err)): ?><div class="mb-4 text-red-400"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if (!empty($warn)): ?><div class="mb-4 text-amber-300"><?= htmlspecialchars($warn) ?></div><?php endif; ?>
  <form id="foodScanForm" method="post" enctype="multipart/form-data" class="space-y-4 rounded-xl border border-neutral-800 bg-neutral-900 p-4">
    <div>
      <label class="block text-sm text-neutral-300 mb-1">Meal title</label>
      <input name="title" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" placeholder="e.g., Chicken rice bowl" />
    </div>
    <div>
      <label class="block text-sm text-neutral-300 mb-1">Photo</label>
      <input type="file" name="photo" accept="image/*" capture="environment" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      <div class="mt-2 flex items-center gap-2">
        <span id="detectMsg" class="text-sm text-neutral-400"></span>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Serving size (optional)</label>
        <input name="serving_size" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" placeholder="e.g., 1 cup (228g)" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Ingredients (optional)</label>
        <input name="ingredients_text" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" placeholder="Auto-filled when detectable" />
      </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Calories</label>
        <input type="number" name="calories" min="0" step="1" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Protein (g)</label>
        <input type="number" step="0.1" min="0" name="protein_g" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Carbs (g)</label>
        <input type="number" step="0.1" min="0" name="carbs_g" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Fat (g)</label>
        <input type="number" step="0.1" min="0" name="fat_g" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
    </div>
    <button id="btnSave" type="submit" class="px-4 py-2 rounded-lg bg-brand text-white">Save</button>
  </form>
  <script>
  (function(){
    const msg = document.getElementById('detectMsg');
    const form = document.getElementById('foodScanForm');
    if (!form || !msg) return;

    msg.textContent = 'Select or capture a photo to auto-detect nutrition.';

    const fileInput = form.querySelector('input[name="photo"]');
    const cal = form.querySelector('input[name="calories"]');
    const p = form.querySelector('input[name="protein_g"]');
    const c = form.querySelector('input[name="carbs_g"]');
    const f = form.querySelector('input[name="fat_g"]');
    const titleInput = form.querySelector('input[name="title"]');
    const ingredientsInput = form.querySelector('input[name="ingredients_text"]');
    const servingInput = form.querySelector('input[name="serving_size"]');

    const clearValues = () => {
      if (cal) cal.value = '';
      if (p) p.value = '';
      if (c) c.value = '';
      if (f) f.value = '';
    };

    const detect = async () => {
      msg.textContent = '';
      clearValues();

      if (!fileInput || !fileInput.files || fileInput.files.length === 0){
        msg.textContent = 'Please select or capture a photo first.';
        return;
      }

      const fd = new FormData();
      fd.append('photo', fileInput.files[0]);

      if (fileInput) fileInput.disabled = true;
      msg.textContent = 'Detecting...';

      try {
        const resp = await fetch('api/nutrition_detect.php', { method: 'POST', body: fd });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok){
          msg.textContent = data && data.error ? data.error : 'Detection failed';
          return;
        }

        if (titleInput && data.description) titleInput.value = data.description;
        if (servingInput && data.serving_size) servingInput.value = data.serving_size;
        if (ingredientsInput && data.ingredients) ingredientsInput.value = data.ingredients;
        if (cal && typeof data.calories !== 'undefined') cal.value = Math.max(0, parseInt(data.calories||0,10));
        if (p && typeof data.protein_g !== 'undefined') p.value = Math.max(0, parseFloat(data.protein_g||0));
        if (c && typeof data.carbs_g !== 'undefined') c.value = Math.max(0, parseFloat(data.carbs_g||0));
        if (f && typeof data.fat_g !== 'undefined') f.value = Math.max(0, parseFloat(data.fat_g||0));

        msg.textContent = 'Detected fields were filled. Review and Save.';
      } catch (e){
        msg.textContent = 'Network error during detection.';
      } finally {
        if (fileInput) fileInput.disabled = false;
      }
    };

    if (fileInput) {
      fileInput.addEventListener('change', () => { void detect(); });
    } else {
      msg.textContent = 'Photo input not found.';
    }
  })();
  </script>
</section>
