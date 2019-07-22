<?php
//this is the script to disconnect connect to the database
if(isset($link)) mysql_close($link);
else mysql_close();
?>
