<?php
// Version 1.2
include 'passwords.php';
$errorHandler = fopen("error.txt", "a") or die ("unable to open error log");
$logFile = fopen("log.txt", "w+	") or die("unable to open log file");
$groupDetailsIDs = array("87", "130", "156", "1", "2", "17", "29", "5", "13", "277", "37", "16", "12", "69", "25", "157", "202", "14", "278", "114", "26", "38", "42", "272", "273", "4");



//45 Cloud Provision 
//61 MS Cloud Provision
//60 Cloud Instance Upgrade
//59 Cloud Reload
//63 MS Cloud Reload
//123 Disk Image Cloud Provision
//67 NetScaler Upgrade

set_time_limit(0);
date_default_timezone_set('America/Chicago');

//timestamp
//date("l jS \of F Y h:i:s A", time()) . PHP_EOL	

$baseQueue = [];
$baseQueue = grabQueue($_SESSION['queueLink'], "tmp\base.htm");

scanQueue($baseQueue);

function scanQueue($theArray){
	global $errorHandler, $groupDetailsIDs, $logFile;
	$recentQueue = array();
	$recentQueue = grabQueue($_SESSION['queueLink'], "tmp\\recent.htm");	

	//search for any new or overdue items
	foreach ($theArray as $key => $value) {
		$recentQueueKey = recursive_array_search($value["txnID"], $recentQueue);
		if($recentQueueKey !== false ) {
			if($recentQueue[$recentQueueKey][0] === $theArray[$key][0]){
				unset($recentQueue[$recentQueueKey]);
			} else {
				if ($recentQueue[$recentQueueKey][0] !== "FF0000") {
					$theArray[$key][0] = $recentQueue[$recentQueueKey][0];
					unset($recentQueue[$recentQueueKey]);
				} else {
					$recentQueue[$recentQueueKey]["overdue"] = true;
				}
			}
		} else {
			unset($value);
		}
		unset($recentQueueKey);
	}

	//remove irrelivant items from queue
	foreach ($recentQueue as $key => $queueValues) {
		$deleteThisItem = true;
		foreach ($groupDetailsIDs as $IDToTest) {
			if($queueValues["groupID"] == $IDToTest){
				$deleteThisItem = false;
				break;
			}
		}
		if ($deleteThisItem == true) {
			unset($recentQueue[$key]);
		}
		unset($deleteThisItem);
		unset($IDToTest);
	}
	unset($queueValues);	

	$updatedArray = [];
	//Do Something if there is anything relevent to report
	if (is_multiArrayEmpty($recentQueue) != true) {

		$theArrayPreMergeLog = fopen("baseQueuePreMergeLog.txt", "a");
		fwrite($theArrayPreMergeLog, date("l jS \of F Y h:i:s A", time()) . PHP_EOL);
		fwrite($theArrayPreMergeLog, var_export($theArray, true) . PHP_EOL);
		fclose($theArrayPreMergeLog);

		//update theArray
		$updatedArray = array_merge($theArray, $recentQueue);

		//record keeping
		//end record keeping
		$theArrayLog = fopen("baseQueueLog.txt", "a");
		fwrite($theArrayLog, date("l jS \of F Y h:i:s A", time()) . PHP_EOL);
		fwrite($theArrayLog, var_export($updatedArray, true) . PHP_EOL);
		fclose($theArrayLog);	

		$recentQueueLog = fopen("recentQueueLog.txt", "a");
		fwrite($recentQueueLog, date("l jS \of F Y h:i:s A", time()) . PHP_EOL);
		fwrite($recentQueueLog, var_export($recentQueue, true) . PHP_EOL);
		fclose($recentQueueLog);	

		sendText($recentQueue);
		unset($recentQueue);
		$recentQueue = null;
		scanQueue($updatedArray);
	}
	
	unset($recentQueue);
	$recentQueue = null;
	scanQueue($theArray);
}

function recursive_array_search($needle,$haystack) {
    foreach($haystack as $key=>$value) {
        $current_key=$key;
        if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
            return $current_key;
        }
    }
    return false;
}

function is_multiArrayEmpty($multiarray) { 
    if(is_array($multiarray) and !empty($multiarray)){ 
        $tmp = array_shift($multiarray); 
            if(!is_multiArrayEmpty($multiarray) or !is_multiArrayEmpty($tmp)){ 
                return false; 
            } 
            return true; 
    } 
    if(empty($multiarray)){ 
        return true; 
    }
    return false;
} 

function grabQueue($url, $file) {
	global $errorHandler, $logFile;
	//cURL call
	$fp = fopen($file, "w+");
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_COOKIEFILE, "tmp\cookieFile.txt");
	curl_setopt($ch, CURLOPT_COOKIEJAR, "tmp\cookieFile.txt");
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FILE, $fp);

	$buf2 = curl_exec ($ch);

	curl_close ($ch);
	unset($ch);
	fclose($fp);

    $dom = new DOMDocument();
    @$dom->loadHTMLFile($file);
    $queue = [];
	$queue = extractTxnIDs($dom);
	
	if (empty($queue)) {
		echo PHP_EOL . "error fetching queue $file " . date("l jS \of F Y h:i:s A", time()) . PHP_EOL;
		fwrite($errorHandler, PHP_EOL . "error fetching queue $file " . date("l jS \of F Y h:i:s A", time()) . PHP_EOL);
	}
	return $queue;
}

function extractTxnIDs($dom){
	global $errorHandler;
	$returnArray = [];
	$searchNode = $dom->getElementsByTagName("tr");

	foreach( $searchNode as $node ) 
	{
		//determine group
	    $links = $node->getElementsByTagName("a");
	    foreach ($links as $href) {
	    	$groupAttribute = $href->getAttribute('href');
	    	if(strpos($groupAttribute, "groupDetails") !== false){
	    		preg_match("/[0-9]{1,}/", $groupAttribute, $groupCategoryMatches);
	    		$info["groupID"] = array_shift($groupCategoryMatches);
	    		$info["groupName"] = $href->nodeValue;
	    	}
	    }

	    //determine overdue status (overdue = array[0], deadline = array[1])
	    $backgroundColorNodes = $node->getElementsByTagName("td");
	    foreach ($backgroundColorNodes as $specificNode) {
	    	$style = $specificNode->getAttribute("style");
	    	if(strpos($style, "background-color") !== false){
	    		preg_match("/[0-9A-F]{6}/", $style, $overdueMatches);
	    		$info[] = array_shift($overdueMatches);
	    	}
	    		
	    }

	    //init ["overdue"]
	    $info["overdue"] = false; 
	    
	    //Assign txnIDs as key and attach info array
	    $txnAttribute = $node->getAttribute('name');
	    if (preg_match_all('/[0-9]{8}/', $txnAttribute, $txnMatches)){
	    	$info["txnID"] = (string)array_shift($txnMatches[0]);
	    	$returnArray[] = $info;
		}
		unset($info);
	}
	unset($node);
	unset($links);
	unset($txnAttribute);
	
	return $returnArray;
}

function sendText($payload) {
	global $errorHandler;
	require_once "Mail.php";

	$from = '<benjamin.kevin.fang@gmail.com>';
	$to = '<bfang@softlayer.com>, <gene.stalnaker@ibm.com>'; //, <tramos@softlayer.com>, <oarce@us.ibm.com>, <hcbrooks@us.ibm.com>, <hernandj@us.ibm.com>, <cim@us.ibm.com>'; // <mamorale@us.ibm.com>
	$subject = 'transaction queue';
	$body = "";

	foreach ($payload as $key => $val) {
		if(@$val["overdue"] === true){
			$body .= "OVERDUE! ";	
		}
		$body .= $val["groupName"] . ": ";
		$body .= " https://internal.softlayer.com/HardwareTransaction/viewTransactionDetails/" . $val["txnID"] . "/1" . PHP_EOL;  
	}
	unset($val);

	$headers = array(
	    'From' => $from,
	    'To' => $to,
	    'Subject' => $subject
	);

	$smtp = Mail::factory('smtp', array(
	        'host' => 'ssl://smtp.gmail.com',
	        'port' => '465',
	        'auth' => true,
	        'username' => 'benjamin.kevin.fang@gmail.com',
	        'password' => $_SESSION['googleApp'] 
	    ));

	$mail = $smtp->send($to, $headers, $body);

	if (PEAR::isError($mail)) {
	    echo PHP_EOL . $mail->getMessage() . date("l jS \of F Y h:i:s A", time()) . PHP_EOL;
	    fwrite($errorHandler, PHP_EOL . $mail->getMessage() . date("l jS \of F Y h:i:s A", time()) . PHP_EOL);
	}
}

?>