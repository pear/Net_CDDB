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
 * @see CDDB_examples.php
 * @link http://freedb.org/ FreeDB.org, a free CDDB server
 * @link http://gracenote.com/ Gracenote.com, a commercial CDDB server
 * 
 * @todo Unicode (protocol level 6) support
 * @todo Database protocol implementation
 * @todo Look at CDDB submission errors
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
 * Utilities class for disc id calculation
 */
require_once 'Net/CDDB/Utilities.php';

require_once 'Net/CDDB/Exception.php';

/**
 * Net_CDDB PEAR package version
 * 
 * @var string
 */
define('NET_CDDB_VERSION', '0.4.0');

/**
 * CDDB protocol level ***DO NOT CHANGE THIS***
 * @var integer
 */
define('NET_CDDB_PROTO_LEVEL', 5);

/**
 * Command OK
 * @var integer
 */
define('NET_CDDB_RESPONSE_OK', 200);

/**
 * Command OK, parameter set
 * @var integer
 */
define('NET_CDDB_RESPONSE_OK_SET', 201);

/**
 * Hello OK, database is read only
 * @var integer
 */
define('NET_CDDB_RESPONSE_OK_RO', 201);

/**
 * Command OK, no match found
 * @var integer
 */
define('NET_CDDB_RESPONSE_OK_NOMATCH', 202);

/**
 * Command OK, response/list follows
 * @var integer
 */
define('NET_CDDB_RESPONSE_OK_FOLLOWS', 210);

/**
 * Command OK, inexact match, list follows
 * @var integer
 */
define('NET_CDDB_RESPONSE_OK_INEXACT', 211);

/**
 * Command OK, service/file unavailable/not found
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_UNAVAIL', 401);

/**
 * Command OK, internal server error
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_ERROR', 402);

/**
 * Command OK, already shook hands
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_ALREADY', 402);

/**
 * Command OK, database entry corrupt
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_CORRUPT', 403);

/**
 * Command OK, internal CGI server error
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_CGIERR', 408);

/**
 * Command OK, need handshake
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_NOHANDSHAKE', 409);

/**
 * Command OK, internal server error
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_BADHANDSHAKE', 431);

/**
 * Command OK, permission denied
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_NOPERM', 432);

/**
 * Command OK, too many users
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_TOOMANYUSERS', 433);

/**
 * Command OK, system load too high
 * @var integer
 */
define('NET_CDDB_RESPONSE_SERVER_SYSLOAD', 434);

/**
 * Error, invalid command syntax
 * @var integer
 */
define('NET_CDDB_RESPONSE_ERROR_SYNTAX', 500);

/**
 * Error, unrecognized CDDB command
 * @var integer
 */
define('NET_CDDB_RESPONSE_ERROR_UNRECOGNIZED', 500);

/**
 * Error, empty CDDB command
 * @var integer
 */
define('NET_CDDB_RESPONSE_ERROR_EMPTY', 500);

/**
 * Error, illegal parameter value
 * @var integer
 */
define('NET_CDDB_RESPONSE_ERROR_ILLEGAL', 501);

/**
 * Error, parameter already set to value
 * @var integer
 */
define('NET_CDDB_RESPONSE_ERROR_ALREADY', 502);

/**
 * Error, timeout
 * @var integer
 */
define('NET_CDDB_RESPONSE_ERROR_TIMEOUT', 530);

/**
 * CDDB record file format key for the disc title
 * @var string
 */
define('NET_CDDB_FIELD_DISC_TITLE', 'DTITLE');

/**
 * CDDB server mode to handle just a single CDDB command
 */
define('NET_CDDB_REQUEST_ONCE', 'once');

//define('NET_CDDB_REQUEST_CONTINOUS', 'continous');

/**
 * Base class for CDDB Client and Server classes 
 * 
 * @see Net_CDDB_Client
 * @see Net_CDDB_Server
 * 
 * @package Net_CDDB
 * @author Keith Palmer <Keith@UglySlug.com>
*/
class Net_CDDB
{
    /**
     * Status of debugging (enabled/disabled)
     * 
     * @var bool
     * @access protected
     */
    var $_debug;
    
    /**
     * Enables or disables debugging ( prints out all responses/requests )
     * 
     * @access public
     * 
     * @todo Inject a log instead?
     * @param bool $true_or_false
     */
    function debug($true_or_false)
    {
        $this->_debug = $true_or_false;
    }
    
    /**
     * Prints/logs debugging messages
     * 
     * @access protected
     * 
     * @param string $msg The debugging message
     * 
     * @return void
     */
    function _debug($msg)
    {
        if ($this->_debug) {
            print(date('Y-m-d H:i:s') . ': ' . $msg . "\n");
        }
    }
    
    /**
     * Parse a CDDB style database record into an array
     * 
     * @todo Inject utilities instead?
     * @see Net_CDDB::_parseResult()
     * @uses Net_CDDB_Utilities::parseRecord()
     * 
     * @access protected
     * 
     * @param string $str
     * @param string $category
     * @return array 
     */
    function _parseRecord($str, $category = '')
    {
        return Net_CDDB_Utilities::parseRecord($str, $category);
    }
    
    /**
     * Parse a result string returned by a CDDB query into an array
     * 
     * @see Net_CDDB::_parseRecord()
     * 
     * @access protected
     * 
     * @param string $str
     * @return array 
     */
    function _parseResult($str)
    {
        $first_space = strpos($str, ' ');
        $second_space = strpos($str, ' ', $first_space + 1);
        
        $category = substr($str, 0, $first_space);
        $discid = substr($str, $first_space, $second_space - $first_space);
        $artist_and_title = substr($str, $second_space);
        
        $str = '';
        $str .= 'DISCID=' . $discid . "\n";
        $str .= 'DTITLE=' . $artist_and_title;
        
        return Net_CDDB_Utilities::parseRecord($str, $category);
    }
    
    /**
     * Calculate a disc ID based on the track offsets and the disc length
     * 
     * @uses Net_CDDB_Utilities::calculateDiscId()
     * 
     * @param array $track_offsets The offsets of the tracks on the CD
     * @param int $length The total number of seconds for the disc
     * @return string 8-character disc ID value
     */
    function calculateDiscId($track_offsets, $length)
    {
        /*
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
        */
        
        return Net_CDDB_Utilities::calculateDiscId($track_offsets, $length);
    }
    
    /**
     * Create a protocol instance of the given type with the given parameters
     * 
     * Creates a protocol object instance of a given type by protocol type. 
     * The array of connection parameters will be passed directly to the protocol 
     * instance. 
     * 
     * @see Net_CDDB_Client
     * @see Net_CDDB_Server
     * @see Net_CDDB_Client::_createReader()
     * 
     * @access public
     * 
     * @param string $type
     * @param array $params
     * @return object
     */
    function _createProtocol($dsn, $options)
    {
        $parse = parse_url(str_replace(':///', '://null/', $dsn));
        
        if (false !== ($pos = strpos($parse['scheme'], '.'))) {
            $parse['scheme'] = substr($parse['scheme'], 0, $pos);
            $dsn = substr($dsn, $pos + 1);
        }
        
        $file = ucfirst(strtolower($parse['scheme']));
        $class = 'Net_CDDB_Protocol_' . $file;
        
        /**
         * Require the file the protocol class is stored in
         */
        include_once 'Net/CDDB/Protocol/' . $file . '.php';
        
        if (class_exists($class)) {
            return new $class($dsn, $options);
        }

        throw new Net_CDDB_Exception('Could not find protocol file for: ' . $file);
    }
}
