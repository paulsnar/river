<?php declare(strict_types=1);

namespace PN\River;

function iterator_coroutine(&$dict) {
  foreach ($dict as $key => $value) {
    yield $key => $value;
  }
}

trait IteratorProxy
{
  protected $iterator;
  private $_IteratorProxy_dict;

  protected function _IteratorProxy_prime(&$dict)
  {
    $this->_IteratorProxy_dict = &$dict;
  }

  public function rewind()
  {
    $this->iterator = iterator_coroutine($this->_IteratorProxy_dict);
    return $this->iterator->rewind();
  }
  public function key() { return $this->iterator->key(); }
  public function current() { return $this->iterator->current(); }
  public function next() { return $this->iterator->next(); }
  public function valid() { return $this->iterator->valid(); }
}

class Bag implements \ArrayAccess, \Iterator
{
  use IteratorProxy;

  protected $bag;
  protected $immutable;

  public function __construct($initial, $immutable = false)
  {
    $this->bag = $initial;
    $this->immutable = $immutable;

    $this->_IteratorProxy_prime($this->bag);
  }

  public function offsetExists($k)
  {
    return array_key_exists($k, $this->bag);
  }

  public function offsetGet($k)
  {
    $val = $this->bag[$k] ?? null;
    if (is_array($val)) {
      return $val[0];
    }
    return $val;
  }

  public function offsetSet($k, $v)
  {
    if ($this->immutable) {
      throw new \LogicException('Tried to modify immutable Bag');
    }

    $this->bag[$k] = $v;
  }

  public function offsetUnset($k)
  {
    if ($this->immutable) {
      throw new \LogicException('Tried to modify immutable Bag');
    }

    unset($this->bag[$k]);
  }

  public function all($k)
  {
    $val = $this->bag[$k] ?? null;
    if ($val === null) {
      return [ ];
    } else if ( ! is_array($val)) {
      return [ $val ];
    }
    return $val;
  }

  public function toArray()
  {
    return $this->bag;
  }
}

class HeaderBag extends Bag
{
  public function __construct($headers, $immutable = false)
  {
    $hds = [ ];
    foreach ($headers as $name => $value) {
      $hds[strtolower($name)] = $value;
    }
    parent::__construct($hds, $immutable);
  }

  public function offsetExists($k)
    { return parent::offsetExists(strtolower($k)); }

  public function offsetGet($k)
    { return parent::offsetGet(strtolower($k)); }

  public function offsetSet($k, $v)
    { return parent::offsetSet(strtolower($k), $v); }

  public function offsetUnset($k)
    { return parent::offsetSet(strtolower($k), null); }

  public static function fromGlobals()
  {
    $hd = [ ];
    foreach ($_SERVER as $key => $value) {
      if (strpos($key, 'HTTP_') === 0) {
        $hd_name = substr($key, 5);
        $hd_name = str_replace('_', '-', $hd_name);
        $hd[$hd_name] = $value;
      }
    }
    return new static($hd, true);
  }
}

const NETSCAPE_EPOCH = 784771200;

class Cookie
{
  public $name;
  public $value;
  public $expires_at = 0;
  public $path;
  public $domain;
  public $is_secure;
  public $is_http_only;

  public function __construct($name, $value, $opts = [ ])
  {
    $this->name = $name;
    $this->value = $value;

    if (array_key_exists('expires_in', $opts)) {
      $this->expires_at = time() + $opts['expires_in'];
    } else if (array_key_exists('expires_at', $opts)) {
      $this->expires_at = $opts['expires_at'];
    }

    if (array_key_exists('path', $opts)) {
      $this->path = $opts['path'];
    }

    if (array_key_exists('domain', $opts)) {
      $this->domain = $opts['domain'];
    }

    if (array_key_exists('is_secure', $opts)) {
      $this->is_secure = $opts['is_secure'];
    }

    if (array_key_exists('is_http_only', $opts)) {
      $this->is_http_only = $opts['is_http_only'];
    }
  }

  public function send()
  {
    $hd = [ "{$this->name}={$this->value}" ];

    if ($this->expires_at !== 0) {
      $hd[] = 'Expires=' . date('r', $this->expires_at);
    }
    if ($this->path !== null) {
      $hd[] = "Path={$this->path}";
    }
    if ($this->domain !== null) {
      $hd[] = "Domain={$this->domain}";
    }
    if ($this->is_secure) {
      $hd[] = 'Secure';
    }
    if ($this->is_http_only) {
      $hd[] = 'HttpOnly';
    }

    $val = implode('; ', $hd);
    header("Set-Cookie: {$val}", false);
  }

  public static function remove($name)
  {
    return static::create($name, '_', [ 'expires_at' => NETSCAPE_EPOCH ]);
  }
}

class Request
{
  public $method;
  public $path;
  public $query;
  public $form;
  public $files;
  public $cookies;

  public $body;
  public $args = [ ];
  public $attributes = [ ];

  public static function fromGlobals()
  {
    $rq = new static();

    $rq->method = $_SERVER['REQUEST_METHOD'];

    $path = $_SERVER['REQUEST_URI'];
    $prefix = conf_get('routing.prefix', '///');
    if (strpos($path, $prefix) === 0) {
      $path = substr($path, strlen($prefix));
    }
    if (strpos($path, '?') !== false) {
      // f
      $query_str = substr($path, strpos($path, '?') + 1);
      parse_str($query_str, $query);
      $path = substr($path, 0, strpos($path, '?'));
    } else {
      $query = $_GET;
    }
    $rq->path = $path;

    foreach ($query as &$value) {
      if ($value === '') {
        $value = true;
      }
    }

    $form = $_POST;
    foreach ($form as &$value) {
      if ($value === '') {
        $value = true;
      }
    }

    $rq->headers = HeaderBag::fromGlobals();

    $rq->query = new Bag($query, true);
    $rq->form = new Bag($_POST, true);
    $rq->files = new Bag($_FILES, true);
    $rq->cookies = new Bag($_COOKIE, true);

    if ($rq->method !== 'GET' && $rq->method !== 'HEAD') {
      $rq->body = file_get_contents('php://input');
    }

    return $rq;
  }
}

class Response
{
  public $status;
  public $headers;
  public $cookies = [ ];
  public $body;

  public function __construct($status = 204, $headers = [ ], $body = null)
  {
    $this->status = $status;
    $this->headers = new HeaderBag($headers);
    $this->body = $body;
  }

  public function send()
  {
    http_response_code($this->status);

    foreach ($this->headers->toArray() as $header => $value) {
      if ($value === null) {
        header_remove($header);
      } else {
        header("{$header}: {$value}");
      }
    }

    foreach ($this->cookies as $cookie) {
      $cookie->send();
    }

    if ($this->body !== null) {
      echo $this->body;
    }

    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    }
  }

  public static function fromTemplate($status, Template $tpl)
  {
    $content = $tpl->render();
    $headers = [ ];

    if (array_key_exists('http_headers', $tpl->metadata)) {
      foreach ($tpl->metadata['http_headers'] as $name => $value) {
        $name = str_replace('_', '-', $name);
        $headers[$name] = $value;
      }
    }

    $headers['Content-Type'] =
      $tpl->metadata['mediatype'] ?? 'text/html; charset=UTF-8';

    return new static($status, $headers, $content);
  }
}
