<?php declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
  if (is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'])['path'])) {
    return false;
  }
}

define('RIVER_PUBLIC_ROOT', __DIR__);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

PN\River\bootstrap_web();
PN\River\dispatch();
