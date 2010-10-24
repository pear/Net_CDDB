<?php

/**
 * Static class to read and parse output of the 'cd-discid' binary
 * 
 * @see Net_CDDB
 * @see Net_CDDB_cdparanoia
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Driver class to extract the TOC from an audio-CD using a 'cd- discid' binary
 * 
 * @see Net_CDDB_cdparanoia
 * @see Net_CDDB
 * 
 * @package Net_CDDB
 */
class Net_CDDB_cddiscid
{
    /**
     * Static method to obtain the track offsets and length of an audio CD 
     * 
     * Returns an array with each array item being the track offset in seconds,
     * and the final item being the total length of the audio disc in seconds.
     * This method uses locates and executes a 'cd-discid' binary on your
     * computer to calculate the track offsets. You need to have 'cd-discid'
     * installed and in your PATH for this to work. You can get 'cd-discid'
     * here: {@link http://lly.org/~rcw/cd-discid/}
     * 
     * @static
     * @param boolean $use_sudo
     * @param string $device
     * @return array
     */
    function calcTrackOffsets($use_sudo, $device)
    {
        if ($use_sudo) {
            $sudo = 'sudo';
        } else {
            $sudo = '';
        }
        
        $cmd = trim(`which cd-discid`);
        
        if (!strlen($cmd) || substr(trim($cmd), 0, 2) == 'no') {
            return PEAR::raiseError('Net_CDDB_cddiscid could not locate the cd-discid binary. Make sure you have cd-discid installed, you can get it here: http://lly.org/~rcw/cd-discid/.');
        }
        
        $output = `$sudo $cmd $device`;
        
        $arr_tmp = explode(' ', $output);
        
        return array_slice($arr_tmp, 2);
    }
}

?>
