<?php

/**
 * Static class to read and parse the output of the 'cdparanoia' binary
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Driver class to get TOC from an audio CD using a 'cdparanoia' binary
 * 
 * @see Net_CDDB
 * @see Net_CDDB_cddiscid
 * 
 * @package Net_CDDB
 */
class Net_CDDB_cdparanoia
{
    /**
     * Static method to execute 'cdparanoia' and get track offsets from audio CD
     * 
     * This static method uses the 'cdparanoia' binary to query your CD-ROM for
     * an audio CD and get the track offsets and total length of the audio CD in 
     * seconds. You need to have 'cdparanoia' installed for this to work, you
     * can download and install it from here: 
     * {@link http://www.xiph.org/paranoia/}
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
        
        $cmd = trim(`which cdparanoia`);
        
        if (!strlen($cmd) || substr(trim($cmd), 0, 2) == 'no') {
            return PEAR::raiseError('Net_CDDB_cdparanoia could not locate the cdparanoia binary. Make sure you have cdparanoia installed, you can get it here: http://www.xiph.org/paranoia/.');
        }
        
        $output = `$sudo $cmd -Q 2>&1`; // -d $device
        
        $offsets = array(); // Holds the array of track offsets 
        $toc_start = 'Table of contents';
        $next = 1;
        
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            
            if (substr($line, 0, 4) == str_pad($next, 3, ' ', STR_PAD_LEFT) . '.') {
                
                $line = trim($line);
                while (false !== strpos($line, '  ')) {
                    $line = str_replace('  ', ' ', $line);
                }
                
                $tmp = explode(' ', $line);
                
                $offsets[] = (int) $tmp[3] + 150;
                $next++;
                
            } else if (substr($line, 0, 5) == 'TOTAL') {
                $offsets[] = (int) ((((int) substr($line, 5)) + 150) / 75);
            } 
        }
        
        return $offsets;
    }
}

?>
