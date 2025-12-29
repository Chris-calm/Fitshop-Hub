// Basic client behaviors: cart count, fake fitness metrics, Google Sign-In placeholder
(function(){
  const navCart = document.getElementById('navCartCount');
  if (navCart) {
    fetch('/Health&Fitness/api/cart_count.php').then(r=>r.json()).then(d=>{ navCart.textContent = d.count ? d.count : ''; });
  }
  const streak = document.getElementById('streak');
  if (streak) {
    const s = Number(localStorage.getItem('fh_streak')||'3');
    const m = Number(localStorage.getItem('fh_minutes')||'120');
    const w = Number(localStorage.getItem('fh_workouts')||'5');
    streak.textContent=s; (document.getElementById('minutes')||{}).textContent=m; (document.getElementById('workouts')||{}).textContent=w;
  }
  const btn = document.getElementById('googleSignInBtn');
  const badge = document.getElementById('accountBadge');
  function showUser(u){ if(!badge) return; badge.classList.remove('hidden'); badge.innerHTML = `<img src="${u.picture}" class="w-6 h-6 rounded-full"/> <span class="text-sm">${u.name}</span>`; if(btn) btn.classList.add('hidden'); }
  if (window.localStorage.getItem('fh_user')) { showUser(JSON.parse(window.localStorage.getItem('fh_user'))); }
  if (btn) {
    btn.addEventListener('click', ()=>{
      // Placeholder for Google Identity Services; replace CLIENT_ID in auth/google.md
      const demoUser = { name:'Demo User', email:'demo@gmail.com', picture:'https://i.pravatar.cc/40' };
      window.localStorage.setItem('fh_user', JSON.stringify(demoUser));
      showUser(demoUser);
    });
  }
  // Back button
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', ()=>{
      if (window.history.length > 1) window.history.back();
      else window.location.href = '/Health&Fitness/index.php';
    });
  }
  // Password visibility toggles
  document.addEventListener('click', function(e){
    const t = e.target.closest('.pw-toggle');
    if (!t) return;
    const id = t.getAttribute('data-target');
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
  });
})();
