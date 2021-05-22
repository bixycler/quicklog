<?php
class RESTful {
  /**
   * RESTful methods accepted by this API
   */
  protected $api_methods = array('GET', 'POST', 'PUT', 'DELETE');
  /**
   * The RESTful method of this request
   */
  protected $method = '';
  /**
   * API endpoint
   */
  protected $endpoint = '';
  /**
   * The params after the endpoint, eg: api.php/endpoint/param0/param1/ => [param0, param1]
   */
  protected $params = array();
  /**
   * Input data of POST & PUT requests, or output data of response(data=NULL)
   */
  protected $data = null;
  /**
   * Acceptable origin of requests to this API
   */
  protected $request_origin = '*';
  /**
   * HTTP header status
   * Ref: https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
   */
  const HTTP_STATUS = array(
    200 => 'OK',
    400 => 'Bad Request',
    401 => 'Unauthorized or Unauthenticated',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    503 => 'Service Unavailable',
    511 => 'Network Authentication Required'
  );
  /**
   * HTTP header status code
   */
  const HTTP_CODE = array(
    'OK' => 200,
    'BadRequest' => 400,
    'Unauthorized' => 401,
    'NotFound' => 404,
    'MethodNotAllowed' => 405,
    'ServerError' => 500,
    'NotImplemented' => 501,
    'ServiceUnavailable' => 503,
    'AuthenticationRequired' => 511
  );


  /**
   * Constructor: construct API from input ($_SERVER[] and php://input)
   * Required fields: $api_methods, $request_origin
   * Constructed fields: $method, $endpoint, $params, $data
   * * Note: some hosting sites, like infinityfree.net, limit methods to [GET, HEAD, POST] only
   *  (other methods will be blocked by 403 Forbidden error page)
   *  => Use header `X-HTTP-Method-Override` instead!
   */
  public function __construct(){
    header("Access-Control-Allow-Origin: ".$this->request_origin);
    header("Access-Control-Allow-Methods: ".implode(", ", $this->api_methods));

    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
    if(!$method){ $method = $_SERVER['REQUEST_METHOD']; }
    if(in_array($method, $this->api_methods)){
      $this->method = $method;
    }else{ $this->response(self::HTTP_CODE['MethodNotAllowed'], '"Unrecognized method: '.$method.'"'); }

    $this->params = explode('/', trim($_SERVER['PATH_INFO'],'/'));
    $this->endpoint = array_shift($this->params);
    if(!method_exists($this, $this->endpoint)){
      $this->response(self::HTTP_CODE['BadRequest'], '"Invalid endpoint: '.$this->endpoint.'"');
    }

    if($this->method=='POST' || $this->method=='PUT') {
      $this->data = file_get_contents("php://input");
      if(!$this->data){
        $this->data = $_SERVER['INPUT'];
      }
    }
  }

  /**
   * Call the method (to be implemented by derived classes) corresponding to the endpoint
   */
  public function process_api(){        
    $this->{$this->endpoint}();
  }

  /**
   * Print response to client
   * If param $data===NULL, the field $this->data will be used as response data 
   *  (Useful for the default 'OK' response)
   * Param $encoded means data has been encoded as JSON
   * * Note: this method exits the script!
   */
  protected function response($status_code=self::HTTP_CODE['OK'], $data=NULL, $encoded=true){
    $status = self::HTTP_STATUS[$status_code];
    header("HTTP/1.1 ".$status_code." ".$status);
    header("Content-Type: application/json");
    if($data===NULL){ $data = $this->data; }
    $str = $encoded? $data: json_encode($data);
    echo $str;
    die(); // response means return! (for error handling cases)
  }

}

?>