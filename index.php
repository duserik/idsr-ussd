<?php

date_default_timezone_set('Africa/Kigali');
require("dbconn.php");
include("logging.php");

/*
 * Function to check if mobile phone is register to access USSD
 * Returns true if registered, otherwise false 
 */

function userregistration($msisdn) {
    $sql = "SELECT * FROM Users WHERE msisdn = '" . $msisdn . "'";
    $query = mysql_query($sql);
    $data = mysql_fetch_array($query);
    if (mysql_num_rows($query) == 0)
        return 0;
    else
        return $data;
}

/*
 * Function to authenticate user, accepts username and password
 * Returns true for correct authentication otherwise returns false
 */

function authenticate($username, $password) {
    $msisdn = $username;
    $sql = "SELECT * FROM Users WHERE msisdn=$msisdn AND password=$password";
    $result = mysql_query($sql);
    if (mysql_num_rows($result) == 0) {
        return false;
    } else {
        return true;
    }
}

/*
 * Function to cancel session
 * Accepts session id, returns true if session is cancel otherwise false
 */

function cancelsession($session_id) {
    $query = "DELETE FROM CurrentState WHERE Session_id=$session_id";
    $result = mysql_query($query);

    if ($result) {
        return true;
    } else {
        return false;
    }
}

/*
 * Gets respective title for and appropriate state
 * Returns required title
 */

function display_title($title_state, $sessionid, $lang) {
    $display_title_query = "SELECT * FROM Ussd_Menu_Title  WHERE State=$title_state";
    $display_title_result = mysql_query($display_title_query);
    $display_title_lookup_num_rows = mysql_num_rows($display_title_result);
    if ($display_title_lookup_num_rows != 0) {
        $a_row = mysql_fetch_array($display_title_result);
        if ($lang == "FR") {
            $title = $a_row['TitleFr'];
        } else {
            $title = $a_row['Title'];
        }

        /* if (($title_state == 27) || ($title_state == 56)) {
          $title = "1";
          }

          if ($title == "1") {
          $title = "Disease Name: ";
          $query_opt4 = "select Option2,Option4,Option5,Option6,Option6,Option7,Option8 from Menu_Ussd_options where Sessionid=$sessionid limit 1";
          $query_opt4_res = mysql_query($query_opt4);
          $opt4 = mysql_fetch_array($query_opt4_res);

          if ((strcmp($opt4['Option2'], "Immediate reporting")) == 0) {
          $title .= $opt4['Option4'];
          } elseif ((strcmp($opt4['Option4'], "Malaria")) == 0) {
          $title .= $opt4['Option4'];
          } else {
          $title .= $opt4['Option5'];
          }
          }

          if ($title_state == 27) {
          $title .= "\n" . $a_row['Title'];
          }

          if ($title_state == 56) {
          if ((strcmp($opt4['Option4'], "Maternal Death")) == 0) {
          $title .= "\nPatient Age: " . $opt4['Option5'] . " years ";
          $title .= "\n\n" . $a_row['Title'];
          } else {
          $title .= "\nPatient Age: " . $opt4['Option7'] . " " . $opt4['Option6'];
          $title .= "\n\n" . $a_row['Title'];
          }
          }


          if ($title_state == 38) {
          $title = "Enter Patient Age (" . $opt4['Option6'] . ")";
          } */
    }
    return $title;
}

/*
 * Function to check whether to retain user old session
 * Returns 0 for sesson to be retained, 1 for sesstion retianed
 */

function retainsession($session_id, $ussd_msisdn1) {
    $mainstate = 1;
    $min1 = 2;
    $query1 = "SELECT * FROM Menu_Ussd_options WHERE Msisdn=$ussd_msisdn1 ORDER BY Time  desc limit 1";
    $result = mysql_query($query1);
    $num_row = mysql_num_rows($result);

    if ($num_row != 0) {
        $data = mysql_fetch_array($result);

        $oldsession = $data['Sessionid'];

        $date1 = date('Y-m-d H:i:s', strtotime("-$min1 minutes"));

        $query2 = "SELECT * FROM CurrentState WHERE Session_id=$oldsession AND csDate >= '$date1'";
        $result1 = mysql_query($query2);
        $num_row1 = mysql_num_rows($result1);

        if ($num_row1 != 0) {
            $data1 = mysql_fetch_array($result1);
            $currentstate = $data1['State'];

            if ($currentstate != $mainstate) {

                $query3 = "UPDATE CurrentState SET Session_id=$session_id WHERE Session_id=$oldsession";
                $query4 = "UPDATE Menu_Ussd_options SET Sessionid=$session_id WHERE Sessionid=$oldsession";

                $logd = "TRS: Session resume from $oldsession to $session_id | $ussd_msisdn1";
                logdata($logd);

                if ((mysql_query($query3)) && (mysql_query($query4))) {
                    $status = 1;
                } else {
                    $status = 0;
                }
            } else {
                $status = 0;
            }
        } else {
            $status = 0;
        }
    } else {
        $status = 0;
    }

    return $status;
}

/*
 * Function to validate user input to detect and errors
 * Accepts user input (message) and state id, returns true for correct input, false for incorrect
 */

function validateInput($state, $message) {
    global $initialSession;
    $output['correct'] = true;
    $output['errormsg'] = NULL;
    $year = date("Y");
    if(!$initialSession && !is_numeric($message)){
      $output['correct'] = false;
      $output['errormsg'] = "Invalid Input Type! \r\n";
    }elseif (!$initialSession && ($state == 3 || $state == 46 || $state == 89) && ($message < 1 || $message > 53)) {
        $output['correct'] = false;
        $output['errormsg'] = "Invalid ISO Week number! \r\n";
    } elseif (!$initialSession && ($state == 5 || $state == 47 || $state == 90) && ($message < $year - 1 || $message > $year)) {
        $output['correct'] = false;
        $output['errormsg'] = "Year out of allowed range! \r\n";
    } elseif (!$initialSession && ($state == 29 || $state == 36 || $state == 37 || $state == 41 || $state == 43) && (strlen($message) != 8 )) {
        $output['correct'] = false;
        $output['errormsg'] = "Please enter correct date formart! \r\n";
    } elseif (!$initialSession && ($state == 29 || $state == 36 || $state == 37 || $state == 41 || $state == 43) && (strlen($message) == 8 )) {
        $dd = intval(substr($message, 0,2));
        $mm = intval(substr($message, 2,2));
        if (($dd <= 0 || $dd > 31) || ($mm <= 0 || $mm > 12)){
            $output['correct'] = false;
            $output['errormsg'] = "Please enter correct date! \r\n";
        }
    } elseif (!$initialSession && ($state == 31) && (strlen($message) < 9 )) {
        $output['correct'] = false;
        $output['errormsg'] = "Invalid Phone number! \r\n";
    }
    return $output;
}

/*
 * Function to generate menu_details import to DHIS disease Name
 * Accepts Menu caption name, and return database DHIS name
 */

function getMappingDiseaseName($captionName) {
    switch ($captionName) {
        case "Acute Flaccid Paralysis":
            $diseaseName = "Acute Flaccid Paralysis";
            break;
        case "Bloody diarhoea (Shigellosis)":
            $diseaseName = "Diarrhea - bloody";
            break;
        case "Chicken Pox":
            $diseaseName = "Chicken Pox";
            break;
        case "Cholera":
            $diseaseName = "Cholera";
            break;
        case "Diphteria":
            $diseaseName = "Diphteria";
            break;
        case "Epidemic Typhus":
            $diseaseName = "Typhus epidemic";
            break;
        case "Food Poisoning":
            $diseaseName = "Food poisoning";
            break;
        case "Haemorrhagic Fever":
            $diseaseName = "Hemorragic fever";
            break;
        case "Measles":
            $diseaseName = "Measles";
            break;
        case "Meningococal Meningitis":
            $diseaseName = "Meningitis";
            break;
        case "Mumps":
            $diseaseName = "Mumps";
            break;
        case "Neonatal Tetanus":
            $diseaseName = "Neonatal tetanos";
            break;
        case "Plague":
            $diseaseName = "Plague";
            break;
        case "Rabies":
            $diseaseName = "Rabies";
            break;
        case "Rubella":
            $diseaseName = "Rubella";
            break;
        case "Typhoid Fever":
            $diseaseName = "Typhoid fever";
            break;
        case "Unknown disease":
            $diseaseName = "Unknown epidemic prone disease";
            break;
        case "Viral Conjunctivitis":
            $diseaseName = "Conjunctivitis viral";
            break;
        case "Whooping Cough":
            $diseaseName = "Whooping Cough";
            break;
        case "Yellow Fever";
            $diseaseName = "Yellow fever";
            break;
        default:
            $diseaseName = "NotFound";
    }
    return $diseaseName;
}

/*Function to return Data Formart from USSD int date
 * Accept date as entered to USSD and Returns yyyy-mm-dd date formart
 */
function getDateFormat($inputDate){
    $dd = substr($inputDate, 0,2);
    $mm = substr($inputDate, 2,2);
    $yyyy = substr($inputDate, 4,4);
    $outPutDate = $yyyy."-".$mm."-".$dd;
    return $outPutDate;
}

/*
 * Function to return main menu/landing page
 * Accepts sesstionId, phone number, and language. Returns Appropriate message from the database.
 */

function returnmainmenu($session_id, $ussd_msisdn1, $lang) {
    global $responseString;
    global $action;
    $main_menu_state = 1;
    $current_time = date("Y-m-d", time());
    $current_state_query = "INSERT INTO CurrentState(Session_id,State,Choice_control) VALUES ($session_id,$main_menu_state,0)";
    $reporting_query = "INSERT INTO Menu_Ussd_options(Sessionid,Msisdn,Status) VALUES ($session_id,$ussd_msisdn1,'Off')";
    $menu_lookup_query = "SELECT * FROM Choices WHERE State=$main_menu_state  ORDER BY Choice";
    $main_lookup_result = mysql_query($menu_lookup_query);
    $main_lookup_num_rows = mysql_num_rows($main_lookup_result);
    if ($main_lookup_num_rows != 0) {
        $i = 1;
        $title = display_title($main_menu_state, $session_id) . "\r\n";
        if ($main_lookup_num_rows > 1) {
            while ($a_row = mysql_fetch_array($main_lookup_result)) {
                if ($lang == "FR") {
                    $resultname = $a_row['NameFr'];
                } else {
                    $resultname = $a_row['Name'];
                }
                $name .= "$i. $resultname\r\n";
                $i++;
            }
        } else {
            $a_row = mysql_fetch_array($main_lookup_result);
            if ($lang == "FR") {
                $name = $a_row['NameFr'];
            } else {
                $name = $a_row['Name'];
            }
        }
        $responseString = $title . $name;
        $action = "request";
        mysql_query($reporting_query);
        mysql_query($current_state_query);

        $logd = "TRS: $session_id | $ussd_msisdn1 | 1 | Session Start";
        logdata($logd);
    }
}

/*
 * Function to return sub-menu based of users stage
 * Return appropriate state message from the database
 */

function returnsubmenu($session_id, $ussd_msisdn1, $message, $status, $lang) {
    global $responseString;
    global $action;

    /* if ($status == 1){
      $responseString = "Session is supposed to be returned here. NewSessionId = $session_id, Status = $status ";
      $action = "end";
      return;
      } */
    //check if subscriber has decided to move back
    if ($message == "###") {
        $result = moveback($session_id);
        if ($result == 1)
            exit;
    }

    if ($message == "##") {
        if (cancelsession($session_id)) {
            if ($lang == "FR") {
                $responseString = "Votre session a ete annulee. Merci";
            } else {
                $responseString = "Your session has been cancelled. Thank you!";
            }
            $action = "end";
            return;
        } else {
            if ($lang == "FR") {
                $responseString = "Annulation seance a echoue.";
            } else {
                $responseString = "Cancelling session Failed.";
            }
            $action = "end";
            return;
        }
    }

    $delete_current_state_query = "DELETE FROM CurrentState WHERE Session_id=$session_id";
    $obtain_current_state_query = "SELECT State,Choice_control,level,docode FROM CurrentState  WHERE session_id=$session_id";
    $obtain_current_state_result = mysql_query($obtain_current_state_query);

    $obtain_current_state_num_rows = mysql_num_rows($obtain_current_state_result);
    if ($obtain_current_state_num_rows != 0) {
        $a_row = mysql_fetch_array($obtain_current_state_result);
        $current_state = $a_row['State'];
        $choice_control = $a_row['Choice_control'];
        $currentdocode = $a_row['docode'];
        if ($status == 0) {
            $slevel = $a_row['level'] + 1;
        } else {
            $slevel = $a_row['level'];
            /* $responseString = "Session is supposed to be returned here. slevel = $slevel, Status = $status ";
              $action = "end";
              return; */
        }

        if (($choice_control == 0) || ($status == 1)) {
            $x = 0;
            $newstate_docode_query = "SELECT NewState,Docode FROM Choices WHERE State=$current_state";
            $reporting_query = "UPDATE Menu_Ussd_options SET Option$slevel='$message' WHERE Sessionid=$session_id";

            $logd = "TRS: $session_id | $ussd_msisdn1 | $slevel | $message";
            logdata($logd);
        } else {
            $x = 1234;
            $newstate_docode_query = "SELECT NewState,Docode,Name,NameFr FROM Choices WHERE State=$current_state AND Choice=$message";
        }
        $inputValidate = validateInput($current_state, $message);
        $newstate_docode_result = mysql_query($newstate_docode_query);
        $newstate_docode_num_rows = mysql_num_rows($newstate_docode_result);
        if ($newstate_docode_num_rows == 0 || !$inputValidate['correct']) {
            $errormsg = $inputValidate['errormsg'];
            $submenu_lookup_query1 = "SELECT Name,NameFr FROM Choices WHERE State=$current_state  ORDER BY Choice";

            $submenu_lookup_result1 = mysql_query($submenu_lookup_query1);
            $menu_options_count1 = mysql_num_rows($submenu_lookup_result1);

            $title = display_title($current_state, $session_id, $lang) . "\r\n";
            $i = 1;
            if ($menu_options_count1 > 1) {
                while ($a_row = mysql_fetch_array($submenu_lookup_result1)) {
                    if ($lang == "FR") {
                        $resultname = $a_row['NameFr'];
                    } else {
                        $resultname = $a_row['Name'];
                    }
                    $name .= "$i. $resultname\r\n";
                    $i++;
                }
            } else {
                $a_row = mysql_fetch_array($submenu_lookup_result1);
                if ($lang == "FR") {
                    $resultname = $a_row['NameFr'];
                } else {
                    $resultname = $a_row['Name'];
                }
                $name = $resultname;
            }
            $moreOptions = "\n#. Back \n ##. Cancel";
            $responseString = $errormsg . $title . $name;
            $action = "request";
            return;
        } else {
            $a_row = mysql_fetch_array($newstate_docode_result);
            $newstate = $a_row['NewState'];
            $docode = $a_row['Docode'];
            $name_current_state = $a_row['Name'];

            if ($status == 1) {
                $newstate = $current_state;
                $docode = $currentdocode;
            }
            if ($x != 0) {
                $reporting_query = "UPDATE Menu_Ussd_options SET Option$slevel='$name_current_state' WHERE Sessionid=$session_id";

                $logd = "TRS: $session_id | $ussd_msisdn1 | $slevel | $name_current_state";
                logdata($logd);
//More option is overridden
                if ((strcmp($name_current_state, "More")) == 0) {
                    $slevel = $slevel - 1;
                }
            }
            if ($status == 0) {
                mysql_query($reporting_query);
            }
            $submenu_lookup_query = "SELECT Name,NameFr FROM Choices WHERE State=$newstate  order by Choice";
            $submenu_lookup_result = mysql_query($submenu_lookup_query);
            $menu_options_count = mysql_num_rows($submenu_lookup_result);
            if ($menu_options_count == 1) {
                $choice_control = 0;
            } else {
                $choice_control = 1;
            }
            $update_current_state_query = "UPDATE CurrentState SET State=$newstate,Choice_control=$choice_control,level=$slevel,docode=$docode WHERE Session_id=$session_id";
            if (($docode == 1 || $docode == 3)) {
                if (($docode == 3) && ($status == 0)) {
                    $authstatus = authenticate($ussd_msisdn1, $message);
                    if (!$authstatus) {
                        if ($lang == "FR") {
                            $responseString = "Vous avez entre PIN incorrect";
                        } else {
                            $responseString = "You have entered Incorrect PIN";
                        }
                        $action = "end";
                        return;
                    }
                }

                $i = 1;
                $title = display_title($newstate, $session_id, $lang) . "\r\n";
                if ($menu_options_count > 1) {
                    while ($a_row = mysql_fetch_array($submenu_lookup_result)) {
                        if ($lang == "FR") {
                            $resultname = $a_row['NameFr'];
                        } else {
                            $resultname = $a_row['Name'];
                        }
                        $name .= "$i. $resultname\r\n";
                        $i++;
                    }
                } else {
                    $a_row = mysql_fetch_array($submenu_lookup_result);
                    if ($lang == "FR") {
                        $name = $a_row['NameFr'];
                    } else {
                        $name = $a_row['Name'];
                    }
                    /* if ($name != 81) {
                      print "$name\r\n";
                      $i++;
                      } else {

                      $name = confirmationstring($session_id);
                      print "$name\r\n";
                      $i++;
                      } */
                }
                $moreOptions = "\n#. Back \n ##. Cancel";
                $responseString = $title . $name;
                $action = "request";
                mysql_query($update_current_state_query);
            } elseif ($docode == 0) {
                $i = 1;
                $title = display_title($newstate, $session_id, $lang) . "\r\n";
                while ($a_row = mysql_fetch_array($submenu_lookup_result)) {
                    $data_trigger = 0;
                    $name = $a_row['Name'];
                    if ($name != "session_completed") {
                        $i++;
                        $data_trigger = 1;
                    } elseif ($message == 1) {
                        if ($lang == "FR") {
                            $responseString = "Les donnees ont ete transmises dans le systeme.";
                        } else {
                            $responseString = "Thanks data captured.";
                        }
                        $action = "end";
                        $data_trigger = 1;
                    } elseif ($message == 2) {
                        if ($lang == "FR") {
                            $responseString = "demande d'annulation";
                        } else {
                            $responseString = "Request cancelled";
                        }
                        $action = "end";
                    } else {
                        if ($lang == "FR") {
                            $responseString = "Option inconnue";
                        } else {
                            $responseString = "Unknown Option";
                        }
                        $action = "end";
                        return;
                    }
                }
                if (($ussd_msisdn1) && ($data_trigger == 1)) {
                    $confirmation_query = "Select * from Menu_Ussd_options where Sessionid=$session_id";
                    $confirm_result = mysql_query($confirmation_query);
                    logdata($confirmation_query);
					// code to get the useid amd orgaunituid from the dhis database
                    //$num = substr($ussd_msisdn1, 3, 9);
                    $pg_link = pg_Connect("host=pgserver dbname=idsr user=idsr password=v65ULz0VVL");
                    $num = $ussd_msisdn1;
                    $dhisCheckSQL = "select userinfo.surname,userinfo.firstname,userinfo.uid as userUID,userinfo.phonenumber, organisationunit.uid as orgunitUID, users.password as password  from userinfo,usermembership,organisationunit,users where userinfo.userinfoid = usermembership.userinfoid and usermembership.organisationunitid = organisationunit.organisationunitid and users.userid = userinfo.userinfoid and userinfo.phonenumber like '%$num' limit 1";
                    $result = pg_exec($pg_link, $dhisCheckSQL);
                    $numrows = pg_numrows($result);
                    $row = pg_fetch_array($result);

                    $useruid = $row['useruid'];
                    $orgunituid = $row['orgunituid'];
                    pg_close($pg_link);

                    $force = 0;

                    while ($a_row = mysql_fetch_array($confirm_result)) {
                        if (((strcmp($a_row['Option2'], "Weekly Reporting")) == 0)) {
                            $reportGroup = $a_row['Option2'];
                            $status = "Complete";
                            $week_no = $a_row['Option3'];
                            $week_year = $a_row['Option4'];
                            $diarrhoeaName = "Diarrhoea - non bloody";
                            $diarrhoeaCaseU5 = $a_row['Option5'];
                            $diarrhoeaCaseA5 = $a_row['Option6'];
                            $diarrhoeaDeathU5 = $a_row['Option7'];
                            $diarrhoeaDeathA5 = $a_row['Option8'];
                            $malariaName = "Malaria";
                            $malariaCaseU5 = $a_row['Option9'];
                            $malariaCaseA5 = $a_row['Option10'];
                            $malariaDeathU5 = $a_row['Option11'];
                            $malariaDeathA5 = $a_row['Option12'];
                            $pneumoniaName = "Pneumonia severe";
                            $pneumoniaCaseU5 = $a_row['Option13'];
                            $pneumoniaDeathU5 = $a_row['Option14'];
                            $fluName = "Flu syndrome";
                            $fluCaseU5 = $a_row['Option15'];
                            $fluCaseA5 = $a_row['Option16'];
                            $fluDeathU5 = $a_row['Option17'];
                            $fluDeathA5 = $a_row['Option18'];

                            $done_query = "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,week_no,week_year,caseU5,caseA5,deathU5,deathA5) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$reportGroup','$diarrhoeaName','$status',$week_no,$week_year,$diarrhoeaCaseU5,$diarrhoeaCaseA5,$diarrhoeaDeathU5,$diarrhoeaDeathA5);";
                            $done_query .= "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,week_no,week_year,caseU5,caseA5,deathU5,deathA5) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$reportGroup','$malariaName','$status',$week_no,$week_year,$malariaCaseU5,$malariaCaseA5,$malariaDeathU5,$malariaDeathA5);";
                            $done_query .= "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,week_no,week_year,caseU5,caseA5,deathU5,deathA5) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$reportGroup','$pneumoniaName','$status',$week_no,$week_year,$pneumoniaCaseU5,0,$pneumoniaDeathU5,0);";
                            $done_query .= "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,week_no,week_year,caseU5,caseA5,deathU5,deathA5) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$reportGroup','$fluName','$status',$week_no,$week_year,$fluCaseU5,$fluCaseA5,$fluDeathU5,$fluDeathA5);";
                        } elseif ((strcmp($a_row['Option2'], "Immediate Notifiable Diseases")) == 0) {
                            $reportGroup = $a_row['Option2'];
                            $status = "Complete";
                            $week_no = $a_row['Option3'];
                            $week_year = $a_row['Option4'];
                            if ($a_row['Option5'] == "Yes") {
                                $done_query = "";
                                for ($x = 5; $x < 50; $x = $x + 5) {
                                    if ($a_row['Option' . $x] == "Yes") {
                                        $diseasGroup = $a_row['Option' . ($x + 1)];
                                        $diseaseName = getMappingDiseaseName($a_row['Option' . ($x + 2)]);
                                        $cases = $a_row['Option' . ($x + 3)];
                                        $deaths = $a_row['Option' . ($x + 4)];
                                        if ($diseaseName != "NotFound") {
                                            $done_query .= "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,week_no,week_year,cases,deaths) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$diseasGroup','$diseaseName','$status',$week_no,$week_year,$cases,$deaths);";
                                        }
                                    } else {
                                        break;
                                    }
                                }
                            } else {
                                $diseasGroup = "Group1 (A - D)";
                                $diseaseName = "Acute Flaccid Paralysis";
                                $cases = 0;
                                $deaths = 0;
                                $done_query = "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,week_no,week_year,cases,deaths) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$diseasGroup','$diseaseName','$status',$week_no,$week_year,$cases,$deaths);";
                            }
                        } elseif ((strcmp($a_row['Option2'], "Immediate Reporting")) == 0) {
                            $reportGroup = $a_row['Option2'];
                            $status = "Complete";
                            $diseasGroup = $a_row['Option3'];
                            $diseaseName = $a_row['Option4'];
                            $outbreak = $a_row['Option5'];
                            if ($outbreak == "Yes") {//Reporting during outbreak
                                $outbreakCode = $a_row['Option6'];
                                $patientId = $a_row['Option7'];
                                $patientDOB = getDateFormat($a_row['Option8']);
                                $patientGender = $a_row['Option9'];
                                $mobile = $a_row['Option10'];
                                $residenceSector = $a_row['Option11'];
                                $originSector = $a_row['Option12'];
                                $classification = $a_row['Option13'];
                                $caseType = $a_row['Option14'];
                                $symptomsDate = getDateFormat($a_row['Option15']);
                                $consultationDate = getDateFormat($a_row['Option16']);
                                $sampleTaken = $a_row['Option17'];
                                $patientVaccinated = $a_row['Option18'];
                                if ($patientVaccinated == "Yes") {
                                    $numberDose = $a_row['Option19'];
                                    $dateLastVaccine = getDateFormat($a_row['Option20']);
                                    $patientOutcome = $a_row['Option21'];
                                    if ($patientOutcome == "Died") {
                                        $dateDeath = getDateFormat($a_row['Option22']);
                                        $patientStatus = NULL;
                                    } else {
                                        $dateDeath = NULL;
                                        $patientStatus = $a_row['Option22'];
                                    }
                                } else {
                                    $numberDose = NULL;
                                    $dateLastVaccine = NULL;
                                    $patientOutcome = $a_row['Option19'];
                                    if ($patientOutcome == "Died") {
                                        $dateDeath = getDateFormat($a_row['Option20']);
                                        $patientStatus = NULL;
                                    } else {
                                        $dateDeath = NULL;
                                        $patientStatus = $a_row['Option20'];
                                    }
                                }
                            } else {
                                $outbreakCode = NULL;
                                $patientId = $a_row['Option6'];
                                $patientDOB = getDateFormat($a_row['Option7']);
                                $patientGender = $a_row['Option8'];
                                $mobile = $a_row['Option9'];
                                $residenceSector = $a_row['Option10'];
                                $originSector = $a_row['Option11'];
                                $classification = $a_row['Option12'];
                                $caseType = $a_row['Option13'];
                                $symptomsDate = getDateFormat($a_row['Option14']);
                                $consultationDate = getDateFormat($a_row['Option15']);
                                $sampleTaken = $a_row['Option16'];
                                $patientVaccinated = $a_row['Option17'];
                                if ($patientVaccinated == "Yes") {
                                    $numberDose = $a_row['Option18'];
                                    $dateLastVaccine = getDateFormat($a_row['Option19']);
                                    $patientOutcome = $a_row['Option20'];
                                    if ($patientOutcome == "Died") {
                                        $dateDeath = getDateFormat($a_row['Option21']);
                                        $patientStatus = NULL;
                                    } else {
                                        $dateDeath = NULL;
                                        $patientStatus = $a_row['Option21'];
                                    }
                                } else {
                                    $numberDose = NULL;
                                    $dateLastVaccine = NULL;
                                    $patientOutcome = $a_row['Option18'];
                                    if ($patientOutcome == "Died") {
                                        $dateDeath = getDateFormat($a_row['Option19']);
                                        $patientStatus = NULL;
                                    } else {
                                        $dateDeath = NULL;
                                        $patientStatus = $a_row['Option19'];
                                    }
                                }
                            }

                            $done_query = "INSERT INTO menu_details(msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,disease_group,disease_name,status,during_outbreak,outbreak_code,patient_id,patient_dob,patient_gender,patient_mobile,residence_sector,origin_sector,patient_classification,type_of_case,date_symptoms,date_consultation,sample_taken,patient_vaccinated,number_doses,date_last_vaccination,patient_outcome,date_of_death,patient_status) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$diseasGroup','$diseaseName','$status','$outbreak','$outbreakCode','$patientId','$patientDOB','$patientGender','$mobile','$residenceSector','$originSector','$classification','$caseType','$symptomsDate','$consultationDate','$sampleTaken','$patientVaccinated','$numberDose','$dateLastVaccine','$patientOutcome','$dateDeath','$patientStatus' )";
                        } elseif ((strcmp($a_row['Option2'], "Submit Weekly Report")) == 0) {
                            $reportGroup = $a_row['Option2'];
                            $status = "Complete";
                            $week_no = $a_row['Option3'];
                            $week_year = $a_row['Option4'];

                            $done_query = "INSERT INTO menu_details (msisdn,date_recorded,dhis_user_uid,dhis_orgunit_uid,report_group,status,week_no,week_year) VALUES ('$ussd_msisdn1',CURRENT_TIMESTAMP,'$useruid','$orgunituid','$reportGroup','$status',$week_no,$week_year);";
                        } else {
                            //Implemente somthing here
                        }
//logdate
                        logdata($done_query);

                        if ($numrows == 1) {
                            $mysqliLink = mysqli_connect("mysql", "idsr", "68wJBhGsZNLJDBBT", "eIDSR_Rwanda");
                            mysqli_multi_query($mysqliLink, $done_query);
                            mysqli_close($mysqliLink);
                        }
                    }
                }
                mysql_query($delete_current_state_query);
//mysql_close($connection);
            } elseif ($docode == 2) {
                $i = 1;
                $title = display_title($newstate, $session_id, $lang) . "\r\n";
                while ($a_row = mysql_fetch_array($submenu_lookup_result)) {
                    $data_trigger = 0;
                    if ($message == 1) {
                        $updateLangSQL = "UPDATE Users SET Lang = 'EN' WHERE msisdn = '$ussd_msisdn1'";
                        mysql_query($updateLangSQL);
                        $responseString = "Thanks, You Language has been changed.";
                        $action = "end";
                        mysql_query($delete_current_state_query);
                        return;
                    } elseif ($message == 2) {
                        $updateLangSQL = "UPDATE Users SET Lang = 'FR' WHERE msisdn = '$ussd_msisdn1'";
                        mysql_query($updateLangSQL);
                        $responseString = "Merci, votre langue a ete modifiee.";
                        $action = "end";
                        mysql_query($delete_current_state_query);
                        return;
                    } else {
                        $responseString = "Unknown Option";
                        $action = "end";
                        mysql_query($delete_current_state_query);
                        return;
                    }
                }
            } else {
                $responseString = "Invalid Selection";
                $action = "end";
                mysql_query($delete_current_state_query);
            }
        }
    } else {
        $responseString = "Opps! Sorry and Error has occured, please try again later";
        $action = "end";
    }
}

/*
 * Function to generate and return new local session id
 * Accepts user phone number, returns local session id to be used to monitor user state
 */

function getNewLocalSession($msisdn) {
    $sessionQuery = "SELECT localsession FROM localsession WHERE id = 1";
    $sessionResult = mysql_query($sessionQuery);
    $sessionRow = mysql_fetch_array($sessionResult);
    $newSession = $sessionRow['localsession'] + 1;
    $updateSessionCountSQL = "UPDATE localsession SET localsession=$newSession WHERE id = 1";
    $updateSessionCountQuery = mysql_query($updateSessionCountSQL);
    $updateSessionSQL = "INSERT INTO localsession_msisdn VALUES ($newSession, '$msisdn', CURRENT_TIMESTAMP)";
    $updateSessionQuery = mysql_query($updateSessionSQL);
    return $newSession;
}

/*
 * Function to get local session ID from user response
 * Accepts user phonenumber, returns latest local session id
 */

function getLocalSession($msisdn) {
    $sessionSQL = "SELECT * FROM localsession_msisdn WHERE msisdn = $msisdn ORDER BY time  DESC LIMIT 1";
    $sessionQuery = mysql_query($sessionSQL);
    $sessionResult = mysql_fetch_array($sessionQuery);
    $localSession = $sessionResult['localsession'];
    return $localSession;
}

/*
 * Function to check if mobile phone number is registered to DHIS
 * Accepts mobile phone number, return number of times phonenumber has been registered
 */

function checkDHISRegistration($num) {
    $pg_link = pg_Connect("host=pgserver dbname=idsr user=idsr password=v65ULz0VVL");
    $checkSQL = "select userinfo.surname,userinfo.firstname,userinfo.uid as userUID,userinfo.phonenumber, organisationunit.uid as orgunitUID, users.password as password  from userinfo,usermembership,organisationunit,users where userinfo.userinfoid = usermembership.userinfoid and usermembership.organisationunitid = organisationunit.organisationunitid and users.userid = userinfo.userinfoid and userinfo.phonenumber like '%$num'";
    $result = pg_exec($pg_link, $checkSQL);
    $numrows = pg_num_rows($result);
    pg_close($pg_link);
    return $numrows;
}

/*
 * Function to get user language setting.
 * Accepts users phone number and returns language se
 */

function process_response($method_name, $params, $app_data) {
    global $responseString;
    global $action;
    global $initialSession;
    $response = "";
    if ((!$params[0]["MSISDN"]) || (!$params[0]["TransactionId"]) || (!$params[0]["TransactionTime"])) {
        $response = array('faultCode' => 4001, 'faultString' => "A mandatory parameter was missing");
    } else {
        //echo gettype( $params[0]["response"]);exit;
        $ussd_msisdn = $params[0]["MSISDN"];
        //$ussd_sessionid = $params[0]["MSISDN"];
        $ussd_msg = $params[0]["USSDRequestString"];
        if ($params[0]["response"] == "false") { //User is dialing the code for the first time
            $initialSession = true;
            $userReg = userregistration($ussd_msisdn);
            if ($userReg != 0) {
                //Check phone number registration in DHIS2
                $dhisReg = checkDHISRegistration($ussd_msisdn);
                if ($dhisReg > 1) {
                    $responseString = "Sorry, you phone number has been registered more than once in DHIS2, please contact system adminitrator.";
                    $action = "end";
                } elseif ($dhisReg == 0) {
                    $responseString = "Sorry, you phone number is not registered in DHIS2, please contact system adminitrator.";
                    $action = "end";
                } else {
                    $lang = $userReg['Lang'];
                    $ussd_sessionid = getNewLocalSession($ussd_msisdn);
                    $status = retainsession($ussd_sessionid, $ussd_msisdn);
                    $logd = "RTS returned status: $ussd_sessionid | $ussd_msisdn  " . $status;
                    logdata($logd);
                    if ($status == 0) {
                        returnmainmenu($ussd_sessionid, $ussd_msisdn, $lang);
                    } else {
                        returnsubmenu($ussd_sessionid, $ussd_msisdn, $ussd_msg, $status, $lang);
                    }
                }
            } else {
                $rejectSQL = "INSERT INTO rejected_phones (msisdn, time) VALUES ('$ussd_msisdn', CURRENT_TIMESTAMP)";
                $rejectQuery = mysql_query($rejectSQL);
                $responseString = "Sorry, you are not registered to access this application";
                $action = "end";
            }
        } else { //User is responding to the menu
            $status = 0;
            $userDetails = userregistration($ussd_msisdn);
            $lang = $userDetails['Lang'];
            $ussd_sessionid = getLocalSession($ussd_msisdn);
            returnsubmenu($ussd_sessionid, $ussd_msisdn, $ussd_msg, $status, $lang);
        }
        $response = array("TransactionId" => $params[0]["TransactionId"], "TransactionTime" => $params[0]["TransactionTime"], "USSDResponseString" => $responseString, "action" => $action);
    }
    return $response;
}

$xmlrpc_server = xmlrpc_server_create();
xmlrpc_server_register_method($xmlrpc_server, "handleUSSDRequest", "process_response");
$request_xml = file_get_contents("php://input");
//Log Requests
$fileRequest = "requests.txt";
file_put_contents($fileRequest, "$request_xml", FILE_APPEND);
$response = xmlrpc_server_call_method($xmlrpc_server, $request_xml, '');
//Log Requests
$fileResponse = "responses.txt";
file_put_contents($fileResponse, "$response", FILE_APPEND);
print $response;
xmlrpc_server_destroy($xmlrpc_server);
?>
