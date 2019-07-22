<?php

require("dbconn.php");
/*Function to check if mobile phone is register to access USSD
Returns data if registered, otherwise null */
function userregistration( $msisdn)
{
	$sql = "SELECT * FROM Users WHERE msisdn = '".$msisdn."'";
	$query = mysql_query($sql);
	$data = mysql_fetch_array($query);
	if (mysql_num_rows($query) == 0)
		return false;
	else
		return true;
}

function process_response( $method_name, $params, $app_data)
{
	$response = "";
	if ( (! $params[0]["MSISDN"]) || (! $params[0]["TransactionId"]) || (! $params[0]["TransactionTime"]) )
	{
		$response = array( 'faultCode'=>4001,'faultString'=>"A mandatory parameter was missing");
	}
	else
	{
		$userdetails = userregistration($params[0]["MSISDN"]);
		if ( $userdetails != NULL ){
			$responseString = "Hello ".$userdetails['firstname']." ".$userdetails['Lastname']." Welcome to eIDSR Reporting.";
			$action = "notify";
		}else{
			$responseString = "Sorry, you are not registered to access this application";
			$action = "end";
		}
		$response =array("TransactionId"=>$params[0]["TransactionId"],"TransactionTime"=>$params[0]["TransactionTime"],"USSDResponseString"=> $responseString,"action"=>$action);
	}
	return $response;
}

	//$xmlrpc_server = xmlrpc_server_create();
	//xmlrpc_server_register_method($xmlrpc_server, "handleUSSDRequest", "process_response");
	$request = $_GET['phone'];
        
        if (userregistration($request)){
            $response = "You are registered!";
        }else{
            $response = "NOT Registered";
        }
        echo $response;
        //Log all recieved requests
        $file = "stresstest.txt";
        file_put_contents($file, "$request_xml :: $response", FILE_APPEND);
        
	//$response = xmlrpc_server_call_method($xmlrpc_server, $request_xml, '');
	//print $response;
	//xmlrpc_server_destroy($xmlrpc_server);
?>