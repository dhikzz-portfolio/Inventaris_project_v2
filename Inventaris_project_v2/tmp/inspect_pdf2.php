<?php

$pdf = file_get_contents($argv[1]);
preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $m);
foreach ($m[1] as $raw) {
    $data = @gzuncompress($raw);
    if ($data === false) {
        continue;
    }
    $lines = preg_split('/\r?\n/', $data);
    $keep = false;
    foreach ($lines as $i => $line) {
        $t = trim($line);
        if ($t === '') {
            continue;
        }
        if (preg_match('/^(BT|ET|q|Q|.*\bTf|.*\bTd|.*\bTD|.*\bTm|.*\bTj|.*\bTJ|.*\bDo|.*\bcm|.*\bre$|.*\bRG|.*\brg|.*\bw$|.*\bd0|.*\bl\b|.*\bm\b)/', $t)) {
            echo str_pad($i, 4), ': ', substr($t, 0, 160), "\n";
        }
    }
    echo "-----\n";
}
