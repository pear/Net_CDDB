<?php

/**
 * Class to represent an individual track of CDDB disc record
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Class to represent an individual track of CDDB disc record
 * 
 * @todo Make sure track length reports are correct
 * @todo Implement toString() method for tracks
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Track
{
	/**
	 * Stores extra data about the track (lyrics, sub-title, etc.)
	 * 
	 * @access protected
	 * @var string
	 */
	var $_extra;
	
	/**
	 * Title of the track
	 * 
	 * @access protected
	 * @var string
	 */
	var $_title;
	
	/**
	 * Artist for the track
	 * 
	 * @access protected
	 * @var string
	 */
	var $_artist;
	
	/**
	 * Offset of the track on the disc
	 * 
	 * @access protected
	 * @var integer
	 */
	var $_offset;
	
	/**
	 * Length of the track (in seconds)
	 * 
	 * @access protected
	 * @var integer
	 */
	var $_length;
	
	/**
	 * Construct a new Net_CDDB_Track object (PHP v4.x)
	 * 
	 * @see Net_CDDB_Track::__construct()
	 */
	function Net_CDDB_Track($arr_or_title, $offset = 0, $extra = '', $length = 0)
	{
		$this->__construct($arr_or_title, $offset, $extra, $length);
	}
	
	/**
	 * Construct a new Net_CDDB_Track object (PHP v5.x)
	 * 
	 * @access public
	 * 
	 * @param mixed $arr_or_title Either an associative array or the track title
	 * @param integer $offset
	 * @param string $extra
	 * @param integer $length
	 */
	function __construct($arr_or_title, $tartist = '', $offset = 0, $extt = '', $length = 0)
	{
		if (is_array($arr_or_title)) {
			$defaults = array(
				'ttitle' => '', 
				'tartist' => '', 
				'offset' => 0, 
				'extt' => '', 
				'length' => 0 );
			
			$arr_or_title = array_merge($defaults, $arr_or_title); // Ensure all required variables are set
			$arr_or_title['arr_or_title'] = $arr_or_title['ttitle'];
			
			extract($arr_or_title); // Extract into local scope so we can assign next
		}
		
		/*if (false !== strpos()) {
			
		}*/
		
		$this->_title = $arr_or_title;
		$this->_artist = '' . $tartist;
		$this->_offset = (int) $offset;
		$this->_extra = $extt;
		$this->_length = (int) $length;
	}
	
	/**
	 * Get the title of the track
	 * 
	 * @access protected
	 * 
	 * @param boolean $with_artist
	 * @return string
	 */
	function getTitle()
	{
		return $this->_title;
	}
	
	/**
	 * Get the artist for the track
	 * 
	 * @access protected
	 * 
	 * @return string
	 */
	function getArtist()
	{
		return $this->_artist;
	}
	
	/**
	 * Get the extra data for the track (lyrics, sub-title, etc.)
	 * 
	 * @access protected
	 * 
	 * @return string
	 */
	function getExtraData()
	{
		return $this->_extra;
	}
	
	/**
	 * Get the length of the track (in seconds or formatted as HH:MM:SS)
	 * 
	 * @access protected
	 * 
	 * @param boolean $formatted TRUE if you want a string formatted as HH:MM:SS, otherwise the track length in seconds will be returned
	 * @return mixed
	 */
	function getLength($formatted = false)
	{
		if ($formatted) {
			$hours = floor($this->_length / (60 * 60));
			$minutes = floor($this->_length / 60);
			$seconds = $this->_length % 60;
			
			return sprintf('%02d', $hours) . ':' . sprintf('%02d', $minutes) . ':' . sprintf('%02d', $seconds);
		} else {
			return $this->_length;
		}
	}
	
	/**
	 * Set the length of the track (in seconds)
	 * 
	 * @access protected
	 * 
	 * @param integer $time
	 * @return void
	 */
	function setLength($time)
	{
		$this->_length = (int) $time;
	}
	
	/**
	 * Get the offset of the track 
	 * 
	 * @access protected
	 * 
	 * @return integer
	 */
	function getOffset()
	{
		return $this->_offset;
	}
	
	/**
	 * @todo Implement this
	 */
	/*function toString()
	{
		
	}*/
}

?>