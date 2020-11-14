<?php
class BlendsConfig
{
    private static $lookup = [];

    public function get($token = null)
    {
        if (isset(static::$lookup[$token])) {
            return static::$lookup[$token];
        }

        if (!$token) {
            $entrypoint = 'base';
        } elseif (defined('ROOT_USERNAME') && Blends::token_username($token) == ROOT_USERNAME) {
            $entrypoint = 'root';
        } else {
            $entrypoint = 'public';
        }

        $subsimple_config = Config::get();

        if (!property_exists($subsimple_config, 'entrypoints')) {
            error_response('Entrypoints not defined');
        }

        if (!isset($subsimple_config->entrypoints[$entrypoint])) {
            error_response("Entrypoint {$entrypoint} not defined");
        }

        $config_class = $subsimple_config->entrypoints[$entrypoint];
        $config = new $config_class();

        foreach (['linetypes', 'blends', 'tables'] as $listname) {
            if (!property_exists($config, $listname)) {
                $config->{$listname} = [];
            }
        }

        if (!in_array('user', array_keys($config->tables))) {
            error_response('User table not defined');
        }

        if (!in_array('user', array_keys($config->linetypes))) {
            $config->linetypes['user'] = (object) [
                'canwrite' => true,
                'class' => 'blends\\linetype\\user',
            ];
        }

        if (!in_array('token', array_keys($config->linetypes))) {
            $config->linetypes['token'] = (object) [
                'cancreate' => true,
                'canwrite' => true,
                'candelete' => true,
                'class' => 'blends\\linetype\\token',
            ];
        }

        if (!in_array('users', array_keys($config->blends))) {
            $config->blends['users'] = 'blends\\blend\\users';
        }

        if (!in_array('tokens', array_keys($config->blends))) {
            $config->blends['tokens'] = 'blends\\blend\\tokens';
        }

        static::$lookup[$token] = $config;

        return $config;
    }
}
