<?php

/**
 * Driver class for Net_CDDB_Client/Server, query the local filesystem in FreeDB database dump format
 * 
 * @see Net_CDDB_Client
 * @see Net_CDDB_Server
 * @see Net_CDDB_CDDBP
 * @see Net_CDDB_HTTP
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
 * Connection protocol for querying local filesystem in FreeDB database dump format
 * 
 * The FreeDB.org project provides database dumps of the entire CDDB/FreeDB 
 * database. The database downloads are usually provided in the following 
 * formats:
 * 	- .tar.bz2 (tarred and bzipped)
 * 	- .tar.7z (tarred and 7-zipped)
 * 	- .torrent (Bittorrent download)
 * 
 * In order to use the database dumps (and thus this protocol) you need to 
 * download and extract one of the database dumps. The DSN provided to the 
 * {@link Net_CDDB_Client} should point to the directory of the database dumps:
 * <code>
 * 	$proto = 'filesystem:///usr/home/keith/FreeDB Database/';
 * 	$client = new Net_CDDB_Client($proto, 'cddiscid:///dev/acd0');
 * </code>
 * 
 * You probably don't want to use this protocol unless you have a very specific 
 * need for it. This protocol doesn't support a large chunk of the CDDB protocol 
 * and chances are your local CDDB data won't be as up-to-date as the FreeDB.org 
 * database servers. On the other hand, if you're without a network connection 
 * or need faster access to CDDB data than slow socket connections can provide, 
 * this protocol might work well for you.
 * 
 * One thing you should watch out for: The 'stat' command 
 * {@link Net_CDDB_Client::statistics()} can be extremelly slow if you set the 
 * 'use_stat_file' option to 'false' or don't provide a valid 'stat.db' file to 
 * the 'stat_file' option. This is because it needs to count the number of files 
 * in each of the CDDB category directories (i.e.: count about 1.8 to 2-million 
 * files). These two parameters default to:
 * 	- use_stat_file = true
 * 	- stat_file = 'stat.db'
 * 
 * When you first start using this protocol, you should create a 'stat.db' file 
 * in the CDDB database dump directory and chmod it so that its read/write by 
 * whatever user will be using the {@link Net_CDDB_Client} class. Run the 'stat' 
 * command immediately to build the 'stat.db' file so that the next 'stat' 
 * request doesn't need to count all of the files next time (it just reads the 
 * 'stat.db' file instead counters instead). 
 * 
 * @todo Finish implementing all of the CDDB commands
 * @todo Sites file
 * @todo Support more than one protocol level (and the proto command...?)
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Protocol_Filesystem extends Net_CDDB_Protocol
{
	/**
	 * The directory the FreeDB database dump is stored in
	 *
	 * @var string 
	 * @access protected
	 */
	var $_dir;
	
	/**
	 * Whether or not to use a 'Message of the Day' file for the 'motd' command
	 * 
	 * @var boolean
	 * @access protected
	 */
	var $_use_motd_file;
	
	/**
	 * The filename of the message of the day message (i.e.: motd.txt, which should be located in the FreeDB database dump directory)
	 * 
	 * @var string
	 * @access protected
	 */
	var $_motd_file;
	
	/**
	 * Whether or not to use a cached statistics file for the 'stat' command
	 * 
	 * If you don't use a the cached statistics file, then the number of files 
	 * in each of the CDDB category directories needs to be counted each time 
	 * you issue a 'stat' command. For a full dump of the database, this can 
	 * take 10 or 20 *minutes*. 
	 * 
	 * @var boolean
	 * @access protected
	 */
	var $_use_stat_file;
	
	/**
	 * The name of the statistics file (defaults to 'stat.db' in the CDDB database directory)
	 * 
	 * @var string
	 * @access protected
	 */
	var $_stat_file;
	
	/**
	 * 
	 * @todo Implement...
	 * @var boolean
	 * @access protected
	 */
	var $_use_sites_file;
	
	/**
	 * The filename of a file containing mirror site entries (i.e.: sites.txt)
	 * 
	 * @var string
	 * @access protected
	 */
	var $_sites_file;
	
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
	 * 
	 * @see Net_CDDB_Protocol_Filesystem::__construct()
	 */
	function Net_CDDB_Protocol_Filesystem($dsn = 'filesystem:///FreeDB', $options)
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
	function __construct($dsn = 'filesystem:///FreeDB', $options)
	{
		$dsn_params = $this->_parseDsn($dsn);	
		
		// Directory where the FreeDB database is stored
		$this->_dir = $dsn_params['path'];
		
		// Default parameter values
		$defaults = array(
			'use_motd_file'		=> true, 
			'motd_file'			=> 'motd.txt',
			'use_stat_file'		=> true, 
			'stat_file'			=> 'stat.db',
			'use_sites_file'	=> false, 
			'sites_file' 		=> 'sites.db', 
			);
		
		$defaults = array_merge($defaults, $options);
		
		$this->_use_motd_file = (boolean) $defaults['use_motd_file'];
		$this->_motd_file = $defaults['motd_file'];
		
		$this->_use_stat_file = (boolean) $defaults['use_stat_file'];
		$this->_stat_file = $defaults['stat_file'];
		
		$this->_use_sites_file = (boolean) $defaults['use_sites_file'];
		$this->_sites_file = $defaults['sites_file'];
		
		// Initialize buffer and status buffer
		$this->_buffer = '';
		$this->_status_buffer = NET_CDDB_RESPONSE_ERROR_SYNTAX;
	}
	
	/**
	 * Pretend to connect to a remote server while we actually just check if the database directory is readable
	 * 
	 * Function will return false if either the filesystem directory does not 
	 * exist or if the directory is not readable. 
	 * 
	 * @access public
	 * 
	 * @return boolean
	 */
	function connect()
	{
		if (is_dir($this -> _dir) and is_readable($this -> _dir)) {
			return true; 
		} else {
			return false;
		}
	}
	
	/**
	 * Pretend to check if we are connected to a server
	 * 
	 * @access public
	 * 
	 * @return boolean
	 */
	function connected()
	{
		return $this->connect();
	}
	
	/**
	 * Send a query to the Net_CDDB_Protocol_Filesystem object, the query will be parsed and the buffers will be filled with the response
	 * 
	 * Not all CDDB commands are implemented for this protocol, some don't make 
	 * sense in the context of a filesystem protocol and some just havn't been 
	 * implemented yet. 
	 * 
	 * This method basically parses and dispatches the query to other protected 
	 * methods of the class for further processing. 
	 * 
	 * @access public
	 * @see Net_CDDB_Protocol_Filesystem::recieve()
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
		
		if ($dh = opendir($this->_dir)) {
			
			while ($dir = readdir($dh)) {
				
				$file = current(explode(' ', $query));
				$path = $this->_dir . '/' . $dir . '/' . $file;
				
				if (is_file($path)) {
					$this->_buffer .= $dir . ' ' . $file . ' ' . Net_CDDB_Utilities::parseFieldFromRecord(file_get_contents($path), NET_CDDB_FIELD_DISC_TITLE) . "\n";
					$matches++;
				}
			}
			
			if ($matches > 1) {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK_INEXACT; // 211
			} else if ($matches == 1) {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK; // 200 OK status
			} else {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK_NOMATCH; // 202
			}
			
		} else {
			$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_CORRUPT; // 403, Couldn't open directory...?
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
		if ($dh = opendir($this->_dir)) {
			
			while ($dir = readdir($dh)) {
				if (is_dir($this -> _dir . "/" . $dir) and $dir != "." and $dir != "..") {
					$this -> _buffer = $this -> _buffer . $dir . "\n";
				}
			}
			
			$this->_buffer = trim($this->_buffer);
			
			$this->_status_buffer = NET_CDDB_RESPONSE_OK_FOLLOWS; // OK status
			
		} else {
			$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_CORRUPT; // Couldn't open directory...?
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
		
		if (count($parts = explode(' ', $query)) == 2 and is_dir($this->_dir . '/' . $parts[0])) {
			
			$path = $this->_dir . '/' . $parts[0] . '/' . $parts[1];
			if (file_exists($path) and $contents = file_get_contents($path)) {
				$this->_status_buffer = NET_CDDB_RESPONSE_OK_FOLLOWS; // OK, record follows
				$this->_buffer = $contents;
			} else {
				$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_UNAVAIL; // Entry does not exist
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
		
		$path = $this->_dir . '/' . $this->_motd_file;
		if ($this->_use_motd_file and file_exists($path) and $contents = file_get_contents($path)) {
			$this->_buffer = $contents;
			$this->_status_buffer = NET_CDDB_RESPONSE_OK_FOLLOWS;
		} else {
			$this->_status_buffer = NET_CDDB_RESPONSE_SERVER_UNAVAIL;
		}
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
	 * Count the number of database entries in a given category (directory)
	 * 
	 * @access protected
	 * 
	 * @param string $category
	 * @return integer
	 */
	function _countDatabaseEntries($category)
	{
		$count = 0;
		if ($dh = opendir($this->_dir . '/' . $category)) {
			while (false !== ($file = readdir($dh))) {
				$count++;
			}
			closedir($dh);
		}
		
		return $count - 2; // Two too many because of '.' and '..' entries
	}
	
	/**
	 * Write the 'stat' file 
	 * 
	 * @access protected
	 * 
	 * @param array $arr
	 * @return boolean
	 */
	function _writeStatFile($arr)
	{
		$bytes = 0;
		$fp = fopen($this->_dir . '/' . $this->_stat_file, 'w');
		foreach ($arr as $key => $value) {
			$bytes = fwrite($fp, $key . '=' . (int) $value . "\r\n");
		}
		fclose($fp);
		return $bytes > 0;
	}
	
	/**
	 * Read the 'stat' file to determine how many database entries are in each CDDB category
	 * 
	 * This method performs a check to make sure every CDDB category has a 
	 * corresponding, valid entry in the 'stat' file. If you want to clear the 
	 * 'stat' file, just truncate it to 0 characters at the command prompt.
	 * 
	 * @access protected
	 * 
	 * @return array Returns an array with CDDB categories as keys and the number of entries in the category as values
	 */
	function _readStatFile()
	{
		if ($dh = opendir($this->_dir) and is_file($this->_dir . '/' . $this->_stat_file)) {
			$defaults = array();
			
			// Get a list of all of the CDDB categories (directories)
			while (false !== ($dir = readdir($dh))) {
				if (is_dir($this->_dir . '/' . $dir)) {
					$defaults[$dir] = -1;
				}
			}
			
			$stats = array_merge($defaults, @parse_ini_file($this->_dir . '/' . $this->_stat_file));
			
			// Sanity check, make sure that counts from stat file are correct
			foreach ($stats as $key => $value) {
				if ($value < 0) {
					return false;
				}
			}
			
			return $stats;
			
		} else {
			return false;
		}
	}
	
	/**
	 * @todo Implement this
	 */
	function _readSitesFile()
	{
		return false;
	}
	
	/**
	 * Handle a cddb 'stat' (get server statistics) and fill the buffers with the response
	 * 
	 * @todo Possibly have an option to not use a 'stat' file *and* not count the entries in the directory
	 * 
	 * @access protected
	 * @uses Net_CDDB_Protocol_Filesystem::_countDatabaseEntries()
	 * @uses Net_CDDB_Protocol_Filesystem::_writeStatFile()
	 * @uses Net_CDDB_Protocol_Filesystem::_readStatFile()
	 * 
	 * @param string $cmd
	 * @param string $query
	 * @return void
	 */
	function _stat($cmd, $query)
	{
		$entry_counts = array();
		$total = 0;
		
		if ($this->_use_stat_file and $entry_counts = $this->_readStatFile()) { 
			;
		} else {
			
			// Keep on counting even if the user aborts the script/connection, just so we can write the 'stat' file
			if ($this->_use_stat_file) {
				ignore_user_abort(true);
			}
			
			if ($dh = opendir($this->_dir)) {
				while (false !== ($dir = readdir($dh))) {
					if (is_dir($this->_dir . '/' . $dir) and $dir != '.' and $dir != '..') {
						$entry_counts[$dir] = $this->_countDatabaseEntries($dir);
					}
				}
			}
			
			if ($this->_use_stat_file) {
				$this->_writeStatFile($entry_counts);
			}
		}
		
		$total = array_sum($entry_counts);
		
		$str = '';
		$str .= 'Server status:' . "\n";
		$str .= '    current proto: ' . NET_CDDB_PROTO_LEVEL . "\n";
		$str .= '    max proto: ' . NET_CDDB_PROTO_LEVEL . "\n";
		$str .= '    interface: Filesystem' . "\n";
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
	 * 
	 * @return int
	 */
	function status()
	{
		return $this->_status_buffer;
	}
	
	/**
	 * Pretend to disconnect (doesn't actually do anything because you don't need to disconnect from the local filesystem) 
	 * 
	 * @access public
	 * 
	 * @return void
	 */
	function disconnect()
	{
		return;
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