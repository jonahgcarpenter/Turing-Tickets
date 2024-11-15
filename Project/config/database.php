<?php
class Database {
    public function __construct() {}

    public static function dbConnect() {
        $pdo = null;
        try {
            require_once('connect.php');
            $pdo = new PDO('mysql:host='.$servername.';dbname='.$db, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error!: " . $e->getMessage());
        }
        return $pdo;
    }

    public static function dbDisconnect() {
        $pdo = null;
    }
}
?>