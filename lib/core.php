<?php declare(strict_types=1);

namespace PN\River;

function mb_str_split($in) {
  $chars = [ ];
  $len = mb_strlen($in);
  for ($i = 0; $i < $len; $i += 1) {
    $chars[] = mb_substr($in, $i, 1);
  }
  return $chars;
}

function maskpos($str, $mask, $offset = 0) {
  $chars = str_split($mask);
  $min = INF;
  foreach ($chars as $char) {
    $p = strpos($str, $char, $offset);
    if ($p !== false) {
      if ($p < $min) {
        $min = $p;
      }
    }
  }
  if ($min === INF) {
    return false;
  }
  return $min;
}

function mb_maskpos($str, $mask, $offset = 0) {
  $chars = mb_str_split($mask);
  $min = INF;
  foreach ($chars as $char) {
    $p = mb_strpos($str, $char, $offset);
    if ($p !== false) {
      if ($p < $min) {
        $min = $p;
      }
    }
  }
  if ($min === INF) {
    return false;
  }
  return $min;
}

function ws_normalize($str) {
  $str = trim($str);
  $str = preg_replace('/\s+/u', ' ', $str);
  return $str;
}

/**
 * Something between array_merge and array_merge_recursive. Do a deep merge but
 * instead of joining all the found values into an array, overwrite them.
**/
function array_override($base, ...$arrays) {
  foreach ($arrays as $array) {
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        if (array_key_exists($key, $base)) {
          $base[$key] = array_override($base[$key], $value);
        } else {
          $base[$key] = $value;
        }
      } else {
        $base[$key] = $value;
      }
    }
  }

  return $base;
}

function backtrace_format($backtrace) {
  $out = '';
  foreach ($backtrace as $item) {
    $line = '';
    if (array_key_exists('file', $item)) {
      $line .= "{$item['file']}:{$item['line']} ";
    } else {
      $line .= '(?) ';
    }
    if (array_key_exists('class', $item)) {
      $line .= "{$item['class']}{$item['type']}";
    }
    $line .= $item['function'];
    $out .= "\n    {$line}";
  }
  return substr($out, 1); // leading newline
}

const RFC2822_MONTHS = [ null, 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul',
  'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];

const RFC2822_OBSOLETE_TIMEZONES = [
  'UT' => 0, 'GMT' => 0,
  'UTC' => 0, // not actually normative, RSS is so broken you guys
  'EST' => -5 * 60, 'EDT' => -4 * 60,
  'CST' => -6 * 60, 'CDT' => -5 * 60,
  'MST' => -7 * 60, 'MDT' => -6 * 60,
  'PST' => -8 * 60, 'PDT' => -7 * 60,
];

function time_rfc2822(string $_d) {
  // strptime is so fucking unreliable (%e not available on Windows? What?)
  // so I have to maky my own :)
  $d = $_d;

  if (strpos($d, ',') === 3) {
    // prefix day-of-week
    $d = substr($d, 4);
  }
  $d = ltrim($d);

  $day_end = strpos($d, ' ');
  $day = substr($d, 0, $day_end);
  $day = intval($day, 10);
  $d = ltrim(substr($d, $day_end));

  $mon = substr($d, 0, 3);
  $mon = array_search($mon, RFC2822_MONTHS, true);
  if ($mon === false) {
    throw new \Exception("Malformed date: {$_d}");
  }
  $d = ltrim(substr($d, 3));

  // Fun, year might be two-digit!
  // Therefore no concrete part length within spec
  // Backwards compatibility ftw and fuck y2k!
  $year_end = strpos($d, ' ');
  $year = substr($d, 0, $year_end);
  $d = ltrim(substr($d, $year_end));

  $year = intval($year, 10);
  if ($year < 100) {
    // Two digit years are so concretely defined!
    $year_now = intval(date('y'));
    $delta = $year_now - $year;
    $year_now = intval(date('Y'));
    if ($delta < -10) {
      $year_now -= 100;
    }
    $year = $year_now + $year;
  }

  $hms_end = strpos($d, ' ');
  $hms = substr($d, 0, $hms_end);
  $d = ltrim(substr($d, $hms_end));

  $hms = array_map('intval', explode(':', $hms));
  [ $h, $m ] = $hms;
  if (count($hms) === 2) {
    $s = 0;
  } else {
    $s = $hms[2];
  }

  $tz = ltrim($d);
  $pm = strpos($d, '+') === 0 || strpos($d, '-') === 0;
  if ( ! $pm) {
    // obsolete names galore!
    $tz = strtoupper($tz);
    $offset = RFC2822_OBSOLETE_TIMEZONES[$tz] ?? 0;
  } else {
    $pm = substr($d, 0, 1);
    $offset = intval(substr($d, 1, 2)) * 60;
    $offset += intval(substr($d, 3, 2));
    $offset *= $pm === '+' ? 1 : -1;
  }

  if ($offset === 0) {
    $offset_s = 'Z';
  } else {
    $offset_s = ($offset < 0) ? '-' : '+';
    $offset_s .= (int) floor($offset / 60);
    $offset_s .= ':';
    $offset_s .= $offset % 60;
  }

  $iso = sprintf('%04d-%02d-%02dT%02d:%02d:%02d%s',
    $year, $mon, $day, $h, $m, $s, $offset_s);
  return strtotime($iso);
}

function json_encode($val, $opts = 0) {
  $opts |= JSON_UNESCAPED_SLASHES;
  return \json_encode($val, $opts);
}

function array_top(&$a) {
  return $a[count($a) - 1];
}

function &array_top_r(&$a) {
  return $a[count($a) - 1];
}
