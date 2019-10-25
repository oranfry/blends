<?php
abstract class Thing
{
    protected static $library = [];
    public $name;

    public static function load($name)
    {
        if (!is_string($name)) {
            error_response('Name passed to Thing::load was not a string', 500);
        }

        $class = strtolower(get_called_class()) . '\\' . $name;

        if (isset(static::$library[$class])) {
            return static::$library[$class];
        }

        $thing = new $class();
        $thing->name = $name;
        static::$library[$class] = $thing;

        return $thing;
    }
}
