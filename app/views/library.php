<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#fafaf9">
  <title><?= htmlspecialchars($title ?? 'Шкільна бібліотека', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/build/assets/library.css">
  <script type="module" src="/build/assets/library.js"></script>
</head>
<body>
  <div id="library-app"></div>
</body>
</html>