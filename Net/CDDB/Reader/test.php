<?php

/**
 * Static class which fakes reading a CD-ROM and returns results based on device given
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */
 
/**
 * Driver class for testing the CDDB service, returns fake TOC for audio CDs
 * 
 * @package Net_CDDB
 */
class Net_CDDB_Reader_test 
{
    /**
     * Fakes querying the CD-ROM drive and returns fake TOC based on the device
     * 
     * This class is simply used for testing, it fakes reading an audio CD from
     * your CD-ROM drive and returns different sets of track offsets/lengths
     * based on the device you pass it. You can pass it any one of these
     * devices:
     *  - /dev/acd0
     *  - /dev/acd1
     *  - /dev/acd2
     *  - /dev/acd3
     * 
     * @static
     * @param boolean $use_sudo
     * @param string $device
     * @return array
     */
    function calcTrackOffsets($use_sudo, $device)
    {
        switch ($device) {
            case '/dev/acd0':
                return array(150, 15471, 34414, 43587, 55098, 65975, 83623, 92225, 105299, 116339, 129797, 2142);
            case '/dev/acd1':
                return array(150, 21052, 43715, 58057, 71430, 92865, 117600, 131987, 150625, 163292, 181490, 195685, 210197, 233230, 249257, 3541);
            case '/dev/acd2':
                return array(150, 20820, 45079, 64070, 79721, 103706, 121416, 145377, 164139, 185379, 204670, 222934, 249264, 271989, 289983, 4121);
            case '/dev/acd3': // Simulate a cd-read error by returning an empty array of track offsets 
            default:
                return array();
        }
    }
}

?>
