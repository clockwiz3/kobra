<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#F4E7D6" />
    <title>Kobra</title>

    {{-- Шрифты дизайн-системы (позже — самохостинг для PWA-offline) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=Pixelify+Sans:wght@400;500;600;700&family=Press+Start+2P&display=swap"
        rel="stylesheet" />

    {{-- Фавикон: медово-лавандовое сердечко (как брендмарк в хедере). SVG для современных вкладок,
         PNG-фолбэк + apple-touch для iOS; PWA-иконки задаёт манифест (vite-plugin-pwa). --}}
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="alternate icon" href="/favicon-32.png" sizes="32x32" type="image/png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    @vite(['resources/css/app.css', 'resources/js/main.ts'])
</head>
<body>
    {{-- Корень дизайн-системы: тема/гамма ставит useTheme() из localStorage --}}
    <div id="app" class="ds-root" data-theme="light" data-accent="honey"></div>
</body>
</html>
