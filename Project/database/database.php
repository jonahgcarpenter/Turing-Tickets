<?php
class Database {
    private static $pdo = null;

    public static function dbConnect() {
        if (self::$pdo === null) {
            try {
                require_once('../config/connect.php');
                self::$pdo = new PDO('mysql:host='.$servername.';dbname='.$db, $username, $password);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Error!: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function dbDisconnect() {
        self::$pdo = null;
    }
}
?>