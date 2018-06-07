<?php declare(strict_types=1);

namespace PN\River;

define('RIVER_ROOT_PRIVATE', __DIR__);

function path_join(...$parts) {
  return implode(DIRECTORY_SEPARATOR, $parts);
}

function bootstrap_core() {
  mb_internal_encoding('UTF-8');

  set_error_handler(function ($errno, $msg, $file, $line) {
    throw new \ErrorException($msg, 0, $errno, $file, $line);
  });

  require path_join(RIVER_ROOT_PRIVATE, 'lib', 'require.php');
  require_lib('core');

  require_lib('conf');
  conf_load(path_join(RIVER_ROOT_PRIVATE, 'conf.php'));
}

function bootstrap_web() {
  bootstrap_core();

  require_src('routes');
  routes_install();

  require_lib('dispatch');
}
