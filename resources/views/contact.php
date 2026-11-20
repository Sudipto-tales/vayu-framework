<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact &middot; <?= e(config('app.name')) ?></title>
    <style>
        :root { color-scheme: light dark; --b:#4f8cff; }
        body { font-family: system-ui, sans-serif; max-width: 520px; margin: 3rem auto; padding: 0 1rem; }
        h1 { font-size: 1.5rem; }
        label { display:block; margin: 1rem 0 .25rem; font-weight: 600; font-size: .9rem; }
        input, textarea { width: 100%; padding: .6rem .7rem; border: 1px solid #8884; border-radius: 8px; font: inherit; background: transparent; color: inherit; }
        .err { color: #e5484d; font-size: .8rem; margin-top: .25rem; }
        button { margin-top: 1.25rem; background: var(--b); color: #fff; border: 0; padding: .7rem 1.2rem; border-radius: 8px; font: inherit; cursor: pointer; }
        .status { background: #30a46c22; border: 1px solid #30a46c; color: #30a46c; padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <h1>Contact us</h1>

    <?php if ($status = session()->get('status')): ?>
        <div class="status"><?= e($status) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('contact') ?>">
        <?= csrf_field() ?>

        <label for="name">Name</label>
        <input id="name" name="name" value="<?= e(old('name')) ?>">
        <?php if ($m = error('name')): ?><div class="err"><?= e($m) ?></div><?php endif; ?>

        <label for="email">Email</label>
        <input id="email" name="email" value="<?= e(old('email')) ?>">
        <?php if ($m = error('email')): ?><div class="err"><?= e($m) ?></div><?php endif; ?>

        <label for="message">Message</label>
        <textarea id="message" name="message" rows="4"><?= e(old('message')) ?></textarea>
        <?php if ($m = error('message')): ?><div class="err"><?= e($m) ?></div><?php endif; ?>

        <button type="submit">Send</button>
    </form>
</body>
</html>
