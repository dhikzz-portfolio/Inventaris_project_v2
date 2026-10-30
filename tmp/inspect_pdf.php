<?php

$pdf = file_get_contents($argv[1]);
preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $m);
$i = 0;
foreach ($m[1] as $raw) {
    $data = @gzuncompress($raw);
    if ($data === false) {
        continue;
    }
    if (! preg_match('/BT| re|cm/', $data)) {
        continue;
    }
    $i++;
    echo "===== stream $i =====\n";
    echo substr($data, 0, 3000), "\n";
}
