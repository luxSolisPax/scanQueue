<?php
//via command line
$username = $argv[1];
$password = $argv[2];
$externalToken = $argv[3];

//via webpage
/*$username = $_POST["username"]; 
$password = $_POST["password"]; 
$externalToken = $_POST["externalToken"]; */

$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, "tmp\cookieFile.txt");
curl_setopt($ch, CURLOPT_URL,"https://internal.softlayer.com");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "data[User][username]=$username&data[User][password]=$password&data[User][externalToken]=$externalToken");

ob_start();      // prevent any output
$result = curl_exec ($ch); // execute the curl command
ob_end_clean();  // stop preventing output

var_dump($result);

curl_close ($ch);

//echo "logged in"

//exec('php main.php');
?>