<?php declare(strict_types=1);

namespace PN\River;

require_lib('conf');
require_lib('http');
require_lib('routing');
require_lib('defer');

class MiddlewareInterrupt extends \Exception
{
}

class Dispatchee
{
  public $response_gen;
  public $middleware_pre;
  public $middleware_post;

  public function __construct(callable $gen, array $pre = [ ], array $post = [ ])
  {
    $this->response_gen = $gen;
    $this->middleware_pre = $pre;
    $this->middleware_post = $post;
  }

  public function run(Request $rq)
  {
    $resp = null;
    try {
      foreach ($this->middleware_pre as $md) {
        $rq_ = $md($rq);
        if ($rq_ !== null) {
          $rq = $rq_;
        }
      }
      $resp = ($this->response_gen)($rq);
      foreach ($this->middleware_post as $md) {
        $resp_ = $md($rq, $resp);
        if ($resp_ !== null) {
          $resp = $resp_;
        }
      }
      return $resp;
    } catch (MiddlewareInterrupt $e) {
      return $resp;
    }
  }
}

function dispatch() {
  $rq = Request::fromGlobals();
  $handler = Routing::dispatch($rq);

  if ( ! $handler instanceof Dispatchee) {
    $handler = new Dispatchee($handler);
  }

  try {
    $resp = $handler->run($rq);
    if ($resp === null) {
      throw new MiddlewareInterrupt();
    }
  } catch (\Throwable $e) {
    $resp = routes_exception($rq, $e);
  }

  $resp->send();

  try {
    deferred_run();
  } catch (\Throwable $e) {
    // TODO
  }
}
