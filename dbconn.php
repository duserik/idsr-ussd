<?php
# FileName="Connection_php"
# Type="MYSQL"
# HTTP="true"
$hostname_connection = "localhost:3306";
$database_connection = "eidsr_rwanda";
$username_connection = "root";
$password_connection = "mysql";
$connection = mysqli_connect($hostname_connection, $username_connection, $password_connection,$database_connection) or trigger_error(mysql_error(),E_USER_ERROR);
if (mysql_select_db($database_connection))
{
  //print "Connected";
}
else
{ // or 
 trigger_error(mysql_error(),E_USER_ERROR);
 //print "Not Connected";
}

//mysqli connection for multiple query execution
global $mysqliLink;
$mysqliLink = mysqli_connect($hostname_connection, $username_connection, $password_connection, $database_connection);

//PostgreSQL Connection to DHIS2 Database
global $pg_link;
$pg_link = pg_Connect("host=pgserver dbname=idsr user=idsr password=v65ULz0VVL");
?>
