<?php

/**
 * Abstract base class Net_CDDB_Request declaration
 * 
 * @see Net_CDDB_Server
 * @see Net_CDDB_Request_HTTP
 *  
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Abstract base class Net_CDDB_Request declaration (handle incoming requests for CDDB server)
 * 
 * The Net_CDDB_Server uses a driver-style interface to different types of 
 * incoming requests. For instance, a driver class might extend this class and 
 * listen on port 80 for CDDB commands. Another driver class might extends this 
 * class and listen on port 8880. A third might listen for SMTP requests.   
 * 
 * @see Net_CDDB_Server
 * @see Net_CDDB_Request_HTTP
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Request
{
	/**
	 * Define the mode the server listens in (listen for a single request, HTTP style, or listen for multiple requests, CDDBD style)
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function mode()
	{
		return NET_CDDB_REQUEST_ONCE;
	}
}

?>