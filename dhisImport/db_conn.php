<?php
//this is the script to connect to the database
@$host="mysql"; //you can put an IP Address if it is the remote host
@$user="idsr";
@$password="68wJBhGsZNLJDBBT";
@$database="eIDSR_Rwanda";
@$link=mysql_connect($host,$user,$password) or die("Could not connect to mysql<br>".mysql_error());
        if(!$link){
        echo "The connection failed!";
        }
@mysql_select_db($database,$link) or die ("Can not select the database<br>".mysql_error());

?>
