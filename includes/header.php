<!doctype html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Fitshop Hub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = { theme: { extend: { colors: { brand: { DEFAULT: 'rgb(var(--fh-brand-rgb) / <alpha-value>)' } } } } };
  </script>
  <script>
    window.__BASE_URL__ = "<?= defined('BASE_URL') ? htmlspecialchars(BASE_URL, ENT_QUOTES) : '' ?>";
  </script>
  <script>
    (function(){
      function enc(s){
        return encodeURIComponent(s)
          .replace(/%20/g, ' ')
          .replace(/%3D/g, '=')
          .replace(/%3A/g, ':')
          .replace(/%2F/g, '/')
          .replace(/%2C/g, ',');
      }
      function svgUrl(stroke, strokeWidth, dotFill){
        const svg = "<svg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'>" +
          "<polyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='" + stroke + "' stroke-width='" + strokeWidth + "' stroke-linecap='round' stroke-linejoin='round'/>" +
          "<circle cx='12' cy='40' r='7' fill='" + dotFill + "'/>" +
          "<circle cx='200' cy='40' r='7' fill='" + dotFill + "'/>" +
          "</svg>";
        return "url(\"data:image/svg+xml," + enc(svg) + "\")";
      }

      const theme = (function(){
        try { return localStorage.getItem('fh_theme') || 'sporty'; } catch(e){ return 'sporty'; }
      })();

      const themes = {
        sporty: {
          bg0: '#05070b',
          bg1: '#070a12',
          brandRgb: '99 102 241',
          accentRgb: '34 211 238',
          ecgA: svgUrl('rgba(99,102,241,0.70)', 6, 'rgba(99,102,241,0.55)'),
          ecgB: svgUrl('rgba(34,211,238,0.50)', 4, 'rgba(34,211,238,0.40)'),
          ecgA2: svgUrl('rgba(99,102,241,0.55)', 6, 'rgba(99,102,241,0.38)'),
          ecgB2: svgUrl('rgba(34,211,238,0.38)', 4, 'rgba(34,211,238,0.28)'),
          ecgA3: svgUrl('rgba(99,102,241,0.78)', 6, 'rgba(99,102,241,0.62)'),
          ecgB3: svgUrl('rgba(34,211,238,0.56)', 4, 'rgba(34,211,238,0.46)'),
          ecgA4: svgUrl('rgba(99,102,241,0.62)', 6, 'rgba(99,102,241,0.46)'),
          ecgB4: svgUrl('rgba(34,211,238,0.44)', 4, 'rgba(34,211,238,0.34)')
        },
        calm: {
          bg0: '#06070c',
          bg1: '#0a0e18',
          brandRgb: '56 189 248',
          accentRgb: '99 102 241',
          ecgA: svgUrl('rgba(56,189,248,0.62)', 6, 'rgba(56,189,248,0.48)'),
          ecgB: svgUrl('rgba(99,102,241,0.36)', 4, 'rgba(99,102,241,0.28)'),
          ecgA2: svgUrl('rgba(56,189,248,0.46)', 6, 'rgba(56,189,248,0.32)'),
          ecgB2: svgUrl('rgba(99,102,241,0.28)', 4, 'rgba(99,102,241,0.20)'),
          ecgA3: svgUrl('rgba(56,189,248,0.70)', 6, 'rgba(56,189,248,0.54)'),
          ecgB3: svgUrl('rgba(99,102,241,0.44)', 4, 'rgba(99,102,241,0.34)'),
          ecgA4: svgUrl('rgba(56,189,248,0.54)', 6, 'rgba(56,189,248,0.40)'),
          ecgB4: svgUrl('rgba(99,102,241,0.34)', 4, 'rgba(99,102,241,0.26)')
        },
        contrast: {
          bg0: '#000000',
          bg1: '#0b0b0b',
          brandRgb: '255 255 255',
          accentRgb: '255 215 0',
          ecgA: svgUrl('rgba(255,255,255,0.78)', 6, 'rgba(255,255,255,0.70)'),
          ecgB: svgUrl('rgba(255,215,0,0.60)', 4, 'rgba(255,215,0,0.54)'),
          ecgA2: svgUrl('rgba(255,255,255,0.55)', 6, 'rgba(255,255,255,0.46)'),
          ecgB2: svgUrl('rgba(255,215,0,0.42)', 4, 'rgba(255,215,0,0.36)'),
          ecgA3: svgUrl('rgba(255,255,255,0.85)', 6, 'rgba(255,255,255,0.78)'),
          ecgB3: svgUrl('rgba(255,215,0,0.66)', 4, 'rgba(255,215,0,0.60)'),
          ecgA4: svgUrl('rgba(255,255,255,0.66)', 6, 'rgba(255,255,255,0.56)'),
          ecgB4: svgUrl('rgba(255,215,0,0.50)', 4, 'rgba(255,215,0,0.44)')
        }
      };

      const t = themes[theme] || themes.sporty;
      const r = document.documentElement;
      r.style.setProperty('--fh-bg-0', t.bg0);
      r.style.setProperty('--fh-bg-1', t.bg1);
      r.style.setProperty('--fh-brand-rgb', t.brandRgb);
      r.style.setProperty('--fh-accent-rgb', t.accentRgb);
      r.style.setProperty('--fh-ecg-a', t.ecgA);
      r.style.setProperty('--fh-ecg-b', t.ecgB);
      r.style.setProperty('--fh-ecg-a2', t.ecgA2);
      r.style.setProperty('--fh-ecg-b2', t.ecgB2);
      r.style.setProperty('--fh-ecg-a3', t.ecgA3);
      r.style.setProperty('--fh-ecg-b3', t.ecgB3);
      r.style.setProperty('--fh-ecg-a4', t.ecgA4);
      r.style.setProperty('--fh-ecg-b4', t.ecgB4);
    })();
  </script>
  <style>
    :root{
      --fh-bg-0:#05070b;
      --fh-bg-1:#070a12;
      --fh-brand-rgb: 99 102 241;
      --fh-accent-rgb: 34 211 238;
      --fh-grain: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140' viewBox='0 0 140 140'%3E%3Cg fill='white' fill-opacity='.08'%3E%3Ccircle cx='9' cy='12' r='1'/%3E%3Ccircle cx='40' cy='18' r='1'/%3E%3Ccircle cx='70' cy='10' r='1'/%3E%3Ccircle cx='110' cy='22' r='1'/%3E%3Ccircle cx='18' cy='46' r='1'/%3E%3Ccircle cx='56' cy='54' r='1'/%3E%3Ccircle cx='98' cy='52' r='1'/%3E%3Ccircle cx='126' cy='60' r='1'/%3E%3Ccircle cx='12' cy='88' r='1'/%3E%3Ccircle cx='44' cy='96' r='1'/%3E%3Ccircle cx='84' cy='92' r='1'/%3E%3Ccircle cx='118' cy='104' r='1'/%3E%3Ccircle cx='26' cy='126' r='1'/%3E%3Ccircle cx='66' cy='128' r='1'/%3E%3Ccircle cx='104' cy='122' r='1'/%3E%3Ccircle cx='132' cy='130' r='1'/%3E%3C/g%3E%3C/svg%3E");
      --fh-ecg-a: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(99,102,241,0.70)' stroke-width='6' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='7' fill='rgba(99,102,241,0.55)'/%3E%3Ccircle cx='200' cy='40' r='7' fill='rgba(99,102,241,0.55)'/%3E%3C/svg%3E");
      --fh-ecg-b: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(34,211,238,0.50)' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='6' fill='rgba(34,211,238,0.40)'/%3E%3Ccircle cx='200' cy='40' r='6' fill='rgba(34,211,238,0.40)'/%3E%3C/svg%3E");
      --fh-ecg-a2: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(99,102,241,0.55)' stroke-width='6' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='7' fill='rgba(99,102,241,0.38)'/%3E%3Ccircle cx='200' cy='40' r='7' fill='rgba(99,102,241,0.38)'/%3E%3C/svg%3E");
      --fh-ecg-b2: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(34,211,238,0.38)' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='6' fill='rgba(34,211,238,0.28)'/%3E%3Ccircle cx='200' cy='40' r='6' fill='rgba(34,211,238,0.28)'/%3E%3C/svg%3E");
      --fh-ecg-a3: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(99,102,241,0.78)' stroke-width='6' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='7' fill='rgba(99,102,241,0.62)'/%3E%3Ccircle cx='200' cy='40' r='7' fill='rgba(99,102,241,0.62)'/%3E%3C/svg%3E");
      --fh-ecg-b3: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(34,211,238,0.56)' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='6' fill='rgba(34,211,238,0.46)'/%3E%3Ccircle cx='200' cy='40' r='6' fill='rgba(34,211,238,0.46)'/%3E%3C/svg%3E");
      --fh-ecg-a4: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(99,102,241,0.62)' stroke-width='6' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='7' fill='rgba(99,102,241,0.46)'/%3E%3Ccircle cx='200' cy='40' r='7' fill='rgba(99,102,241,0.46)'/%3E%3C/svg%3E");
      --fh-ecg-b4: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='80' viewBox='0 0 220 80'%3E%3Cpolyline points='12,40 60,40 70,40 78,20 86,60 94,40 116,40 126,50 136,30 146,40 170,40 200,40' fill='none' stroke='rgba(34,211,238,0.44)' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='12' cy='40' r='6' fill='rgba(34,211,238,0.34)'/%3E%3Ccircle cx='200' cy='40' r='6' fill='rgba(34,211,238,0.34)'/%3E%3C/svg%3E");
    }
    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background:
        radial-gradient(900px 500px at 15% 20%, rgb(var(--fh-brand-rgb) / .14), transparent 60%),
        radial-gradient(700px 420px at 85% 10%, rgb(var(--fh-accent-rgb) / .10), transparent 60%),
        linear-gradient(180deg, var(--fh-bg-0), var(--fh-bg-1));
    }
    body::before{
      content:'';
      position:fixed;
      inset:-20vh -20vw;
      pointer-events:none;
      z-index:-1;
      background-image: var(--fh-ecg-a), var(--fh-ecg-b), var(--fh-ecg-a2), var(--fh-ecg-b2);
      background-repeat: repeat;
      background-size: 200px 110px;
      background-position: 0 0, 0 0, 100px 55px, 100px 55px;
      opacity:.16;
      filter: drop-shadow(0 0 10px rgb(var(--fh-brand-rgb) / .10)) drop-shadow(0 0 16px rgb(var(--fh-accent-rgb) / .08));
      transform: translateZ(0) rotate(140deg);
    }

    body::after{
      content:'';
      position:fixed;
      inset:-20vh -20vw;
      pointer-events:none;
      z-index:-2;
      background-image: var(--fh-grain);
      background-repeat: repeat;
      background-size: 140px 140px;
      background-position: 0 0;
      opacity:.06;
      mix-blend-mode: overlay;
    }
    #fhSplash{
      position:fixed;
      inset:0;
      z-index:1000;
      display:flex;
      align-items:center;
      justify-content:center;
      background:
        radial-gradient(900px 500px at 15% 20%, rgb(var(--fh-brand-rgb) / .20), transparent 60%),
        radial-gradient(700px 420px at 85% 10%, rgb(var(--fh-accent-rgb) / .16), transparent 60%),
        linear-gradient(180deg, var(--fh-bg-0), var(--fh-bg-1));
    }
    #fhSplash::before{
      content:'';
      position:absolute;
      inset:-20vh -20vw;
      background-image: var(--fh-ecg-a3), var(--fh-ecg-b3), var(--fh-ecg-a4), var(--fh-ecg-b4);
      background-repeat: repeat;
      background-size: 200px 110px;
      background-position: 0 0, 0 0, 100px 55px, 100px 55px;
      opacity:.24;
      filter: drop-shadow(0 0 12px rgb(var(--fh-brand-rgb) / .14)) drop-shadow(0 0 18px rgb(var(--fh-accent-rgb) / .12));
      transform: translateZ(0) rotate(140deg);
      pointer-events:none;
    }

    #fhSplash::after{
      content:'';
      position:absolute;
      inset:-20vh -20vw;
      background-image: var(--fh-grain);
      background-repeat: repeat;
      background-size: 140px 140px;
      background-position: 0 0;
      opacity:.08;
      mix-blend-mode: overlay;
      pointer-events:none;
    }
    #fhSplash .fh-splash-card{
      position:relative;
      text-align:center;
      padding:24px 28px;
      border-radius:16px;
      border:1px solid rgba(255,255,255,.08);
      background:rgba(10,12,18,.55);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      box-shadow: 0 20px 60px rgba(0,0,0,.45);
      min-width: 240px;
    }
    #fhSplash .fh-logo{
      font-weight:800;
      letter-spacing:.2px;
      font-size:28px;
      line-height:1.1;
    }
    #fhSplash .fh-sub{
      margin-top:8px;
      font-size:12px;
      color: rgba(255,255,255,.65);
    }
    #fhSplash .fh-loader{
      margin:16px auto 0;
      width:56px;
      height:6px;
      border-radius:999px;
      background: rgba(255,255,255,.10);
      overflow:hidden;
    }
    #fhSplash .fh-loader > span{
      display:block;
      height:100%;
      width:40%;
      border-radius:999px;
      background: linear-gradient(90deg, rgb(var(--fh-brand-rgb) / 0.95), rgb(var(--fh-accent-rgb) / 0.95));
      animation: fhLoad 1.05s ease-in-out infinite;
    }
    @keyframes fhLoad{
      0%{ transform: translateX(-120%); opacity:.85; }
      50%{ opacity:1; }
      100%{ transform: translateX(260%); opacity:.85; }
    }
    #fhSplash.fh-hide{
      opacity:0;
      visibility:hidden;
      transition: opacity .28s ease, visibility .28s ease;
    }
    @media (prefers-reduced-motion: reduce){
      #fhSplash .fh-loader > span{ animation:none; width:100%; }
      #fhSplash.fh-hide{ transition:none; }
    }
  </style>
</head>
<body class="bg-neutral-950 text-neutral-100 min-h-screen">
  <div id="fhSplash" aria-hidden="true">
    <div class="fh-splash-card" role="status" aria-live="polite">
      <div class="fh-logo"><span style="color:rgba(255,255,255,.75)">Fitshop</span> <span style="color:rgb(var(--fh-brand-rgb))">Hub</span></div>
      <div class="fh-sub">Loading…</div>
      <div class="fh-loader" aria-hidden="true"><span></span></div>
    </div>
  </div>
  <?php
    require_once __DIR__ . '/cart_store.php';
    $cart = fh_cart_get();
    $cartCount = fh_cart_count($cart);
  ?>
  <header class="border-b border-neutral-800 sticky top-0 z-50 backdrop-blur supports-[backdrop-filter]:bg-neutral-950/70">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-3 flex items-center gap-3">
      <a href="index.php?page=<?= !empty($_SESSION['user']) ? 'landing' : 'login' ?>" class="text-xl font-bold"><span class="text-neutral-300">Fitshop</span> <span class="text-brand">Hub</span></a>
      <button id="backBtn" type="button" class="ml-2 px-2 py-1 rounded-md hover:bg-neutral-900 text-neutral-300" aria-label="Back">←</button>
      <button id="mobileMenuBtn" type="button" class="ml-auto md:hidden px-3 py-2 rounded-md hover:bg-neutral-900 text-neutral-300" aria-controls="mobileMenu" aria-expanded="false">Menu</button>
      <form action="index.php" method="get" class="hidden md:flex flex-1">
        <input type="hidden" name="page" value="catalog" />
        <input name="q" placeholder="Search products or guides..." class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-4 py-2 outline-none focus:ring-2 focus:ring-brand/60" />
      </form>
      <nav class="hidden md:flex ml-auto items-center gap-2">
        <a href="index.php?page=health" class="px-3 py-2 rounded-md hover:bg-neutral-900">Health</a>
        <a href="index.php?page=fitness" class="px-3 py-2 rounded-md hover:bg-neutral-900">Fitness</a>
        <a href="index.php?page=cart" class="px-3 py-2 rounded-md hover:bg-neutral-900">Cart<span id="navCartCount" class="ml-1 text-xs bg-brand/20 text-brand px-1.5 py-0.5 rounded"><?= (int)$cartCount ?></span></a>
        <?php if (!empty($_SESSION['user'])): $u=$_SESSION['user']; ?>
          <a href="index.php?page=profile" class="flex items-center gap-2 px-2 py-1 rounded-md bg-neutral-900 border border-neutral-800">
            <img src="<?= htmlspecialchars($u['photo_url'] ?? 'https://i.pravatar.cc/40') ?>" class="w-6 h-6 rounded-full" alt="avatar"/>
            <span class="text-sm hidden sm:block"><?= htmlspecialchars($u['name']) ?></span>
          </a>
          <a href="index.php?page=logout" class="px-3 py-2 rounded-md bg-brand/20 text-brand hover:bg-brand/30">Logout</a>
        <?php endif; ?>
      </nav>
    </div>
    <div id="mobileMenu" class="md:hidden hidden border-t border-neutral-800">
      <div class="mx-auto max-w-7xl px-4 py-3 space-y-2">
        <form action="index.php" method="get" class="flex">
          <input type="hidden" name="page" value="catalog" />
          <input name="q" placeholder="Search products or guides..." class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-4 py-2 outline-none focus:ring-2 focus:ring-brand/60" />
        </form>
        <div class="grid grid-cols-2 gap-2">
          <a href="index.php?page=health" class="px-3 py-2 rounded-md hover:bg-neutral-900 border border-neutral-800">Health</a>
          <a href="index.php?page=fitness" class="px-3 py-2 rounded-md hover:bg-neutral-900 border border-neutral-800">Fitness</a>
          <a href="index.php?page=cart" class="px-3 py-2 rounded-md hover:bg-neutral-900 border border-neutral-800">Cart<span id="navCartCountMobile" class="ml-1 text-xs bg-brand/20 text-brand px-1.5 py-0.5 rounded"><?= (int)$cartCount ?></span></a>
          <?php if (!empty($_SESSION['user'])): ?>
            <a href="index.php?page=profile" class="px-3 py-2 rounded-md hover:bg-neutral-900 border border-neutral-800">Profile</a>
            <a href="index.php?page=logout" class="px-3 py-2 rounded-md bg-brand/20 text-brand hover:bg-brand/30 border border-neutral-800">Logout</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>
  <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
