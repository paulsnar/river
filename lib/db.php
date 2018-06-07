<?php declare(strict_types=1);

namespace PN\River;

require_lib('conf');

class DBException extends \Exception
{
  public function __construct(array $errorInfo)
  {
    [ $sqlstate, $code, $msg ] = $errorInfo;

    parent::__construct("SQLSTATE {$sqlstate}: {$msg} ({$code})");
  }
}

class DBTransactionRollback extends \Exception
{
}

abstract class DB
{
  protected static $conn;
  protected static function getDBHandle()
  {
    if (static::$conn === null) {
      static::$conn = new \PDO('sqlite:' . conf_get('db.file_path'));
    }

    return static::$conn;
  }

  public static function transaction($callback)
  {
    $db = static::getDBHandle();

    $db->beginTransaction();
    $ok = false;

    try {
      $callback();
      $db->commit();
      $ok = true;
    } catch (DBTransactionRollback $e) {
      $db->rollback();
      $ok = true;
    } finally {
      if ( ! $ok) {
        $db->rollback();
      }
    }
  }

  public static function query($query, $params = [ ])
  {
    $db = static::getDBHandle();

    $q = $db->prepare($query);
    if ($q === false) {
      throw new DBException($db->errorInfo());
    }

    foreach ($params as $key => $value) {
      if (is_integer($key)) {
        $key += 1;
      }
      $q->bindValue($key, $value);
    }

    $ok = $q->execute();
    if ( ! $ok) {
      throw new DBException($q->errorInfo());
    }

    return $q;
  }

  public static function selectOne($query, $params = [ ])
  {
    $q = static::query($query, $params);
    $row = $q->fetch(\PDO::FETCH_ASSOC);
    if ($row === false) { return null; }
    return $row;
  }

  public static function selectAll($query, $params = [ ])
  {
    $q = static::query($query, $params);
    return $q->fetchAll(\PDO::FETCH_ASSOC);
  }
}
