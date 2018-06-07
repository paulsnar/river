<?php declare(strict_types=1);

namespace PN\River\TemplatingStdlib;

abstract class Sandbox
{
  public static function render(string $__filename, array $__ctx = [ ])
  {
    extract($__ctx);
    ob_start();
    try {
      require $__filename;
      return ob_get_contents();
    } finally {
      ob_end_clean();
    }
  }

  public static function eval(string $__code, array $__ctx = [ ])
  {
    extract($__ctx);
    ob_start();
    try {
      eval('namespace PN\\River\\TemplatingStdlib; ?' . '>' . $__code);
      return ob_get_contents();
    } finally {
      ob_end_clean();
    }
  }
}

function he(string $msg) {
  return htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5);
}

function url($path) {
  $proto = $_SERVER['HTTPS'] ?? false;
  $proto = $proto ? 'https' : 'http';
  $base = $_SERVER['HTTP_HOST'] ?? null;

  if ($base === null) {
    $base = '';
  } else {
    $base = "{$proto}://{$base}";
  }

  $prefix = \PN\River\conf_get('routing.prefix');
  if ($prefix !== null) {
    $base .= $prefix;
  }

  return he($base . '/' . ltrim($path, '/'));
}

function link($target, $params = [ ]) {
  return he(\PN\River\Routing::genurl($name, $params));
}

function tpl_include($name, $vars = [ ]) {
  $tpl = new \PN\River\Template("includes.{$name}");
  $tpl->env = $vars + $tpl->env;
  return $tpl->render();
}

function shortdate($ts) {
  if (is_string($ts)) {
    $ts = intval($ts, 10);
  }

  $now = time();
  if ($now - $ts < 24 * 60 * 60) {
    // less than 24h
    return date('H:i', $ts);
  }
  return date('M d H:i', $ts);
}

function truncate($text, $max = 70) {
  if (mb_strlen($text) > $max) {
    $next_sep = \PN\River\mb_maskpos($text, ',.!? ', $max);
    if ($next_sep === false) {
      $next_sep = $max;
    }
    if ($next_sep > $max * 1.25) {
      $next_sep = \PN\River\mb_maskpos($text, ',.!? ', (int) ($max * 0.75));
    }
    $text = mb_substr($text, 0, $next_sep);
    $text .= '…';
  }
  return $text;
}

function json($val, $opts = 0) {
  $opts |= JSON_HEX_TAG;
  return htmlspecialchars(\json_encode($val, $opts), ENT_HTML5);
}

function _($str) {
  // TODO: this could be used in the future for i18n. Right now it isn't, so...
  return $str;
}
