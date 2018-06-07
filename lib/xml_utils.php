<?php declare(strict_types=1);

namespace PN\River;

function xd_text($tree) {
  if ($tree === null) {
    return '';
  }

  $out = '';

  for ( $node = $tree->firstChild;
        $node !== null;
        $node = $node->nextSibling) {

    if ($node instanceof \DOMCharacterData) {
      $out .= $node->data;
    }

  }

  return $out;
}

function xd_text_recursive($node) {
  if ($node === null) {
    return '';
  }

  if ($node instanceof \DOMCharacterData) {
    return $node->data;
  }

  $out = '';
  for ( $n = $node->firstChild;
        $n !== null;
        $n = $n->nextSibling) {
    $out .= xd_text_recursive($n);
  }

  return $out;
}

function xd_find($tree, $name) {
  if ($tree === null) {
    return null;
  }

  for ( $node = $tree->firstChild;
        $node !== null;
        $node = $node->nextSibling) {

    if ($node instanceof \DOMElement && $node->tagName === $name) {
      return $node;
    }

  }

  return null;
}

function xd_find_all($tree, $name) {
  if ($tree === null) {
    return [ ];
  }

  $nodes = [ ];

  for ( $node = $tree->firstChild;
        $node !== null;
        $node = $node->nextSibling) {

    if ($node instanceof \DOMElement && $node->tagName === $name) {
      $nodes[] = $node;
    }

  }

  return $nodes;
}

function xd_find_ns($tree, $ns, $name) {
  if ($tree === null) {
    return null;
  }

  for ( $node = $tree->firstChild;
        $node !== null;
        $node = $node->nextSibling) {

    if ($node instanceof \DOMElement &&
        $node->namespaceURI === $ns &&
        $node->tagName === $name) {

      return $node;

    }

  }

  return null;
}

function xd_find_all_ns($tree, $ns, $name) {
  if ($tree === null) {
    return [ ];
  }

  $nodes = [ ];

  for ( $node = $tree->firstChild;
        $node !== null;
        $node = $node->nextSibling) {

    if ($node instanceof \DOMElement &&
        $node->namespaceURI === $ns &&
        $node->tagName === $name) {

      $nodes[] = $node;

    }

  }

  return $nodes;
}

function xd_attr($el, $name) {
  if ( ! $el->hasAttribute($name)) {
    return null;
  }
  return $el->getAttribute($name);
}

function xe_attr_serialize($attrs) {
  $out = '';
  $i = 1;
  $max = count($attrs);
  $space = ' ';
  foreach ($attrs as $key => $value) {
    $value = xe_escape($value);
    if ($i === $max) {
      $space = '';
    }
    $out .= "{$key}=\"{$value}\"{$space}";
  }
  return $out;
}

function xe_escape(string $val) {
  $val = str_replace('&', '&amp;', $val);
  $val = str_replace('"', '&quot;', $val);
  $val = str_replace("'", '&apos;', $val);
  $val = str_replace('<', '&lt;', $val);
  $val = str_replace('>', '&gt;', $val);
  return $val;
}
