<?php declare(strict_types=1);

namespace PN\River;

function task_end($ok = true) {
  exit($ok ? 0 : 1);
}

function task_fail($task, $msg) {
  $stderr = fopen('php://stderr', 'w');
  $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

  $bt = backtrace_format($bt);
  $bt = substr($bt, 4); // remove leading indent

  $out = <<<OUT
-- {$task} failed!
{$msg}
At: {$bt}

OUT;
  fwrite($stderr, $out);
  fclose($stderr);

  task_end(false);
}

function task_failure($e) {
  $stderr = fopen('php://stderr', 'w');

  $bt = backtrace_format($e->getTrace());
  $bt = substr($bt, 4);

  $msg = $e->getMessage();

  $out = <<<OUT
-- Task failed with an exception!
{$msg}
At: {$bt}

OUT;
  fwrite($stderr, $out);
  fclose($stderr);

  task_end(false);
}

function task_handle_exceptions() {
  set_exception_handler('PN\River\task_failure');
}

function task_log_debug($msg) {
  if (conf_get('debug', false)) {
    echo $msg . PHP_EOL;
  }
}
