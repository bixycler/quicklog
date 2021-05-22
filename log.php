<?php
require 'RESTful.php';

$args = $argv;
if($args && count($args) >= 3){ array_shift($args);
  $_SERVER['REQUEST_METHOD'] = array_shift($args);
  $_SERVER['PATH_INFO'] = array_shift($args);
  $_SERVER['INPUT'] = implode(" ", $args);
}

if(false){ //DEBUG
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

function json_encode_utf8($obj){
  return json_encode($obj,JSON_UNESCAPED_UNICODE);
}
class logapi extends RESTful {

  protected $sql = null;
  protected $sql_host = "sql303.epizy.com";
  protected $sql_user = "epiz_26251837";
  protected $sql_pass = "8iMFi94qjod5";
  protected $sql_port = 3306;
  protected $sql_db = "epiz_26251837_quicklog";

  protected $logs = null;

	public function __construct(){
    $this->api_methods = array('GET', 'POST', 'PUT', 'DELETE', 'UPDATE');
		parent::__construct();
    $this->sql = new mysqli($this->sql_host, $this->sql_user, $this->sql_pass, $this->sql_db, $this->sql_port);
    if($this->sql->connect_error){ $this->response(self::HTTP_CODE['ServerError'], '"MySQL connection error: '.$this->sql->connect_error.'"'); }
    // Notes: 
    //  - First connection is very slow!
    //  - Enable ext/php_mysqli.dll in php.ini: extension_dir = "ext" extension=mysqli
    //  - max_user_connections = 9 // total open connections per user
    //    <phpMyAdmin<SQL: SHOW VARIABLES LIKE 'max_user_connections';
    //    <phpMyAdmin<server_variables.php: "max user connections"
    if(false){//DEBUG
      // check on phpMyAdmin: SHOW VARIABLES LIKE '%character%'; SHOW VARIABLES LIKE '%collation%'; 
      echo 'MySQL character set: ' . $this->sql->character_set_name() . "\n";
      // the connection's charset is set by the server: character_set_server = latin1 (even though we've set character_set_database = utf8mb4)
    }
    // so we must manually set charset for this connection!
    if(!$this->sql->set_charset('utf8mb4')){
      $this->response(self::HTTP_CODE['ServerError'], '"MySQL setting character set utf8mb4 error: '.$this->sql->error.'"'); }
    $json = file_get_contents("logs.json");
    $this->logs = json_decode($json);
    if($this->sql && !$this->logs){ $this->updateMetadata(); }
    if(!$this->logs){ $this->response(self::HTTP_CODE['ServerError'], '"Failed loading metadata: logs.json"'); }
	}

  /**
   * Process entry or entries
   */
	protected function entry(){
    // check $id
    if(in_array($this->method, ['GET','PUT','DELETE'])){
      $id = $this->params[0];
      if(!$id || !intval($id) || 't'.intval($id) != 't'.$id){
        $this->response(self::HTTP_CODE['NotFound'], '"Invalid id: \"'.$id.'\""');  
      }
    }
    // process entry with $id
    //// READ //////////////////////////////////////////////////////
		if($this->method == 'GET'){ 
      $entry = $this->sql->query("SELECT json FROM logs WHERE id = $id");
      if(false){//DEBUG
        echo "SQL query result: "; print_r($entry); echo "\n";
      }
      if($entry && $entry->num_rows){ 
        $this->data = $entry->fetch_object()->json;
        $entry->close();
      }else{ $this->response(self::HTTP_CODE['NotFound'], '"No entry with id = \"'.$id.'\""'); }
      $this->response();
      /*
      $json = file_get_contents("logs/log.json");
      $logs = json_decode($json, true);
      $data = $logs['entries'][$id];
      if(!$data){ $this->response(self::HTTP_CODE['NotFound'], '"No entry with id = \"'.$id.'\""'); }
      $this->response(self::HTTP_CODE['OK'], $data, false);  
      */
		}
    //// APPEND //////////////////////////////////////////////////////
    elseif($this->method == 'POST'){ 
      $id = $this->params[0];
      $maxId = $this->logs->entry_maxId;
      if(!$id){ $id = $maxId+1; } 
      if($id != $maxId+1){ $this->response(self::HTTP_CODE['BadRequest'], 
        "\"POST entry with id = $id mismatches with maxId+1 = $maxId+1\""); 
      }
      $json = $this->sql->real_escape_string($this->data);
      $success = $this->sql->query("INSERT INTO logs (id, json) VALUES ($id, '$json')");
      if($success){ 
        $this->logs->entry_maxId = $id;
        $success = file_put_contents('logs.json', json_encode_utf8($this->logs));
        if(!$success){ $this->response(self::HTTP_CODE['ServerError'], '"INSERT to #'.$id.' successfully, but failed to write metadata: logs.json"'); }
        $this->data = '"INSERT successfully to #'.$id.'"'; $this->response(); 
      }
      else{ $this->response(self::HTTP_CODE['ServerError'], '"INSERT to #'.$id.' failed: '.$this->sql->error.'"'); }
		}
    //// UPDATE //////////////////////////////////////////////////////
    elseif($this->method == 'PUT'){ 
      $entry = $this->sql->query("SELECT json FROM logs WHERE id = $id");
      if($entry && $entry->num_rows){ $entry->close();
      }else{ $this->response(self::HTTP_CODE['NotFound'], '"No entry with id = \"'.$id.'\""'); }
      $json = $this->sql->real_escape_string($this->data);
      $success = $this->sql->query("UPDATE logs SET json = '$json' WHERE id = $id");
      if(!$success){ $this->response(self::HTTP_CODE['ServerError'], '"PUT entry #'.$id.' failed: '.$this->sql->error.'"'); }
      $this->data = '"PUT entry #'.$id.' successfully"'; $this->response();
    }
    //// REMOVE //////////////////////////////////////////////////////
    elseif($this->method == 'DELETE'){ 
      $entry = $this->sql->query("SELECT json FROM logs WHERE id = $id");
      if($entry && $entry->num_rows){ $entry->close();
      }else{ $this->response(self::HTTP_CODE['NotFound'], '"No entry with id = \"'.$id.'\""'); }
      $success = $this->sql->query("DELETE FROM logs WHERE id = $id");
      if($success){ 
        if($minmax=$this->_metadata_db()){
          $this->logs->entry_minId = intval($minmax[0]);
          $this->logs->entry_maxId = intval($minmax[1]);
        }else{/*WARNING*/}
        $success = file_put_contents('logs.json', json_encode_utf8($this->logs));
        if(!$success){ $this->response(self::HTTP_CODE['ServerError'], '"DELETE entry #'.$id.' successfully, but failed to write metadata: logs.json"'); }
        $this->data = '"DELETE entry #'.$id.' successfully"'; $this->response(); 
      }
      else{ $this->response(self::HTTP_CODE['ServerError'], '"DELETE entry #'.$id.' failed: '.$this->sql->error.'"'); }
    }
    // and no more method
    else{
      $this->response(self::HTTP_CODE['MethodNotAllowed'], '"Method \"'.$this->method.'\" is not supported on endpoint \"'.$this->endpoint.'\""');
    }
  }

  /**
   * Handle the metadata (logs.json)
   */
  protected function metadata(){
		if($this->method == 'GET'){ 
      $this->response(self::HTTP_CODE['OK'], $this->logs, false);
    }
		elseif($this->method == 'PUT'){ 
      $success = file_put_contents('logs.json', $this->data);
      if(!$success){ $this->response(self::HTTP_CODE['ServerError'], '"Failed to write metadata: logs.json"'); }
      $this->data = '"PUT metadata successfully"'; $this->response(); 
    }
		elseif($this->method == 'UPDATE'){ 
      if(!$this->updateMetadata()){ $this->response(self::HTTP_CODE['ServerError'], '"Failed to update metadata: logs.json"'); }
      $this->data = '"UPDATE metadata successfully"'; $this->response(); 
    }
    else{
      $this->response(self::HTTP_CODE['MethodNotAllowed'], '"Method \"'.$this->method.'\" is not supported on endpoint \"'.$this->endpoint.'\""');
    }
  }

  /**
   * Update metadata (logs.json) from DB etc.
   */
  protected function updateMetadata(){
    $logs = (object)[
      "desc"=>"Metadata of logs",
      "entry_minId"=>1,
      "entry_maxId"=>1,
      "logs"=>[]        
    ];
    if($minmax=$this->_metadata_db()){
      $logs->entry_minId = intval($minmax[0]);
      $logs->entry_maxId = intval($minmax[1]);
    }
    $logs->logs = (object)$this->_metadata_logs();
    $this->logs = $logs; 
    if(!file_put_contents("logs.json",json_encode_utf8($this->logs))){ return false; }
    return true;
  }
  private function _metadata_db(){
    $res = $this->sql->query("SELECT MIN(id), MAX(id) FROM logs WHERE id <> 0");
    if($res && $res->num_rows){ 
      $minmax = $res->fetch_row();
      $res->close();
    }else{ return false; }
    return $minmax;
  }
  private function _metadata_logs(){
    $logs = [];
    foreach(scandir('logs') as $fn){ 
      if(substr($fn,-5,5) != '.json'){ continue; }
      $log = json_decode(file_get_contents('logs/'.$fn));
      if($log){ $logId = $log->logId; }else{/*WARNING*/}
      if($logId){ $logs[$logId] = $fn; }else{/*WARNING*/}
    };
    return $logs;
  }

  /**
   * Export MySQL -> JSON
   */
  protected function export(){
    $this->response(self::HTTP_CODE['NotImplemented'], '"To be implemented: Export MySQL -> JSON"');
  }

  /**
   * Import MySQL <- JSON
   */
  protected function import(){
    $json = file_get_contents("logs/log.json");
    $log = json_decode($json, true);
    if(!$json || !$log){ $this->response(self::HTTP_CODE['ServerError'], '"Failed loading logs/log.json"'); }
    $success = $this->sql->query("DELETE FROM logs");
    if(!$success){ $this->response(self::HTTP_CODE['ServerError'], '"DELETE entries failed: '.$this->sql->error.'"'); }
    $n = 0;
    foreach(array_keys($log['entries']) as $id){ 
      $json = $this->sql->real_escape_string(json_encode_utf8($log['entries'][$id]));
      $success = $this->sql->query("INSERT INTO logs (id, json) VALUES ($id, '$json')");
      if($success){ if($this->logs->entry_maxId < $id){ $this->logs->entry_maxId = $id; }
      }else{ $this->response(self::HTTP_CODE['ServerError'], '"INSERT to #'.$id.' failed: '.$this->sql->error.'"'); }
      $n++;
    }
    $success = file_put_contents('logs.json', json_encode_utf8($this->logs));
    if(!$success){ $this->response(self::HTTP_CODE['ServerError'], '"IMPORT '.$n.' entries successfully, but failed to write metadata: logs.json"'); }
    $this->data = '"IMPORT '.$n.' entries successfully"'; $this->response();  
  }

}

$logapi = new logapi();
$logapi->process_api();

?>