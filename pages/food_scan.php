<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
require_login();
$u = $_SESSION['user'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  // Default macros to 0 if empty; ensure non-negative
  $cal = isset($_POST['calories']) && $_POST['calories'] !== '' ? intval($_POST['calories']) : 0;
  $protein = isset($_POST['protein_g']) && $_POST['protein_g'] !== '' ? floatval($_POST['protein_g']) : 0.0;
  $carbs = isset($_POST['carbs_g']) && $_POST['carbs_g'] !== '' ? floatval($_POST['carbs_g']) : 0.0;
  $fat = isset($_POST['fat_g']) && $_POST['fat_g'] !== '' ? floatval($_POST['fat_g']) : 0.0;
  if ($cal < 0 || $protein < 0 || $carbs < 0 || $fat < 0) {
    $err = 'Macros must be non-negative.';
  }
  $photoPath = null;

  if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp'=>'.webp'];
    $mime = mime_content_type($_FILES['photo']['tmp_name']);
    $ext = $allowed[$mime] ?? null;
    if (!$ext) {
      $err = 'Unsupported image type.';
    } else {
      if (IS_VERCEL) {
        try {
          $bucket = getenv('SUPABASE_FOOD_BUCKET') ?: 'food';
          $key = 'food_' . $u['id'] . '_' . time() . '_' . bin2hex(random_bytes(6)) . $ext;
          $photoPath = supabase_storage_upload_tmpfile($bucket, $key, $_FILES['photo']['tmp_name'], $mime ?: 'application/octet-stream');
        } catch (Throwable $e) {
          $err = 'Failed to save uploaded file.';
          error_log('Food upload failed: ' . $e->getMessage());
        }
      } else {
        $dir = __DIR__ . '/../uploads/food';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $fname = 'food_' . $u['id'] . '_' . time() . $ext;
        $dest = $dir . '/' . $fname;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
          $photoPath = rtrim(BASE_URL, '/') . '/uploads/food/' . $fname;
        } else {
          $err = 'Failed to save uploaded file.';
        }
      }
    }
  }

  if (!$err) {
    $stmt = $pdo->prepare('INSERT INTO food_logs (user_id, title, photo_path, calories, protein_g, carbs_g, fat_g) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$u['id'], $title ?: 'Meal', $photoPath, $cal, $protein, $carbs, $fat]);
    header('Location: index.php?page=food_history');
    exit;
  }
}
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Food Scan</h2>
  <p class="text-neutral-400 mb-4">Capture a meal photo and log basic nutrition. On phones, this will open the camera.</p>
  <?php if (!empty($err)): ?><div class="mb-4 text-red-400"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="space-y-4 rounded-xl border border-neutral-800 bg-neutral-900 p-4">
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
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Calories</label>
        <input type="number" name="calories" min="0" step="1" required readonly class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Protein (g)</label>
        <input type="number" step="0.1" min="0" required readonly name="protein_g" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Carbs (g)</label>
        <input type="number" step="0.1" min="0" required readonly name="carbs_g" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-300 mb-1">Fat (g)</label>
        <input type="number" step="0.1" min="0" required readonly name="fat_g" class="w-full bg-neutral-900 border border-neutral-800 rounded px-3 py-2" />
      </div>
    </div>
    <button id="btnSave" type="submit" disabled class="px-4 py-2 rounded-lg bg-brand text-white disabled:opacity-60 disabled:cursor-not-allowed">Save</button>
  </form>
  <script>
  (function(){
    const msg = document.getElementById('detectMsg');
    const form = document.querySelector('form');
    if (!form || !msg) return;

    msg.textContent = 'Select or capture a photo to auto-detect nutrition.';

    const fileInput = form.querySelector('input[name="photo"]');
    const btnSave = document.getElementById('btnSave');
    const cal = form.querySelector('input[name="calories"]');
    const p = form.querySelector('input[name="protein_g"]');
    const c = form.querySelector('input[name="carbs_g"]');
    const f = form.querySelector('input[name="fat_g"]');
    const titleInput = form.querySelector('input[name="title"]');

    const setSaveEnabled = (enabled) => {
      if (!btnSave) return;
      btnSave.disabled = !enabled;
    };

    const clearValues = () => {
      if (cal) cal.value = '';
      if (p) p.value = '';
      if (c) c.value = '';
      if (f) f.value = '';
    };

    const detect = async () => {
      msg.textContent = '';
      setSaveEnabled(false);
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
        if (cal && typeof data.calories !== 'undefined') cal.value = Math.max(0, parseInt(data.calories||0,10));
        if (p && typeof data.protein_g !== 'undefined') p.value = Math.max(0, parseFloat(data.protein_g||0));
        if (c && typeof data.carbs_g !== 'undefined') c.value = Math.max(0, parseFloat(data.carbs_g||0));
        if (f && typeof data.fat_g !== 'undefined') f.value = Math.max(0, parseFloat(data.fat_g||0));

        const ok = !!(cal && cal.value !== '' && p && p.value !== '' && c && c.value !== '' && f && f.value !== '');
        if (!ok) {
          msg.textContent = 'Detection completed, but values were missing. Try another photo.';
          return;
        }

        msg.textContent = 'Nutrition detected. You can Save now.';
        setSaveEnabled(true);
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
