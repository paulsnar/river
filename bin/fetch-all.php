<?php declare(strict_types=1);

namespace PN\River;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

bootstrap_core();
require_lib('db');
require_lib('tasks');
task_handle_exceptions();

require_lib('feed');
$feeds = DB::selectAll('select _id from feeds');

foreach ($feeds as $feed) {
  $f = Feed::lookup($feed['_id']);
  if ($f === null) {
    task_fail("fetch-all", "Couldn't find feed _id {$feed['_id']}");
  }

  task_log_debug("{$f->name}...");

  $f->sync();
  // $f->rescheduleSync($poll);
}

task_log_debug('Done!');
