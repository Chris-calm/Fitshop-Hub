<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];
$programId = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$items = $content['gym'] ?? [];
$program = null;
foreach ($items as $it) { if (intval($it['id']) === $programId) { $program = $it; break; } }
?>
<section>
  <?php if (!$program): ?>
    <h2 class="text-2xl font-bold mb-2">Program not found</h2>
    <a class="text-brand" href="index.php?page=gym">← Back</a>
  <?php else: ?>
    <div class="mb-4"><a class="text-brand" href="index.php?page=gym_detail&id=<?= urlencode($program['id']) ?>">← Back</a></div>
    <h2 class="text-2xl font-bold mb-1"><?= htmlspecialchars($program['title']) ?></h2>
    <div class="text-sm text-neutral-400 mb-4">Level: <?= htmlspecialchars($program['level']) ?></div>

    <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-4 mb-4 flex items-center gap-4">
      <div>
        <div class="text-neutral-400 text-sm">Elapsed</div>
        <div id="elapsed" class="text-2xl font-bold">00:00</div>
      </div>
      <div class="h-8 w-px bg-neutral-800"></div>
      <div>
        <div class="text-neutral-400 text-sm">Rest</div>
        <div id="rest" class="text-2xl font-bold">00:00</div>
      </div>
      <div class="ml-auto flex gap-2">
        <button id="btnStart" class="px-3 py-2 rounded-lg bg-brand text-white">Start</button>
        <button id="btnPause" class="px-3 py-2 rounded-lg bg-neutral-800">Pause</button>
        <button id="btnReset" class="px-3 py-2 rounded-lg bg-neutral-800">Reset</button>
      </div>
    </div>

    <form method="post" action="index.php?page=gym_summary" class="space-y-6">
      <input type="hidden" name="program_id" value="<?= htmlspecialchars($program['id']) ?>" />
      <input type="hidden" name="program_title" value="<?= htmlspecialchars($program['title']) ?>" />
      <input type="hidden" id="total_duration_sec" name="total_duration_sec" value="0" />

      <?php $exerciseIndex = 0; foreach (($program['exercises'] ?? []) as $ex): $exerciseIndex++; ?>
        <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
          <div class="flex items-center justify-between mb-2">
            <div class="font-semibold"><?= htmlspecialchars($ex['name']) ?></div>
            <div class="text-sm text-neutral-400">Target: <?= intval($ex['sets']) ?> × <?= intval($ex['reps']) ?> · Rest <?= intval($ex['rest_sec']) ?>s</div>
          </div>
          <?php if (!empty($ex['media_url'])): 
            $m=$ex['media_url']; 
            $isVideo = preg_match('/\\.(mp4|webm|ogg)$/i',$m);
            $isYouTube = preg_match('/(youtube\\.com\\/watch\\?v=|youtu\\.be\\/)([A-Za-z0-9_-]+)/i',$m, $ym);
            $isVimeo = preg_match('/vimeo\\.com\\/(\\d+)/i', $m, $vm);
            $ytId = $isYouTube ? (end($ym)) : null;
            $vimeoId = $isVimeo ? $vm[1] : null;
          ?>
            <div class="mb-3">
              <?php if ($isYouTube && $ytId): ?>
                <div class="aspect-video w-full rounded overflow-hidden">
                  <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>
              <?php elseif ($isVimeo && $vimeoId): ?>
                <div class="aspect-video w-full rounded overflow-hidden">
                  <iframe class="w-full h-full" src="https://player.vimeo.com/video/<?= htmlspecialchars($vimeoId) ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                </div>
              <?php elseif ($isVideo): ?>
                <video src="<?= htmlspecialchars($m) ?>" class="w-full rounded" playsinline controls muted></video>
              <?php else: ?>
                <img src="<?= htmlspecialchars($m) ?>" alt="<?= htmlspecialchars($ex['name']) ?>" class="w-full rounded" onerror="this.style.display='none'" />
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="overflow-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-neutral-400">
                  <th class="text-left py-2">Set</th>
                  <th class="text-left">Reps</th>
                  <th class="text-left">Weight (kg)</th>
                  <th class="text-left">RPE</th>
                  <th class="text-left">Rest (s)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php for ($s=1; $s<=intval($ex['sets']); $s++): ?>
                <tr>
                  <td class="py-2">#<?= $s ?></td>
                  <td><input type="number" name="sets[<?= $exerciseIndex ?>][<?= $s ?>][reps]" class="w-24 bg-neutral-900 border border-neutral-800 rounded px-2 py-1" value="<?= intval($ex['reps']) ?>" /></td>
                  <td><input type="number" step="0.5" name="sets[<?= $exerciseIndex ?>][<?= $s ?>][weight]" class="w-28 bg-neutral-900 border border-neutral-800 rounded px-2 py-1" /></td>
                  <td><input type="number" step="0.5" min="1" max="10" name="sets[<?= $exerciseIndex ?>][<?= $s ?>][rpe]" class="w-20 bg-neutral-900 border border-neutral-800 rounded px-2 py-1" /></td>
                  <td><input type="number" name="sets[<?= $exerciseIndex ?>][<?= $s ?>][rest]" class="w-24 bg-neutral-900 border border-neutral-800 rounded px-2 py-1" value="<?= intval($ex['rest_sec']) ?>" /></td>
                  <td>
                    <button type="button" class="px-2 py-1 text-xs rounded bg-neutral-800 start-rest" data-rest="<?= intval($ex['rest_sec']) ?>">Start Rest</button>
                  </td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <input type="hidden" name="exercise_names[<?= $exerciseIndex ?>]" value="<?= htmlspecialchars($ex['name']) ?>" />
          <input type="hidden" name="exercise_order[<?= $exerciseIndex ?>]" value="<?= $exerciseIndex ?>" />
        </div>
      <?php endforeach; ?>

      <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
        <label class="text-sm text-neutral-300">Session notes</label>
        <textarea name="notes" class="w-full mt-2 bg-neutral-900 border border-neutral-800 rounded px-3 py-2" rows="3"></textarea>
      </div>

      <button type="submit" class="px-4 py-2 rounded-lg bg-brand text-white">Finish Workout</button>
    </form>

    <script>
    (function(){
      let started = false, paused = false, t0 = 0, acc = 0, timer = null, restTimer = null, restRemaining = 0;
      const elapsedEl = document.getElementById('elapsed');
      const restEl = document.getElementById('rest');
      const btnStart = document.getElementById('btnStart');
      const btnPause = document.getElementById('btnPause');
      const btnReset = document.getElementById('btnReset');
      const totalInput = document.getElementById('total_duration_sec');
      function fmt(sec){ sec=Math.max(0,sec|0); const m=(sec/60)|0, s=sec%60; return String(m).padStart(2,'0')+":"+String(s).padStart(2,'0'); }
      function tick(){ const now=Date.now(); const sec=((now - t0)/1000)|0; elapsedEl.textContent = fmt((acc+sec)); totalInput.value = (acc+sec); }
      btnStart.addEventListener('click', ()=>{
        if (!started || paused){ started = true; paused=false; t0=Date.now(); if(!timer) timer=setInterval(tick, 500); }
      });
      btnPause.addEventListener('click', ()=>{
        if (started && !paused){ paused=true; acc = parseInt(totalInput.value||'0',10); clearInterval(timer); timer=null; }
      });
      btnReset.addEventListener('click', ()=>{ started=false; paused=false; acc=0; totalInput.value=0; elapsedEl.textContent=fmt(0); if(timer){clearInterval(timer); timer=null;} });
      document.querySelectorAll('.start-rest').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          restRemaining = parseInt(btn.dataset.rest||'0',10);
          if (restTimer) clearInterval(restTimer);
          restEl.textContent = fmt(restRemaining);
          restTimer = setInterval(()=>{
            restRemaining--; restEl.textContent = fmt(restRemaining);
            if (restRemaining<=0){ clearInterval(restTimer); restTimer=null; }
          },1000);
        });
      });
    })();
    </script>
  <?php endif; ?>
</section>
