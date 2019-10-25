<?php
final class Config
{
    private static $instance = null;

    public static function set($config)
    {
        if (self::$instance !== null) {
            die('Only one config allowed');
        }

        self::$instance = $config;
    }

    public static function get()
    {
        return self::$instance;
    }
}
