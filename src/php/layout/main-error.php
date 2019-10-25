<style>
    .backtrace-line {
        border: 1px solid #977;
        background-color: #fee;
        padding: 1em;
        white-space: pre;
        font-family: monospace;
    }
    .backtrace-line + .backtrace-line {
        margin-top: 1em;
    }
</style>
<h1><?= $code ?></h1>
<pre style="font-size: 1.4em"><?php if (is_string($error)) {
    echo $error;
} else {
    var_export($error);
} ?></pre>

<?php foreach (debug_backtrace() as $i => $bt) {
    echo '<div class="backtrace-line">';

    if (@$bt['file']) {
        echo $bt['file'] . ':' . $bt['line'] . "\n";
    }

    if (@$bt['function']) {
        echo "    " . $bt['function'];

        if (isset($bt['args'])) {
            echo "(";

            foreach ($bt['args'] as $i => $arg) {
                echo($i ? ', ' : '') . var_export($arg, 1);
            }

            echo ")";
        }
    }
    echo '</div>';
}
