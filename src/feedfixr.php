<?php declare(strict_types=1);

namespace PN\River;

const FEEDFIXR_BROKEN = [
  'c50ffd836e74a85dd537e552295f7f6bd9e2888a' =>
    [ 'broken_dst' ],
];

/*
  Sites such as LSM provide incorrect timestamps in their RSS feeds.
  They appear to use EET even when EEST should be specified.
  Therefore we conditionally add an hour to their value if necessary.
 */
function feedfixr_fix_broken_dst($feed, $entry) {
  static $dst_offset;

  if ($dst_offset === null) {
    $tz = new \DateTimeZone('Europe/Riga');
    $ts = new \DateTime('now', new \DateTimeZone('GMT+02:00'));

    $offset = $tz->getOffset($ts);
    $dst_offset = $offset / 3600;
  }

  if ($dst_offset === 3) {
    $entry->timestamp += 60 * 60;
  }

  return $entry;
}

function feedfixr_has($url) {
  $hsh = hash('sha1', $url);
  if (array_key_exists($hsh, FEEDFIXR_BROKEN)) {
    return $hsh;
  }
  return null;
}

function feedfixr_entry($hsh, $feed, $entry) {
  $broken = FEEDFIXR_BROKEN[$hsh];
  $fixes = array_map(function ($breakage) {
    return __NAMESPACE__ . '\\' . 'feedfixr_fix_' . $breakage;
  }, $broken);
  foreach ($fixes as $fix) {
    $entry = $fix($feed, $entry);
  }
  return $entry;
}
