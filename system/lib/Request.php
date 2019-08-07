<?php
// valid operations to execute
include_once 'Operations.php';
/**
 * Request object thats in charge of validating and brokering requests
 */
class Request {
    private $status;
    private $contentType;
    public $validateJWT;
    public function __construct() {
        // status is dead, something has to escalate it
        $this->status = 500;
        $this->contentType = "application/json";
        // default to validation of both though not everything needs it
        $this->validateJWT = true;
    }
    /**
     * Validate the current request
     */
    private function valid() {
      if ($this->validateJWT) {
        if (!$GLOBALS['HAXCMS']->validateJWT()) {
          return FALSE;
        }
      }
      return TRUE;
    }
    /**
     * Return encoded data, optional flag for data without headers
     */
    private function encodeData($response, $dataOnly = false) {
      if (!$dataOnly) {
        header('Status: ' . $this->status);
        header('Content-Type: ' . $this->contentType);
      }
      print json_encode($response);
      if (!$dataOnly) {
        exit();
      }
    }
    /**
     * Execute the callback ensuring its ones we support
     * @todo need to support custom / modular callbacks
     */
    public function execute($op, $params = array(), $rawParams = array()) {
      // we only skip JWT validation on edge cases
      if (in_array($op, array('generateAppStore', 'listSites'))) {
        $this->validateJWT = FALSE;
      }
      if ($this->valid()) {
        // validated so lets mark it so in headers
        $this->status = 200;
        $operations = new Operations();
        // if this method exists, it's been validated so execute it
        // and return response data
        if (method_exists($operations, $op)) {
          $operations->params = $params;
          $operations->rawParams = $rawParams;
          $response = $operations->{$op}();
          if (is_array($response) && isset($response['__failed'])) {
            $this->status = $response['__failed']['status'];
            $this->encodeData($response['__failed']['message']);
          }
          else {
            $this->encodeData($response);
          }
        }
        else {
          $this->status = 500;
          $this->encodeData("$op is not a valid callback");
        }
      }
    }
}