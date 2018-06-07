<?php declare(strict_types=1);

namespace PN\River;

abstract class Requirer
{
  const LIB_BASE = RIVER_ROOT_PRIVATE . DIRECTORY_SEPARATOR . 'lib';
  const SRC_BASE = RIVER_ROOT_PRIVATE . DIRECTORY_SEPARATOR . 'src';

  protected static $done = [ ];

  public static function require($name, $prefix, $base)
  {
    $key = "{$prefix}.{$name}";
    if (array_key_exists($key, static::$done)) { return; }

    static::$done[$key] = true;

    $fsname = str_replace('.', DIRECTORY_SEPARATOR, $name);
    $fsname .= '.php';
    return require path_join($base, $fsname);
  }
}

function require_lib($name) {
  return Requirer::require($name, 'lib', Requirer::LIB_BASE);
}
function require_src($name) {
  return Requirer::require($name, 'src', Requirer::SRC_BASE);
}
