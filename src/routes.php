<?php declare(strict_types=1);

namespace PN\River;

require_lib('db');
require_lib('http');
require_lib('routing');
require_lib('templating');
require_src('data');
require_src('routes.api1');

const ERROR_404_MESSAGE = <<<'HTML'
<!DOCTYPE html>
<article>Atvainojiet, nav atrasts.</article>
HTML;

const ERROR_500_MESSAGE = <<<'HTML'
<!DOCTYPE html>
<article>Atvainojiet, kaut kas nogāja greizi.</article>
HTML;

function _route($name) {
  return __NAMESPACE__ . '\\' . $name;
}

function routes_install() {
  Routing::$map += [
    '/frontend/config.js' => [
      'target' => _route('route_frontend_config_js'),
      'name' => 'frontend.config_js',
    ],

    '/' => [
      'target' => _route('route_index'),
      'name' => 'index',
    ],
  ];

  routes_api1_install();

  Routing::$map['{_*}'] = [
    'target' => _route('route_404'),
    'name' => 'err.404',
    'urlgen' => false,
  ];
}

const FRONT_PAGE_SIZE = 50;

function route_index($rq) {
  $t = new Template('misc.index');

  $feeds = DBFeed::frontpage();
  $feeds_map = [ ];
  foreach ($feeds as $feed) {
    $feeds_map[$feed['_id']] = $feed;
  }

  $t['feeds'] = $feeds;
  $t['feeds_map'] = $feeds_map;
  $t['entries'] = DBEntry::frontpage(FRONT_PAGE_SIZE);

  return Response::fromTemplate(200, $t);
}

function route_frontend_config_js($rq) {
  $urlbase = TemplatingStdlib\url('/');
  $urlbase = html_entity_decode($urlbase);
  $urlbase = json_encode($urlbase);

  ob_start();
?>
(function() {
  "use strict";
  var River = { }
  if ('River' in window) {
    River = window.River
  } else {
    window.River = River
  }

  River.Config = {
    urlbase: <?= $urlbase ?>,
    page_size: <?= API1_PAGE_SIZE ?>,
  }
})()
<?php

  return new Response(200, [
    'Content-Type' => 'application/javascript; charset=UTF-8',
  ], ob_get_clean());
}

function route_404($rq) {
  return new Response(404, [
    'Content-Type' => 'text/html; charset=UTF-8',
  ], ERROR_404_MESSAGE);
}

function route_500($rq) {
  if (conf_get('debug', false)) {
    $content = "--- Catastrophic Failure ---\n";
    $content .= $rq->attributes['error'];
    return new Response(500, [
      'Content-Type' => 'text/plain; charset=UTF-8',
    ], $content);
  }

  return new Response(500, [
    'Content-Type' => 'text/html; charset=UTF-8',
  ], ERROR_500_MESSAGE);
}

function routes_exception($rq, \Throwable $e) {
  $rq->attributes['error'] = $e;
  return route_500($rq);
}
