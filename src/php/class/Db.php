<?php
final class Db
{
    protected static $link;
    protected static $pdo;

    public static function connect()
    {
        static::$link = mysqli_connect(
            @Config::get()->db_creds->host ?: '127.0.0.1',
            Config::get()->db_creds->username,
            Config::get()->db_creds->password,
            Config::get()->db_creds->db
        );
    }

    public static function succeed($query, $error_message = null)
    {
        $result = mysqli_query(static::$link, $query);

        if ($result === false) {
            $message = ($error_message ? "{$error_message}\n\n" : '') . mysqli_error(static::$link) . "\n\n{$query}";

            error_response($message, 500);
        }

        return $result;
    }

    public static function error()
    {
        return mysqli_error(static::$link);
    }

    public static function affected()
    {
        return mysqli_affected_rows(static::$link);
    }

    private static function checkPdoLink()
    {
        if (!static::$pdo) {
            $connection_string =
                "mysql:" .
                "host=" . (@Config::get()->db_creds->host ?: '127.0.0.1') . ';' .
                "dbname=" . Config::get()->db_creds->db;

            static::$pdo = new PDO(
                $connection_string,
                Config::get()->db_creds->username,
                Config::get()->db_creds->password
            );

            if (!static::$pdo) {
                error_response("Pdo connection problem\n" . implode("\n", static::$pdo->errorInfo()));
            }
        }
    }

    public static function prepare($query)
    {
        static::checkPdoLink();

        $statement = static::$pdo->prepare($query);

        if (!$statement) {
            error_response("Statement problem\n" . implode("\n", static::$pdo->errorInfo()) . "\n{$query}");
        }

        return $statement;
    }

    public static function insert_id()
    {
        return mysqli_insert_id(static::$link);
    }

    public static function pdo_insert_id()
    {
        static::checkPdoLink();

        return static::$pdo->lastInsertId();
    }
}
