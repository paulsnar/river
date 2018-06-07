<?php declare(strict_types=1);

namespace PN\River;

abstract class ConfContainer
{
  public static $conf = [ ];
}

function conf_load($path) {
  ConfContainer::$conf = require $path;
}

function conf_get($name, $default = null) {
  if ( ! is_array($name)) {
    $name = explode('.', $name);
  }

  $conf = ConfContainer::$conf;
  foreach ($name as $part) {
    if ( ! array_key_exists($part, $conf)) {
      return null;
    }
    $conf = $conf[$part];
  }
  return $conf;
}

function conf_default($name, $default) {
  if ( ! is_array($name)) {
    $name = explode('.', $name);
  }

  if ( ! conf_is_set($name)) {
    conf_set($name, $default);
  }

  return conf_get($name);
}

function conf_is_set($name) {
  if ( ! is_array($name)) {
    $name = explode('.', $name);
  }

  $conf = ConfContainer::$conf;
  foreach ($name as $part) {
    if ( ! array_key_exists($part, $conf)) {
      return false;
    }
  }

  return true;
}

function conf_set($name, $value) {
  if ( ! is_array($name)) {
    $name = explode('.', $name);
  }
  $leaf = array_pop($name);

  $conf =& ConfContainer::$conf;
  foreach ($name as $part) {
    if ( ! array_key_exists($part, $conf)) {
      $conf[$part] = [ ];
    }

    $conf =& $conf[$part];
  }

  $conf[$leaf] = $value;

  return $conf;
}

function conf_override($name, $value) {
  ConfContainer::$conf[$name] = $value;
}
