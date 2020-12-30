<?php
namespace blends;

class HttpRouter extends \Router
{
    protected static $routes = [
        /***************************************
         *                AUTH                 *
         ***************************************/

        // login
        'POST /auth/login' => ['PAGE' => 'api/login', 'AUTHSCHEME' => 'none', 'LAYOUT' => 'json'],

        // logout
        'POST /auth/logout' => ['PAGE' => 'api/logout', 'AUTHSCHEME' => 'header', 'LAYOUT' => 'json'],

        // touch token
        'GET /touch' => ['PAGE' => 'api/touch', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        /***************************************
         *                LINE                 *
         ***************************************/

        // save
        'POST /([a-z]+)' => ['LINETYPE_NAME', 'PAGE' => 'api/line/save', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
        'POST /([a-z]+)/add' => ['LINETYPE_NAME', 'PAGE' => 'api/line/add', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
        'CLI save \S+ \S+ \S+' => [null, 'USERNAME', 'PASSWORD', 'LINETYPE', 'PAGE' => 'cli/save', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'onetime'],

        // read
        'GET /([a-z]+)/([A-Z0-9]+)' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'api/line/index', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
        'GET /([a-z]+)/([A-Z0-9]+)/html' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'api/line/html', 'AUTHSCHEME' => 'header'],
        'GET /([a-z]+)/([A-Z0-9]+)/pdf' => ['LINETYPE_NAME', 'LINE_ID', 'PAGE' => 'api/line/pdf', 'AUTHSCHEME' => 'header'],

        // delete
        'DELETE /([a-z]+)' => ['LINETYPE_NAME', 'PAGE' => 'api/line/delete', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // unlink
        'POST /([a-z]+)/([A-Z0-9]+)/unlink/([a-z]+_[a-z]+)' => ['LINETYPE_NAME', 'LINE_ID', 'PARNT', 'PAGE' => 'api/line/unlink', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // meta
        'GET /([a-z]+)/info' => ['LINETYPE_NAME', 'PAGE' => 'api/line/info', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
        'GET /([a-z]+)/suggested' => ['LINETYPE_NAME', 'PAGE' => 'api/line/suggested', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // print
        'POST /([a-z]+)/print' => ['LINETYPE_NAME', 'PAGE' => 'api/line/print', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        /***************************************
         *                BLEND                *
         ***************************************/

        // read
        'GET /blend/([a-z]+)/search' => ['BLEND_NAME', 'PAGE' => 'api/blend/index', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
        'GET /blend/([a-z]+)/summary' => ['BLEND_NAME', 'PAGE' => 'api/blend/summary', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // update
        'POST /blend/([a-z]+)/update' => ['BLEND_NAME', 'PAGE' => 'api/blend/update', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // delete
        'DELETE /blend/([a-z]+)' => ['BLEND_NAME', 'PAGE' => 'api/blend/delete', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // meta
        'GET /blend/([a-z]+)/info' => ['BLEND_NAME', 'PAGE' => 'api/blend/info', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
        'GET /blend/list' => ['PAGE' => 'api/blend/list', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        // print
        'POST /blend/([a-z]+)/print' => ['BLEND_NAME', 'PAGE' => 'api/blend/print', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],

        /***************************************
         *               FILES                 *
         ***************************************/

        'GET /download/(.*)' => ['FILE', 'PAGE' => 'api/download'],
        'GET /file/(.*)' => ['FILE', 'PAGE' => 'api/file', 'LAYOUT' => 'json', 'AUTHSCHEME' => 'header'],
   ];
}
