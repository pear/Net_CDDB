<?php

/**
 * Example file showing Net_CDDB_Server usage
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

require 'Net/CDDB/Server.php';

// Create a HTTP CDDB server using a local copy of the FreeDB database
$server = new Net_CDDB_Server('http://localhost:80', 'filesystem:///Users/keith/Sites/Net/docs/FreeDB');

// Create a HTTP CDDB server by proxying requests to a FreeDB.org srever
//$server = new Net_CDDB_Server('http://localhost:80', 'http://freedb.org/~cddb/cddb.cgi');

/*
 * Most standard CDDB servers exist a particular URL, usually: 
 * http://www.example.org/~cddb/cddb.cgi
 * 
 * If you're using Apache and have access to mod_rewrite, you might consider 
 * using a rewrite rule to map your PHP server script to a more standard
 * location by placing the following rule in your .htaccess file or your Apache 
 * configuration file:
 * 
 * RewriteRule	^/~cddb/cddb\.cgi?(.*)	/path/to/your/CDDB_server_script.php$1
 */

/*
 * Optionally, you can write and register hook functions that get at certain at 
 * certain times during the request/response process. There are three types of 
 * hook functions:
 * 	- request (gets run whenever a request is started, gets a reference to all request data)
 * 	- response (gets run whenever a response is sent back, gets a reference to all request *and* response data)
 * 	- command (gets run whenever a command is sent, gets a reference to the the command sent)
 * 
 * Hooks can be used to modify incoming requests/commands, modify outgoing 
 * responses, or even override default command behavior and perform custom 
 * actions instead. Look at the examples below for more details. 
 * 
 * If a hook returns an array containing four elements, then the server will 
 * use the four returned elements as the CDDB response to send back to the 
 * client and will not execute the internal default behaviour. If a hook returns 
 * anything else, the server will continue to handle the request. The elements 
 * should be:
 * 
 * <code>
 * $my_custom_response = array(
 * 	NET_CDDB_RESPONSE_OK, // This could actually be *any* valid response code
 * 	'status message to be returned with the response code', 
 * 	'any data the response returns (a cddb record, a list of matching records, a list of mirror sites, etc.)', 
 * 	true, // true if a '.' should be appended to the response as a terminating character, false otherwise
 * 	);
 * </code>
 */ 

/*
function my_custom_request_hook(&$arr_cddb_cmds)
{
	// do something with the array of commands sent to the CDDB server
	return false;
}
*/

/**
 * This custom hook allows a local CDDB server to act as caching proxy for a 
 * remote CDDB server. So, you're running a local CDDB server, proxying 
 * all requests to a remote FreeDB.org server. But, everytime a 'cddb read' 
 * command is issued, instead of just going directly to FreeDB.org, you can use 
 * this custom hook to first check if you have a locally cached copy of the 
 * record on disk already. If you do, return that one. Otherwise, fetch it from 
 * the FreeDB.org server and return that one. 
 * 
 * This hook only implements the check for a local cache, it doesn't actually 
 * cache anything it downloads from the remote CDDB server it's proxying. You 
 * could implement a second hook, a custom response hook, to save a cached 
 * copy of anything you download from a remote site. 
 * 
 * Special thanks to Michael Bushey (http://www.sendthemtomir.com/) for this 
 * idea and for getting me to think about the addition of custom server hooks. 
 * 
 * @param string $str_cddb_cmd
 * @return array
 */
function my_custom_command_hook(&$str_cddb_cmd)
{
	$path_to_locally_cached_files = '/Users/keith/Sites/Net/docs/FreeDB/';
	
	if (substr($str_cddb_cmd, 0, 9) == 'cddb read' and // If the command is a valid 'cddb read' command... 
		$exp = explode(' ', $str_cddb_cmd) and 
		count($exp) == 4 and 
		ctype_alpha($exp[2]) and 
		ctype_alnum($exp[3])) {
		
		$category = $exp[2];
		$discid = $exp[3];
		
		if (is_file($path_to_locally_cached_files . $category . '/' . $discid)) { // If we have a locally cached copy...
			
			return array( // The server will use the returned array as the CDDB response
				NET_CDDB_RESPONSE_OK_FOLLOWS, // response code
				$category . ' ' . $discid . ' CD database entry (local cache retrieved by ' . __FUNCTION__ . ') follows (until terminating `.\'', // response message 
				file_get_contents($path_to_locally_cached_files . $category . '/' . $discid), 
				true, // Yes, we want the response to have the terminator appended
				);
		}
		
		// We don't have a locally cached copy of the record, let the server handle it as normal
	}
	
	// It's another command type, just let the server handle it as normal
}

/**
 * This custom hook examines track, title, and artist names for words like:
 * The, They, It, And, An, etc. which are in propercase, and transforms them to 
 * lowercase words such as the, they, it, and, an, etc.
 * 
 * Special thanks to Michael Bushey (http://www.sendthemtomir.com/) for the idea 
 * for his (slightly modified) code below and for getting me to think about the 
 * addition of custom server hooks. 
 * 
 * @param string $str_cddb_cmd
 * @param string $str_cddb_response_status
 * @param string $str_cddb_response_message
 * @param string $str_cddb_response_data
 * @param boolean $bln_add_a_terminating_character
 * @return void
 */
function my_custom_response_hook($str_cddb_cmd, &$int_cddb_response_status, &$str_cddb_response_message, &$str_cddb_response_data, &$bln_add_a_terminating_character)
{
	if (substr($str_cddb_cmd, 0, 9) == 'cddb read' and $int_cddb_response_status == NET_CDDB_RESPONSE_OK_FOLLOWS) // If it's a CDDB read command and it succeeded...
	{
		require_once 'Net/CDDB/Disc.php';
		
		$transform = array( // List of characters to transform
			'In', 'By', 'On', 'It', 'Is', 'It\'s', 'Of', 'Or', 'To', 'Into', 'As', 
			'Are', 'A', 'An', 'And', 'Ain\'t', 'All', 'The', 'Then', 'Than', 
			'These', 'This', 'Let', 'For', 'From', 'Has', 'Have', 'Go', 'Goes', 
			'With', 'Within', 'Like', 'Very' ); 
		
		foreach ($transform as $word) {
			$patterns[] = '/(\w)\ ' . $word . '\ /';
			$replaces[] = '${1} ' . strtolower($word) . ' ';
		}
		
		$record = Net_CDDB_Utilities::parseRecord($str_cddb_response_data); // Parse the record and transform the words
		foreach ($record['tracks'] as $key => $track) {
			$record['tracks'][$key]['ttitle'] = preg_replace($patterns, $replaces, $track['ttitle']);
			$record['tracks'][$key]['tartist'] = preg_replace($patterns, $replaces, $track['tartist']);
		}
		$record['dtitle'] = preg_replace($patterns, $replaces, $record['dtitle']);
		
		$disc = new Net_CDDB_Disc($record); // Make the string back into a disc object
		
		$str_cddb_response_data = $disc->toString(); // Make the record back into a string
		
		// Note in the response header that we've modified the response
		$str_cddb_response_message = str_replace('CD database entry ', 'CD database entry (response modified by ' . __FUNCTION__ . ') ', $str_cddb_response_message);
	}
}

// Register some server hooks
$server->registerHook(NET_CDDB_SERVER_HOOK_COMMAND, 'my_custom_command_hook');
$server->registerHook(NET_CDDB_SERVER_HOOK_RESPONSE, 'my_custom_response_hook');

// Start the server to handle the request
$server->start();

/*
 * Now that you have that set up, you can just point a CDDB client to the URL of 
 * the file you put that code in. So if you put that code in: 
 * 	http://www.your-domain.com/cddb_server.php
 * Point your CDDB client to that URL and you'll have a functioning 
 * CDDB-over-HTTP server.
 */

?>