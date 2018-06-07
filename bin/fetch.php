<?php declare(strict_types=1);

namespace PN\River;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

bootstrap_core();
require_lib('db');
require_lib('tasks');
task_handle_exceptions();

$polls = DB::selectAll('select * from feed_polls_incomplete');
require_lib('feed');

$polls_c = count($polls);
task_log_debug("Fetching {$polls_c} feeds...");

$i = 0;

foreach ($polls as $poll) {
  $i += 1;

  $f = Feed::lookup($poll['of_feed']);
  if ($f === null) {
    task_fail("fetch task {$poll['_id']}",
      "Couldn't find feed _id {$poll['of_feed']}");
  }

  task_log_debug("{$i}/{$polls_c}...");

  $f->sync();
  $f->rescheduleSync($poll);

  DB::query('update feed_poll_schedule set done = 1 where _id = ?',
    [ $poll['_id'] ]);
}

task_log_debug('Done!');
