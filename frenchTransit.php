<?php
require("dbconn.php");

$sql = "SELECT * FROM Choices";
$query = mysql_query($sql);
while ($row = mysql_fetch_array($query)){
    $name = $row['Name'];
    $state = $row['State'];
    $newState = $row['NewState'];
    $choice = $row['Choice'];
    $nameFr = $name." (FR)";
    $update = "UPDATE Choices SET NameFr = '$nameFr' WHERE State = $state AND Choice = $choice AND NewState = $newState";
    mysql_query($update);
}


$sqlTitle = "SELECT * FROM Ussd_Menu_Title";
$queryTitle = mysql_query($sqlTitle);
while ($rowT = mysql_fetch_array($queryTitle)){
    $stateT = $rowT['State'];
    $title = $rowT['Title'];
    $titleFr = $title." (FR)";
    $update = "UPDATE Ussd_Menu_Title SET TitleFr = '$titleFr' WHERE State = $stateT";
    mysql_query($update);
}

echo "Process Completed.";


?>