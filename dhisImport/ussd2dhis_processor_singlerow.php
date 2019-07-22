<?php

include('functions.php');

ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
ignore_user_abort(true); // run script in background
set_time_limit(0); // run script forever
$intervalSeconds = 10; // interval in seconds

do {
    //connect to db
    include('db_conn.php');
    $serverSQL = "SELECT * FROM dhis_server WHERE id = 1";
    $serverQuery = mysql_query($serverSQL);
    $server = mysql_fetch_array($serverQuery);
    $dhisUrl = $server['dhisUrl'];
    $dhisUser = $server['dhisUser'];
    $dhisKey = $server['dhisKey'];
    //get weekly data from db and form xml and Import to DHIS
    $selWeeklyMenu = "SELECT * from menu_details, dhis_de_mapping WHERE status='Complete' AND menu_details.disease_name = dhis_de_mapping.diseaseName AND (report_group = 'Weekly Reporting' OR report_group = 'Immediate Notifiable Diseases')";
    $queryWeeklyMenu = mysql_query($selWeeklyMenu);
    $numWeeklyMenu = mysql_num_rows($queryWeeklyMenu);
    if ($numWeeklyMenu) {
        //DHIS CategoryOptionCombo UID
        $optionComboCaseU5 = "tYLXCbn1U0D";
        $optionComboCaseA5 = "PTYcwJq28Dr";
        $optionComboDeathU5 = "WhNme5LyjuK";
        $optionComboDeathA5 = "g1c0hd0q4Vv";
        $optionComboCase = "r5nYXFbbX2w";
        $optionComboDeath = "RYE9mqF4lLQ";
        $dataSetId = "vjALB8uFBxk";
        $processedId = "";
        //$xml = '<dataValueSet xmlns="http://dhis2.org/schema/dxf/2.0">' . PHP_EOL;
        //loop in all new customer selections
        while ($arrMenu = mysql_fetch_array($queryWeeklyMenu)) {
            $msisdn = $arrMenu['msisdn'];
            $report_group = $arrMenu['report_group'];
            $id = $arrMenu['id'];
            $processedId .= $id . ",";
            $date_recorded = $arrMenu['date_recorded'];
            $completeDate = date("Y-m-d", strtotime($date_recorded));
            $year = date("Y", strtotime($date_recorded));
            $weekYear = $arrMenu['week_year'];
            $weekNo = $arrMenu['week_no'];
            $periodWeek = $weekYear . "W" . $weekNo;
            $OU = $arrMenu['dhis_orgunit_uid'];
            $userid = $arrMenu['dhis_user_uid'];
            $diseaseName = $arrMenu['disease_name'];
            $dataelementUID = $arrMenu['dhisDeUID'];
            $diseaseGroupName = $arrMenu['disease_group'];
			$xml = '<dataValueSet xmlns="http://dhis2.org/schema/dxf/2.0" dataSet="'.$dataSetId.'" period="' . $periodWeek . '" orgUnit="' . $OU . '">' . PHP_EOL;
            if ($report_group == "Weekly Reporting") {
                $caseU5 = $arrMenu['caseU5'];
                $caseA5 = $arrMenu['caseA5'];
                $deathU5 = $arrMenu['deathU5'];
                $deathA5 = $arrMenu['deathA5'];
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboCaseU5 . '" value="' . $caseU5 . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboCaseA5 . '" value="' . $caseA5 . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboDeathU5 . '" value="' . $deathU5 . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboDeathA5 . '" value="' . $deathA5 . '"/>' . PHP_EOL;
            } elseif ($report_group == "Immediate Notifiable Diseases") {
                $case = $arrMenu['cases'];
                $death = $arrMenu['deaths'];
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboCase . '" value="' . $case . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboDeath . '" value="' . $death . '"/>' . PHP_EOL;
            }
            $xml .= '</dataValueSet>';
            logdata('general_logs', 'log/', $report_group . ' to be sent to DHIS, XML: ' . $xml . PHP_EOL . PHP_EOL);
			//create xml file
			$fp = fopen('weeklySingleData.xml', 'w');
			fwrite($fp, $xml);
			fclose($fp);
			$dataUrl = $dhisUrl . "/api/dataValueSets";
			$dhisOutput = addslashes(exec('curl -d @weeklySingleData.xml "' . $dataUrl . '" -H "Content-Type:application/xml" -u ' . $dhisUser . ':' . $dhisKey . ' -v'));
			logdata('general_logs', 'log/', $report_group . ' Response XML from DHIS: ' . $dhisOutput . PHP_EOL . PHP_EOL);
			$dhisResponse = getResponseAfterPush2_DHIS($dhisOutput, $report_group);

			if ($dhisResponse == "SUCCESS") {
				$updateStatus = "SENT2DHIS-SUCCESS";
				logdata('general_logs', 'log/', $report_group . 'Data Successfully imported to the DHIS with response ' . $dhisResponse);
			} else {
				$updateStatus = "SENT2DHIS-FAIL";
				logdata('general_logs', 'log/', $report_group . 'Data was not imported into the DHIS with an error ' . $dhisResponse);
			}
			$processedId = rtrim($processedId, ",");
			$updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus' WHERE id = ".$id;
			logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
			$updateMenu = mysql_query($updateMenuSQL);
        }//end while

    }//end if $num
    //End of Weekly DHIS Import


//disconect db
    include('db_disconn.php');
    usleep(1000000 * $intervalSeconds);
//
} while (true)
?>
