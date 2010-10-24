<?php

/**
 * Driver class for providing an HTTP connection to a CDDB server
 * 
 * @see Net_CDDB
 * @see Net_CDDB_CDDBP
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Use the HTTP_Client PEAR class for HTTP connections
 */
require_once 'HTTP/Request.php';

/**
 * We need the constants from the CDDB class
 */
require_once 'Net/CDDB.php';

/**
 * We need the protocol base class
 */
require_once 'Net/CDDB/Protocol.php';

/**
 * Connection class for CDDB access using HTTP wrappers
 * 
 * @see Net_CDDB
 * @see Net_CDDB_CDDBP
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Protocol_HTTP extends Net_CDDB_Protocol
{
	/**
	 * String buffer to store response
	 * 
	 * @var string
	 * @access protected
	 */
	var $_buffer;
	
	/**
	 * Integer buffer to store HTTP response ( 200 OK, etc. )
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_status_buffer;
	
	/**
	 * URI to use access CGI program with ( /~cddb/cddb.cgi )
	 * 
	 * @var string
	 * @access protected
	 */
	var $_request_uri;
	
	/**
	 * HTTP server to connect to ( freedb.org )
	 * 
	 * @var string
	 * @access protected
	 */
	var $_server;
	
	/**
	 * HTTP port to connect on ( 80 )
	 * 
	 * @var integer 
	 * @access protected
	 */
	var $_port;
	
	/**
	 * Username to report to CDDB server
	 * 
	 * @var string
	 * @access protected
	 */
	var $_user;
	
	/**
	 * Hostname to report to CDDB server
	 * 
	 * @var string
	 * @access protected
	 */
	var $_host;
	
	/**
	 * Client string to report to CDDB server
	 * 
	 * @var string
	 * @access protected
	 */
	var $_client;
	
	/**
	 * Client version string to report to CDDB server
	 * 
	 * @var string
	 * @access protected
	 */
	var $_version;
	
	/**
	 * Construct a Net_CDDB_HTTP protocol object
	 * 
	 * @todo Make protocol subclasses set defaults for any passed optoin parameters instead of Net_CDDB_Client (ie: option_params['host'])
	 * 
	 * @access public
	 *  
	 * @param string $protocol DSN string
	 * @param array $option_params An array of options/parameters
	 * @return void
	 */
	function Net_CDDB_Protocol_HTTP($protocol, $option_params)
	{
		$protocol_params = $this->_parseDsn($protocol);
		
		$this->_request_uri = $protocol_params['path'];
		$this->_server = $protocol_params['host'];
		$this->_port = $protocol_params['port'];
		$this->_user = $protocol_params['user'];
		
		if (isset($option_params['host'])) {
			$this->_host = $option_params['host'];
		} else {
			$this->_host = 'unknown_host';
		}
		
		$this->_client = 'PHP/PEAR(' . get_class($this) . ')';
		$this->_version = NET_CDDB_VERSION;
		
		$this->_buffer = '';
		$this->_status_buffer = '';
	}
	
	/**
	 * Establish a connection to the HTTP server
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function connect()
	{
		return true;
	}
	
	/**
	 * Tell whether or not a connection has been established already
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function connected()
	{
		return false;
	}
	
	/**
	 * Send a query to the HTTP CDDB server
	 * 
	 * @access public
	 * @see Net_CDDB_Protocol_HTTP::recieve()
	 * 
	 * @param string $query
	 * @return bool
	 */
	function send($query)
	{
		$request =& new HTTP_Request('http://' . $this->_server . ':' . $this->_port . $this->_request_uri);
		
		$request->setMethod(HTTP_REQUEST_METHOD_POST);
		
		$request->addHeader('User-Agent', 'PEAR/PHP Net_CDDB via HTTP_Request (http://pear.php.net/)');
		
		$request->addPostData('cmd', $query);
		$request->addPostData('hello', $this->_user . ' ' . $this->_host . ' ' . $this->_client . ' ' . $this->_version);
		$request->addPostData('proto', NET_CDDB_PROTO_LEVEL);
		
		if (PEAR::isError($err = $request->sendRequest())) {
			return PEAR::raiseError('HTTP request failed, HTTP_Request object said: ' . $err->getMessage());
		}
		
		$response = trim($request->getResponseBody());
		
		if (false !== strpos($response, "\n")) {
			
			// Set the status buffer
			$this->_status_buffer = (int) substr($response, 0, strpos($response, "\n"));
			
			// Trim off the status line and set the response buffer
			$this->_buffer = trim(substr($response, strpos($response, "\n") + 1), " .\n\r\t");
			
		} else {
			$this->_status_buffer = (int) $response;
			$this->_buffer = substr($response, 4);
		}
		
		return true;
	}
	
	/**
	 * Receive a response after issuing a command with send()
	 * 
	 * @see Net_CDDB_HTTP::send()
	 * 
	 * @access public
	 * 
	 * @return string 
	 */
	function recieve()
	{
		return $this->_buffer;
	}
	
	/**
	 * Return the status code from the last request
	 * 
	 * @access public
	 * 
	 * @return int
	 */
	function status()
	{
		return $this->_status_buffer;
	}
	
	/**
	 * Disconnect from the server
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function disconnect()
	{
		return true;
	}

}

?>