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
		return NULL;
	else
		return $data;
}

function process_response( $method_name, $params, $app_data)
{
	$phone = "default";
	$response = "";
	if ( (! $params[0]["MSISDN"]) || (! $params[0]["TransactionId"]) || (! $params[0]["TransactionTime"]) )
	{
		$response = array( 'faultCode'=>4001,'faultString'=>"A mandatory parameter was missing");
	}
	else
	{
		$phone = $params[0]["MSISDN"];
		$userdetails = userregistration($params[0]["MSISDN"]);
		if ( $userdetails != NULL ){
			$responseString = "Hello ".$userdetails['firstname']." ".$userdetails['Lastname']." Welcome to eIDSR Reporting.";
			$action = "notify";
		}else{
			$responseString = "Sorry, your phonenumber ".$params[0]["MSISDN"]." registered to access this application";
			$action = "end";
		}
		$response =array("TransactionId"=>$params[0]["TransactionId"],"TransactionTime"=>$params[0]["TransactionTime"],"USSDResponseString"=> $responseString,"action"=>$action);
	}
	$file = "allrequests-phones.txt";
        file_put_contents($file, "$phone \r\n", FILE_APPEND);
	return $response;
}

	$xmlrpc_server = xmlrpc_server_create();
	xmlrpc_server_register_method($xmlrpc_server, "handleUSSDRequest", "process_response");
	$request_xml = $HTTP_RAW_POST_DATA;
	$response = xmlrpc_server_call_method($xmlrpc_server, $request_xml, '');
	print $response;
	xmlrpc_server_destroy($xmlrpc_server);
?>
