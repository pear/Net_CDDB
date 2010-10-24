<?php

/**
 * Example file showing misc Net_CDDB package functionality
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */



require_once 'Net/CDDB/Utilities.php';

require_once 'Net/CDDB/Disc.php';

// Start with a CDDB record (text file)
$str = file_get_contents('CDDB_record_example.txt');

// Turn it into an array
$record = Net_CDDB_Utilities::parseRecord($str);
print_r($record);

// Turn the array into a Net_CDDB_Disc object
$disc = new Net_CDDB_Disc($record);
print_r($disc);

// Turn the disc back into a CDDB record (text file)
$str = $disc->toString();
print($str);

// And back into an array again!
$record = Net_CDDB_Utilities::parseRecord($str);
print_r($record);

?>