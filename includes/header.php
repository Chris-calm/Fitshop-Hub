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
  <style> body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; } </style>
</head>
<body class="bg-neutral-950 text-neutral-100 min-h-screen">
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
        <a href="index.php?page=cart" class="px-3 py-2 rounded-md hover:bg-neutral-900">Cart<span id="navCartCount" class="ml-1 text-xs bg-brand/20 text-brand px-1.5 py-0.5 rounded"></span></a>
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
          <a href="index.php?page=cart" class="px-3 py-2 rounded-md hover:bg-neutral-900 border border-neutral-800">Cart<span id="navCartCountMobile" class="ml-1 text-xs bg-brand/20 text-brand px-1.5 py-0.5 rounded"></span></a>
          <?php if (!empty($_SESSION['user'])): ?>
            <a href="index.php?page=profile" class="px-3 py-2 rounded-md hover:bg-neutral-900 border border-neutral-800">Profile</a>
            <a href="index.php?page=logout" class="px-3 py-2 rounded-md bg-brand/20 text-brand hover:bg-brand/30 border border-neutral-800">Logout</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>
  <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
