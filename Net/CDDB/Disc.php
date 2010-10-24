<?php

/**
 * Class representing an audio-CD with CDDB information
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Require CDDB class, we need the constants
 */
require_once 'Net/CDDB.php';

/**
 * Require the CDDB Track class, so we can assign tracks to discs...
 */
require_once 'Net/CDDB/Track.php';

/**
 * Class representing an audio-CD with CDDB information
 * 
 * This class has methods to access all of the information a CDDB server has 
 * available about a specific audio CD. 
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Disc
{
	/**
	 * Array of tracks for the disc, {@link Net_CDDB_Track} objects
	 * 
	 * @var array
	 * @access protected
	 */
	var $_tracks;
	
	/**
	 * Artist name
	 * 
	 * @var string
	 * @access protected
	 */
	var $_artist;
	
	/**
	 * Disc/audio CD title
	 * 
	 * @var string
	 * @access protected
	 */
	var $_title;
	
	/**
	 * 8-char discid field
	 * 
	 * @var string
	 * @access protected
	 */
	var $_discid;
	
	/**
	 * CDDB category string (the directory the disc record is stored in)
	 * 
	 * @var string
	 * @access protected
	 */
	var $_category;
	
	/**
	 * CDDB genre string
	 * 
	 * @var string
	 * @access protected
	 */
	var $_genre;
	
	/**
	 * Year audio CD was published
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_year;
	
	/**
	 * Length in seconds of the audio-CD
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_length;
	
	/**
	 * Playorder of tracks ( optional CDDB attribute )
	 * 
	 * @var string
	 * @access protected
	 */
	var $_playorder;
	
	/**
	 * CDDB revision attribute
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_revision;
	
	/**
	 * The program which submitted this record
	 * 
	 * @var string
	 * @access protected
	 */
	var $_submitted_via;
	
	/**
	 * The program which processing the record submission
	 * 
	 * @var string
	 * @access protected
	 */
	var $_processed_by;
	
	/**
	 * Revision id if we've edited the record
	 * 
	 * @var integer
	 * @access protected
	 */
	var $_this_revision;
	
	/**
	 * Construct a new Net_CDDB_Disc object
	 * 
	 * The Net_CDDB_Disc actually supports two differnet methods of constructing 
	 * a Net_CDDB_Disc object. The first is to provide all of the parameters. 
	 * An alternative constructor allows passing an associative array as the 
	 * first *and only* parameter with the following keys: 
	 * 	- discid, 8-char discid
	 * 	- dartist, string artist name
	 * 	- dtitle, string title of the cd
	 * 	- dgenre, string genre of the cd
	 * 	- dyear, integer year the cd was published
	 * 	- tracks, array of either {@link Net_CDDB_Track} objects or of arrays containing keys to construct a {@link Net_CDDB_Track} object with
	 * 	- dlength, length of disc (in seconds)
	 * 	- revision, CDDB record revision # of disc 
	 * 	- playorder, string describing custom track play order
	 * 
	 * @access public
	 * @todo Make sure documentation about field names is correct and complete
	 *  
	 * @param string $arr_or_discid *See note above!*
	 * @param string $dartist
	 * @param string $dtitle
	 * @param string $category
	 * @param string $dgenre
	 * @param integer $dyear
	 * @param array $tracks An array of {@link Net_CDDB_Track} objects or an array of arrays to construct {@link Net_CDDB_Track} objects with 
	 * @param integer $dlength
	 * @param integer $revision The CDDB revision from the CDDB record
	 * @param string $playorder Optional play-order of the tracks
	 */
	function Net_CDDB_Disc($arr_or_discid, $dartist = '', $dtitle = '', $category = '', $dgenre = '', $dyear = '', $tracks = array(), $dlength = 0, $revision = 0, $playorder = '', $submitted_via = '', $processed_by = '')
	{
		if (is_array($arr_or_discid)) // If the first parameter is an array, we'll treat this as an associative array constructor
		{
			$defaults = array(
				'discid' => '', 
				'dartist' => '', 
				'dtitle' => '',
				'category' => '',  
				'dgenre' => '', 
				'dyear' => '', 
				'tracks' => array(), 
				//'extras' => array(), 
				//'offsets' => array(), 
				'dlength' => 0, 
				'revision' => 0,
				'submitted_via' => '', 
				'processed_by' => '',  
				'playorder' => '' 
				);
			
			$arr_or_discid = array_merge($defaults, $arr_or_discid); // Ensure all required variables are set
			$arr_or_discid['arr_or_discid'] = $arr_or_discid['discid'];
			
			extract($arr_or_discid); // Extract into local scope so we can assign next
		}
				
		$this->_discid = substr(str_pad($arr_or_discid, 8), 0, 8);
		$this->_artist = '' . $dartist;
		$this->_title = '' . $dtitle;
		$this->_category = $category;
		$this->_genre = '' . $dgenre;
		$this->_year = (int) $dyear;
		$this->_length = (int) $dlength;
		$this->_revision = (int) $revision;
		$this->_playorder = $playorder;
		$this->_submitted_via = $submitted_via;
		$this->_processed_by = $processed_by;
		
		// Assign the tracks 
		foreach ($tracks as $track) {
			if (is_array($track)) {
				$this->_tracks[] = new Net_CDDB_Track($track);
			} else if (is_a($track, 'Net_CDDB_Track')) {
				$this->_tracks[] = $track;
			}
		}
		
		// Check the first track, if the first track doesn't have a length set, 
		//	we'll go ahead and calculate lengths for all of the tracks
		/*$count = count($this->_tracks);
		if ($count and !$this->_tracks[0]->getLength()) {
			$start = $this->_tracks[0]->getOffset(); // Initial disc offset
			
			for ($i = 1; $i < $count; $i++) {
				$end = $this->_tracks[$i]->getOffset();
				$this->_tracks[$i - 1]->setLength(round(($end - $start) / 75)); // Set track offsets (seconds get rounded)
				$start = $this->_tracks[$i]->getOffset();
			}
			
			// Set the final track length
			$this->_tracks[$count - 1]->setLength($this->_length - round($start / 75));
		}*/
		
		$this->_this_revision = -1;
	}
	
	/**
	 * Get the 8-char discid for the disc
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function getDiscId()
	{
		return $this->_discid;
	}
	
	/**
	 * Get the artist for the audio CD
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function getArtist()
	{
		return $this->_artist;
	}
	
	/**
	 * Get the title of this disc
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function getTitle()
	{
		return $this->_title;
	}
	
	/**
	 * Get the category this disc is stored in (directory on CDDB server)
	 * 
	 * CDDB servers usually maintain about 11 different categories for discs 
	 * which (very roughly) correspond to a few different genres of music. The 
	 * category is needed for disc submission and CDDB reads. There is also a 
	 * free-form 'genre' field stored with each disc record which should be used 
	 * for a more accurate disc genre description.  
	 * 
	 * @see Net_CDDB_Disc::getGenre()
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function getCategory()
	{
		return $this->_category;
	}
	
	/**
	 * Get the genre this disc belongs to
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function getGenre()
	{
		return $this->_genre;
	}
	
	/**
	 * Get the number of tracks the CD contains
	 * 
	 * @access public
	 * 
	 * @return integer
	 */
	function numTracks()
	{
		return count($this->_tracks);
	}
	
	/**
	 * Get the track title for a specific track number ( track #s start at 0 )
	 * 
	 * <code>
	 * print("The title of the first song is: ");
	 * $disc->getTrackTitle(0);
	 * </code>
	 * 
	 * @access public
	 * @see Net_CDDB_Disc::getTrack()
	 * @see Net_CDDB_Track
	 * 
	 * @param integer $track_num
	 * @return string
	 */
	function getTrackTitle($track_num)
	{
		if ($track = $this->getTrack($track_num)) {
			return $track->getTitle();
		} else {
			return null;
		}
	}
	
	/**
	 * Get the track offset for a specific track
	 * 
	 * @access public
	 * @see Net_CDDB_Disc::getTrack()
	 * @see Net_CDDB_Track
	 * 
	 * @param integer $track_num
	 * @return integer
	 */
	function getTrackOffset($track_num)
	{
		if ($track = $this->getTrack($track_num)) {
			return $track->getOffset();
		} else {
			return null;
		}
	}
	
	/**
	 * Get the track length for the given track number
	 * 
	 * @access public
	 * @see Net_CDDB_Disc::getTrack()
	 * @see Net_CDDB_Track
	 * 
	 * @param integer $track_num
	 * @param boolean $formatted
	 * @return mixed
	 */
	function getTrackLength($track_num, $formatted = false)
	{
		if ($track = $this->getTrack($track_num)) {
			return $track->getLength($formatted);
		}
		return null;
	}
	
	/**
	 * Get the extra data for the given track number
	 * 
	 * @access public
	 * @see Net_CDDB_Disc::getTrack()
	 * 
	 * @param integer $track_num
	 * @return string
	 */
	function getTrackExtraData($track_num)
	{
		if ($track = $this->getTrack($track_num)) {
			return $track->getExtraData();
		}
		return null;
	}

	/**
	 * Get the track artist for the given track number
	 * 
	 * @access public
	 * @see Net_CDDB_Disc::getTrack()
	 * @param integer $track_num
	 * @return string 
	 */
	function getTrackArtist($track_num)
	{
		if ($track = $this->getTrack($track_num)) {
			return $track->getArtist();
		}
		return null;
	}
	
	/**
	 * Get the {@link Net_CDDB_Track} object representing the given track number
	 * 
	 * @access public
	 * 
	 * @param integer $track_num
	 * @return Net_CDDB_Track
	 */
	function getTrack($track_num)
	{
		if (isset($this->_tracks[$track_num])) {
			return $this->_tracks[$track_num];
		} 
		return null;
	}
	
	/**
	 * Retrieve the length of this disc in seconds
	 * 
	 * @access public
	 * @param bool $formatted Whether or not to return in string format: HH:MM:SS ( defaults to returning an integer number of seconds in length )
	 * @return integer
	 */
	function getDiscLength($formatted = false)
	{
		if ($formatted) {
			$hours = floor($this->_length / (60 * 60));
			$minutes = floor(($this->_length / 60) % 60);
			$seconds = $this->_length % 60;
			
			return sprintf('%02d', $hours) . ':' . sprintf('%02d', $minutes) . ':' . sprintf('%02d', $seconds);
		} else {
			return $this->_length;
		}
	}
	
	/**
	 * Retrieve the year this disc was published
	 * 
	 * @access public
	 * @return integer
	 */
	function getDiscYear()
	{
		return $this->_year;
	}
	
	/**
	 * Get the record revision number
	 * 
	 * @access public
	 * @return integer
	 */
	function getRevision()
	{
		return $this->_revision;
	}
	
	/**
	 * Get the name of the program which processed the disc
	 * 
	 * @access public
	 * @return string
	 */
	function getProcessedBy()
	{
		return $this->_processed_by;
	}
	
	/**
	 * Get the name of the program that submitted the disc record
	 * 
	 * @access public
	 * @return string
	 */
	function getSubmittedVia()
	{
		return $this->_submitted_via;
	}
	
	/**
	 * Get any extra data associated with the disc
	 * 
	 * @todo Finish supporting this
	 * @access public
	 * @return string
	 */
	function getDiscExtraData()
	{
		return '';
	}
	
	/**
	 * Get the playorder of the disc
	 * 
	 * @todo Make sure this actually works
	 * @access public
	 * @return string
	 */
	function getDiscPlayorder()
	{
		return $this->_playorder;
	}
	
	/**
	 * Return a string representation of this CDDB Disc ( the CDDB file format )
	 * 
	 * @todo Make EXTD data field work
	 * @todo Probably should return an *exact* copy of already submitted record (submitted by, processed by are optoinal)
	 * 
	 * @access public
	 * 
	 * @return string
	 */
	function toString()
	{
		$str = "# xcmd\r\n";
		$str .= "#\r\n";
		$str .= "# Track frame offsets:\r\n";
		
		foreach ($this->_tracks as $track) {
			$str .= '#    ' . $track->getOffset() . "\r\n";
		}
		
		//foreach ($this->_offsets as $key => $offset) {
		//	$str .= '#    ' . $offset . "\r\n";
		//}
			
		$str .= "#\r\n";
		$str .= '# Disc length: ' . $this->_length . " seconds\r\n";
		$str .= "#\r\n";
		$str .= "# Revision: " . $this->_revision . "\r\n";
		$str .= "# Submitted via: " . $this->_submitted_via . "\r\n";
		$str .= "# Processed by: " . $this->_processed_by . "\r\n";
		$str .= "#\r\n";
		$str .= 'DISCID=' . $this->_discid . "\r\n";
		$str .= 'DTITLE=' . $this->_artist . ' / ' . $this->_title . "\r\n";
		$str .= 'DYEAR=' . $this->_year . "\r\n";
		$str .= 'DGENRE=' . $this->_genre . "\r\n";
		
		foreach ($this->_tracks as $key => $track) {
			if ($track->getArtist() != $this->_artist) {
				$str .= 'TTITLE' . $key . '=' . $track->getArtist() . ' / ' . $track->getTitle() . "\r\n";
			} else {
				$str .= 'TTITLE' . $key . '=' . $track->getTitle() . "\r\n";
			}
		}
		
		$str .= 'EXTD=' . "\r\n";
		
		foreach ($this->_tracks as $key => $track) {
			$str .= 'EXTT' . $key . '=' . $track->getExtraData() . "\r\n";
		}
		
		$str .= 'PLAYORDER=' . $this->_playorder . "\r\n";
		
		return trim($str);
	}
}

?>