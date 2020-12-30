<?php
$token = Blends::login(USERNAME, PASSWORD, true);
$definitions = build_table_definitions($token);

foreach (array_merge(array_keys($definitions['tablelink']), array_keys($definitions['record'])) as $table) {
    Db::succeed("drop table if exists `{$table}`");
}

foreach (array_merge(array_values($definitions['record']), array_values($definitions['tablelink'])) as $definition) {
    Db::succeed($definition);
}

return [
    'definitions' => $definitions,
];
