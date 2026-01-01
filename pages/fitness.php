<section>
  <h2 class="text-2xl font-bold mb-4">Fitness Dashboard</h2>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
    <div class="rounded-2xl border border-neutral-800 bg-gradient-to-b from-neutral-900 to-neutral-950 p-6 flex flex-col items-center justify-center">
      <svg viewBox="0 0 120 120" class="w-56 h-56">
        <circle cx="60" cy="60" r="52" stroke="#262626" stroke-width="12" fill="none" />
        <circle id="stepsArc" cx="60" cy="60" r="52" stroke="#6366F1" stroke-width="12" fill="none" stroke-linecap="round" transform="rotate(-90 60 60)" stroke-dasharray="0 999" />
        <circle cx="60" cy="8" r="5" fill="#6366F1" />
        <text id="stepsToday" x="60" y="58" text-anchor="middle" fill="#e5e7eb" font-size="28" font-weight="700">0</text>
        <text id="stepsGoal" x="60" y="78" text-anchor="middle" fill="#9ca3af" font-size="10">of 10,000 steps</text>
      </svg>
      <p class="text-xs text-neutral-400 mt-2">Steps are automatically synced from the mobile app.</p>
    </div>
    <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-4">
      <div class="text-neutral-400">Streak</div>
      <div id="streak" class="text-4xl font-bold">0</div>
    </div>
    <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-4">
      <div class="text-neutral-400">Minutes this week</div>
      <div id="minutes" class="text-4xl font-bold">0</div>
    </div>
  </div>
  <h3 class="text-xl font-semibold mt-8 mb-3">Explore</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <a href="index.php?page=choreography" class="rounded-xl border border-neutral-800 bg-neutral-900 p-6 hover:border-brand/50">Choreography</a>
    <a href="index.php?page=guides" class="rounded-xl border border-neutral-800 bg-neutral-900 p-6 hover:border-brand/50">Guides</a>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 mt-6 gap-6">
    <a href="index.php?page=gym" class="rounded-xl border border-neutral-800 bg-neutral-900 p-6 hover:border-brand/50">Gym Programs</a>
    <a href="index.php?page=fitness_history" class="rounded-xl border border-neutral-800 bg-neutral-900 p-6 hover:border-brand/50">My Fitness History</a>
  </div>
  <div class="mt-8 text-sm text-neutral-400">For your personalized plan, visit <a class="text-brand" href="index.php?page=profile">your Profile</a>.</div>
</section>
<script>
// Steps ring logic
(function(){
  const arc = document.getElementById('stepsArc');
  const tToday = document.getElementById('stepsToday');
  const tGoal = document.getElementById('stepsGoal');
  const elStreak = document.getElementById('streak');
  const elMinutes = document.getElementById('minutes');
  const R = 52, C = 2*Math.PI*R;
  let goal = 10000; // default; could be loaded from server later
  tGoal.textContent = `of ${goal.toLocaleString()} steps`;
  function setProgress(steps){
    const pct = Math.max(0, Math.min(1, steps/goal));
    arc.setAttribute('stroke-dasharray', `${(pct*C).toFixed(1)} ${C.toFixed(1)}`);
    tToday.textContent = steps.toLocaleString();
  }
  setProgress(0);

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
      if (elStreak) elStreak.textContent = String(data.streak||0);
      if (elMinutes) elMinutes.textContent = String(data.minutes_week||0);
    } catch(e) {}
  }

  loadStats();
  setInterval(loadStats, 30_000);
})();
</script>
