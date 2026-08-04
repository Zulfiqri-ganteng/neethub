<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title><?= esc($title ?? 'NetHub') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon_io/favicon-32x32.png">
    <link rel="stylesheet" href="/css/main.css">
</head>
<body>
<?= $this->renderSection('content') ?>
<script src="/js/main.js" type="module"></script>
</body>
</html>
