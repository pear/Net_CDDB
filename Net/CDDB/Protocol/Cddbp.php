<?php

/**
 * Driver class for Net_CDDB, provides a CDDBP protocol connection
 * 
 * @see Net_CDDB_HTTP
 * @see CDDB_protocol.txt
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Use the Net_Socket class to make the socket connection
 */
require_once 'Net/Socket.php';

/**
 * We need the constants from the CDDB class
 */
require_once 'Net/CDDB.php';

/**
 * All protocols extend the Net_CDDB_Protocol base class
 */
require_once 'Net/CDDB/Protocol.php';

/**
 * Connection handler class for CDDBP protocol
 * 
 * Provides a CDDBP protocol connection to a CDDB server.
 * 
 * @see Net_CDDB_HTTP
 * @see CDDB_protocol.txt
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Protocol_CDDBP extends Net_CDDB_Protocol
{
	/**
	 * Connection resource to remove server
	 *
	 * @var resource 
	 * @access protected
	 */
	var $_conn;
	
	/**
	 * String buffer for recieving responses
	 * 
	 * @var string
	 * @access protected
	 */
	var $_buffer;
	
	/**
	 * String buffer for holding status line of last request
	 * 
	 * @var string
	 * @access protected
	 */
	var $_status_buffer;
	
	/**
	 * CDDB server to connect to
	 * 
	 * @var string 
	 * @access protected
	 */
	var $_server;
	
	/**
	 * CDDB port to connect on
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_port;
	
	/**
	 * User to connect to CDDB server as
	 * 
	 * @var string
	 * @access protected
	 */
	var $_user;
	
	/**
	 * Hostname of remote CDDB server
	 * 
	 * @var string
	 * @access protected
	 */
	var $_host;
	
	/**
	 * Client name to report to CDDB server
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
	 * String indicating CDDB server returned a multi-line response
	 * 
	 * @var string
	 * @access protected
	 */
	var $_multi_line_ident;
	
	/**
	 * Construct a CDDBP object
	 * 
	 * @see Net_CDDB
	 * @see Net_CDDB_HTTP
	 * 
	 * @param string $protocol DSN String
	 * @param array $option_params
	 * @return void
	 */
	function Net_CDDB_Protocol_CDDBP($protocol, $option_params) 
	{
		$protocol_params = $this->_parseDsn($protocol);
		
		$this->_conn = null;
		
		$this->_server = $protocol_params['host'];
		$this->_port = $protocol_params['port'];
		$this->_user = $protocol_params['user'];
		
		$this->_host = $option_params['host'];
		
		$this->_client = 'PHP/PEAR(' . get_class($this) . ')';
		$this->_version = NET_CDDB_VERSION;
		
		$this->_buffer = '';
		$this->_status_buffer = '';
		
		$this->_buffer = '';
		$this->_status_buffer = '';
		
		$this->_multi_line_ident = 'until terminating';
	}
	
	/**
	 * Tell whether or not a CDDBP response is a multi-line response
	 * 
	 * @access private
	 * 
	 * @param string $str_first_line The first line of the response
	 * @return bool
	 */
	function _isMultiLineResponse($str_first_line)
	{
		return false !== strpos($str_first_line, $this->_multi_line_ident);
	}
	
	/**
	 * Connect to the CDDBP server
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function connect()
	{
		$this->_conn = new Net_Socket(); 
		
		if (true == ($err = $this->_conn->connect($this->_server, $this->_port))) {
			
			// Read welcome banner
			$this->_conn->readLine();
			
			// Handshake/login
			$this->_conn->writeLine('cddb hello ' . $this->_user . ' ' . $this->_host . ' ' . $this->_client . ' ' . $this->_version);
			$response = $this->_conn->readLine();
			
			// Change protocol levels
			$this->_conn->writeLine('proto ' . NET_CDDB_PROTO_LEVEL);
			$this->_conn->readLine();
			
			switch((int) substr($response, 0, 3)) {
				case NET_CDDB_RESPONSE_OK: // 200, Handshake OK
				case NET_CDDB_RESPONSE_SERVER_ALREADY: // 402, Already shook hands...?
					return true;
				case NET_CDDB_RESPONSE_SERVER_BADHANDSHAKE: // 431, Handshake not OK, closing connection
				default: 
					return false;
			}
		} else {
			$this->_conn = null;
			return $err;
		}
	}
	
	/**
	 * Tell whether or not a connection has been established
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function connected()
	{
		return !is_null($this->_conn);
	}
	
	/**
	 * Send a query to the CDDB server
	 * 
	 * If a connection has not yet been established, it will try to connect 
	 * before sending any data. Otherwise, the established connection will be 
	 * used. 
	 * 
	 * @access public
	 * 
	 * @param string $query
	 * @return void
	 */
	function send($query, $try_again = true)
	{
		if (!$this->connected()) {
			$this->connect();
		}
		
		if ($this->connected()) {
			
			$char = ' '; // Stores character by character read
			$response = ''; // Stores entire response
			
			// Send the command
			$this->_conn->writeLine($query);
			
			// Read first (only?) line of response
			$response = $this->_conn->readLine() . "\n";
			
			// If response has more than one line, keep on reading
			//	(We can't just use the Net_Socket::readAll() method here because 
			//	it hangs - the data is terminated by a '.', not an EOF, so we just 
			//	need to read until we find the '.')
			if ($this->_isMultiLineResponse($response)) {
				
				$last_char = ' ';
				while (true) {
					
					$char = $this->_conn->read(1);
					if ($char == "." and $last_char == "\n") {
						break;
					} else {
						$last_char = $char;
					}
					
					$response = $response . $char;
				}
			}
			
			// Get rid of leading newlines...?
			$response = trim($response);
			
			//p/rint('entire response: ' . "\n");
			//print($response);
			//print("\n" . '--------------------' . "\n");
			
			// If you are doing multiple queries then the CDDBP server may drop 
			//	the connection without warning and we'll get an empty response. 
			//	If this happens, we'll try to re-establish the connection and 
			//	send the query again (just once, so it won't get caught in an 
			//	infinite loop) 
			if (!strlen($response) and $try_again) 
			{
				$this->disconnect();
				return $this->send($query, false);
			}
			else if (!strlen($response))
			{
				$this->disconnect();
				return false;
			}
			
			if (false !== strpos($response, "\n")) { // Multi-line
				$this->_status_buffer = substr($response, 0, strpos($response, "\n"));
				$this->_buffer = substr($response, strpos($response, "\n") + 1);
			} else { // Single-line response
				$this->_status_buffer = $response;
				$this->_buffer = substr($response, 4);
			}
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Recieve a string response from the CDDB server
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function recieve()
	{
		$buffer = $this->_buffer;
		$this->_buffer = '';
		return $buffer;
	}
	
	/**
	 * Get the integer status code the last response contained
	 * 
	 * @access public
	 * 
	 * @return integer
	 */
	function status()
	{
		$status = (int) $this->_status_buffer;
		$this->_status_buffer = 0;
		return $status;
	}
	
	/**
	 * Disconnect from the CDDBP server
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function disconnect()
	{
		//fclose($this->_conn);
		$this->_conn->disconnect();
		$this->_conn = null;
		return true;
	}

}

?>