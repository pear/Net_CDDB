<?php

/**
 * 
 * 
 * 
 */

define('NET_CDDB_MDB2_DSN', 'mysql://root@127.0.0.1/freedb');

//define('NET_CDDB_FREEDB_PATH', '/Volumes/keith.palmer/freedb');
define('NET_CDDB_FREEDB_PATH', '/Users/keith/Sites/Net/docs/FreeDB');

require_once 'MDB2.php';

require_once 'Net/CDDB.php';

require_once 'Net/CDDB/Utilities.php';

require_once 'Net/CDDB/Disc.php';

$options = array(
	'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
	);
$mdb2 =& MDB2::factory(NET_CDDB_MDB2_DSN, $options);
$mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);
$mdb2->connect();
if (PEAR::isError($mdb2)) {
	die($mdb2->getMessage());
}

//$mdb2->exec('DELETE FROM artist');
//$mdb2->exec('DELETE FROM disc');
//$mdb2->exec('DELETE FROM track');

$dirs = array(
	'blues', 
	'classical', 
	'country', 
	'data', 
	'folk', 
	'jazz', 
	'misc', 
	'newage', 
	'reggae', 
	'rock', 
	'soundtrack', 
	);

foreach ($dirs as $dir) {
	Net_CDDB_MDB2_Import($mdb2, NET_CDDB_FREEDB_PATH . '/' . $dir);
}

/**
 * 
 */
function Net_CDDB_MDB2_Import(&$mdb2, $dir)
{
	$dh = opendir($dir);
	if ($dh) {
		
		print('Analyzing directory: ' . $dir . "\n");
		$category = basename($dir);
		
		while (false !== ($file = readdir($dh))) {
			if (strlen($file) == 8 and $file{0} != '.') {
				
				$disc = new Net_CDDB_Disc(Net_CDDB_Utilities::parseRecord(file_get_contents($dir . '/' . $file)));
				
				// First, check to see if the artist is already in the database
				$artist_id = Net_CDDB_MDB2_Import_artist($disc->getArtist());
				
				// Check to see if the genre is already in the database
				$genre_id = Net_CDDB_MDB2_Import_genre($disc->getGenre());
				
				$disc_id = Net_CDDB_MDB2_Import_disc($disc->getDiscId(), $category, $disc->getArtist(), $disc->getTitle(), $disc->getDiscYear(), $disc->getGenre(), $disc->getDiscLength(), $disc->getRevision(), $disc->getProcessedBy(), $disc->getSubmittedVia(), $disc->getDiscExtraData(), $disc->getDiscPlayorder());
				
				for ($i = 0; $i < $disc->numTracks(); $i++)
				{
					Net_CDDB_MDB2_Import_track($disc_id, $i + 1, $disc->getTrackArtist($i), $disc->getTrackTitle($i), $disc->getTrackOffset($i), $disc->getTrackExtraData($i));
				}
			}
		}
	}
	else
	{
		print('Could not open directory: ' . $dir . "\n");
	}
}

function Net_CDDB_MDB2_Import_artist($name)
{
	global $mdb2;
	
	if (strlen($name) > 255)
	{
		$name = substr($name, 0, 255);
	}
	
	$res = $mdb2->query('SELECT id FROM artist WHERE name = ' . $mdb2->quote($name));
	if ($res->numRows()) {
		$id = $res->fetchone(0);
	} else {
		$id = $mdb2->nextID('artist__id');
		$res = $mdb2->exec('INSERT INTO artist ( id, name, create_datetime, mod_datetime ) VALUES ( ' . $id . ', ' . $mdb2->quote($name) . ', NOW(), NOW() ) ');
		
		if (PEAR::isError($res)) {
			print_r($res);
			die($res->getMessage());
		}
	}
	
	return $id;
}

function Net_CDDB_MDB2_Import_genre($name)
{
	global $mdb2;
	
	if (strlen($name) > 64)
	{
		$name = substr($name, 0, 64);
	}
	
	$res = $mdb2->query('SELECT id FROM genre WHERE name = ' . $mdb2->quote($name, 'text'));
	if ($res->numRows()) {
		$id = $res->fetchone();
	} else {
		$id = $mdb2->nextID('genre__id');
		$res = $mdb2->exec('INSERT INTO genre (id, name, create_datetime) VALUES ( ' . $id . ', ' . $mdb2->quote($name, 'text') . ', NOW() )');
		
		if (PEAR::isError($res)) {
			print_r($res);
			die($res->getMessage());
		}
	}
	
	return $id;
}

function Net_CDDB_MDB2_Import_category($name)
{
	global $mdb2;
	
	$res = $mdb2->query('SELECT id FROM category WHERE name = ' . $mdb2->quote($name));
	if ($res->numRows()) {
		$id = $res->fetchone(0);
	} else {
		$id = $mdb2->nextID('category__id');
		$res = $mdb2->exec('INSERT INTO category ( id, name, create_datetime ) VALUES ( ' . $id . ', ' . $mdb2->quote($name) . ', NOW() )');
		
		if (PEAR::isError($res)) {
			print_r($res);
			die($res->getMessage());
		} 
	}
	
	return $id;
}

function Net_CDDB_MDB2_Import_disc($discid, $category_name, $artist_name, $title, $year, $genre_name, $length, $revision, $processed_by, $submitted_via, $extra, $playorder)
{
	global $mdb2;
	
	$artist_id = Net_CDDB_MDB2_Import_artist($artist_name);
	$genre_id = Net_CDDB_MDB2_Import_genre($genre_name);
	$category_id = Net_CDDB_MDB2_Import_category($category_name);
	
	$res = $mdb2->query('SELECT id FROM disc WHERE discid = ' . $mdb2->quote($discid) . ' AND category_id = ' . $category_id);
	if ($res->numRows()) {
		$id = $res->fetchone(0);
	} else {
		$id = $mdb2->nextID('disc__id');
		$res = $mdb2->exec('INSERT INTO disc ( id, discid, category_id, artist_id, title, year, genre_id, length, revision, processed_by, submitted_via, extra_data, playorder ) VALUES ( ' . $id . ', ' . $mdb2->quote($discid) . ', ' . $category_id . ', ' . $artist_id . ', ' . $mdb2->quote($title) . ', ' . $year . ', ' . $genre_id . ', ' . $length . ', ' . $revision . ', ' . $mdb2->quote($processed_by) . ', ' . $mdb2->quote($submitted_via) . ', ' . $mdb2->quote($extra) . ', ' . $mdb2->quote($playorder) . ' ) ');
		
		if (PEAR::isError($res)) {
			print_r($res);
			die($res->getMessage());
		}
	}
	
	return $id;
}

function Net_CDDB_MDB2_Import_track($disc_id, $track_num, $artist_name, $title, $offset, $extra)
{
	global $mdb2;
	
	$artist_id = Net_CDDB_MDB2_Import_artist($artist_name);
	
	$res = $mdb2->query('SELECT disc_id FROM track WHERE disc_id = ' . $disc_id . ' AND num = ' . $mdb2->quote($track_num));
	
	if (PEAR::isError($res)) {
		print_r($res);
		die($res->getMessage() . "\n");
	}
	
	if ($res->numRows()) {
		// Already inserted, don't bother
		;
	} else {
		// Insert the track record
		$res = $mdb2->exec('INSERT INTO track ( disc_id, num, artist_id, title, toffset ) VALUES ( ' . $disc_id . ', ' . $track_num . ', ' . $artist_id . ', ' . $mdb2->quote($title) . ', ' . $offset . ' ) ');
		
		if (PEAR::isError($res)) {
			print_r($res);
			die($res->getMessage());
		}
	}
}

?>