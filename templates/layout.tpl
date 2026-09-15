<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title|default:'Waymark'}</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/">Waymark</a>
        <nav class="site-nav">
            <a href="/">Главная</a>
            <a href="/tracks">Публичные треки</a>
        </nav>
    </div>
</header>

<main class="container">
    {block name=content}{/block}
</main>

<footer class="site-footer">
    <div class="container">&copy; {$year} Waymark — фото-воспоминания о путешествиях</div>
</footer>
</body>
</html>