<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();

$u = $_SESSION['user'];
$stmt = $pdo->prepare('SELECT plan_json FROM users WHERE id=?');
$stmt->execute([(int)$u['id']]);
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

// If not set (older users), show all.
$filterEnabled = !empty($selected);

$tiles = [
  'choreography' => [
    'href' => 'index.php?page=choreography',
    'title' => 'Choreography',
    'desc' => 'Dance-based workouts',
  ],
  'guides' => [
    'href' => 'index.php?page=guides',
    'title' => 'Guides',
    'desc' => 'Step-by-step routines',
  ],
  'strength' => [
    'href' => 'index.php?page=gym',
    'title' => 'Strength / Gym',
    'desc' => 'Plans for strength and muscle',
  ],
  'cardio' => [
    'href' => 'index.php?page=gym',
    'title' => 'Cardio',
    'desc' => 'Endurance and conditioning plans',
  ],
  'nutrition' => [
    'href' => 'index.php?page=food_scan',
    'title' => 'Nutrition Tracking',
    'desc' => 'Log meals and track macros',
  ],
  'recovery' => [
    'href' => 'index.php?page=guides',
    'title' => 'Recovery / Mobility',
    'desc' => 'Stretching and mobility guides',
  ],
  // steps is already the dashboard itself; we don't add a tile.
];

$exploreOrder = ['choreography','guides','strength','cardio','nutrition','recovery'];
?>

<section>
  <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
    <div>
      <h2 class="text-2xl font-bold">Fitness Dashboard</h2>
      <div class="text-sm text-neutral-400">Track your steps, streak, and weekly minutes.</div>
    </div>
    <div class="flex items-center gap-2">
      <a href="index.php?page=fitness_history" class="fh-btn fh-btn-ghost">History</a>
      <a href="index.php?page=profile" class="fh-btn fh-btn-primary">Goals</a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
    <div class="fh-card p-6 lg:col-span-5 flex flex-col items-center justify-center relative overflow-hidden">
      <div class="absolute -inset-20 pointer-events-none" style="background: radial-gradient(380px 260px at 50% 45%, rgb(var(--fh-brand-rgb) / .18), transparent 60%), radial-gradient(320px 240px at 35% 60%, rgb(var(--fh-accent-rgb) / .12), transparent 62%);"></div>
      <svg viewBox="0 0 140 140" class="w-60 h-60 relative" aria-label="Steps progress ring">
        <defs>
          <linearGradient id="fhStepsGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="rgb(var(--fh-brand-rgb))" stop-opacity="1"/>
            <stop offset="100%" stop-color="rgb(var(--fh-accent-rgb))" stop-opacity="1"/>
          </linearGradient>
          <filter id="fhGlow" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur stdDeviation="3" result="b"/>
            <feMerge>
              <feMergeNode in="b"/>
              <feMergeNode in="SourceGraphic"/>
            </feMerge>
          </filter>
        </defs>
        <circle cx="70" cy="70" r="56" stroke="rgba(255,255,255,.12)" stroke-width="14" fill="none" />
        <circle id="stepsArc" cx="70" cy="70" r="56" stroke="url(#fhStepsGrad)" stroke-width="14" fill="none" stroke-linecap="round" transform="rotate(-90 70 70)" stroke-dasharray="0 999" filter="url(#fhGlow)" />
        <text id="stepsToday" x="70" y="72" text-anchor="middle" fill="rgba(255,255,255,.92)" font-size="30" font-weight="800">0</text>
        <text id="stepsGoal" x="70" y="96" text-anchor="middle" fill="rgba(255,255,255,.58)" font-size="11">of 10,000 steps</text>
      </svg>
      <div id="stepsSync" class="relative mt-3 text-xs text-neutral-400">Steps are synced from the mobile app.</div>
    </div>

    <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div class="fh-card p-5 relative overflow-hidden">
        <div class="absolute -inset-16 pointer-events-none" style="background: radial-gradient(320px 220px at 20% 20%, rgb(var(--fh-brand-rgb) / .14), transparent 62%);"></div>
        <div class="relative text-sm text-neutral-400">Streak</div>
        <div class="relative mt-1 flex items-end justify-between">
          <div id="streak" class="text-5xl font-extrabold leading-none">0</div>
          <div class="text-sm text-neutral-400">days</div>
        </div>
        <div class="relative mt-4 h-2 rounded-full overflow-hidden" style="background: rgba(255,255,255,.08);">
          <div id="streakBar" class="h-full rounded-full" style="width:0%; background: linear-gradient(90deg, rgb(var(--fh-brand-rgb) / .8), rgb(var(--fh-accent-rgb) / .7));"></div>
        </div>
        <div class="relative mt-2 text-xs text-neutral-400">Consistency is the secret.</div>
      </div>
      <div class="fh-card p-5 relative overflow-hidden">
        <div class="absolute -inset-16 pointer-events-none" style="background: radial-gradient(320px 220px at 80% 25%, rgb(var(--fh-accent-rgb) / .14), transparent 62%);"></div>
        <div class="relative text-sm text-neutral-400">Minutes this week</div>
        <div class="relative mt-1 flex items-end justify-between">
          <div id="minutes" class="text-5xl font-extrabold leading-none">0</div>
          <div class="text-sm text-neutral-400">min</div>
        </div>
        <div class="relative mt-4 h-2 rounded-full overflow-hidden" style="background: rgba(255,255,255,.08);">
          <div id="minutesBar" class="h-full rounded-full" style="width:0%; background: linear-gradient(90deg, rgb(var(--fh-accent-rgb) / .8), rgb(var(--fh-brand-rgb) / .7));"></div>
        </div>
        <div class="relative mt-2 text-xs text-neutral-400">Aim for 150+ minutes per week.</div>
      </div>

      <div class="fh-card p-5 sm:col-span-2">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm text-neutral-400">Explore</div>
            <div class="font-semibold">Pick a workout style</div>
          </div>
          <a href="index.php?page=customize" class="fh-btn fh-btn-ghost">Customize</a>
        </div>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <?php
            $rendered = 0;
            foreach ($exploreOrder as $pid) {
              if (!isset($tiles[$pid])) continue;
              if ($filterEnabled && !in_array($pid, $selected, true)) continue;
              $t = $tiles[$pid];
              $rendered++;
              ?>
              <a href="<?= htmlspecialchars($t['href']) ?>" class="fh-card p-4 border border-white/10 hover:border-white/15" style="background: rgba(255,255,255,.03);">
                <div class="font-semibold"><?= htmlspecialchars($t['title']) ?></div>
                <div class="text-sm text-neutral-400"><?= htmlspecialchars($t['desc']) ?></div>
              </a>
              <?php
            }
          ?>

          <?php if ($rendered === 0): ?>
            <div class="fh-card p-4 border border-white/10" style="background: rgba(255,255,255,.03);">
              <div class="font-semibold">No programs selected</div>
              <div class="text-sm text-neutral-400">Choose your programs in Customize to see them here.</div>
              <div class="mt-3"><a href="index.php?page=customize" class="fh-btn fh-btn-primary">Open Customize</a></div>
            </div>
          <?php endif; ?>

          <a href="index.php?page=fitness_history" class="fh-card p-4 border border-white/10 hover:border-white/15" style="background: rgba(255,255,255,.03);">
            <div class="font-semibold">My Fitness History</div>
            <div class="text-sm text-neutral-400">Sessions & activity logs</div>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-8 text-sm text-neutral-400">For your personalized plan, visit <a class="text-brand" href="index.php?page=profile">your Profile</a>.</div>
</section>
<script>
// Steps ring logic
(function(){
  const arc = document.getElementById('stepsArc');
  const tToday = document.getElementById('stepsToday');
  const tGoal = document.getElementById('stepsGoal');
  const tSync = document.getElementById('stepsSync');
  const elStreak = document.getElementById('streak');
  const elMinutes = document.getElementById('minutes');
  const elStreakBar = document.getElementById('streakBar');
  const elMinutesBar = document.getElementById('minutesBar');
  const R = 56, C = 2*Math.PI*R;
  let goal = 10000; // default; could be loaded from server later
  tGoal.textContent = `of ${goal.toLocaleString()} steps`;
  function setProgress(steps){
    const pct = Math.max(0, Math.min(1, steps/goal));
    arc.setAttribute('stroke-dasharray', `${(pct*C).toFixed(1)} ${C.toFixed(1)}`);
    tToday.textContent = steps.toLocaleString();
  }
  setProgress(0);

  function setBar(el, pct){
    if (!el) return;
    const v = Math.max(0, Math.min(1, Number(pct || 0)));
    el.style.width = `${Math.round(v*100)}%`;
  }

  async function loadStats(){
    try {
      const url = `index.php?page=api_fitness_stats&t=${Date.now()}`;
      const r = await fetch(url, { headers: { 'Cache-Control': 'no-cache' } });
      const data = r.ok ? await r.json() : null;
      if (!data || !data.ok) return;
      goal = Math.max(1, parseInt(data.steps_goal||goal,10));
      tGoal.textContent = `of ${goal.toLocaleString()} steps`;
      const today = Math.max(0, parseInt(data.today_steps||0,10));
      setProgress(today);
      const syncing = !!data.syncing;
      if (tSync) tSync.textContent = syncing ? 'Syncing steps from the mobile app…' : 'Steps are synced from the mobile app.';

      const streak = Math.max(0, parseInt(data.streak||0,10));
      const minutes = Math.max(0, parseInt(data.minutes_week||0,10));
      if (elStreak) elStreak.textContent = String(streak);
      if (elMinutes) elMinutes.textContent = String(minutes);
      setBar(elStreakBar, streak / 14);
      setBar(elMinutesBar, minutes / 150);
    } catch(e) {}
  }

  loadStats();
  setInterval(loadStats, 30_000);
})();
</script>
