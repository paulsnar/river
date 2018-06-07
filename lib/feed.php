<?php declare(strict_types=1);

namespace PN\River;

require_lib('db');
require_lib('httpc');
require_lib('xml_utils');
require_src('feedfixr');

const FEED_DEFAULT_SYNC_INTERVAL = 15 * 60;
const FEED_JITTER = 200;

const XMLNS_ATOM = 'http://www.w3.org/2005/Atom';
const XMLNS_XHTML = 'http://www.w3.org/1999/xhtml';

class Item
{
  public $_id;
  public $of_feed;
  public $feedwide_id;
  public $timestamp;
  public $title;
  public $content;
  public $link;

  public function save()
  {
    $exists = DB::selectOne('select count(1) as feed_exists from entries ' .
      'where of_feed = ? and feedwide_id = ?',
      [ $this->of_feed, $this->feedwide_id ])['feed_exists'];
    $exists = intval($exists, 10);
    if ($exists === 1) {
      return;
    }

    DB::query('insert into entries (of_feed, feedwide_id, created_at, ' .
      'published_at, title, content, link) values (?, ?, ?, ?, ?, ?, ?)',
      [ $this->of_feed, $this->feedwide_id, time(), $this->timestamp,
        $this->title, $this->content, $this->link ]);
  }
}

class Feed
{
  public $_id;
  public $name;
  public $url;
  public $type;
  public $ttl;

  protected $entries;

  public function __construct($self)
  {
    $this->_id = $self['_id'];
    $this->name = $self['name'];
    $this->url = $self['url'];
    $this->type = $self['type'];
  }

  public static function lookup($id)
  {
    $data = DB::selectOne('select * from feeds where _id = ?', [ $id ]);
    if ($data === null) {
      return null;
    }
    return new Feed($data);
  }

  public function sync()
  {
    if ( ! in_array($this->type, [ 'rss', 'atom' ])) {
      throw new \Exception("Don't know how to parse feed type {$this->type}");
    }

    [ $status, $headers, $resp ] = httpc_request('GET', $this->url);
    if (200 > $status || 300 <= $status) {
      throw new \Exception("Feed fetch failed with status {$status}");
    }

    if ($this->type === 'rss') {
      $p = new RSSParser();
    } else if ($this->type === 'atom') {
      $p = new AtomParser();
    }

    $p->ingest($resp);

    $entries = $p->feedItems();
    $meta = $p->feedMeta();

    $key = feedfixr_has($this->url);

    foreach ($entries as $entry) {
      $entry->of_feed = $this->_id;
      if ($key !== null) {
        $entry = feedfixr_entry($key, $this, $entry);
      }
      $entry->save();
    }

    if (array_key_exists('ttl', $meta)) {
      $this->ttl = $meta['ttl'];
    }
  }

  public function rescheduleSync($last_sync)
  {
    $next = time();
    if ($this->ttl !== null) {
      $next += $this->ttl * 60;
    } else {
      $next += FEED_DEFAULT_SYNC_INTERVAL;
    }

    $next += mt_rand(-FEED_JITTER, FEED_JITTER);

    DB::query('insert into feed_poll_schedule ' .
      '(of_feed, at, done) values (?, ?, 0)',
      [ $this->_id, $next ]);
  }
}

abstract class XMLParser
{
  protected $p;
  protected $_els = [ ];
  protected $_ns;
  protected $_nsHistory = [ ];
  protected $_charbuf;

  public function __construct()
  {
    $this->_ns = [ ];

    $this->p = xml_parser_create();
    xml_parser_set_option($this->p, XML_OPTION_CASE_FOLDING, 0);

    xml_set_start_namespace_decl_handler($this->p,
      [ $this, '_xmlStartNamespace' ]);
    xml_set_element_handler($this->p,
      [ $this, '_xmlStartElementNSWrapper' ],
      [ $this, '_xmlEndElementNSWrapper' ]);
    xml_set_character_data_handler($this->p,
      [ $this, '_xmlCdata' ]);
  }

  public function __destruct()
  {
    xml_parser_free($this->p);
  }

  public function nsResolve($name, $attrs)
  {
    if (strpos($name, ':') !== false) {
      [ $ns, $name ] = explode(':', $name, 2);
      $ns = $this->_ns[$ns] ?? $ns;
      return [ $ns, $name ];
    } else if (array_key_exists('', $this->_ns)) {
      return [ $this->_ns[''], $name ];
    } else {
      return [ null, $name ];
    }
  }

  public function _xmlStartNamespace($p, $prefix, $uri)
  {
    $this->_ns[$prefix] = $uri;
  }

  public function _xmlCdata($p, $data)
  {
    if (trim($data) === '') {
      return;
    }

    if ($this->_charbuf === null) {
      $this->_charbuf = $data;
    } else {
      $this->_charbuf .= $data;
    }
  }

  public function ingest(string $xml)
  {
    xml_parse($this->p, $xml);
  }

  public function _xmlStartElementNSWrapper($p, $name, $attrs)
  {
    if (array_key_exists('xmlns', $attrs)) {
      $this->_nsHistory[] = [ $name, $attrs['xmlns'], 1 ];
      $this->_ns[''] = $attrs['xmlns'];
    } else {
      if (count($this->_nsHistory) > 0) {
        $h =& array_top_r($this->_nsHistory);
        $h[2] += 1;
      }
    }
    $el = $this->_els[] = $this->nsResolve($name, $attrs);

    // var_dump($this);
    $this->_xmlStartElement($p, $el, $attrs);
    // var_dump($this);
  }

  public function _xmlEndElementNSWrapper($p, $name)
  {
    if (count($this->_nsHistory) > 0) {
      $h =& array_top_r($this->_nsHistory);
      [ $el, $ns ] = $h;
      $h[2] -= 1;
      if ($h[2] === 0 && $name === $el) {
        array_pop($this->_nsHistory);
        if (count($this->_nsHistory) > 0) {
          $h =& array_top_r($this->_nsHistory);
          [ $el, $ns ] = $h;
          $this->_ns[''] = $ns;
          $h[2] -= 1;
        } else {
          unset($this->_ns['']);
        }
      }
    }

    $el = array_pop($this->_els);
    // var_dump($this);
    $this->_xmlEndElement($p, $el);
    $this->_charbuf = null;
    // var_dump($this);
  }

  abstract public function feedItems();
  abstract public function feedMeta();
  abstract public function _xmlStartElement($p, array $ns_name, $attrs);
  abstract public function _xmlEndElement($p, array $ns_name);
}

class RSSParser extends XMLParser
{
  protected $_meta = [ ];
  protected $_items = [ ];
  protected $_item;

  public function feedItems()
  {
    return $this->_items;
  }

  public function feedMeta()
  {
    return $this->_meta;
  }

  public function _xmlStartElement($p, array $ns_name, $attrs)
  {
    $name = $ns_name[1];
    if ($name === 'item') {
      if ($this->_item === null) {
        $this->_item = new Item();
      }
    } else if ($name === 'guid') {
      $this->_meta['guid_isPermalink'] = $attrs['isPermaLink'] ?? null;
    }
  }

  public function _xmlEndElement($p, array $ns_name)
  {
    $name = $ns_name[1];
    if ($this->_item === null) {
      if ($name === 'ttl') {
        $this->_meta['ttl'] = trim($this->_charbuf);
      }
    } else {
      $item = $this->_item;

      switch ($name) {
      case 'title':
        $item->title = $this->_charbuf;
        break;

      case 'description':
        // we just assume it's serialized HTML
        $descr = html_entity_decode($this->_charbuf);
        $descr = strip_tags($descr);
        $item->content = ws_normalize($descr);
        break;

      case 'guid':
        if (array_key_exists('guid_isPermalink', $this->_meta)) {
          $isPermalink = $this->_meta['guid_isPermalink'];

          if ($item->link === null && $isPermalink === 'true') {
            $item->link = $this->_charbuf;
          }

          unset($this->_meta['guid_isPermalink']);
        }
        $item->feedwide_id = $this->_charbuf;
        break;

      case 'pubDate':
        $item->timestamp = time_rfc2822(trim($this->_charbuf));
        break;

      case 'link':
        $item->link = $this->_charbuf;
        break;

      case 'item':
        $this->_items[] = $item;
        $this->_item = null;
        break;
      }
    }
  }
}

class AtomParser extends XMLParser
{
  protected $_nsHistory = [ ];
  protected $_items = [ ];
  protected $_item;
  protected $_attrs = [ ];

  public function feedItems()
  {
    return $this->_items;
  }

  public function feedMeta()
  {
    return [ ];
  }

  protected $_xhtml;
  protected $_xhtmlLvl = 0;

  public function _xmlStartElement($p, $ns_name, $attrs)
  {
    [ $ns, $name ] = $ns_name;
    if ($ns === XMLNS_ATOM && $name === 'entry') {
      if ($this->_item === null) {
        $this->_item = new Item();
      }
    } else if ($ns === XMLNS_XHTML) {
      if ($this->_xhtml === null) {
        $this->_xhtml = "<{$name}";
      } else {
        $this->_xhtml .= "<{$name}";
      }
      if ( ! array_key_exists('xmlns', $attrs)) {
        $attrs['xmlns'] = XMLNS_XHTML;
      }
      if (count($attrs) > 0) {
        $this->_xhtml .= ' ' . xe_attr_serialize($attrs);
      }
      $this->_xhtml .= '>';
      $this->_xhtmlLvl += 1;
    }
    $this->_attrs[] = $attrs;
  }

  public function _xmlEndElement($p, $ns_name)
  {
    [ $ns, $name ] = $ns_name;
    $attrs = array_pop($this->_attrs);

    if ($this->_xhtml !== null) {
      $this->_xhtml .= $this->_charbuf;
      $this->_xhtml .= "</{$name}>";
      $this->_xhtmlLvl -= 1;
      if ($this->_xhtmlLvl === 0) {
        $this->_charbuf = $this->_xhtml;
        $this->_xhtml = null;
      }
    } else if ($this->_item !== null) {
      $item = $this->_item;

      switch ($name) {
      case 'id':
        $item->feedwide_id = $this->_charbuf;
        break;

      case 'title':
        $item->title = $this->_atomSanitizeString($attrs);
        break;

      case 'content':
        if ($item->content === null) {
          $item->content = $this->_atomSanitizeString($attrs);
        }
        break;

      case 'summary':
        $item->content = $this->_atomSanitizeString($attrs);
        break;

      case 'updated':
        if ($item->timestamp === null) {
          $item->timestamp = strtotime($this->_charbuf);
        }
        break;

      case 'published':
        $item->timestamp = strtotime($this->_charbuf);
        break;

      case 'link':
        if (array_key_exists('rel', $attrs) &&
            $attrs['rel'] === 'alternate') {
          if ($item->link === null || (
              array_key_exists('type', $attrs) &&
              $attrs['type'] === 'text/html')) {
            $item->link = $attrs['href'];
          }
        }
        break;

      case 'entry':
        $this->_items[] = $item;
        $this->_item = null;
        break;
      }
    }
  }

  protected function _atomSanitizeString($attrs)
  {
    $t = $attrs['type'] ?? 'text';
    $text = $this->_charbuf;
    if ($t === 'html' || $t === 'xhtml') {
      $text = strip_tags($text);
    }
    return ws_normalize(trim($text));
  }
}

