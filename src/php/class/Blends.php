<?php
class Blends
{
    public static function login()
    {
        $stmt = Db::prepare("select * from record_user where username = :username and password = sha2(concat(:password, `salt`), 256)");
        $result = $stmt->execute([
            'username' => @$_POST['username'],
            'password' => @$_POST['password'],
        ]);

        if (!$result) {
            error_response('Login Error ' . Db::error(), 500);
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $stmt = Db::prepare("select * from record_accesstoken where token = :token and `used` + interval `ttl` second >= now()");
        $result = $stmt->execute([
            'token' => $token,
        ]);

        if (!$result) {
            error_response('Token Verification Error ' . Db::error(), 500);
        }

        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!count($tokens)) {
            error_response('Invalid / expired token');
        }

        $stmt = Db::prepare("update record_accesstoken set used = current_timestamp where token = :token");
        $result = $stmt->execute([
            'token' => $token,
        ]);

        return true;
    }
}
