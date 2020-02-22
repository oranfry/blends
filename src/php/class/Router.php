<?php
class Router
{
    private static $routes = [
        '/blend/([a-z]+)/info' => ['BLEND_NAME', 'PAGE' => 'blend/info'],
        '/blend/([a-z]+)/search' => ['BLEND_NAME', 'PAGE' => 'blend/index'],
        '/blend/([a-z]+)/summaries' => ['BLEND_NAME', 'PAGE' => 'blend/summaries'],

        '/([a-z]+)/info' => ['LINETYPE_NAME', 'PAGE' => 'line/info'],
        '/([a-z]+)/suggested' => ['LINETYPE_NAME', 'PAGE' => 'line/suggested'],
        '/([a-z]+)/([0-9]+)' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'line/index'],
        '/([a-z]+)/save' => ['LINETYPE_NAME', 'LINE_ID' => null, 'PAGE' => 'line/save'],
        '/([a-z]+)/([0-9]+)/save' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'line/save'],
        '/([a-z]+)/([0-9]+)/([a-z]+)' => ['LINETYPE_NAME', 'LINE_ID', 'CHILDSET', 'PAGE' => 'line/childset'],

        '/tablelink/([a-z]+)/info' => ['TABLELINK_NAME', 'PAGE' => 'tablelink/info'],

        '/api/blend/([a-z]+)/delete' => ['BLEND_NAME', 'PAGE' => 'blend/delete'],
        '/api/blend/([a-z]+)/update' => ['BLEND_NAME', 'PAGE' => 'blend/update'],
        '/api/blend/([a-z]+)/print' => ['BLEND_NAME', 'PAGE' => 'blend/print'],

        '/([a-z]+)/([a-z]+)/add' => ['BLEND_NAME', 'LINETYPE_NAME', 'PAGE' => 'line/save', 'LINE_ID' => null, 'BULK_ADD' => true],
        '/([a-z]+)/([0-9]+)/html' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'line/html'],
        '/([a-z]+)/([0-9]+)/delete' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'line/delete'],
        '/([a-z]+)/([0-9]+)/unlink' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'line/unlink'],
        '/([a-z]+)/([0-9]+)/print' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'line/print'],
        '/download/(.*)' => ['FILE', 'PAGE' => 'download'],
    ];

    public static function match($path)
    {
        foreach (static::$routes as $url => $params) {
            if (!preg_match("@^{$url}$@", $path, $groups)) {
                continue;
            }

            array_shift($groups);

            $page_params = [];

            foreach ($groups as $i => $group) {
                if (!isset($params[$i])) {
                    error_response('Routing error', 500);
                }

                $page_params[$params[$i]] = $group;
            }

            foreach ($params as $key => $value) {
                if (!is_int($key)) {
                    $page_params[$key] = $value;
                }
            }

            define('PAGE_PARAMS', $page_params);

            foreach ($page_params as $key => $value) {
                define($key, $value);
            }

            return true;
        }

        return false;
    }
}
