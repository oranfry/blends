<?php
class Blends
{
    public static $verified_tokens = [];

    public static function login($username, $password)
    {
        if (defined('ROOT_USERNAME') && $username == ROOT_USERNAME) {
            if ($password == ROOT_PASSWORD) {
                $users = [['username' => $username]];
            }
        } else {
            $stmt = Db::prepare("select * from record_user where username = :username and password = sha2(concat(:password, `salt`), 256)");
            $result = $stmt->execute([
                'username' => $username,
                'password' => $password,
            ]);

            if (!$result) {
                error_response('Login Error ' . Db::error(), 500);
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!count($users)) {
            return;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(32));
        $user = (object) reset($users);
        $stmt = Db::prepare("insert into record_accesstoken (username, token) values (:username, :token)");
        $result = $stmt->execute([
            'username' => $user->username,
            'token' => $token,
        ]);

        if (!$result) {
            error_response('Login Error ' . Db::error(), 500);
        }

        return $token;
    }

    public static function verify_token($token)
    {
        if (in_array($token, static::$verified_tokens)) {
            return true;
        }

        $stmt = Db::prepare("update record_accesstoken set used = current_timestamp, hits = hits + 1 where token = :token and used + interval ttl second >= current_timestamp");
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
        $stmt = Db::prepare("delete from record_accesstoken where token = :token");
        $result = $stmt->execute([
            'token' => $token,
        ]);

        if (!$result) {
            error_response('Logout Error ' . Db::error(), 500);
        }
    }
}
