<?php

/**
 * Driver class for Net_CDDB_Client/Server, query a database of FreeDB data
 * 
 * @see Net_CDDB_Client
 * @see Net_CDDB_Server
 * @see Net_CDDB_Protocol_CDDBP
 * @see Net_CDDB_Protocol_HTTP
 * @see Net_CDDB_Protocol_Filesystem
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Require the utilities class, needed for calculating disc ids
 */
require_once 'Net/CDDB/Utilities.php';

/**
 * We need the constants from the Net_CDDB base file
 */
require_once 'Net/CDDB.php';

/**
 * All protocols extend the Net_CDDB_Protocol base class
 */
require_once 'Net/CDDB/Protocol.php';

/**
 * MDB2 database class
 */
require_once 'MDB2.php';

/**
 * Connection protocol for querying a database import of FreeDB data
 * 
 * You probably don't want to use this protocol unless you have a very specific 
 * need for it. This protocol doesn't support a large chunk of the CDDB protocol 
 * and chances are your local CDDB data won't be as up-to-date as the FreeDB.org 
 * database servers. 
 * 
 * To use this protocol to access disc information from an SQL database of 
 * FreeDB.org data, you need to first load your SQL database. The 
 * CDDB_Importer.php script included with the package can do this for you, or 
 * you can download and run a .SQL database dump from 
 * {@link http://www.uglysug.com/} After that's all set, use a DSN string like 
 * this when you instantiate your {@see Net_CDDB_Client} class:
 * 
 * <code>
 * 	$client = new Net_CDDB_Client('mdb2.mysql://user:pass@host/db_name', ...);
 * </code>
 * 
 * The DSN string is a standard PEAR MDB2-style DSN string used to connect 
 * to a database, except that the string 'mdb2.' is appended to the beginning 
 * of the MDB2 DSN string.  
 * 
 * @todo Finish implementing all of the CDDB commands
 * @todo Support more than one protocol level (and the proto command...?)
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Protocol_Mdb2 extends Net_CDDB_Protocol
{
	/**
	 * Database MDB2 instance
	 * 
	 * @var object
	 * @access protected
	 */
	var $_mdb2;
	
	/**
	 * Array of MDB2 options
	 * 
	 * @var array
	 * @access protected
	 */
	var $_options;
	
	/**
	 * DSN string to the MDB2 database connection
	 * 
	 * @var string
	 * @access protected
	 */
	var $_dsn;
	
	/**
	 * String buffer containing protocol data
	 * 
	 * @var string
	 * @access protected
	 */
	var $_buffer;
	
	/**
	 * Integer buffer containing protocol status information
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_status_buffer;
	
	/**
	 * Constructor (PHP v4.x)
	 * 
	 * @access public
	 * @see Net_CDDB_Protocol_Filesystem::__construct()
	 */
	function Net_CDDB_Protocol_Mdb2($dsn = 'mysql://root:@localhost:3306/cddb', $options)
	{
		$this->__construct($dsn, $options);
	}
	
	/**
	 * Constructor (PHP v5.x)
	 * 
	 * @access public
	 * @param string $dsn A PEAR MDB2 style database connection string
	 * @param array $options
	 */
	function __construct($dsn = 'mysql://root:@localhost:3306/cddb', $options)
	{
		$this->_dsn = $dsn;
		
		// Initialize buffer and status buffer
		$this->_buffer = '';
		$this->_status_buffer = NET_CDDB_RESPONSE_ERROR_SYNTAX;
	}
	
	/**
	 * Connect to the database
	 * 
	 * @access public
	 * @return boolean
	 */
	function connect()
	{
		$this->_mdb2 =& MDB2::factory($this->_dsn, $this->_options);
		$this->_mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);
	}
	
	/**
	 * Check if we've connected to the database or not
	 * 
	 * @access public
	 * @return boolean
	 */
	function connected()
	{
		return !is_null($this->_mdb2);
	}
	
	/**
	 * Send a query to the Net_CDDB_Protocol_Mdb2 object, the query will be parsed and the buffers will be filled with the response
	 * 
	 * Not all CDDB commands are implemented for this protocol, some don't make 
	 * sense in the context of a database-based protocol and some just havn't 
	 * been implemented yet. 
	 * 
	 * This method basically parses and dispatches the query to other protected 
	 * methods of the class for further processing. 
	 * 
	 * @access public
	 * @see Net_CDDB_Protocol_Mdb2::recieve()
	 * 
	 * @param string $query
	 * @return void
	 */
	function send($query)
	{
		// First, break the query up into two parts, $cmd and $query
		//	- $cmd holds the base command (i.e.: cddb read)
		//	- $query holds the command parameters (i.e.: rock 7709a259)
		$cmd = trim($query);
		
		if (current($explode = explode(' ', $query)) == 'cddb') {
			$cmd = current($explode) . ' ' . next($explode);
		} else {
			$cmd = current($explode);
		}
		
		$query = trim(substr($query, strlen($cmd)));
		
		// Initial buffers
		$this->_buffer = '';
		$this->_status_buffer = NET_CDDB_RESPONSE_ERROR_SYNTAX; // 500 error by default
		
		$impl_cmds = array(
			'cddb read'		=> '_cddbRead', 
			'cddb lscat'	=> '_cddbLscat',
			'cddb query'	=> '_cddbQuery', 
			'discid'		=> '_discid', 
			'ver'			=> '_ver', 
			'cddb hello'	=> '_cddbHello', 
			//'help'		=> '_help', 
			'motd'			=> '_motd', 
			//'proto'		=> '_proto', 
			'quit'			=> '_quit', 
			//'sites'		=> '_sites', 
			'stat'			=> '_stat', 
			//'whom'		=> '_whom',  
			);
		
		if (isset($impl_cmds[$cmd]) and method_exists($this, $impl_cmds[$cmd])) {
			$this->{$impl_cmds[$cmd]}($cmd, $query);
			return;
		} else {
			return;
		}
	}
	
	/**
	 * Handle a CDDB 'discid' (Calculate a Disc ID) query and fill the buffer with a response
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _discid($cmd, $query)
	{
		$track_offsets = explode(' ', $query);
		array_pop($track_offsets);
		array_shift($track_offsets);
		
		$this->_buffer = 'Disc ID is ' . Net_CDDB_Utilities::calculateDiscId($track_offsets);
		$this->_status_buffer = NET_CDDB_RESPONSE_OK;
	}
	
	/**
	 * Handle a 'cddb query' (Find possible disc matches by disc id) and fill the buffer with a response
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _cddbQuery($cmd, $query)
	{
		// 200	Found exact match
		// 211	Found inexact matches, list follows (until terminating marker)
		// 202	No match found
		
		/*
		211 Found inexact matches, list follows (until terminating `.')
		reggae d50dd30f Various / Ska Island
		misc d50dd30f Various / Ska Island
		.
		
		200 jazz 820e770a Joshua Redman / Wish 1993
		 */
		
		$matches = 0;
		
		$res = $this->_mdb2->query('
			SELECT 
				category.name AS category_name,
				artist.name AS artist_name, 
				disc.title AS disc_title,
				disc.discid AS disc_discid
			FROM 
				disc
			LEFT JOIN 
				artist ON disc.artist_id = artist.id
			LEFT JOIN
				category ON disc.category_id = category.id 
			WHERE 
				disc.discid = ' . $this->_mdb2->quote(current(explode(' ', $query))));
		
		if (PEAR::isError($res)) {
			$this->_status_buffer = 403; // Something is botched with the database...?
		} else {
			
			while ($row = $res->fetchRow()) {
				// category_name disc_id artist / title
				$this->_buffer .= $row['category_name'] . ' ' . $row['disc_discid'] . ' ' . $row['artist_name'] . ' / ' . $row['disc_title'] . "\n";
				$matches++;
			}
			
			if ($matches > 1) {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK_INEXACT; // 211
			} else if ($matches == 1) {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK; // 200 OK status
			} else {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK_NOMATCH; // 202
			}
			
		}
	}
	
	/**
	 * Handle a 'cddb lscat' (Display disc categories) query and fill the buffer with a response
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _cddbLscat($cmd, $query)
	{
		$res = $this->_mdb2->query('SELECT name FROM category ORDER BY name');
		
		if (PEAR::isError($res)) {
			$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_CORRUPT; // Error in SQL query...?			
		} else {
			
			while ($row = $res->fetchRow()) {
				$this->_buffer .= $row['name'] . "\n";
			}
			
			$this->_buffer = trim($this->_buffer);
			
			$this->_status_buffer = NET_CDDB_RESPONSE_OK_FOLLOWS; // OK status
		}
	}	
	
	/**
	 * Handle a 'cddb read ...' (read a complete disc entry) query and fill the buffer with a response
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _cddbRead($cmd, $query)
	{
		/*
		210	OK, CDDB database entry follows (until terminating marker)
		401	Specified CDDB entry not found.
		402	Server error.
		403	Database entry is corrupt.
		409	No handshake.
		 */
		
		if (count($parts = explode(' ', $query)) == 2) {
			
			// The aliased field names match the associative array keys so 
			//	we can pass the result directly to the Net_CDDB_Disc object
			$res_disc = $this->_mdb2->query('
				SELECT 
					disc.id AS id,
					disc.discid AS discid, 
					artist.name AS dartist, 
					disc.title AS dtitle, 
					category.name AS category, 
					genre.name AS dgenre,
					disc.year AS dyear,  
					NULL AS tracks, 
					disc.length AS dlength,
					disc.revision AS revision, 
					disc.submitted_via AS submitted_via, 
					disc.processed_by AS processed_by, 
					disc.extra_data AS dextra, 
					disc.playorder AS playorder 
				FROM
					disc
				LEFT JOIN 
					artist ON disc.artist_id = artist.id
				LEFT JOIN 
					genre ON disc.genre_id = genre.id
				LEFT JOIN 
					category ON disc.category_id = category.id
				WHERE
					category.name = ' . $this->_mdb2->quote($parts[0]) . ' AND 
					disc.discid = ' . $this->_mdb2->quote($parts[1]));
			
			if (!PEAR::isError($res_disc) and $res_disc->numRows()) {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK_FOLLOWS;
				
				$arr_disc = $res_disc->fetchRow();
				
				// Fetch the tracks for this disc from the database
				$res_track = $this->_mdb2->query('
					SELECT
						track.title AS ttitle, 
						artist.name AS tartist, 
						track.toffset AS offset, 
						track.extra_data AS extt, 
						track.length AS length
					FROM 
						track
					LEFT JOIN 
						artist ON track.artist_id = artist.id
					WHERE
						disc_id = ' . $arr_disc['id'] . ' 
					ORDER BY
						num ASC ');
				
				$arr_disc['tracks'] = $res_track->fetchAll();
				unset($arr_disc['id']);
				
				// Create the disc record and then dump it to a string
				$obj_disc = new Net_CDDB_Disc($arr_disc);
				$this->_buffer = $obj_disc->toString();
			} else {
				$this->_status_buffer = NET_CDDDB_RESPONSE_SERVER_UNAVAIL;
			}
			
		} else {
			$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_ERROR; // Server error, bad parameters
		}
	}
	
	/**
	 * Handle a 'motd' (Message Of The Day) query and fill the buffers with the repsonse
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _motd($cmd, $query)
	{
		/*
		210	Last modified: 05/31/96 06:31:14 MOTD follows (until terminating marker)
		401	No message of the day available
		*/
		
		//$res = $this->_mdb2->query("");
		$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_UNAVAIL;
	}
	
	/**
	 * Handle a 'ver' (get CDDB server version) command and fill the buffers with a response
	 * 
	 * @access protected
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _ver($cmd, $query)
	{
		$this->_status_buffer = NET_CDDB_RESPONSE_OK;
		$this->_buffer = 'PHP/PEAR/' . get_class($this) . ' v' . NET_CDDB_VERSION . ' Copyright (c) 2006-' . date('Y') . ' Keith Palmer Jr.';
	}
	
	/**
	 * Handle a cddb 'stat' (get server statistics) and fill the buffers with the response
	 * 
	 * @access protected
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _stat($cmd, $query)
	{
		$entry_counts = array();
		$total = 0;
		
		$res = $this->_mdb2->query('
			SELECT 
				category.name AS category_name, 
				COUNT(disc.category_id) AS category_entries 
			FROM
				category, 
				disc
			WHERE
				category.id = disc.category_id
			GROUP BY 
				disc.category_id ');
		
		while ($row = $res->fetchRow()) {
			$entry_counts[$row['category_name']] = $row['category_entries'];
			$total += $row['category_entries'];
		}
		
		$str = '';
		$str .= 'Server status:' . "\n";
		$str .= '    current proto: ' . NET_CDDB_PROTO_LEVEL . "\n";
		$str .= '    max proto: ' . NET_CDDB_PROTO_LEVEL . "\n";
		$str .= '    interface: MDB2' . "\n";
		$str .= '    gets: no' . "\n";
		$str .= '    puts: no' . "\n";
		$str .= '    updates: no' . "\n";
		$str .= '    posting: no' . "\n";
		$str .= '    validation: accepted' . "\n";
		$str .= '    quotes: no' . "\n";
		$str .= '    strip ext: no' . "\n";
		$str .= '    secure: yes' . "\n";
		$str .= '    current users: 1' . "\n";
		$str .= '    max users: 100' . "\n";
		$str .= 'Database entries: ' . $total . "\n";
		$str .= 'Database entries by category:' . "\n";
		
		foreach ($entry_counts as $category => $count) {
			$str .= '    ' . $category . ': ' . $count . "\n";
		}
		
		$this->_buffer = $str;
		$this->_status_buffer = NET_CDDB_RESPONSE_OK_FOLLOWS;
	}
	
	/**
	 * Read data from the protocol buffer
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
	 * Read the status of the last executed command from the protocol buffer
	 * 
	 * @access public
	 * @return int
	 */
	function status()
	{
		return $this->_status_buffer;
	}
	
	/**
	 * Disconnect from the database 
	 * 
	 * @access public
	 * @return void
	 */
	function disconnect()
	{
		$this->_mdb2->disconnect();
		$this->_mdb2 = null;
	}
	
	/**
	 * Report this class as *not* accessing remote resources for protocol output
	 * 
	 * @see Net_CDDB_Protocol::remote()
	 * @access public
	 * 
	 * @return boolean
	 */
	function remote()
	{
		return false;
	}
}

?>