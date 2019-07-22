<?php
$phone = "250781049609";
$sender = "eIDSR";
$txt = "Trying Kannel SMS from USSD Application";
$username = "SMPP218";
$password = "218SMPPxy";
$url = "http://localhost:14556/cgi-bin/sendsms?username=$username&password=$password&text=".urlencode($txt)."&from=$sender&to=$phone";
$output = file_get_contents($url);
echo $output;
?>
