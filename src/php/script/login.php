<?php
if (@$_POST['auth'] == Config::get()->password) {
    $_SESSION['AUTH'] = Config::get()->password;
}

if (@$_SESSION["AUTH"] == Config::get()->password) {
    header("Location: /blend/" . @Config::get()->blends[0]);
    die('Redirecting...');
}

if (isset($_POST['auth'])) {
    $message = "Incorrect auth key";
}
?>
<!DOCTYPE html>
<html style="height: 100%; text-align: center; margin: 0">
<head>
    <title>Blends</title>
    <meta http-equiv="Content-type" content="text/html;charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" href="/icon.png">
    <link rel="stylesheet" type="text/css" href="/css/styles.<?= latest('css') ?>.css">
</head>

<body class="login-page">
    <div class="middle">
        <div class="middle-inner" style="position: relative">
            <div class="cols">
                <div class="col">
                    <h1 class="mobile-only">Blends</h1>
                    <div style="display: inline-block; background: url(/img/logo.png) center center; width: 200px; height: 200px; background-size: cover"></div>
                </div>
                <div class="col">
                    <form action="" method="post">
                            <div style="display: inline-block; text-align: left; margin: 1em 0">
                            <h1 class="desktop-only">Blends</h1>
                                <div class="cred-line">
                                    <p>Auth Key</p>
                                    <input type="text" name="auth" id="auth" autocomplete="off">
                                </div>
                                <div class="cred-line">
                                    <input type="submit" value="Proceed">
                                </div>

                                <?php if (isset($message) && $message): ?>
                                <div class="cred-line">
                                    <p class="error"><?= $message ?></p>
                                </div>
                                <?php endif ?>
                            </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>document.getElementById('auth').focus();</script>
</body>
</html>
