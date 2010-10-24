<?php

/**
 * Class to listen for and handle HTTP CDDB requests
 * 
 * @see Net_CDDB_Server
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Require the CDDB file for constants, etc.
 */
require_once 'Net/CDDB.php';

/**
 * Require the {@link Net_CDDB_Request} base class
 */
require_once 'Net/CDDB/Request.php';

/**
 * Class to listen for, process, and respond to HTTP CDDB requests
 * 
 * The {@link Net_CDDB_Server} class can listen for and respond to multiple 
 * types of requests depending on the driver it uses. This particular driver 
 * listens for CDDB commands encapsulated in the HTTP protocol and provides a 
 * method to respond to those CDDB commands/requests. 
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Request_HTTP extends Net_CDDB_Request
{
	/**
	 * Constructor (PHP v4.x)
	 * 
	 * @see __construct()
	 */
	function Net_CDDB_Request_HTTP($dsn, $options = array())
	{
		$this->__construct($dsn, $options);
	}
	
	/**
	 * Constructor (PHP v5.x)
	 * 
	 * @access public
	 * 
	 * @param string $dsn
	 * @param array $options
	 */
	function __construct($dsn, $options = array())
	{
		
	}
	
	/**
	 * Listen for incoming CDDB commands (i.e.: Read and parse the $_REQUEST['cmd'], $_REQUEST['proto'], and $_REQUEST['hello'] variables)
	 * 
	 * @access public
	 * @see Net_CDDB_Request_HTTP::respond()
	 * 
	 * @return array An array of CDDB commands
	 */
	function listen()
	{
		if (isset($_REQUEST['cmd']) and isset($_REQUEST['proto']) and isset($_REQUEST['hello'])) {
			
			return array(
				'cddb hello ' . $_REQUEST['hello'],
				'proto ' . $_REQUEST['proto'],
				$_REQUEST['cmd'], 
				);
							
		} else {
			return array(); // Return something sure to trigger an error code
		}
	}

	/**
	 * Respond to a CDDB command with a status code, status message, and data string (i.e.: print to stdout)
	 * 
	 * @access public
	 * @see Net_CDDB_Request_HTTP::listen()
	 * 
	 * @param integer $status Status code (i.e.: 200)
	 * @param string $message Status message (i.e.: 'OK record follows...')
	 * @param string $data The data to send back to the client
	 * @param boolean $terminating Whether or not to terminate the data with a '.'
	 * @return void
	 */
	function respond($status, $message, $data = '', $terminating = false)
	{
		header('Content-type: text/plain');
		
		print($status . ' ' . $message . "\r\n");
		print($data . "\r\n");
		
		if ($terminating) {
			print('.');
		}
	}

	/**
	 * Tell what request mode this driver operates in
	 * 
	 * @access public
	 * @see Net_CDDB_Request::mode()
	 * 
	 * @return string
	 */
	function mode()
	{
		return NET_CDDB_REQUEST_ONCE;
	}
	
	/**
	 * Tell what interface alias this request class handles (http, cddb, etc.)
	 *
	 * @access public
	 * @return string
	 */
	function face()
	{
		return 'http';
	}
}

?>