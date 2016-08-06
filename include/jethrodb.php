<?php
class JethroDB extends PDO {

  public function __construct ( string $dsn, string $username, string $password, array $options) {
    return parent::query($dsn, $username, $password, $options);
  }
}

class JethroStatement extends PDOStatement {

}
