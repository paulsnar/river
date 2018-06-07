<?php declare(strict_types=1);

namespace PN\River;

use PN\River\TemplatingStdlib\Sandbox;
require_lib('templating_std');

const TEMPLATE_ROOT = RIVER_ROOT_PRIVATE . DIRECTORY_SEPARATOR . 'tpl';

class Template implements \ArrayAccess
{
  public $name;
  protected $path;
  public $env;
  public $layout;
  public $metadata = [ ];
  public $content;

  public static $globals = [ ];

  public function __construct($name)
  {
    $this->name = $name;
    $this->env = static::$globals;

    $fsname = str_replace('.', DIRECTORY_SEPARATOR, $name);
    $this->path = path_join(TEMPLATE_ROOT, "{$fsname}.tpl.php");
  }

  public function offsetExists($name)
  {
    return array_key_exists($name, $this->env);
  }

  public function offsetGet($name)
  {
    return $this->env[$name] ?? null;
  }

  public function offsetSet($name, $value)
  {
    $this->env[$name] = $value;
  }

  public function offsetUnset($name)
  {
    unset($this->env[$name]);
  }

  public function render()
  {
    if ($this->content !== null) {
      if ($this->layout !== null) {
        return $this->layout->content;
      }
      return $this->content;
    }

    $content = @file_get_contents($this->path);
    if ($content === false) {
      throw new \Exception("Template {$this->name} not found.");
    }

    if (strpos($content, "---") === 0) {
      $meta_end = strpos($content, "\n---", 3);
      if ($meta_end === false) {
        goto skip_meta_block;
      }

      $meta = substr($content, 3, $meta_end - 3);
      $content = substr($content, $meta_end + 4);

      $this->metadata = parse_ini_string($meta, true, INI_SCANNER_TYPED);
    }

    $vars = $this->metadata['vars'] ?? [ ];
    $this->env = array_override($vars, $this->env);

  skip_meta_block:
    $content = $this->content = Sandbox::eval($content, $this->env);

    $layout = $this->metadata['layout'] ?? null;
    if ($layout !== null) {
      $layout = $this->layout = new Template($layout);
      $layout->env['content'] = $content;

      $layout->env = array_override($layout->env, $vars);

      $content = $layout->render();
      $this->metadata = array_override(
        $layout->metadata, $this->metadata);
      if (array_key_exists('mediatype', $layout->metadata)) {
        $this->metadata['mediatype'] = $layout->metadata['mediatype'];
      }
    }

    return $content;
  }
}

