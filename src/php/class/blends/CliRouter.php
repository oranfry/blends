<?php
namespace blends;

class CliRouter extends \Router
{
    protected static $routes = [
        'CLI collisions \S+ \S+' =>     [null, 'MAX', 'TABLE', 'PAGE' => 'cli/collisions', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'none'],
        'CLI collisions \S+' =>         [null, 'MAX', 'TABLE' => null, 'PAGE' => 'cli/collisions', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'none'],
        'CLI export' =>                 [null, 'PAGE' => 'cli/export', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'onetime'],
        'CLI import' =>                 [null, 'PAGE' => 'cli/import', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'onetime'],
        'CLI expunge-tokens' =>         [null, 'PAGE' => 'cli/expunge-tokens', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'onetime'],
        'CLI reset-schema' =>           [null, 'PAGE' => 'cli/reset-schema', 'LAYOUT' => 'cli', 'AUTHSCHEME' => 'onetime'],
        'CLI h2n \S+ \S+' =>            [null, 'TABLE', 'H', 'PAGE' => 'cli/h2n', 'AUTHSCHEME' => 'none', 'LAYOUT' => 'cli'],
        'CLI n2h \S+ \S+' =>            [null, 'TABLE', 'N', 'PAGE' => 'cli/n2h', 'AUTHSCHEME' => 'none', 'LAYOUT' => 'cli'],
   ];
}
