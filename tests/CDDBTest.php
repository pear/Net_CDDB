<?php

require_once 'Net/CDDB/Client.php';
require_once 'PHPUnit/Framework/TestCase.php';

class Net_CDDBTest extends PHPUnit_Framework_TestCase
{
	var $_cddb;
	
	function Net_CDDBTest($name)
	{
		$this->__construct($name);
	}
	
	function setUp()
	{
		$this->_cddb = null;
	}
	
	function tearDown()
	{
		$this->_cddb = null;
	}
	
	function testConnect()
	{
		$this->assertTrue($this->_cddb->connect());
	}
	
	function testCreateDisc()
	{
		$disc = new Net_CDDB_Disc('xxxxyyyy', 'Keith Palmer', 'Test Title');
		
		$this->assertEquals('Test Title', $disc->getTitle());
	}
	
	function testCreateDiscArray()
	{
		$disc = new Net_CDDB_Disc(array('discid' => 'xxxxyyyy', 'dartist' => 'Keith Palmer', 'dtitle' => 'Test Title'));
		
		$this->assertEquals('Test Title', $disc->getTitle());
	}
	
	function testGetDetails()
	{
		$discs = $this->_cddb->searchDatabaseForCD();
		$disc = $this->_cddb->getDetails($discs[0]);
		
		$this->assertEquals('Caring Is Creepy', $disc->getTrackTitle(0));
	}
	
	function testGetGenres()
	{
		$genres = array(
			0 => 'data',
			1 => 'folk',
			2 => 'jazz',
			3 => 'misc',
			4 => 'rock',
			5 => 'country',
			6 => 'blues',
			7 => 'newage',
			8 => 'reggae',
			9 => 'classical',
			10 => 'soundtrack',
			);
		
		$this->assertEquals($genres, $this->_cddb->getCategories());
	}
	
	function testSearchDatabaseForCD()
	{
		$discs = $this->_cddb->searchDatabaseForCD();
		
		$this->assertEquals('Oh, Inverted World', $discs[0]->getTitle());
	}
	
	function testCalculateDiscIdForCD()
	{
		$this->assertEquals('d50dd30f', $this->_cddb->calculateDiscIdForCD('/dev/acd1'));
	}
	
	function testCalculateLengthForCD()
	{
		$this->assertEquals(4121, $this->_cddb->calculateLengthForCD('/dev/acd2'));
	}
	
	function testCalculateTrackOffsetsForCD()
	{
		$offsets = array(
			0 => 150, 
			1 => 20820, 
			2 => 45079, 
			3 => 64070, 
			4 => 79721, 
			5 => 103706, 
			6 => 121416, 
			7 => 145377, 
			8 => 164139, 
			9 => 185379, 
			10 => 204670, 
			11 => 222934, 
			12 => 249264, 
			13 => 271989, 
			14 => 289983, 
			);
		
		$this->assertEquals($offsets, $this->_cddb->calculateTrackOffsetsForCD('/dev/acd2'));
	}
	
	function testVersion()
	{
		$version = $this->_cddb->version();
		$this->assertContains('cddbd', $version);
	}

	function testStatistics()
	{
		$stats = $this->_cddb->statistics();
		
		$this->assertTrue(isset($stats['current_proto']));
	}
	
	/*
	function testProtocolResponses()
	{
		require_once(' ');
		require_once(' ');
		
		$http = new Net_CDDB_HTTP();
		$cddb = new Net_CDDB_CDDBP();
		
		$http->send('cddb lscat');
		$cddb->send('cddb lscat');
		
		$this->assertEquals($http->recieve(), $cddb->recieve());
	}
	*/
	
	/*
	function testCdromResponses()
	{
		
	}
	*/
}

?>