<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#0F0F17" />
    <title>Cuploid</title>

    {{-- Шрифты дизайн-системы (позже — самохостинг для PWA-offline) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=Pixelify+Sans:wght@400;500;600;700&family=Press+Start+2P&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/main.ts'])
</head>
<body>
    {{-- Корень дизайн-системы: тема/гамма ставит useTheme() из localStorage --}}
    <div id="app" class="ds-root" data-theme="dark" data-accent="honey"></div>
</body>
</html>
