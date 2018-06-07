<?php declare(strict_types=1);

namespace PN\River;

require_lib('db');
require_lib('http');
require_lib('routing');
require_src('routes');
require_src('data');

function routes_api1_install() {
  Routing::$map += [
    '/1/front/feeds' => [
      'target' => _route('route_api1_front_feeds'),
      'name' => 'api.1.front.feeds',
    ],
    '/1/front/entries' => [
      'target' => _route('route_api1_front_entries'),
      'name' => 'api.1.front.entries',
    ],
  ];
}

function api1_json_response($status, $obj = null) {
  if ($obj !== null) {
    $obj = json_encode($obj);
  }
  return new Response($status, [
    'Content-Type' => 'application/json; charset=UTF-8',
  ], $obj);
}

function route_api1_front_feeds($rq) {
  if ($rq->method !== 'GET') {
    return api1_json_response(405);
  }

  return api1_json_response(200, DBFeed::frontpage());
}

const API1_PAGE_SIZE = 50;
const API1_ENTRIES_FRONT_QUERY_BASE = <<<'SQL'
  select e._id, e.feedwide_id, e.timestamp, e.title, e.content, e.link,
    f.name as feed_name
  from recent_entries e
    join feeds f on f._id = e.of_feed
SQL;

function route_api1_front_entries($rq) {
  if ($rq->method !== 'GET') {
    return api1_json_response(405);
  }

  $before = $since = null;

  if ($rq->query['before'] !== null) {
    $before = $rq->query['before'];
    if (ctype_digit($before)) {
      $before = intval($before, 10);
    } else {
      return api1_json_response(400, [ 'param' => 'before' ]);
    }
  }

  if ($before === null && $rq->query['since'] !== null) {
    $since = $rq->query['since'];
    if (ctype_digit($since)) {
      $since = intval($since, 10);
    } else {
      return api1_json_response(400, [ 'param' => 'since' ]);
    }
  }

  $count = $rq->query['count'] ?? null;
  if ($count !== null) {
    if (ctype_digit($count)) {
      $count = intval($count, 10);
    } else {
      return api1_json_response(400, [ 'param' => 'count' ]);
    }
  } else {
    $count = API1_PAGE_SIZE;
  }

  return api1_json_response(200,
    DBEntry::frontpageFiltered($before, $since, $count));
}
