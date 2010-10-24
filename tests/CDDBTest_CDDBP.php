<?php

require_once 'CDDBTest.php';

class Net_CDDBTest_CDDBP extends Net_CDDBTest
{
	function setUp()
	{
		$this->_cddb = new Net_CDDB_Client('cddbp://freedb.org:8880', 'test:///dev/acd0');
	}
	
	function tearDown()
	{
		
	}
}


?>