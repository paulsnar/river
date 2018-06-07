<?php declare(strict_types=1);

namespace PN\River;

require_lib('db');

abstract class DBFeed
{
  public static function serialize(array $db)
  {
    if (array_key_exists('type', $db)) {
      unset($db['type']);
    }
    if (array_key_exists('is_frontpage', $db)) {
      unset($db['is_frontpage']);
    }
    if (array_key_exists('is_standout', $db)) {
      $db['is_standout'] = ($db['is_standout'] == '1');
    }
    return $db;
  }

  public static function frontpage()
  {
    $feeds = DB::selectAll(
      'select * from feeds f where f.is_frontpage = ?', [ true ]);
    return array_map([ static::class, 'serialize' ], $feeds);
  }
}

abstract class DBEntry
{
  public static function serialize(array $db)
  {
    $db['created_at'] = intval($db['created_at'], 10);
    $db['published_at'] = intval($db['published_at'], 10);
    return $db;
  }

  public static function frontpage($count)
  {
    $entries = DB::selectAll(
      'select e._id, e.of_feed, e.feedwide_id, e.created_at, e.published_at,' .
        'e.title, e.content, e.link from recent_entries e limit ?',
        [ $count ]);
    return array_map([ static::class, 'serialize' ], $entries);
  }

  public static function frontpageFiltered($before, $since, $count)
  {
    $q = 'select e._id, e.of_feed, e.feedwide_id, e.created_at, ' .
      'e.published_at, e.title, e.content, e.link from recent_entries e';
    $params = [ ];

    if ($before !== null) {
      $q .= ' where e.published_at < ?';
      $params[] = $before;
    } else if ($since !== null) {
      $q .= ' where e.created_at > ? order by e.published_at asc';
      $params[] = $since;
    }

    $q .= ' limit ?';
    $params[] = $count;

    $entries = DB::selectAll($q, $params);
    return array_map([ static::class, 'serialize' ], $entries);
  }
}
