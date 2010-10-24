<?php

/**
 * Object-oriented interface to accessing CDDB servers
 * 
 * CDDB servers provide track/artist/album/etc. information about audio CDs.
 * A disc-id is calculated for an audio CD and then the CDDB database can be
 * queried for possible matches. Detailed data can then be retrieved from the
 * CDDB database including track titles, album name, artist name, disc year,
 * genre, etc. 
 * 
 * @see Net_CDDB_Server
 * @see CDDB_examples.php
 * @link http://freedb.org/ FreeDB.org, a free CDDB server
 * @link http://gracenote.com/ Gracenote.com, a commercial CDDB server
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * PEAR class for error handling
 */
require_once 'PEAR.php';

/**
 * CDDB Base class (provides some generic methods, constants, etc.)
 */
require_once 'Net/CDDB.php';

/**
 * Class to represent an CDDB audio disc is required
 */
require_once 'Net/CDDB/Disc.php';

/**
 * Base class for CDDB protocols
 */
require_once 'Net/CDDB/Protocol.php';

/**
 * Utilities class for disc id calculations and record parsing
 */
require_once 'Net/CDDB/Utilities.php';

/**
 * Class for communication with CDDB/FreeDB.org servers 
 * 
 * @todo Look at CDDB submission (broken? ugly dsn for determining submit server?)
 * 
 * @package Net_CDDB
 * @author Keith Palmer <Keith@UglySlug.com>
*/
class Net_CDDB_Client extends Net_CDDB
{
	/**
	 * CDDB protocol backend object
	 * 
	 * @var object
	 * @access protected
	 */
	var $_backend;
	
	/**
	 * CD TOC reader backend object, reads TOC from a CD-ROM disc
	 * 
	 * @var array
	 * @access protected
	 */
	var $_cdreader;
	
	/**
	 * Connection should persist over multiple method calls or not (valid for HTTP only)
	 * 
	 * @var bool
	 * @access protected 
	 */
	var $_persist;
	
	/**
	 * Whether or not to use sudo to access the CD-ROM device
	 * 
	 * @var bool
	 * @access protected
	 */
	var $_sudo;
	
	/**
	 * Location of CD-ROM device ( /dev/acd0, etc. )
	 * 
	 * @var string
	 * @access protected
	 */
	var $_device;
	
	/**
	 * String buffer for calls to backend
	 * 
	 * @var string
	 * @access protected
	 */
	var $_buffer;
	
	/**
	 * String server name for new submissions via HTTP
	 * 
	 * @var string
	 * @access protected
	 */
	var $_submit_server;
	
	/**
	 * String email address to use for submitting CDDB records
	 * 
	 * @var string
	 * @access protected
	 */
	var $_email;
	
	/**
	 * Construct a new Net_CDDB_Client object using the given backend/connection parameters (PHP v4.x)
	 * 
	 * @see Net_CDDB_Client::__construct()
	 */
	function Net_CDDB_Client($protocol = 'cddbp://freedb.org:8880', $cdreader = 'cddiscid:///dev/acd0', $option_params = array())
	{
		$this->__construct($protocol, $cdreader, $option_params);
	}
	
	/**
	 * Construct a new CDDB object using the given backend/connection parameters (PHP v5.x)
	 * 
	 * Create a new CDDB object which gives access to CDDB/FreeDB.org servers to 
	 * allow a user to query the database for information about music CDs. You 
	 * can use this class to submit discs to the FreeDB.org project or retrieve 
	 * music CD information such as disc title, artist, track names, etc. 
	 * 
	 * The class uses a driver architecture both for accessing CDDB 
	 * servers and for reading the table of contents off a local CD-ROM drive 
	 * so that new CDDB and CD-ROM access methods can be easily added in the 
	 * future. 
	 * 
	 * The class currently offers three different driver protocols to access this 
	 * the CDDB/FreeDB.org database: CDDBP, HTTP, or the local Filesystem. 
	 * 	- http:// - Queries the database by making HTTP requests  
	 *  - cddbp:// - CDDBP/Telnet style protocol to query database (default)
	 * 	- filesystem:// - Read data from a local FreeDB.org format CDDB database dump
	 * 	- mdb2.your_database_type_here:// - Read data from an SQL database
	 * 
	 * The class also offers a few different drivers to read the table of 
	 * contents from the audio-CD in your CD/DVD drive: 
	 * 	- cddiscid:// - Use the Linux/UNIX 'cd-discid' binary 
	 * 	- cdparanoia:// - Use the Linux/UNIX 'cdparanoia' binary
	 * 	- test:// - Testing class to make sure things work like they should
	 * 
	 * You may optionally provide an array of options parameters and CD TOC
	 * reader parameters to the class which specify options. Here are the
	 * valid array keys for each protocol:
	 * 
	 * For CDDBP:
	 * 	- persist (defaults to false, set to true to reuse connection for multiple CDDB queries)
	 * 	- sudo (defaults to false, set to true if you want to execute the callback binary using sudo)
	 * 	- submit_uri (defaults to /~cddb/submit.cgi)
	 * 
	 * For HTTP:
	 * 	- sudo (defaults to false, set to true if you want to execute the callback binary using sudo)
	 * 	- submit_uri (defaults to /~cddb/submit.cgi)
	 * 
	 * For Filesystem: 
	 * 	- sudo (defaults to false, set to true if you want to execute the callback binary using sudo)
	 * 	- motd_file (defaults to 'motd.txt', the file to display if you send a 'motd' Message Of The Day CDDB command)
	 * 
	 * For MDB2:
	 * 	- sudo (defaults to false, set to true if you want to execute the callback binary using sudo)
	 * 	- 
	 * 
	 * <code>
	 * $params_cddbp = array(
	 * 	'persist' => true, 
	 * );
	 * 
	 * $cddb = new Net_CDDB_Client('cddbp://my_username@freedb.org:8880', 'cddiscid:///dev/acd0', $params_cddbp);
	 * //$cddb = new Net_CDDB_Client('http://my_username@freedb.org:80/~cddb/cddb.cgi', 'cddiscid:///dev/acd0');
	 * //$cddb = new Net_CDDB_Client('filesystem:///path/to/FreeDB_Database/', 'cddiscid:///dev/acd0');
	 * //$cddb = new Net_CDDB_Client('mdb2.mysql://username:password@hostname/my_freedb_database', 'cddiscid:///dev/acd0');
	 * 
	 * print("\n\nSearching for the CD in the CD-ROM drive:\n"); 
	 * $discs = $cddb->searchDatabaseForCD(); 
	 * print_r($discs);
	 * </code>
	 * 
	 * @todo Net_CDDB::listSupportedProtocols()
	 * @todo Net_CDDB::listSupportedReaders()
	 * @todo Net_CDDB::addObserver()
	 * @todo Sudo to a specific user? (use user key of cdreader dsn?)
	 * 
	 * @access public
	 * 
	 * @param string $protocol A DSN string specifying the protocol and server to connect to (i.e.: cddbp://freedb.org:8880)
	 * @param string $cdreader A DSN string specifying which CD-ROM TOC reader to use (i.e.: cddiscid:///dev/acd0)
	 * @param array $option_params An array of options / connection parameters
	 */
	function __construct($protocol = 'cddbp://freedb.org:8880', $cdreader = 'cddiscid:///dev/acd0', $option_params = array())
	{
		$option_defaults = array(
			'submit_uri' => '/~cddb/submit.cgi', 
			'submit_server' => 'freedb.org', 
			'user' => 'unknown_user', 
			'host' => 'unknown_host', 
			'email' => '', 
			'persist' => true, 
			'sudo' => false, 
			'debug' => false, 
			);
		
		$option_params = array_merge($option_defaults, $option_params); // Set defaults for unset array keys
		
		$cdreader_defaults = array(
			'scheme' => 'cddiscid', // Class used to read TOC
			'host' => '', // Not used
			'port' => '', // Not used
			'user' => '', // Not used
			'path' => '/dev/acd0', // Path to CD-ROM device
			);
		
		$cdreader_params = array_merge($cdreader_defaults, parse_url(str_replace('://', '://null', $cdreader)));
		
		// Store cd reader params
		$this->_device = $cdreader_params['path'];
		
		// Set class vars
		$this->_debug = $option_params['debug']; 
		$this->_email = $option_params['email'];
		$this->_persist = $option_params['persist'];
		$this->_submit_server = $option_params['submit_server'];
		//$this->_submit_uri = $option_params['submit_uri'];
		$this->_sudo = $option_params['sudo'];
		
		// Create instances of the protocol/cd reader classes
		$this->_backend = $this->_createProtocol($protocol, $option_params); // Protocol instance
		$this->_cdreader = $this->_createReader($cdreader_params['scheme'], $option_params); // CD reader instance
		
		$this->_buffer = '';
	}
	
	/**
	 * Establish connection to CDDB server
	 * 
	 * You *do not* need to manually call this, the other public methods of this 
	 * class will ensure a connection is established before querying the 
	 * database on their own. This is provided as a convience method just in 
	 * case someone needs it. 
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function connect()
	{
		if (!$this->_backend->connected()) {
			return $this->_backend->connect();
		}
		
		return true;
	}
	
	/**
	 * Disconnect from CDDB server
	 * 
	 * @access public
	 * 
	 * @return bool
	 */
	function disconnect()
	{
		if ($this->_backend->connected()) {
			return $this->_backend->disconnect();
		}
		
		return true;
	}
	
	/**
	 * Retrieve list of CDDB categories from CDDB server
	 * 
	 * Although the list of CDDB categories might look suspiciously like a list 
	 * of music genres, there is actually a distinction. The CDDB server 
	 * protocol defines a few categories which every audio CD must fit into. 
	 * Beyond that, each actual record has a free-form text field indicating a 
	 * more specific music genre. For instance, a record for a 'Punk Rock' audio
	 * CD would belong in the 'Rock' category with a disc genre of 'Punk Rock'.
	 * 
	 * @access public
	 * 
	 * @return array
	 */
	function getCategories()
	{
		$arr_categories = array();
		
		$this->_send('cddb lscat');
		
		switch ($this->_readResponseStatus()) {
			case NET_CDDB_RESPONSE_OK_FOLLOWS: // 210 
				while ($str = $this->_readLine()) {
					$arr_categories[] = $str;
				}
				
				return $arr_categories;
			default:
				return array(); 
		}
	}
	
	/**
	 * Send a query to the backend and retrieve the result from the query
	 * 
	 * @access protected
	 * 
	 * @param string $query The command string to send
	 * 
	 * @return void
	 */
	function _send($query)
	{
		$this->connect();
		
		$this->_backend->send($query); // Send command
		$this->_buffer = $this->_backend->recieve(); // Recieve data and place in buffer
		
		if (!$this->_persist) {
			$this->disconnect();
		}
	}
	
	/**
	 * Reads the return status for the last command ( 200, 210, 500, etc. )
	 * 
	 * @access protected
	 * 
	 * @return int
	 */
	function _readResponseStatus()
	{
		return $this->_backend->status();
	}
	
	/**
	 * Read a line from the response buffer
	 * 
	 * @access protected
	 * 
	 * @return string
	 */
	function _readLine()
	{
		$return = '';
		
		if (!strlen($this->_buffer) or trim($this->_buffer) == '.') {
			$this->_buffer = '';
			return false;
		}
		
		if (false !== ($pos = strpos($this->_buffer, "\n"))) {
			$return = substr($this->_buffer, 0, $pos);
			$this->_buffer = substr($this->_buffer, $pos + 1);
		} else {
			$return = $this->_buffer;
			$this->_buffer = '';
		} 
		
		return trim($return);
	}
	
	/**
	 * Get CD information by genre and disc id value
	 * 
	 * Searches the CDDB database for a CD matching the given genre and discid 
	 * and returns a {@link Net_CDDB_Disc} object containing information for 
	 * that CD. Returns false if the disc was not found in the database or an 
	 * error occurs. 
	 * 
	 * @see Net_CDDB_Disc 
	 * @see Net_CDDB_Client::getDetails()
	 * 
	 * @access public
	 * 
	 * @param string $genre
	 * @param string $discid
	 * @return Net_CDDB_Disc 
	 */
	function getDetailsByDiscId($category, $discid, $obj = true)
	{
		$this->_send('cddb read ' . $category . ' ' . $discid);
		
		/*
		rock 7707af0b
	 	rock 85095a0b
	 	misc 1209b613
	 	misc cb10ad0d
		
		210	OK, CDDB database entry follows (until terminating marker)
		401	Specified CDDB entry not found.
		402	Server error.
		403	Database entry is corrupt.
		409	No handshake.
		*/
		
		switch ($this->_readResponseStatus())	{
			case NET_CDDB_RESPONSE_OK_FOLLOWS: // 210
				
				$record = $this->_parseRecord($this->_buffer, $category);
				
				if ($obj) {
					// $discid, $artist, $title, $genre, $year, $tracks, $extras, $offsets, $length, $revision = 0, $playorder = ''
					return new Net_CDDB_Disc($record['discid'], $record['dartist'], $record['dtitle'], $category, $record['dgenre'], $record['dyear'], $record['tracks'], $record['dlength'], $record['revision'], $record['playorder']);
				} else {
					return $record;
				}
				
			case NET_CDDB_RESPONSE_SERVER_UNAVAIL: // 401, file not found
			case NET_CDDB_RESPONSE_SERVER_ERROR: // 402, Server error
			case NET_CDDB_RESPONSE_SERVER_CORRUPT: // 403, Corrupt database entry
			case NET_CDDB_RESPONSE_SERVER_NOHANDSHAKE: // 409, No handshake...?
			default:
				return false;
		}
	}
	
	/**
	 * Fill a CDDBDisc object with detailed information about the disc 
	 * 
	 * After searching the CDDB database for discs matching yours by discid and 
	 * track offsets, you can use this function to get detailed information 
	 * about one of the CDDBDisc objects. This function will fill and return the 
	 * {@link Net_CDDB_Disc} object with information about track offsets, song titles, etc.
	 * 
	 * @access public
	 * 
	 * @param Net_CDDB_Disc $disc
	 * @return Net_CDDB_Disc $disc
	 */
	function getDetails($disc)
	{
		if (is_a($disc, 'Net_CDDB_Disc')) {
			return $this->getDetailsByDiscId($disc->getCategory(), $disc->getDiscId());
		} else if (is_array($disc)) {
			return $this->getDetailsByDiscId($disc['category'], $disc['discid'], false);
		} else {
			return PEAR::raiseError('Parameter to Net_CDDB::getDetails() must be a Net_CDDB_Disc or an array, type was: ' . gettype($disc) . '.');
		}
	}
	
	/**
	 * Calculate the 8-byte disc id from the CD-ROM drive
	 * 
	 * @uses Net_CDDB_Utilities::calculateDiscId()
	 * @see Net_CDDB_Client::calculateTrackOffsetsForCD()
	 * @see Net_CDDB_Client::calculateLengthForCD()
	 * 
	 * @access public
	 * 
	 * @param string $device
	 * @return string 
	 */
	function calculateDiscIdForCD($device = null)
	{
		if (is_null($device)) {
			return $this->calculateDiscId($this->calculateTrackOffsetsForCD(), $this->calculateLengthForCD());
		} else {
			return $this->calculateDiscId($this->calculateTrackOffsetsForCD($device), $this->calculateLengthForCD($device));
		}
	}
	
	/**
	 * Calculate the track offsets from the TOC of the disc in the CD-ROM drive
	 * 
	 * @see Net_CDDB_Utilities::calculateDiscIdForCD()
	 * 
	 * @access public
	 * 
	 * @param string $device
	 * @return array
	 */
	function calculateTrackOffsetsForCD($device = null)
	{
		$offsets = array(); // Holds the array of track offsets
		
		if (is_null($device)) {
			$offsets = $this->_cdreader->calcTrackOffsets($this->_sudo, $this->_device);
		} else {
			$offsets = $this->_cdreader->calcTrackOffsets($this->_sudo, $device);
		}
		
		if (PEAR::isError($offsets)) {
			return $offsets;
		} else if (is_array($offsets)) {
			return array_slice($offsets, 0, -1);
		} else {
			return PEAR::raiseError('The cdreader driver must return an array of track offsets and the disc length.');
		}
	}
	
	/**
	 * Calculate the length in seconds of the disc in the CD-ROM drive
	 * 
	 * @access public
	 * 
	 * @param string $device
	 * @return integer
	 */
	function calculateLengthForCD($device = null)
	{
		if (is_null($device)) {
			$tmp = $this->_cdreader->calcTrackOffsets($this->_sudo, $this->_device);
		} else {
			$tmp = $this->_cdreader->calcTrackOffsets($this->_sudo, $device);
		}
		
		if (PEAR::isError($tmp)) {
			return $tmp;
		} else if (is_array($tmp)) {
			return end($tmp);
		} else {
			return PEAR::raiseError('The cdreader driver must return an array of track offsets and the disc length.');
		}
	}
	
	/**
	 * Search and return {@link Net_CDDB_Disc} objects matching the disc in the CD-ROM drive
	 * 
	 * @access public
	 * 
	 * @param string $device
	 * @return array
	 */
	function searchDatabaseForCD($device = null)
	{
		if (is_null($device)) {
			
			$offsets = $this->calculateTrackOffsetsForCD($this->_device);
			$length = $this->calculateLengthForCD($this->_device);
			
			if (PEAR::isError($offsets)) {
				return $offsets;
			} else if (PEAR::isError($length)) {
				return $length;
			}
			
			return $this->searchDatabase($offsets, $length);
			
		} else {
			
			$offsets = $this->calculateTrackOffsetsForCD($device);
			$length = $this->calculateLengthForCD($device);
			
			if (PEAR::isError($offsets)) {
				return $offsets;
			} else if (PEAR::isError($length)) {
				return $length;
			}
			
			return $this->searchDatabase($offsets, $length);
		}
	}
	
	/**
	 * Search and return {@link Net_CDDB_Disc} objects for CDs matching given length/offsets
	 * 
	 * @access public
	 * 
	 * @param array $track_offsets
	 * @param integer $length
	 * 
	 * @return Net_CDDB_Disc
	 */
	function searchDatabase($track_offsets, $length, $obj = true)
	{
		$query = 'cddb query ';
		
		$query = $query . $this->calculateDiscId($track_offsets, $length) . ' ';
		
		$query = $query . count($track_offsets) . ' ';
		
		foreach ($track_offsets as $track => $track_offset) {
			$query = $query . $track_offset . ' ';
		}
		
		$query = $query . $length;
		
		return $this->searchDatabaseWithRawQuery($query, $obj);
	}
	
	/**
	 * Utility method to search for CDDB discs using a raw 'cddb query ...' command
	 * 
	 * This is provided as a utility method for anyone who might want to search 
	 * the CDDB database with a raw CDDB query command. It returns an array of 
	 * {@link Net_CDDB_Disc} objects which match the query. 
	 * 
	 * <code>
	 * // CDDB queries look like this:
	 * // cddb query [discid] [num_tracks] [offset_1] [offset_2] ... [offset_n] [length]
	 * // Replace [discid] with the 8-char discid, [num_tracks] with the number of tracks, [offset_*] with the track offsets, and [length] with the total length of the CD in seconds
	 * $query = "cddb query 50dd30f 15 150 21052 43715 58057 71430 92865 117600 131987 150625 163292 181490 195685 210197 233230 249257 3541";
	 * print_r($cddbsearchDatabaseWithRawQuery($query));
	 * </code>
	 * 
	 * @access public
	 * 
	 * @param string $query
	 * @return array
	 */
	function searchDatabaseWithRawQuery($query, $obj = true)
	{
		$this->_send($query);
		
		/*
		200	Found exact match
		211	Found inexact matches, list follows (until terminating marker)
		202	No match found
		403	Database entry is corrupt
		409	No handshake
		*/
		
		switch ($this->_readResponseStatus()) {
			case NET_CDDB_RESPONSE_OK: // 200, Just one match
				
				$record = $this->_parseResult($this->_buffer);
				
				if ($obj) {
					return array(new Net_CDDB_Disc($record['discid'], $record['dartist'], $record['dtitle'], $record['category'], $record['dgenre'], 0, array(), 0, 0, ''));
				} else {
					return array($record);
				}
			
			case NET_CDDB_RESPONSE_OK_FOLLOWS: // 210, Multiple exact matches
			case NET_CDDB_RESPONSE_OK_INEXACT: // 211, Multiple inexact matches
				
				$arr = array(); // Will store each CDDB object/
				
				while ($str = $this->_readLine()) {
					
					$record = $this->_parseResult($str);
					
					if ($obj) {
						$arr[] = new Net_CDDB_Disc($record['discid'], $record['dartist'], $record['dtitle'], $record['category'], $record['dgenre'], 0, array(), 0, 0, '');
					} else {
						$arr[] = $record;
					}
				}
				
				return $arr;
				
			case NET_CDDB_RESPONSE_OK_NOMATCH: // 202, No matches found
			case NET_CDDB_RESPONSE_SERVER_CORRUPT: // 403, Database entry is corrupt
				return array(); // Return empty array, nothing was found
			case NET_CDDB_RESPONSE_ERROR_ALREADY: // 502, Already performed a query for disc ID: xxxxxxxx
				
				$this->disconnect();
				return $this->searchDatabaseWithRawQuery($query, $obj);
				
			case NET_CDDB_RESPONSE_SERVER_NOHANDSHAKE: // 409, No handshake
			default:
				return false; // No handshake ( connection error? )
		}
	}
	
	/**
	 * Calculate a disc ID based on the track offsets and the disc length
	 * 
	 * @param array $track_offsets The offsets of the tracks on the CD
	 * @param int $length The total number of seconds for the disc
	 * @param bool $query 
	 * @return string 8-character disc ID value
	 */
	function calculateDiscId($track_offsets, $length, $query = false)
	{
		if ($query) {
			$query = 'discid ' . count($track_offsets) . ' ';
			
			foreach ($track_offsets as $track => $track_offset) {
				$query = $query . $track_offset . ' ';
			}
			
			$query = $query . $length;
			
			$this->_send($query);
			
			switch($this->_readResponseStatus()) {
				case NET_CDDB_RESPONSE_OK: // 200, OK
					return substr(trim($this->_buffer), -8, 8);
				case NET_CDDB_RESPONSE_ERROR_SYNTAX: // 500, Syntax error
				default:
					return false;
			}
		} else {
			return parent::calculateDiscId($track_offsets, $length);
		}
	}
	
	/**
	 * Submit a revised/new {@link Net_CDDB_Disc} object to the FreeDB.org database 
	 * 
	 * Note that at this time only submission via HTTP is supported. The only 
	 * other option is SMTP, which may be supported in the future. This method 
	 * uses the HTTP_Request class to make the request. I chose to include the 
	 * HTTP_Request class here instead of globally because most users will use 
	 * the Net_CDDB package for read-only access to CDDB servers, and thus 
	 * won't be using this method.  
	 * 
	 * @uses HTTP_Request
	 * 
	 * @access public
	 * 
	 * @param Net_CDDB_Disc $obj A {@link Net_CDDB_Disc} object containing CD information
	 * @param string $email 
	 * @param bool $test Whether or not this should be a test submission
	 * @return bool
	 */
	function submitDisc($obj, $email = null, $test = false)
	{
		/*
		POST /~cddb/submit.cgi HTTP/1.0
		Category: newage
		Discid: 4306eb06
		User-Email: joe@myhost.home.com
		Submit-Mode: submit
		Charset: ISO-8859-1
		X-Cddbd-Note: Sent by free CD player - Questions: support@freecdplayer.org.
		Content-Length: 960
		
		# xmcd
		#
		# Track frame offsets:
		[ data omitted for brevity ]
		PLAYORDER=
		
		200 OK, submission has been sent.
		500 Missing required header information.
		500 Internal Server Error: [description].
		501 Invalid header information [details].
		
		where "details" can be one of the following:
		freedb category
		disc ID
		email address
		charset
		*/
		
		/**
		 * Using HTTP_Request object to send the request
		 */
		include_once 'HTTP/Request.php';
		
		if (!class_exists('HTTP_Request')) {
			return PEAR::raiseError('Net_CDDB::submitDisc() requires the PEAR HTTP_Request class to be available.');
		}
		
		if (!strlen($email)) {
			if (!strlen($this->_email)) {
				return PEAR::raiseError('Net_CDDB::submitDisc() must be provided with a valid email address via the \'email\' constructor connection parameter or the second parameter to Net_CDDB::submitDisc().');
			}
			
			$email = $this->_email;
		}
		
		if ($test) {
			$submit_mode = 'test';
		} else {
			$submit_mode = 'submit';
		}
		
		// Dump record back to a string
		$record = $obj->toString();
		
		// Create a new HTTP_Request object, POST CDDB record to server
		$request = new HTTP_Request('http://' . $this->_submit_server . ':80/~cddb/submit.cgi');
		$request->setMethod(HTTP_REQUEST_METHOD_POST);
		
		$request->addHeader('Category', $obj->getCategory());
		$request->addHeader('Discid', $obj->getDiscId());
		$request->addHeader('User-Email', $email);
		$request->addHeader('Submit-Mode', $submit_mode);
		$request->addHeader('Charset', 'ISO8859-1');
		$request->addHeader('X-Cddb-Note', 'Sent by PHP/PEAR/NET_CDDB, questions to keith@uglyslug.com');
		$request->addHeader('Content-Length', strlen($record));
		
		$request->setBody($record);
		
		if (PEAR::isError($resp = $request->sendRequest())) {
			return PEAR::raiseError('Net_CDDB::submitDisc() failed, HTTP_Request said: ' . $err->getMessage());
		} else {
			switch ((int) $resp) {
				case NET_CDDB_RESPONSE_OK: // 200
					return true;
				case NET_CDDB_RESPONSE_ERROR_SYNTAX: // 500
				case NET_CDDB_RESPONSE_ERROR_ILLEGAL: // 501
				default:
					return PEAR::raiseError('Submit failed, CDDB server said: ' . trim($resp, " .\n\r\t"));
			}
		}
	}
	
	/**
	 * Create a cd reader instance of a given type with the parameters
	 * 
	 * @see Net_CDDB_Client
	 * @see Net_CDDB::_createProtocol()
	 * 
	 * @access public
	 * 
	 * @param string $type
	 * @param array $params
	 * @return object
	 */
	function _createReader($type, $params)
	{
		$file = strtolower($type);
		$class = 'Net_CDDB_Reader_' . $file;
		
		/**
		 * Require the file the reader class is stored in
		 */
		include_once 'Net/CDDB/Reader/' . $file . '.php';
		
		if (class_exists($class)) {	 
			return new $class($params);
		} else {
			return PEAR::raiseError('Could not find reader file for: ' . $file);
		}
	}
	
	/**
	 * Get protocol help from the CDDB server
	 * 
	 * @access public
	 * 
	 * @param string $cmd
	 * @param string $subcmd
	 * @return string
	 */
	function help($cmd = '', $subcmd = '')
	{
		$this->_send('help ' . $cmd . ' ' . $subcmd);
		
		return trim($this->_buffer, ' .');
	}
	
	/**
	 * Get the message of the day from the CDDB server
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function motd()
	{
		$this->_send('motd');
		
		return trim($this->_buffer, ' .');
	}
	
	/**
	 * Get CDDB database server statistics
	 * 
	 * @access public
	 * 
	 * @return array
	 */
	function statistics()
	{
		$arr = array(); // Stores the server statistics in a key => value array
		
		$this->_send('stat');
		
		/*
		current proto: 1
		max proto: 6
		interface: cddbp
		gets: no
		puts: no
		updates: no
		posting: no
		validation: accepted
		quotes: no
		strip ext: no
		secure: yes
		current users: 4
		max users: 100
		data: 19209
		folk: 133737
		jazz: 108090
		misc: 599479
		rock: 539808
		country: 48886
		blues: 97594
		newage: 71832
		reggae: 24004
		classical: 161561
		soundtrack: 67342
		*/
		
		while ($line = $this->_readLine()) {
			
			if (false !== strpos($line, ':')) {
				
				$tmp = explode(':', $line);
				$key = str_replace(' ', '_', trim($tmp[0]));
				$val = trim($tmp[1]);
				
				switch ($key) {
					case 'current_proto':
					case 'max_proto':
					case 'interface':
					case 'gets':
					case 'puts':
					case 'updates':
					case 'posting':
					case 'validation':
					case 'quotes':
					case 'strip_ext':
					case 'secure':
					case 'current_users':
					case 'max_users':
					case 'data':
					case 'folk':
					case 'jazz':
					case 'misc':
					case 'rock':
					case 'country':
					case 'blues':
					case 'newage':
					case 'reggae':
					case 'classical':
					case 'soundtrack':
						$arr[$key] = $val; 
				}
			}
		}
		
		return $arr;
	}
	
	/**
	 * Get a list of CDDB mirrors
	 * 
	 * @access public
	 * 
	 * @return array
	 */
	function sites()
	{
		$sites = array(); // Array containing records for all sites
		$this->_send('sites');
		
		// site protocol port address latitude longitude description
		while ($line = $this->_readLine()) {
			
			$arr = explode(' ', $line);
			$sites[] = array(
				'site' => $arr[0], 
				'protocol' => $arr[1], 
				'port' => (int) $arr[2], 
				'address' => $arr[3], 
				'latitude' => $arr[4], 
				'longitude' => $arr[5], 
				'description' => implode(' ', array_slice($arr, 6, count($arr) - 6))
				);
		}
		
		return $sites;
	}
	
	/**
	 * Get the CDDB server version string 
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function version()
	{
		$this->_send('ver');
		return trim($this->_buffer);
	}
}

?>