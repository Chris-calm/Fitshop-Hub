<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$items = $content['guides'] ?? [];
$item = null;
foreach ($items as $it) { if (intval($it['id']) === $itemId) { $item = $it; break; } }
?>
<section>
  <?php if (!$item): ?>
    <h2 class="text-2xl font-bold mb-2">Guide not found</h2>
    <a class="text-brand" href="index.php?page=guides">← Back</a>
  <?php else: ?>
    <div class="mb-4"><a class="text-brand" href="index.php?page=guide_detail&id=<?= urlencode($item['id']) ?>">← Back</a></div>
    <h2 class="text-2xl font-bold mb-1"><?= htmlspecialchars($item['title']) ?></h2>
    <div class="text-sm text-neutral-400 mb-4">Level: <?= htmlspecialchars($item['level']) ?></div>
    <?php if (!empty($item['media_url'])): $m=$item['media_url']; $isVideo = preg_match('/\\.(mp4|webm|ogg)$/i',$m); ?>
      <div class="mb-4">
        <?php if ($isVideo): ?>
          <video src="<?= htmlspecialchars($m) ?>" class="w-full rounded" playsinline controls muted></video>
        <?php else: ?>
          <img src="<?= htmlspecialchars($m) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full rounded" />
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-4 mb-4 flex items-center gap-4">
      <div>
        <div class="text-neutral-400 text-sm">Elapsed</div>
        <div id="elapsed" class="text-2xl font-bold">00:00</div>
      </div>
      <div class="h-8 w-px bg-neutral-800"></div>
      <div>
        <div class="text-neutral-400 text-sm">Cue</div>
        <div id="cueLabel" class="text-2xl font-bold">-</div>
      </div>
      <div class="ml-auto flex gap-2">
        <button id="btnPrev" class="px-3 py-2 rounded-lg bg-neutral-800">Prev</button>
        <button id="btnNext" class="px-3 py-2 rounded-lg bg-neutral-800">Next</button>
        <button id="btnStart" class="px-3 py-2 rounded-lg bg-brand text-white">Start</button>
        <button id="btnPause" class="px-3 py-2 rounded-lg bg-neutral-800">Pause</button>
      </div>
    </div>

    <form method="post" action="index.php?page=guide_summary" class="space-y-4">
      <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>" />
      <input type="hidden" name="title" value="<?= htmlspecialchars($item['title']) ?>" />
      <input type="hidden" id="duration_sec" name="duration_sec" value="0" />
      <input type="hidden" id="completed_steps" name="completed_steps" value="0" />

      <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
        <label class="text-sm text-neutral-300">Notes</label>
        <textarea name="notes" class="w-full mt-2 bg-neutral-900 border border-neutral-800 rounded px-3 py-2" rows="3"></textarea>
      </div>

      <button type="submit" class="px-4 py-2 rounded-lg bg-brand text-white">Finish Guide</button>
    </form>

    <script>
    (function(){
      const cues = <?= json_encode($item['cues'] ?? []) ?>;
      let idx = 0; let elapsed = 0; let running=false; let t0=0; let timer=null; let completed=0;
      const elapsedEl = document.getElementById('elapsed');
      const cueLabel = document.getElementById('cueLabel');
      const durationInput = document.getElementById('duration_sec');
      const completedInput = document.getElementById('completed_steps');
      function fmt(sec){ sec=Math.max(0,sec|0); const m=(sec/60)|0, s=sec%60; return String(m).padStart(2,'0')+":"+String(s).padStart(2,'0'); }
      function render(){ cueLabel.textContent = cues[idx] ? (cues[idx].title || '-') : '-'; }
      function tick(){ const now=Date.now(); const sec=((now - t0)/1000)|0; elapsedEl.textContent=fmt(elapsed+sec); durationInput.value = (elapsed+sec); }
      function start(){ if(!running){ running=true; t0=Date.now(); timer=setInterval(tick,500);} }
      function pause(){ if(running){ running=false; elapsed = parseInt(durationInput.value||'0',10); clearInterval(timer); timer=null; } }
      document.getElementById('btnStart').addEventListener('click', start);
      document.getElementById('btnPause').addEventListener('click', pause);
      document.getElementById('btnPrev').addEventListener('click', ()=>{ if(idx>0){ idx--; render(); } });
      document.getElementById('btnNext').addEventListener('click', ()=>{ if(idx < cues.length-1){ idx++; completed++; completedInput.value=completed; render(); } });
      render();
    })();
    </script>
  <?php endif; ?>
</section>
