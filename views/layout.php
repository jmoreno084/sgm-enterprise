<?php
declare(strict_types=1);
$escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $escape(isset($title) ? $title : 'SGPM') ?></title>
<link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<a class="sr-only sr-only-focusable" href="#contenido">Saltar al contenido</a>
<header class="bg-dark text-white p-3"><strong>SGPM</strong> · Gestión de proyectos mineros</header>
<main id="contenido" class="sgpm-main">
<?php require __DIR__ . '/components/alerts.php'; ?>
<?php if (isset($render) && is_callable($render)) { $render($escape); } ?>
</main>
</body>
</html>

