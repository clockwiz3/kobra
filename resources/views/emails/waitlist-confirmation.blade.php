<!DOCTYPE html>
<html lang="ru">
<body style="margin:0;background:#0F0F17;color:#F4F1F7;font-family:Inter,Arial,sans-serif;padding:32px">
    <div style="max-width:440px;margin:0 auto;background:#171722;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:28px">
        <div style="font-size:20px;font-weight:700">♥ Cuploid</div>
        <h1 style="font-size:22px;margin:18px 0 8px">Подтвердите подписку</h1>
        <p style="color:#A39FB3;line-height:1.6;margin:0 0 22px">
            Спасибо, что хотите быть в числе первых! Нажмите кнопку, чтобы занять место в вайтлисте.
        </p>
        <a href="{{ $confirmUrl }}"
           style="display:inline-block;background:linear-gradient(135deg,#FF6B9D,#7C5CFC);color:#fff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:999px">
            Подтвердить ♥
        </a>
        <p style="color:#A39FB3;font-size:13px;margin:22px 0 0">
            Если кнопка не работает, откройте ссылку: <br />{{ $confirmUrl }}
        </p>
        <p style="color:#A39FB3;font-size:12px;margin:18px 0 0">
            Если это были не вы — просто проигнорируйте письмо.
        </p>
    </div>
</body>
</html>
