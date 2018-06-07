<?php declare(strict_types=1);

namespace PN\River;

class NotFoundException extends \Exception
{
}

abstract class Routing
{
  public static $map = [ ];
  protected static $map_parsed;
  protected static $urlgen_map;

  protected const TOKEN_LITERAL = 1;
  protected const TOKEN_VARIABLE = 4;
  protected const TOKEN_VARIABLE_SPLAT = 6;

  protected static function parseRoutes($routemap)
  {
    /* Implements a subset of RFC6570 "URI Template". */
    $patterns = [ ];
    $targets = [ ];

    foreach ($routemap as $route => $target) {
      $item = [ ];
      $i = 0;
      $max = strlen($route);
      while ($i < $max) {
        $next_var = strpos($route, '{', $i);
        if ($next_var === false) {
          // remainder is a literal
          $item[] = [ static::TOKEN_LITERAL, substr($route, $i, $max - $i) ];
          $i = $max;
          break;
        } else if ($next_var !== $i) {
          $lit = substr($route, $i, $next_var - $i);
          $item[] = [ static::TOKEN_LITERAL, $lit ];
        }
        $var_end = strpos($route, '}', $next_var);
        $var = substr($route, $next_var + 1, $var_end - $next_var - 1);
        if (rtrim($var, '*') !== $var) {
          $var = rtrim($var, '*');
          $item[] = [ static::TOKEN_VARIABLE_SPLAT, $var ];
        } else {
          $item[] = [ static::TOKEN_VARIABLE, $var ];
        }
        $i = $var_end + 1;
      }

      $patterns[] = $item;
      $targets[] = $target;
    }

    return [ $patterns, $targets ];
  }

  public static function dispatch($rq)
  {
    if (static::$map_parsed === null) {
      static::$map_parsed = static::parseRoutes(static::$map);
    }
    [ $patterns, $targets ] = static::$map_parsed;

    $url = $rq->path;
    $max = strlen($url);

    $match = null;
    foreach ($patterns as $i => $pattern) {
      $captures = [ ];
      $offset = 0;
      for ($j = 0; $j < count($pattern); $j += 1) {
        [ $type, $part ] = $pattern[$j];
        if ($type === static::TOKEN_LITERAL) {
          if (substr($url, $offset, strlen($part)) !== $part) {
            continue 2;
          } else {
            $offset += strlen($part);
          }
        } else if ($type & static::TOKEN_VARIABLE) {
          $next_lit = null;
          for ($k = $j; $k < count($pattern); $k += 1) {
            [ $next_type, $next_part ] = $pattern[$k];
            if ($next_type === static::TOKEN_LITERAL) {
              $next_lit = $next_part;
              break;
            }
          }
          if ($next_lit === null) {
            $capture = substr($url, $offset);
          } else {
            $next_pos = strpos($url, $next_lit, $offset);
            if ($next_pos === false) {
              continue 2;
            }
            $capture = substr($url, $offset, $next_pos - $offset);
          }
          if ($type === static::TOKEN_VARIABLE &&
              strpos($capture, '/') !== false) {
            // regular variables shouldn't capture slashes
            continue 2;
          }

          $captures[$part] = $capture;
          $offset += strlen($capture);
        }
      }
      if ($offset !== $max) {
        continue;
      }

      $match = $i;
      break;
    }

    $rq->args += $captures;

    if ($match !== null) {
      return $targets[$match]['target'];
    } else {
      throw new NotFoundException($url);
    }
  }

  public static function genurl($name, $params = [ ], $absolute = false)
  {
    if (static::$urlgen_map === null) {
      $map = static::$map_parsed;
      if ($map === null) {
        static::$map_parsed = $map = static::parseRoutes(static::$map);
      }

      [ $patterns, $descrs ] = $map;
      $urlgen_map = [ ];

      for ($i = 0; $i < count($patterns); $i += 1) {
        $pattern = $patterns[$i];
        $descr = $descrs[$i];

        $d_name = $descr['name'] ?? null;
        $d_incl = $descr['urlgen'] ?? true;
        if ( ! ($d_name && $d_incl)) {
          continue;
        }
        $urlgen_map[$d_name] = $pattern;
      }

      static::$urlgen_map = $urlgen_map;
    } else {
      $urlgen_map = static::$urlgen_map;
    }

    $pat = $urlgen_map[$name] ?? null;
    if ($pat === null) {
      throw new NotFoundException("<route:{$name}>");
    }

    $url = '';
    foreach ($pat as [ $type, $part ]) {
      if ($type === static::TOKEN_LITERAL) {
        $url .= $part;
      } else if ($type & static::TOKEN_VARIABLE) {
        $val = $params[$part] ?? null;
        if ($val === null) {
          throw new \Exception("Required parameter {$part} not provided.");
        }
        $url .= $params[$part];
      }
    }

    return gen_url($url, $absolute);
  }
}

