<?php

/**
 * Base class for basing CDDB protocol/access on
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Base class for basing CDDB protocol/access on
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Protocol
{
	/**
	 * Parse a DSN string and return the parsed array
	 * 
	 * This function parses a DSN string and returns the parsed array. The array
	 * will always contain the following elements (elements not included in the 
	 * DSN string will be filled with default values):
	 *  - scheme, The protocol used to connect (cddbp, http, etc.)
	 * 	- host, The hostname of the remove server
	 * 	- pass, The password for remove server access
	 * 	- user, The username for remove server access
	 * 	- path, The path to the script/CGI program (for HTTP)
	 * 
	 * @param string $protocol
	 * @return array
	 */
	function _parseDsn($protocol)
	{
		$protocol_defaults = array(
			'scheme' => 'cddbp', // Protocol
			'host' => 'freedb.org', // Server
			//'port' => 8880, // Port to connect on (gets set later depending on http or cddbp)
			'pass' => '', 
			'user' => 'unknown_user', // Username to connect with
			'path' => '/~cddb/cddb.cgi' // Path (for HTTP only)
			);
		
		// This is for DSN strings which lack a 'host' part, we just insert a null host so we can parse it
		$protocol = str_replace(':///', '://null/', $protocol);
		
		$protocol_params = parse_url($protocol); // Parse DSN
		
		if (!isset($protocol_params['port'])) { // Default for 'port' changes depending on scheme
			if ($protocol_params['scheme'] == 'http') {
				$protocol_params['port'] = 80; // For HTTP
			} else {
				$protocol_params['port'] = 8880; // For anything else (CDDBP)
			}
		}
		
		return array_merge($protocol_defaults, $protocol_params); // Merge DSN defaults with parsed DSN
	}
	
	/**
	 * Tell whether or not a protocol acts on a remote resource
	 * 
	 * The Net_CDDB_Server command likes to know whether or not a particular 
	 * protocol is accessing a remote server (HTTP or CDDBP) or a local 
	 * version of the database. By default all protocols will inherit this field 
	 * and report themselves as accessing remote resources. A protocol and 
	 * override this method and return false to report local cddb access.
	 * 
	 * @access public
	 * 
	 * @return boolean
	 */
	function remote()
	{
		return true;
	}
}

?>