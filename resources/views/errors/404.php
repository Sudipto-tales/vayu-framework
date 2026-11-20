<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 &middot; Not Found</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin:0; height:100vh; display:grid; place-items:center;
               font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
               background:#0b0d12; color:#e6e8ee; }
        .box { text-align:center; }
        h1 { font-size:5rem; margin:0; font-weight:800; letter-spacing:-2px; }
        p { opacity:.65; margin:.25rem 0 1.5rem; }
        a { color:#4f8cff; text-decoration:none; font-size:.9rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>404</h1>
        <p><?= htmlspecialchars($message ?? 'Not Found') ?></p>
        <a href="<?= base_url('/') ?>">&larr; Back home</a>
    </div>
</body>
</html>
