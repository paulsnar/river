<?php declare(strict_types=1);

namespace PN\River;

abstract class Deferrals
{
  public static $d = [ ];
}

function defer(callable $callback, ...$args) {
  Deferrals::$d[] = [ $callback, $args ];
}

function deferred_run() {
  while (($work = array_pop(Deferrals::$d))) {
    [ $cb, $args ] = $work;
    call_user_func_array($cb, $args);
  }
}
