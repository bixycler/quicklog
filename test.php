<?php
if($argc >= 2){ 
  if($argv[1]=="GET"){
    // local cmd: `php data.php -- id=1a a[]=2 a[]=3` => URL `data.php?id=1a&a[]=2&a[]=3`
    parse_str(implode('&', array_slice($argv, 2)), $_GET); // clearer view on cmd line
  }else{
    // local cmd: `php data.php "id=1a&a[]=2&a[]=3"` => URL `data.php?id=1a&a[]=2&a[]=3`
    parse_str($argv[1], $_GET); // easy to debug on both cmd line and URL
  }
}
header("Content-Type:application/json");

if(true){ //DEBUG
  echo "Command line: ";
  print_r($argv); echo "\n";

  echo "GET: ";
  print_r($_GET); echo "\n";
  echo "POST: ";
  print_r($_POST); echo "\n";
  echo "_SERVER: ";
  print_r($_SERVER); echo "\n";
  echo "php://input: ";
  echo file_get_contents("php://input")."\n";

  echo "OS info: \n";
  echo "  Family: ".PHP_OS_FAMILY."\n";
  echo "  Host: ".php_uname()."\n";
  echo "\n";
}

$id = $_GET['id'];
$result = [];
if(empty($id) || intval($id)==0){
  $result = ['ERROR'=>'Invalid id: "'.$id.'"'];
}else{
  $json = file_get_contents("logs/log.json");
  $logs = json_decode($json, true);
  $result = $logs['entries'][$id];
  if(!$result){ $result = ['ERROR'=>'No entry with id="'.$id.'"']; }
}
echo json_encode($result);

?>