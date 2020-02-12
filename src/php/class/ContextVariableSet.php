<?php
abstract class ContextVariableSet
{
    protected static $library = [];

    public $prefix;
    public $label;
    public $vars = [];

    public function __construct($prefix)
    {
        $this->prefix = $prefix;
    }

    abstract public function display();

    public function tinydisplay()
    {
    }

    public function enddisplay()
    {
    }

    protected function getRawData()
    {
        foreach (getallheaders() as $hname => $hvalue) {
            if ($hname == 'X-Cvs') {
                $fromheaders = [];
                $cvsheaders = explode(',', $hvalue);

                foreach ($cvsheaders as $rawheader) {
                    list($fname, $fvalue) = explode('=', $rawheader, 2);
                    list($fmajor, $fminor) = explode('__', $fname, 2);

                    if ($fmajor == $this->prefix) {
                        $fromheaders[$fminor] = $fvalue;
                    }
                }

                return $fromheaders;
            }
        }

        return @$_SESSION['contextvariables'][CONTEXT][$this->prefix] ?: [];
    }

    public static function get($name)
    {
        return @static::$library[$name];
    }

    public static function getAll()
    {
        return @static::$library;
    }

    public static function put($name, $object)
    {
        static::$library[$name] = $object;
    }

    public static function dump()
    {
        $function = implode('_', ['var', 'dump']);

        $function(static::$library);
    }
}
