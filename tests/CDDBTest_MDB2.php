<?php

require_once 'CDDBTest.php';

class Net_CDDBTest_MDB2 extends Net_CDDBTest
{
	function setUp()
	{
		$this->_cddb = new Net_CDDB_Client('mdb2.mysql://root@localhost/freedb', 'test:///dev/acd0');
	}
	
	function tearDown()
	{
		
	}
}

header('Content-type: text/plain');

//$suite  = new PHPUnit_TestSuite('Net_CDDBTest_MDB2');
//$result = PHPUnit::run($suite);

//print($result->toString());


?>