<?php

/**
 * Example file showing Net_CDDB_Client usage
 * 
 * @author Keith Palmer <Keith@UglySlug.com>
 * @category Net
 * @package Net_CDDB
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

header('Content-type: text/plain');
error_reporting(E_ALL);
set_time_limit(0);

require_once 'Net/CDDB/Client.php';

$params_cddbp = array(
    'host' => 'bsdslug', 
    'persist' => true, 
    );

$cddb_cddbp = new Net_CDDB_Client('cddbp://keithpalmerjr@freedb.org:8880', 'test:///dev/acd0', $params_cddbp);

$params_http = array(
    'host' => 'bsdslug', 
    );

$cddb_http = new Net_CDDB_Client('http://keithpalmerjr@freedb.org:80', 'test:///dev/acd0', $params_http);

$params_filesystem = array();
$cddb_filesystem = new Net_CDDB_Client('filesystem:///Users/keith/Sites/Net/docs/FreeDB', 'test:///dev/acd0', $params_filesystem);

$cddb_mdb2 = new Net_CDDB_Client('mdb2.mysql://root@localhost/freedb', 'test:///dev/acd0');

print("\n\nHTTP PROTOCOL RESPONSE:\n\n"); flush();
print(Net_CDDB_example($cddb_http));
    
print("\n\nCDDBP PROTOCOL RESPONSE:\n\n"); flush();
//print(Net_CDDB_example($cddb_cddbp));

/*
 * You can only use the filesystem one if you make sure that the configuration 
 * DSNs about point to a valid local CDDB database dump
 */
print("\n\nFILESYSTEM PROTOCOL RESPONSE:\n\n"); flush();
//print(Net_CDDB_example($cddb_filesystem));

/*
 * You can only use the MDB2 one if you have an SQL database loaded with 
 * the FreeDB information
 */
print("\n\nMDB2 PROTOCOL RESPONSE:\n\n"); flush();
//print(Net_CDDB_example($cddb_mdb2));

function Net_CDDB_example($cddb)
{
    print("Searching for the CD in the CD-ROM drive:\n");
    $discs = $cddb->searchDatabaseForCD();
    print_r($discs);
    
    print("\n\nDetails for the first CD from the search are: \n");
    $details = $cddb->getDetails($discs[0]);
    print_r($details);
    
    print("\n\nDumping that disc back to a string looks like this: \n");
    print($details->toString());
    
    print("\n\nDoing a test submit of that disc back to the CDDB server: \n");
    //$cddb->submitDisc($details, 'keith@uglyslug.com', true);

    print("\n\nThe CDDB music genres are: \n");
    print_r($cddb->getCategories());

    print("\n\nThe details for disk id: 'rock' '7708d309' are: \n");
    print_r($cddb->getDetailsByDiscId('rock', '7708d309', false));
    
    print("\n\nThe details for disk id: 'misc' '83085c0b' are: \n");
    $disc = $cddb->getDetailsByDiscId('misc', '83085c0b');
    print_r($disc);
    
    print("\n\nThe length of the first three tracks on that disc are: \n");
    print('    1. ' . $disc->getTrackTitle(0) . ': ' . $disc->getTrackLength(0, true) . ' (' . $disc->getTrackLength(0) . ")\n");
    print('    2. ' . $disc->getTrackTitle(1) . ': ' . $disc->getTrackLength(1, true) . ' (' . $disc->getTrackLength(1) . ")\n");
    print('    3. ' . $disc->getTrackTitle(2) . ': ' . $disc->getTrackLength(2, true) . ' (' . $disc->getTrackLength(2) . ")\n");
    
    print("\n\nThe length of the last track is: \n");
    $num_tracks = $disc->numTracks();
    print('    ' . $num_tracks . '. ' . $disc->getTrackTitle($num_tracks - 1) . ': ' . $disc->getTrackLength($num_tracks - 1, true) . ' (' . $disc->getTrackLength($num_tracks - 1) . ")\n");
    
    print("\n\nThe length of the whole disc is: \n");
    print('    Disc length: ' . $disc->getDiscLength(true) . "\n");
    
    print("\n\nThe disc id for this record is: \n");
    $arr_track_offsets = array(
        150, 
        21052, 
        43715, 
        58057,
        71430, 
        92865, 
        117600, 
        131987, 
        150625, 
        163292, 
        181490, 
        195685, 
        210197, 
        233230, 
        249257
        );
    print_r($cddb->calculateDiscId($arr_track_offsets, 3541));
    
    print("\n\nDiscs which match the description of that record are: \n");
    $discs = $cddb->searchDatabase($arr_track_offsets, 3541);
    print_r($discs);
    
    print("\n\nLet's see a dump of that first disc: \n");
    $disc = $cddb->getDetails($discs[0]);
    print($disc->toString());
    
    print("\n\nCDDB server message of the day: \n");
    print($cddb->motd());
    
    print("\n\nCDDB server protocol help: \n");
    print($cddb->help());
    
    print("\n\nCDDB server version is: \n");
    print($cddb->version());
    
    print("\n\nCDDB server statistics look like this: \n");
    print_r($cddb->statistics());
    
    print("\n\nCDDB server site list looks like this: \n");
    print_r($cddb->sites());
    
    print("\n\n");
}

?>
