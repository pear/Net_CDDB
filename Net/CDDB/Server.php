<?php

/**
 * Object-oriented CDDB Server components 
 * 
 * Alpha-quality CDDB server capable of serving CDDB requests over a variety of 
 * CDDB protocol options (HTTP only for now, more coming soon) and using a 
 * variety of data sources. 
 * 
 * @see Net_CDDB_Client
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Need the constants from this file...
 */
require_once 'Net/CDDB.php';

/**
 * A hook that runs when the server receives the request
 * 
 * The function signature for request hooks should look like this: 
 * <code>
 * function your_request_hook(&$arr_a_list_of_cddb_commands_passed_in_the_request)
 * {
 * 	...
 * 	// return true; (if you want the CDDB server to not run any other request hooks)
 * 	// return false; (if you want the CDDB server to continue to run the other request hooks)
 * }	
 * </code>
 * 
 * @var string
 */
define('NET_CDDB_SERVER_HOOK_REQUEST', 'request');

/**
 * A hook that runs whenever the server responds to a request
 * 
 * The function signature for response hooks should look like this:
 * <code>
 * funtion your_response_hook($cmd, &$int_response_code, &$str_response_message, &$str_any_data_returned_with_request, &$bln_whether_or_not_to_add_a_period_as_a_termination_character)
 * {
 * 	...
 * 	// return true; (if you want the CDDB server to not run any other response hooks)
 * 	// return false; (if you want the CDDB server to continue to run the other response hooks)
 * }
 * </code>
 * 
 * @var string
 */
define('NET_CDDB_SERVER_HOOK_RESPONSE', 'response');

/**
 * A hook that runs for each command the CDDB server recieves
 * 
 * The function signature for response hooks should look like this:
 * <code>
 * function your_command_hook(&$str_the_cddb_command)
 * {
 * 	...
 * 	// return true; (if you want the CDDB server to not run any other command hooks)
 * 	// return false; (if you want the CDDB server to continue to run the other command hooks)
 * }
 * </code>
 * 
 * @var string
 */
define('NET_CDDB_SERVER_HOOK_COMMAND', 'command');

/**
 * CDDB Server (ALPHA QUALITY, *USE AT YOUR OWN RISK!*)
 * 
 * *** WARNING *** 
 * Use at your own risk, it doesn't work very well yet!
 * 
 * Current features: 
 * 	- CDDB protocol level 5
 * 	- Multiple data sources: tell the server to read from a local filesystem, a remote HTTP CDDB server, a remote CDDBP server, a database (coming soon) etc.
 * 	- Supports about half of the CDDB commands  
 * 	- Write and register your own 'hooks' which run when the CDDB server responds to commands
 * 
 * Known issues: 
 * 	- Extremelly lightly tested (use at your own risk!)
 * 	- Command: 'motd' reports and incorrect last modified date
 * 	- Lack of any error reporting...
 * 	- Lack of any login/hello command support
 * 	- Only supports protocol level 5
 * 	- Only provides HTTP request support (no CDDBP or SMTP... yet)
 * 
 * You can write your own custom 'hooks' that get run a certain stages of the 
 * CDDB request/response process and register them with the ->registerHook() 
 * method. You can register multiple hooks at each stage of the process, and 
 * they will get run in the order you register them in. 
 * 
 * Hooks should receive passed parameters by reference if they intend to modify 
 * commands/requests/responses. Hooks should return either a four element array 
 * which will be treated as the response (as below) or void if the server should 
 * continue to process the response. 
 * 
 * <code>
 * $my_custom_response = array(
 * 	NET_CDDB_RESPONSE_OK, // This could actually be *any* valid response code
 * 	'status message to be returned with the response code', 
 * 	'any data the response returns (a cddb record, a list of matching records, a list of mirror sites, etc.)', 
 * 	true, // true if a '.' should be appended to the response as a terminating character, false otherwise
 * 	);
 * </code>
 *  
 * @todo Probably have the Net_CDDB_Request_* classes have three methods: getHello(), getProto(), and getCmd() or something
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Server extends Net_CDDB
{
	/**
	 * Net_CDDB_Request_* object instance to use for listening for requests
	 * 
	 * @access protected
	 * @var object
	 */
	var $_request;
	
	/**
	 * Net_CDDB_Protocol_* object instance to use for data access
	 * 
	 * @access protected
	 * @var object
	 */
	var $_protocol;
	
	/*var $_connect_logging;
	
	var $_use_auth_file;
	var $_auth_file;
	var $_use_auth_mysql;
	var $_auth_method;
	*/
	
	/**
	 * An array of function names to call as request hooks
	 * 
	 * @access protected
	 * @var array
	 */
	var $_hook_request;
	
	/**
	 * An array of function names to call as command hooks
	 * 
	 * @access protected
	 * @var array
	 */
	var $_hook_cmds;
	
	/**
	 * An arra of function names to call as response hooks
	 * 
	 * @access protected
	 * @var array
	 */
	var $_hook_resp;
	
	/**
	 * Constructor (PHP v4.x)
	 * 
	 * @access public
	 * @see Net_CDDB_Server::__construct()
	 */
	function Net_CDDB_Server($request_dsn, $protocol_dsn, $options = array())
	{
		$this->__construct($request_dsn, $protocol_dsn, $options);
	}
	
	/**
	 * Constructor (PHP v5.x)
	 * 
	 * @access public
	 * 
	 * @param string $request_dsn
	 * @param string $protocol_dsn
	 * @param array $options
	 */
	function __construct($request_dsn, $protocol_dsn, $options = array())
	{
		$this->_protocol = $this->_createProtocol($protocol_dsn, $options);
		$this->_request = $this->_createRequest($request_dsn, $options);
		
		$this->_hook_request = array();
		$this->_hook_cmds = array();
		$this->_hook_resp = array();
	}
	
	/**
	 * Create a Net_CDDB_Request object instance
	 * 
	 * @access protected
	 * 
	 * @param string $dsn
	 * @param array $options
	 * @return Net_CDDB_Request Returns an instance of a subclass of Net_CDDB_Request (i.e: Net_CDDB_Request_HTTP instance)
	 */
	function _createRequest($dsn, $options)
	{
		$parse = parse_url($dsn);
		
		$file = ucfirst(strtolower($parse['scheme']));
		$class = 'Net_CDDB_Request_' . $file;
		
		/**
		 * Require the file the protocol class is stored in
		 */
		include_once 'Net/CDDB/Request/' . $file . '.php';
		
		if (class_exists($class)) {
			return new $class($dsn, $options);
		} else {
			return PEAR::raiseError('Could not find request file for: ' . $file);
		}
	}
	
	/**
	 * Start the CDDB server
	 * 
	 * @access public
	 * @todo Listen forever support (daemon mode)
	 * 
	 * @return boolean 
	 */
	function start()
	{
		if (PEAR::isError($this->_request)) {
			return $this->_request;
		}
		
		if (PEAR::isError($this->_protocol)) {
			return $this->_request;
		}
		
		// Implemented commands
		$impl_cmds = array( 
			'cddb lscat' 	=> '_cddbLscat', 
			'cddb hello' 	=> '_cddbHello', 
			'cddb query' 	=> '_cddbQuery',
			'proto' 		=> '_proto',
			'cddb read' 	=> '_cddbRead', 
			'motd' 			=> '_motd', 
			'ver' 			=> '_ver',
			'stat' 			=> '_stat',  
			);
		
		if ($this->_request->mode() == NET_CDDB_REQUEST_ONCE) { // Listen just once (HTTP for instance)
			
			// Read data from request listener
			$commands = $this->_request->listen();
			
			if (!$this->_doRequestHooks($commands)) {
				return true;
			}
			
			if (!count($commands)) {
				$this->_request->respond(NET_CDDB_RESPONSE_ERROR_SYNTAX, 'Command syntax error: incorrect number of arguments.');
				return true;
			}
			
			foreach ($commands as $cmd)
			{
				if (!$this->_doCommandHooks($cmd)) {
					continue;
				}
				
				// Parse command from command parameters
				$tmp = explode(' ', $cmd);
				if (current($tmp) == 'cddb' and count($tmp) >= 2) {
					$cmd = 'cddb ' . next($tmp);
					$query = implode(' ', array_slice($tmp, 2));
				} else {
					$cmd = current($tmp);
					$query = implode(' ', array_slice($tmp, 1));
				}
				
				if (!strlen($cmd)) {
					// Empty command...?
					
					$status = NET_CDDB_RESPONSE_ERROR_EMPTY;
					$message = 'Empty command input.';
					$data = '';
					$term = false;
					
					if ($this->_doResponseHooks($cmd, $status, $message, $data, $term)) {
						$this->_request->respond($status, $message);
					}
					
				} else if (isset($impl_cmds[$cmd]) and method_exists($this, $impl_cmds[$cmd])) {
					// Call the appropriate command handler, and then respond
					
					$status = NET_CDDB_RESPONSE_SERVER_ERROR;
					$message = '';
					$data = '';
					$term = false;
					
					if ($this->{$impl_cmds[$cmd]}($cmd, $query, $status, $message, $data, $term)) {
						if ($this->_doResponseHooks($cmd, $status, $message, $data, $term)) {
							$this->_request->respond($status, $message, $data, $term);
						}
					}
					
				} else {
					// Respond indicating the command could not be found
					
					$status = NET_CDDB_RESPONSE_ERROR_UNRECOGNIZED;
					$message = 'Unrecognized command.';
					$data = '';
					$term = false;
					
					if ($this->_doResponseHooks($cmd, $status, $message, $data, $term))
					{
						$this->_request->respond($status, $message); // Unrecognized command...?
					}
				}
			}
			
			return true;
			
		} else {
			// listen forever, not implemented yet
		}
	}
	
	/**
	 * Register a hook function 
	 * 
	 * See the example file or the class description for more details. Custom 
	 * hooks can be used to hook into CDDB server requests and implement custom 
	 * behaviors or actions. 
	 * 
	 * @access public
	 * @param char $when
	 * @param function $function
	 * @return boolean
	 */
	function registerHook($when, $function)
	{
		if (function_exists($function)) {
			switch ($when) {
				case NET_CDDB_SERVER_HOOK_REQUEST:
					$this->_hook_request[] = $function;
					break;
				case NET_CDDB_SERVER_HOOK_COMMAND:
					$this->_hook_cmds[] = $function;
					break;
				case NET_CDDB_SERVER_HOOK_RESPONSE:
					$this->_hook_resp[] = $function;
					break;
				default:
					return false;
			}
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Execute the command hooks
	 * 
	 * @access protected
	 * @param string $cmd
	 * @return boolean
	 */
	function _doCommandHooks(&$cmd)
	{
		foreach ($this->_hook_cmds as $hook) {
			if ($arr = $hook($cmd) and is_array($arr) and count($arr) == 4) {
				// We still need to run the response hooks though...
				$this->_doResponseHooks($cmd, $arr[0], $arr[1], $arr[2], $arr[3]);
				
				// Respond...
				$this->_request->respond($arr[0], $arr[1], $arr[2], $arr[3]);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Execute the response hooks
	 * 
	 * @access protected
	 * @param string $cmd
	 * @param integer $status
	 * @param string $message
	 * @param string $data
	 * @param boolean $term
	 * @return boolean
	 */
	function _doResponseHooks($cmd, &$status, &$message, &$data, &$term)
	{
		foreach ($this->_hook_resp as $hook) {
			if ($arr = $hook($cmd, $status, $message, $data, $term) and is_array($arr) and count($arr) == 4) {
				$this->_request->respond($arr[0], $arr[1], $arr[2], $arr[3]);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Execute the request hooks
	 * 
	 * @access protected
	 * @param array $arr_cmds
	 * @return boolean
	 */
	function _doRequestHooks(&$arr_cmds)
	{
		foreach ($this->_hook_request as $hook) {
			if ($arr = $hook($arr_cmds) and is_array($arr) and count($arr) == 4) {
				$this->_request->respond($arr[0], $arr[1], $arr[2], $arr[3]);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Dummy placeholder for the 'cddb proto' command
	 * 
	 * @access protected
	 * 
	 * @return void
	 */
	function _proto()
	{
		return false;
	}
	
	/**
	 * Dummy placeholder for 'cddb hello' command
	 * 
	 * @access protected
	 * 
	 * @return void
	 */
	function _cddbHello()
	{
		return false;	
	}
	
	/**
	 * Respond to the 'cddb lscat' CDDB command (list categories)
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _cddbLscat($cmd, $query, &$status, &$message, &$data, &$term)
	{
		$headers = array(
			NET_CDDB_RESPONSE_OK_FOLLOWS => 'OK, category list follows (until terminating `.\')',
			);
		
		$this->_protocol->send('cddb lscat');
		$resp_body = $this->_protocol->recieve();
		$resp_stat = $this->_protocol->status();
		
		switch ($resp_stat) {
			case NET_CDDB_RESPONSE_OK_FOLLOWS:
				$status = $resp_stat;
				$message = $headers[$status];
				$data = $resp_body;
				$term = true;
				break;
			default:
				$status = NET_CDDB_RESPONSE_SERVER_ERROR;
				$message = 'Internal server error.';
				break;
		}
		
		return true;
	}
	
	/**
	 * Respond to the 'cddb read ...' CDDB command
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _cddbRead($cmd, $query, &$status, &$message, &$data, &$term)
	{
		$headers = array(
			NET_CDDB_RESPONSE_OK_FOLLOWS => $query . ' CD database entry follows (until terminating `.\')', 
			);
		
		$this->_protocol->send($cmd . ' ' . $query);
		$resp_body = $this->_protocol->recieve();
		$resp_stat = $this->_protocol->status();
		
		switch ($resp_stat) {
			case NET_CDDB_RESPONSE_OK_FOLLOWS:
				$status = $resp_stat;
				$message = $headers[$status];
				$data = $resp_body;
				$term = true;
				break;
			default:
				$status = NET_CDDB_RESPONSE_SERVER_ERROR;
				$message = 'Internal server error.';
				break;
		}
		
		return true;
	}
	
	/**
	 * Respond to the 'cddb query ...' CDDB command
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _cddbQuery($cmd, $query, &$status, &$message, &$data, &$term)
	{
		$this->_protocol->send($cmd . ' ' . $query);
		$resp_body = $this->_protocol->recieve();
		$resp_stat = $this->_protocol->status();
		
		switch ($resp_stat) {
			case NET_CDDB_RESPONSE_OK:
				$status = $resp_stat;
				$message = $resp_body;
				break;
			default:
				$status = NET_CDDB_RESPONSE_SERVER_ERROR;
				$message = 'Internal server error.';
				break;
		}
		
		return true;
	}
	
	/**
	 * Respond to the CDDB 'motd' command
	 * 
	 * @access protected
	 * @todo Find out the motd modified time somehow...?
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void 
	 */
	function _motd($cmd, $query, &$status, &$message, &$data, &$term)
	{
		$headers = array(
			NET_CDDB_RESPONSE_OK_FOLLOWS => 'Last modified: ' . date('m/d/Y H:i:s') . ' MOTD follows (until terminating `.\')', 
			);
		
		$this->_protocol->send('motd');
		$resp_body = $this->_protocol->recieve();
		$resp_stat = $this->_protocol->status();
		
		switch ($resp_stat) {
			case NET_CDDB_RESPONSE_OK_FOLLOWS:
				$status = $resp_stat;
				$message = $headers[$status];
				$data = $resp_body;
				$term = true;
				break;
			default:
				$status = NET_CDDB_RESPONSE_SERVER_ERROR;
				$message = 'Internal server error.';
				break;
		}
		
		return true;
	}
	
	/**
	 * Respond to the CDDB 'ver' command
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _ver($cmd, $query, &$status, &$message, &$data, &$term)
	{
		$this->_protocol->send('ver');
		$resp_body = $this->_protocol->recieve();
		$resp_stat = $this->_protocol->status();
		
		/*
		 * If the protocol we're using to get the data is a remote protocol 
		 * (i.e.: We're reading this data from a remote CDDB server via 
		 * HTTP/CDDBP instead of using a local copy of the CDDB db) then we'll 
		 * modify the version information to reflect the tunnelling of the 
		 * response through the Net_CDDB/PHP server. 
		 */ 
		if ($this->_protocol->remote()) {
			$resp_body .= ' (Tunnelled through PHP/PEAR/' . get_class($this) . ' v' . NET_CDDB_VERSION . ')';
		}
		
		switch ($resp_stat) {
			case NET_CDDB_RESPONSE_OK:
				$status = $resp_stat;
				$message = $resp_body;
				break;
			default:
				$status = NET_CDDB_RESPONSE_SERVER_ERROR;
				$messsage = 'Internal server error.';
				break;
		}
		
		return true;
	}
	
	function _stat($cmd, $query, &$status, &$message, &$data, &$term)
	{
		$this->_protocol->send('stat');
		$resp_body = $this->_protocol->recieve();
		$resp_stat = $this->_protocol->status();
		
		// Correct the interface stat header
		$explode = explode("\n", $resp_body);
		foreach ($explode as $num => $line) {
			if (trim(current(explode(':', $line))) == 'interface') {
				$explode[$num] = '    interface: ' . $this->_request->face();
			}
		}
		$resp_body = implode("\n", $explode);
		
		switch ($resp_stat)
		{
			case NET_CDDB_RESPONSE_OK_FOLLOWS:
				$status = $resp_stat;
				$message = 'OK, status information follows (until terminating `.\')';
				$data = trim($resp_body);
				$term = true;
				break;
			default:
				$status = NET_CDDB_RESPONSE_SERVER_ERROR;
				$message = 'Internal server error.';
				break;
		}
		
		return true;
	}
}

?>