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
        $processedId = "";
        $xml = '<dataValueSet xmlns="http://dhis2.org/schema/dxf/2.0">' . PHP_EOL;
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

            if ($report_group == "Weekly Reporting") {
                $caseU5 = $arrMenu['caseU5'];
                $caseA5 = $arrMenu['caseA5'];
                $deathU5 = $arrMenu['deathU5'];
                $deathA5 = $arrMenu['deathA5'];
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboCaseU5 . '" period="' . $periodWeek . '" orgUnit="' . $OU . '" value="' . $caseU5 . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboCaseA5 . '" period="' . $periodWeek . '" orgUnit="' . $OU . '" value="' . $caseA5 . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboDeathU5 . '" period="' . $periodWeek . '" orgUnit="' . $OU . '" value="' . $deathU5 . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboDeathA5 . '" period="' . $periodWeek . '" orgUnit="' . $OU . '" value="' . $deathA5 . '"/>' . PHP_EOL;
            } elseif ($report_group == "Immediate Notifiable Diseases") {
                $case = $arrMenu['cases'];
                $death = $arrMenu['deaths'];
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboCase . '" period="' . $periodWeek . '" orgUnit="' . $OU . '" value="' . $case . '"/>' . PHP_EOL;
                $xml .= '<dataValue dataElement="' . $dataelementUID . '" categoryOptionCombo="' . $optionComboDeath . '" period="' . $periodWeek . '" orgUnit="' . $OU . '" value="' . $death . '"/>' . PHP_EOL;
            }
        }//end while
        $xml .= '</dataValueSet>';

        logdata('general_logs', 'log/', $report_group . ' to be sent to DHIS, XML: ' . $xml . PHP_EOL . PHP_EOL);
        //create xml file
        $fp = fopen('weeklydata.xml', 'w');
        fwrite($fp, $xml);
        fclose($fp);

        $dataUrl = $dhisUrl . "/api/dataValueSets";
        $dhisOutput = addslashes(exec('curl -d @weeklydata.xml "' . $dataUrl . '" -H "Content-Type:application/xml" -u ' . $dhisUser . ':' . $dhisKey . ' -v'));
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
        $updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus' WHERE id IN ($processedId)";
        logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
        $updateMenu = mysql_query($updateMenuSQL);
    }//end if $num
    //End of Weekly DHIS Import
    //get Submit data from db and form xml and import to DHIS
    $selSubmitMenu = "SELECT * from menu_details WHERE status='Complete' AND report_group = 'Submit Weekly Report'";
    $querySubmitMenu = mysql_query($selSubmitMenu);
    $numSubmitMenu = mysql_num_rows($querySubmitMenu);
    if ($numSubmitMenu) {
        $dataSetId = "vjALB8uFBxk";
        while ($arrMenu = mysql_fetch_array($querySubmitMenu)) {
            $id = $arrMenu['id'];
            $msisdn = $arrMenu['msisdn'];
            $report_group = $arrMenu['report_group'];
            $weekYear = $arrMenu['week_year'];
            $weekNo = $arrMenu['week_no'];
            $periodWeek = $weekYear . "W" . $weekNo;
            $date_recorded = $arrMenu['date_recorded'];
            $completeDate = date("Y-m-d", strtotime($date_recorded));
            $OU = $arrMenu['dhis_orgunit_uid'];

            $checkWeeklySQL = "SELECT * FROM menu_details WHERE dhis_orgunit_uid = '$OU' AND week_no = $weekNo AND week_year = $weekYear AND report_group = 'Weekly Reporting' AND status = 'SENT2DHIS-SUCCESS'";
            $checkWeeklyQuery = mysql_query($checkWeeklySQL);
            $numCheckWeekly = mysql_num_rows($checkWeeklyQuery);
            if ($numCheckWeekly) {
                $xml = '<dataValueSet xmlns="http://dhis2.org/schema/dxf/2.0" dataSet="' . $dataSetId . '" completeDate="' . $completeDate . '" period="' . $periodWeek . '" orgUnit="' . $OU . '">' . PHP_EOL;
                $xml .= '</dataValueSet>';

                logdata('general_logs', 'log/', $report_group . ' to be sent to DHIS, XML: ' . $xml . PHP_EOL . PHP_EOL);
                //create xml file
                $fp = fopen('submitWeeklydata.xml', 'w');
                fwrite($fp, $xml);
                fclose($fp);

                $dataUrl = $dhisUrl . "/api/dataValueSets";
                $dhisOutput = addslashes(exec('curl -d @submitWeeklydata.xml "' . $dataUrl . '" -H "Content-Type:application/xml" -u ' . $dhisUser . ':' . $dhisKey . ' -v'));
                logdata('general_logs', 'log/', $report_group . ' Response XML from DHIS: ' . $dhisOutput . PHP_EOL . PHP_EOL);
                $dhisResponse = getResponseAfterPush2_DHIS($dhisOutput, $report_group);

                if ($dhisResponse == "SUCCESS") {
                    $updateStatus = "SENT2DHIS-SUCCESS";
                    logdata('general_logs', 'log/', $report_group . 'Data Successfully imported to the DHIS with response ' . $dhisResponse);
                    $smsTxt = "Your report for Week ".$weekNo." of ".$weekYear." has been sumitted succesfully to DHIS. Ref:".$id;
                } else {
                    $updateStatus = "SENT2DHIS-FAIL";
                    logdata('general_logs', 'log/', $report_group . 'Data was not imported into the DHIS with an error ' . $dhisResponse);
                    $smsTxt = "Your report for Week ".$weekNo." of ".$weekYear." failed to be sumitted due to technical reasons, please contact your system administrator. Ref:".$id;
                }
                $updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus' WHERE id = $id";
                logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
                $updateMenu = mysql_query($updateMenuSQL);
                sendSMS($msisdn, $smsTxt, $updateStatus);
            } else {
                $updateStatus = "INVALID-SUBMIT";
                $updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus' WHERE id = $id";
                logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
                $updateMenu = mysql_query($updateMenuSQL);
                $smsTxt = "INVALID Report submission for Week ".$weekNo." of ".$weekYear.". Make sure you report both weekly and immediate notifiable reports.";
                sendSMS($msisdn, $smsTxt, $updateStatus);
            }
        }
    }
    //End of Submit Completed to DHIS
    //get Immediate Reporting data from db and form json and send to DHIS
    $selImmediateMenu = "SELECT * from menu_details WHERE status='Complete' AND report_group = 'Immediate Reporting'";
    $queryImmediateMenu = mysql_query($selImmediateMenu);
    $numImmediateMenu = mysql_num_rows($queryImmediateMenu);
    if ($numImmediateMenu) {
        while ($arrMenu = mysql_fetch_array($queryImmediateMenu)) {
            $id = $arrMenu['id'];
	    $msisdn = $arrMenu['msisdn'];
            $report_group = $arrMenu['report_group'];
            $disease = array('attribute' => 'uOTHyxNv2W4', 'value' => $arrMenu['disease_name']);
            $duringOutbreak = array('attribute' => 'qkQCA6ieVyu', 'value' => $arrMenu['during_outbreak']);
            $outbreakCode = array('attribute' => 'rK06rQeRu6V', 'value' => $arrMenu['outbreak_code']);
            $patientId = array('attribute' => 'JgbTeRB32lX', 'value' => $arrMenu['patient_id']);
            $patiendDob = array('attribute' => 'A31FfrjPqyp', 'value' => $arrMenu['patient_dob']);
            $patientGender = array('attribute' => 'Rq4qM2wKYFL', 'value' => $arrMenu['patient_gender']);
            $patientPhone = array('attribute' => 'E7u9XdW24SP', 'value' => $arrMenu['patient_mobile']);
            $residenceSector = array('attribute' => 'iBB5ejHjJbC', 'value' => $arrMenu['residence_sector']);
            $originSector = array('attribute' => 'LJ6wn7NsWnR', 'value' => $arrMenu['origin_sector']);
            $classification = array('attribute' => 'bt06ynPCyFd', 'value' => $arrMenu['patient_classification']);
            $caseType = array('attribute' => 'CJUpWSk36TQ', 'value' => $arrMenu['type_of_case']);
            $sampleTaken = array('attribute' => 'NuRldDwq0AJ', 'value' => getBoolean($arrMenu['sample_taken']));
            $vacinated = array('attribute' => 'WypSLyCzzlH', 'value' => $arrMenu['patient_vaccinated']);
            $numberDose = array('attribute' => 'yfFaRjKClwe', 'value' => $arrMenu['number_doses']);
            $lastVaccineDate = array('attribute' => 'hG66PSsqVkf', 'value' => $arrMenu['date_last_vaccination']);
            $patientOutcome = array('attribute' => 'i6A3z9QQEBt', 'value' => $arrMenu['patient_outcome']);
            $dateDeath = array('attribute' => 'WjRkZqn8Mjk', 'value' => $arrMenu['date_of_death']);
            $patientStatus = array('attribute' => 'gmNerbTgJcz', 'value' => $arrMenu['patient_status']);
            $attributes = array($disease, $duringOutbreak, $outbreakCode, $patientId, $patiendDob, $patientGender, $patientPhone, $residenceSector, $originSector, $classification, $caseType, $sampleTaken, $vacinated, $numberDose, $lastVaccineDate, $patientOutcome, $dateDeath, $patientStatus);
            $trackedEntitiy = "j9TllKXZ3jb";
            $dataarray = array('trackedEntity' => $trackedEntitiy, 'orgUnit' => $arrMenu['dhis_orgunit_uid'], 'attributes' => $attributes);
            $dataJson = json_encode($dataarray);
            logdata('general_logs', 'log/', $report_group . " TrackedEntityInstance" . ' to be sent to DHIS, JSON: ' . $dataJson . PHP_EOL . PHP_EOL);
            $fp = fopen('tei.json', 'w');
            fwrite($fp, $dataJson);
            fclose($fp);

            $dataUrl = $dhisUrl . "/api/trackedEntityInstances";
            $dhisOutput = exec('curl -d @tei.json "' . $dataUrl . '" -H "Content-Type:application/json" -u ' . $dhisUser . ':' . $dhisKey . ' -v');
            logdata('general_logs', 'log/', $report_group . " TrackedEntityInstance" . ' Response JSON from DHIS: ' . $dhisOutput . PHP_EOL . PHP_EOL);
            $dhisResults = json_decode($dhisOutput, true);
            if ($dhisResults['status'] == "SUCCESS") {
                $program = "U86iDWxDek8";
                $trackedEntityInstace = $dhisResults['reference'];
                $symptomDate = $arrMenu['date_symptoms'];
                $consultationDate = $arrMenu['date_consultation'];
                $enrolArray = array('trackedEntityInstance' => $trackedEntityInstace, 'program' => $program, 'dateOfEnrollment' => $consultationDate, 'dateOfIncident' => $symptomDate);
                $enrolJson = json_encode($enrolArray);
                logdata('general_logs', 'log/', $report_group . " Enrollment" . ' to be sent to DHIS, JSON: ' . $enrolJson . PHP_EOL . PHP_EOL);
                $fp = fopen('enrol.json', 'w');
                fwrite($fp, $enrolJson);
                fclose($fp);
                $dataUrl = $dhisUrl . "/api/enrollments";
                $enrolOutput = exec('curl -d @enrol.json "' . $dataUrl . '" -H "Content-Type:application/json" -u ' . $dhisUser . ':' . $dhisKey . ' -v');
                logdata('general_logs', 'log/', $report_group . " Enrollment" . ' Response JSON from DHIS: ' . $enrolOutput . PHP_EOL . PHP_EOL);
                $enrolResults = json_decode($enrolOutput, true);
                if ($enrolResults['status'] == "SUCCESS") {
                    logdata('general_logs', 'log/', $report_group . 'Data Successfully imported to the DHIS with response ' . $enrolResults['status']);
                    $updateStatus = "SENT2DHIS-SUCCESS";
                    $enrollment = $enrolResults['reference'];
                    $updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus', trackedEntityInstance_ref = '$trackedEntityInstace', enrollment_ref = '$enrollment' WHERE id = $id";
                    logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
                    $updateMenu = mysql_query($updateMenuSQL);
                    $smsTxt = "Your immediate report has been submitted sucessfully to DHIS2. Thank you. Ref:".$id;
                    sendSMS($msisdn, $smsTxt, $updateStatus);
                } else {
                    $updateStatus = "SENT2DHIS-FAIL-PART";
                    logdata('general_logs', 'log/', $report_group . 'Enrollment was not succesful into the DHIS with an error ' . $enrolResults['status']);
                    $updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus', trackedEntityInstance_ref = '$trackedEntityInstace' WHERE id = $id";
                    logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
                    $updateMenu = mysql_query($updateMenuSQL);
                    $smsTxt = "Your immediate report has failed to be sumitted due to technical reasons, please contact your system administrator. Ref:".$id;
                    sendSMS($msisdn, $smsTxt, $updateStatus);
                }
            } else {
                $updateStatus = "SENT2DHIS-FAIL";
                $errorMessage = addslashes($dhisResults['conflicts'][0]['value']);
                logdata('general_logs', 'log/', $report_group . 'TrackedEntityInstance Registration not succesful into the DHIS with an error ' . $errorMessage);
                $updateMenuSQL = "UPDATE menu_details SET status = '$updateStatus', dhisErrorResponse = '$errorMessage' WHERE id = $id";
                logdata('general_logs', 'log/', $report_group . ' UPDATE menudetails SQL : ' . $updateMenuSQL . PHP_EOL . PHP_EOL);
                $updateMenu = mysql_query($updateMenuSQL);
                $smsTxt = "Your immediate report has failed to be sumitted due to technical reasons, please contact your system administrator. Ref:".$id;
                sendSMS($msisdn, $smsTxt, $updateStatus);
            }
        }
    }
    //End of Immediate Reporting Import to DHIS

//disconect db
    include('db_disconn.php');
    usleep(1000000 * $intervalSeconds);
//
} while (true)
?>
