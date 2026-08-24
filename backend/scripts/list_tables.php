<?php
$data = json_decode(file_get_contents(__DIR__ . '/schema_dump.json'), true);
$keys = array_keys($data);
sort($keys);
foreach ($keys as $i => $k) {
    echo ($i + 1) . ". $k\n";
}
