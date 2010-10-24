<?php

require_once 'CDDBTest.php';

class Net_CDDBTest_Filesystem extends Net_CDDBTest
{
	function setUp()
	{
		$this->_cddb = new Net_CDDB_Client('filesystem:///Users/keith/Sites/Net/docs/FreeDB', 'test:///dev/acd0');
	}
	
	function tearDown()
	{
		
	}
	
	/**
	 * Overridden
	 */
	function testVersion()
	{
		$version = $this->_cddb->version();
		$this->assertContains('PHP', $version);
	}
}

header('Content-type: text/plain');

//$suite  = new PHPUnit_TestSuite('Net_CDDBTest_Filesystem');
//$result = PHPUnit::run($suite);

//print($result->toString());


?>