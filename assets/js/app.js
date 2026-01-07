// Basic client behaviors: cart count, fake fitness metrics, Google Sign-In placeholder
(function(){
  const BASE = (typeof window !== 'undefined' && window.__BASE_URL__) ? window.__BASE_URL__ : '';
  function hideSplash(){
    const el = document.getElementById('fhSplash');
    if (!el) return;
    el.classList.add('fh-hide');
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hideSplash, { once: true });
  } else {
    hideSplash();
  }
  window.addEventListener('pageshow', hideSplash);
  setTimeout(hideSplash, 2500);
  function setCartBadges(count){
    const val = String(Number(count || 0));
    const a = document.getElementById('navCartCount');
    const b = document.getElementById('navCartCountMobile');
    if (a) a.textContent = val;
    if (b) b.textContent = val;
  }
  function refreshCartBadges(){
    fetch(BASE + '/api/cart_count.php', { cache: 'no-store' })
      .then(r=>r.json())
      .then(d=>{ setCartBadges(d && d.count ? d.count : 0); })
      .catch(()=>{});
  }
  if (document.getElementById('navCartCount') || document.getElementById('navCartCountMobile')) {
    refreshCartBadges();
    window.addEventListener('pageshow', refreshCartBadges);
  }
  const streak = document.getElementById('streak');
  if (streak) {
    const s = Number(localStorage.getItem('fh_streak')||'0');
    const m = Number(localStorage.getItem('fh_minutes')||'0');
    const w = Number(localStorage.getItem('fh_workouts')||'0');
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
      else window.location.href = 'index.php';
    });
  }
  // Mobile menu
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', ()=>{
      const isHidden = mobileMenu.classList.contains('hidden');
      mobileMenu.classList.toggle('hidden');
      mobileMenuBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
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
