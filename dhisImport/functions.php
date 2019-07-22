<?php

function logdata($logType, $filePath, $content) {
    $content = $content . PHP_EOL . PHP_EOL;
    //$LOG_DIR = "logs/";
    $LOG_DIR = $filePath;
    $date = date("Ymd");
    $date2 = date("Y m d H:i");
    $file = $LOG_DIR . $logType . "-$date.txt";
    file_put_contents($file, "$date2 $content", FILE_APPEND);
}

function getResponseAfterPush2_DHIS($dhisOutput, $report_group) {
    if ($xml = @simplexml_load_string(stripslashes($dhisOutput))) {
        if ($report_group == 'Immediate reporting')
            return (string) $xml->importSummaryList->importSummary->status;
        else
            return (string) $xml->status;
    }
    else {
        preg_match("'<h1>(.*?)</h1>'si", $dhisOutput, $match);
        if ($match)
            return $match[1];
    }
}

/* Function to return boolena values from Yes/No
 * Accepts Yes/No, returns true/false
 */

function getBoolean($val) {
    if ($val == "Yes")
        return true;
    else
        return false;
}

/*
 * Function to Send SMS using Kannel HTTP
 */

function sendSMS($phone, $txt, $smsType) {
    $sender = "eIDSR";
    $username = "SMPP218";
    $password = "218SMPPxy";
    $url = "http://localhost:14556/cgi-bin/sendsms?username=$username&password=$password&text=" . urlencode($txt) . "&from=$sender&to=$phone";
    $output = file_get_contents($url);
    $reportSQL = "INSERT INTO sms_reports (sms_recepient,sms_msg,sms_type,smsgw_response)VALUE ('$phone','$txt','$smsType','$output')";
    mysql_query($reportSQL);
    //logdata('general_logs','log/',$reportSQL.PHP_EOL);
    logdata('general_logs','log/','SENT SMS summary: '.$phone.' | '.$sender.' | '.$txt.' | '.$output.PHP_EOL);
}
?>

