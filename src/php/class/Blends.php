<?php
class Blends
{
    public static $verified_tokens = [];

    public static function login($username, $password)
    {
        $dbtable = @Config::get()->tables['user'];

        if (!$dbtable) {
            error_response('Login Error (1)', 500);
        }

        if (defined('ROOT_USERNAME') && $username == ROOT_USERNAME) {
            if ($password != ROOT_PASSWORD) {
                return;
            }

            $users = [['username' => $username]];
        } else {
            $stmt = Db::prepare("select * from {$dbtable} where username = :username and password = sha2(concat(:password, `salt`), 256)");
            $result = $stmt->execute([
                'username' => $username,
                'password' => $password,
            ]);

            if (!$result) {
                error_response('Login Error (2)', 500);
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!count($users)) {
            return;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(32));
        $user = (object) reset($users);

        static::$verified_tokens[] = $token; // we are authorised before the token even hits the db

        $token_objects = Linetype::load('token')->save($token, [(object)['username' => $user->username, 'token' => $token]]);

        if (!count($token_objects)) {
            error_response('Login Error (3)', 500);
        }

        return $token;
    }

    public static function verify_token($token)
    {
        $dbtable = @Config::get()->tables['token'];

        if (!$dbtable) {
            error_response('Login Error (1)', 500);
        }

        if (in_array($token, static::$verified_tokens)) {
            return true;
        }

        $stmt = Db::prepare("update {$dbtable} set used = current_timestamp, hits = hits + 1 where token = :token and used + interval ttl second >= current_timestamp");
        $result = $stmt->execute([
            'token' => $token,
        ]);

        if (!$result) {
            error_response('Token Verification Error ' . implode(' - ', $stmt->errorInfo()), 500);
        }

        if ($stmt->rowCount() > 0) {
            static::$verified_tokens[] = $token;
        }

        return $stmt->rowCount() > 0;
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
