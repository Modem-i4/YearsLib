<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#020113">
  <link rel="icon" type="image/svg+xml" href="/assets/yearslib-logo.svg">
  <title><?= htmlspecialchars($title ?? 'Вхід адміністратора – Бібліотека Years', ENT_QUOTES, 'UTF-8') ?></title>
  <script>
    try {
      const savedTheme = localStorage.getItem('library.theme');
      const theme = savedTheme === 'light' ? 'light' : 'dark';
      document.documentElement.dataset.theme = theme;
      document.querySelector('meta[name="theme-color"]')?.setAttribute(
        'content', theme === 'dark' ? '#020113' : '#f7f9fd'
      );
    } catch {}
  </script>
  <link rel="stylesheet" href="/build/assets/library.css">
  <script type="module" src="/build/assets/library.js"></script>
</head>
<body>
  <div id="library-app"></div>
</body>
</html>
