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
          <a href="index.php?page=profile" class="fh-btn fh-btn-ghost">Customize</a>
        </div>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <a href="index.php?page=choreography" class="fh-card p-4 border border-white/10 hover:border-white/15" style="background: rgba(255,255,255,.03);">
            <div class="font-semibold">Choreography</div>
            <div class="text-sm text-neutral-400">Dance-based workouts</div>
          </a>
          <a href="index.php?page=guides" class="fh-card p-4 border border-white/10 hover:border-white/15" style="background: rgba(255,255,255,.03);">
            <div class="font-semibold">Guides</div>
            <div class="text-sm text-neutral-400">Step-by-step routines</div>
          </a>
          <a href="index.php?page=gym" class="fh-card p-4 border border-white/10 hover:border-white/15" style="background: rgba(255,255,255,.03);">
            <div class="font-semibold">Gym Programs</div>
            <div class="text-sm text-neutral-400">Strength & cardio plans</div>
          </a>
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
