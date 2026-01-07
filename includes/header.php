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
    tailwind.config = { theme: { extend: { colors: { brand: { DEFAULT: '#6366F1' } } } } };
  </script>
  <script>
    window.__BASE_URL__ = "<?= defined('BASE_URL') ? htmlspecialchars(BASE_URL, ENT_QUOTES) : '' ?>";
  </script>
  <style>
    :root{
      --fh-bg-0:#05070b;
      --fh-bg-1:#070a12;
      --fh-line-1:rgba(99,102,241,.20);
      --fh-line-2:rgba(34,211,238,.16);
      --fh-line-3:rgba(244,114,182,.12);
    }
    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background:
        radial-gradient(900px 500px at 15% 20%, rgba(99,102,241,.12), transparent 60%),
        radial-gradient(700px 420px at 85% 10%, rgba(34,211,238,.10), transparent 60%),
        linear-gradient(180deg, var(--fh-bg-0), var(--fh-bg-1));
    }
    body::before{
      content:'';
      position:fixed;
      inset:-20vh -20vw;
      pointer-events:none;
      z-index:-1;
      background:
        linear-gradient(135deg,
          transparent 0%,
          transparent 38%,
          var(--fh-line-1) 44%,
          var(--fh-line-2) 52%,
          var(--fh-line-3) 60%,
          transparent 66%,
          transparent 100%);
      background-size: 220% 220%;
      animation: fhDiagonalFlow 14s linear infinite;
      filter: blur(0px);
      transform: translateZ(0);
    }
    #fhSplash{
      position:fixed;
      inset:0;
      z-index:1000;
      display:flex;
      align-items:center;
      justify-content:center;
      background:
        radial-gradient(900px 500px at 15% 20%, rgba(99,102,241,.18), transparent 60%),
        radial-gradient(700px 420px at 85% 10%, rgba(34,211,238,.14), transparent 60%),
        linear-gradient(180deg, var(--fh-bg-0), var(--fh-bg-1));
    }
    #fhSplash::before{
      content:'';
      position:absolute;
      inset:-20vh -20vw;
      background:
        linear-gradient(135deg,
          transparent 0%,
          transparent 38%,
          var(--fh-line-1) 44%,
          var(--fh-line-2) 52%,
          var(--fh-line-3) 60%,
          transparent 66%,
          transparent 100%);
      background-size: 220% 220%;
      animation: fhDiagonalFlow 14s linear infinite;
      pointer-events:none;
    }

    @keyframes fhDiagonalFlow{
      0%{ background-position: 0% 0%; }
      100%{ background-position: 100% 100%; }
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
      background: linear-gradient(90deg, rgba(99,102,241,.95), rgba(34,211,238,.95));
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
      body::before{ animation:none; }
      #fhSplash::before{ animation:none; }
      #fhSplash .fh-loader > span{ animation:none; width:100%; }
      #fhSplash.fh-hide{ transition:none; }
    }
  </style>
</head>
<body class="bg-neutral-950 text-neutral-100 min-h-screen">
  <div id="fhSplash" aria-hidden="true">
    <div class="fh-splash-card" role="status" aria-live="polite">
      <div class="fh-logo"><span style="color:rgba(255,255,255,.75)">Fitshop</span> <span style="color:#6366F1">Hub</span></div>
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
