---
mediatype = "text/html; charset=UTF-8"
---
<!DOCTYPE html>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title><?= ($title ?? false) ? "{$title} – River" : 'River' ?></title>
<link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
<div class="container">
  <?= $content ?>
</div>
