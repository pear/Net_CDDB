<?php

/**
 * Utilities class for CDDB Servers/Clients
 * 
 * @see Net_CDDB_Client
 * @see Net_CDDB_Server
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */
 
/**
 * Utilities class for use by CDDB Servers/Clients
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Utilities
{
	/**
	 * Calculate the disc id from the track offsets and disc length
	 * 
	 * @access public
	 * @static 
	 * 
	 * @uses Net_CDDB_Utilities::_sum()
	 * 
	 * @param array $track_offsets
	 * @param int $disc_length Disc length in seconds
	 * @return string 8-char disc id
	 */
	function calculateDiscId($track_offsets, $disc_length)
	{
		$n = 0;
		
		$count = count($track_offsets);
		for ($i = 0; $i < $count; $i++) {
			$n = $n + Net_CDDB_Utilities::_sum($track_offsets[$i] / 75);
		}
		
		// The $disc_length - 2 accounts for the 150 frame offset the RedBook standard uses... I think...
		return dechex(($n % 0xff) << 24 | ($disc_length - 2) << 8 | count($track_offsets));
	}
	
	/**
	 * Helper function for calculateDiscId() method, sum digits in track offset
	 * 
	 * @access public
	 * @static
	 * 
	 * @param int $n 
	 * @return int
	 */
	function _sum($n)
	{
		$ret = 0;
		
		while ($n > 0) {
			$ret = $ret + ($n % 10);
			$n = (int) ($n / 10);
		}
		
		return $ret;	
	}
	
	/**
	 * Recursively trim all values in an array (of arrays)
	 * 
	 * @access public
	 * @static
	 * 
	 * @param array $arr
	 * @return void
	 */
	function _trim(&$arr)
	{
		foreach ($arr as $key => $value) {
			if (is_array($value)) {
				Net_CDDB_Utilities::_trim($arr[$key]);
			} else {
				$arr[$key] = trim($value);
			}
		}
	}
	
	/**
	 * Parse a record in CDDB format into a multidimensional array 
	 * 
	 * @todo Documentation about file format and method output
	 * 
	 * @access public
	 * @static
	 * 
	 * @param string $str
	 * @param string $category
	 * @return array
	 */
	function parseRecord($str, $category = '')
	{
		$record = array(
			'discid' => '', 
			'dtitle' => '', 
			'dyear' => '', 
			'dgenre' => '',
			'category' => trim($category), // Not ever parsed out of a disc record, just here because we need to store it  
			'extd' => '', 
			'playorder' => '', 
			'tracks' => array(),
			'dlength' => 0,
			'revision' => 0, 
			'submitted_via' => '', 
			'processed_by' => '',   
			);
		
		// Some records seem to use \r and some use \n... convert all to one or the other
		$str = str_replace("\r", "\n", $str);
		$str = str_replace("\r\n", "\n", $str);
		$str = str_replace("\n\n", "\n", $str);
		
		$lines = explode("\n", $str);
		foreach ($lines as $line_num => $line) {
			
			if (count($explode = explode('=', $line)) == 2) { // key=value type line
				
				$key = trim(strtolower(current($explode)));
				$value = trim(end($explode));
				
				if (substr($key, 0, 6) == 'ttitle') {
					
					$track_num = (int) substr($key, 6);
					if (!isset($record['tracks'][$track_num])) {
						$record['tracks'][$track_num] = array(
							'ttitle' => '', 
							'extt' => '', 
							'offset' => '', 
							'length' => 0, 
							);
					}
					
					$record['tracks'][$track_num]['ttitle'] .= ' ' . trim($value);
					
				} else if (substr($key, 0, 4) == 'extt') {	
					
					$track_num = (int) substr($key, 6);
					if (!isset($record['tracks'][$track_num])) {
						$record['tracks'][$track_num] = array(
							'ttitle' => '', 
							'extt' => '', 
							'offset' => '', 
							'length' => 0, 
							);
					}
					
					$record['tracks'][$track_num]['extt'] .= ' ' . trim($value);

				} else {
					$record[$key] .= ' ' . trim($value);
				}
			} else { // Other data line
				
				if (false !== strpos($line, 'frame offsets')) {
					
					$track_num = 0;
					$line_num++;
					while ((int) trim(substr($lines[$line_num], 1))) {
						
						if (!isset($record['tracks'][$track_num])) {
							$record['tracks'][$track_num] = array(
								'ttitle' => '', 
								'extt' => '', 
								'offset' => '', 
								'length' => 0,
								);
						}
						
						$record['tracks'][$track_num]['offset'] = (int) trim(substr($lines[$line_num], 1));
						
						$track_num++;
						$line_num++;
					}
				} else if (false !== ($pos = strpos($line, 'Disc length:'))) {
					
					$record['dlength'] = (int) substr($line, $pos + 12);
					
				} else if (false !== ($pos = strpos($line, 'Revision:'))) {
					
					$record['revision'] = substr($line, $pos + 9);
					
				} else if (false !== ($pos = strpos($line, 'Submitted via:'))) {
					
					$record['submitted_via'] = substr($line, $pos + 14);
					
				} else if (false !== ($pos = strpos($line, 'Processed by:'))) {
					
					$record['processed_by'] = substr($line, $pos + 13);
					
				}
			}
		}
		
		// Now, lets seperate artists from titles 
		if (count($explode = explode(' / ', $record['dtitle'])) == 2) {
			$record['dartist'] = current($explode);
			$record['dtitle'] = end($explode);
		} else {
			$record['dartist'] = $record['dtitle'];
		}
		
		foreach ($record['tracks'] as $key => $track)
		{
			if (count($explode = explode(' / ', $track['ttitle'])) == 2) {
				$record['tracks'][$key]['tartist'] = current($explode);
				$record['tracks'][$key]['ttitle'] = end($explode);
			} else {
				$record['tracks'][$key]['tartist'] = $record['dartist'];
			}
		}
		
		// Finally, lets do some trimming and cleanup
		Net_CDDB_Utilities::_trim($record);
		$record['dyear'] = (int) $record['dyear'];
		
		// Calculate the lengths for each of the disc's tracks
		$count = count($record['tracks']);
		if ($count) {
			$start = $record['tracks'][0]['offset']; // Initial disc offset
			
			for ($i = 1; $i < $count; $i++) {
				$end = $record['tracks'][$i]['offset'];
				$record['tracks'][$i - 1]['length'] = round(($end - $start) / 75); // Set track offsets (seconds get rounded)
				$start = $record['tracks'][$i]['offset'];
			}
			
			// Set the final track length
			$record['tracks'][$count - 1]['length'] = $record['dlength'] - round($start / 75);
		}
		
		return $record;
	}
	
	/**
	 * Extract/parse just a single field by key from a CDDB record
	 * 
	 * Most of the data in a CDDB file format has a key associated with it, 
	 * you can use this method to extract the value for just one key. Here are 
	 * the available keys: 
	 * 	- DISCID (8-character disc id)
	 * 	- DTITLE (title/artist of disc)
	 * 	- DYEAR (year disc was published)
	 * 	- DGENRE (genre of music)
	 * 	- TTITLE* (where * is the track number you want)
	 * 	- EXTD (extra data about disc)
	 * 	- YEAR (...?)
	 * 	- EXTT* (where * is the track number you want to extra data for)
	 * 	- PLAYORDER (custom play-order of the tracks?)
	 * 
	 * @see NET_CDDB_FIELD_DISC_TITLE
	 * 
	 * @param string $str
	 * @param string $field
	 * @return string
	 */
	function parseFieldFromRecord($str, $field)
	{
		foreach (explode("\n", $str) as $line) {
			if (substr($line, 0, strlen($field)) == $field) {
				return trim(substr($line, strlen($field) + 1));
			}
		}
		return '';
	}
}

?>