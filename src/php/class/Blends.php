<?php
class Blends
{
    public static $verified_tokens = [];

    public static function validate_username($username)
    {
        return
            is_string($username)
            &&
            strlen($username) > 0
            &&
            (
                preg_match('/^[a-z0-9_]+$/', $username)
                ||
                filter_var($username, FILTER_VALIDATE_EMAIL) !== false
            )
            ;
    }

    public static function validate_password($password)
    {
        return
            is_string($password)
            &&
            strlen($password) > 5
            ;
    }

    public static function login($username, $password, $one_time = false)
    {
        $dbtable = @Config::get()->tables['user'];

        if (!$dbtable) {
            error_response('User table not set up', 500);
        }

        if (!static::validate_username($username)) {
            error_response('Invalid username');
        }

        if (!static::validate_password($password)) {
            error_response('Invalid password');
        }

        if (defined('ROOT_USERNAME') && $username == ROOT_USERNAME) {
            if (!defined('ROOT_PASSWORD')) {
                error_response('Root username is set up without a root password');
            }

            if ($password !== ROOT_PASSWORD) {
                return;
            }

            if (!static::validate_password(ROOT_PASSWORD)) {
                error_response('Root password is set to an invalid value and so cannot be used to log in');
            }

            $users = [['username' => $username]];
        } else {
            $stmt = Db::prepare("select * from {$dbtable} where username = :username and password is not null and password = sha2(concat(:password, `salt`), 256)");            $result = $stmt->execute([
                'username' => $username,
                'password' => $password,
            ]);

            if (!$result) {
                error_response('Login Error (1)', 500);
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!count($users)) {
            return;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(32));
        $user = (object) reset($users);
        $token_object = (object)['username' => $user->username, 'token' => $token];

        static::$verified_tokens[$token] = $token_object; // we are authorised before the token even hits the db

        if (!$one_time) {
            $token_objects = Linetype::load('token')->save($token, [$token_object]);

            if (!count($token_objects)) {
                error_response('Login Error (2)', 500);
            }
        }

        return $token;
    }

    public static function token_username($token)
    {
        if (!static::verify_token($token)) {
            return;
        }

        return static::$verified_tokens[$token]->username;
    }

    public static function verify_token($token)
    {
        $dbtable = @Config::get()->tables['token'];

        if (!$dbtable) {
            error_response('Login Error (1)', 500);
        }

        if (isset(static::$verified_tokens[$token])) {
            return true;
        }

        $stmt = Db::prepare("update {$dbtable} set used = current_timestamp, hits = hits + 1 where token = :token and used + interval ttl second >= current_timestamp");
        $result = $stmt->execute([
            'token' => $token,
        ]);

        if (!$result) {
            error_response('Token Verification Error (1)' . implode(' - ', $stmt->errorInfo()), 500);
        }

        if (!$stmt->rowCount()) {
            return;
        }

        $stmt = Db::prepare("select username from {$dbtable} where token = :token");
        $result = $stmt->execute([
            'token' => $token,
        ]);

        if (!$result) {
            error_response('Token Verification Error (2)' . implode(' - ', $stmt->errorInfo()), 500);
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!count($users)) {
            error_response('Token Verification Error (3)' . implode(' - ', $stmt->errorInfo()), 500);
        }

        if (count($users) > 1) {
            error_response('Token Verification Error (4)' . implode(' - ', $stmt->errorInfo()), 500);
        }

        $user = reset($users);

        $token_object = (object)['token' => $token, 'username' => $user['username']];

        static::$verified_tokens[$token] = $token_object;

        return true;
    }

    public static function logout($token)
    {
        Linetype::load('token')->delete($token, [(object)[
            'field' => 'token',
            'cmp' => '=',
            'value' => $token
        ]]);
    }
}
