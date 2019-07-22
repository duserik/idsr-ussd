<?php

function logdata($content) {
    $content = $content . PHP_EOL;
    $LOG_DIR = "logs/";
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_USER_AGENT']))
        $agent = $_SERVER['HTTP_USER_AGENT'];
    else
        $agent = 'Client';
    $date = date("Ymd");
    $date2 = date("Y m d H:i");
    $file = $LOG_DIR . "ussd-$date.txt";
    file_put_contents($file, "$date2 $ip $agent $content", FILE_APPEND);
}

?>
