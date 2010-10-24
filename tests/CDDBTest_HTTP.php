<?php

require_once 'CDDBTest.php';

class Net_CDDBTest_HTTP extends Net_CDDBTest
{
	function setUp()
	{
		$this->_cddb = new Net_CDDB_Client('http://freedb.org:80', 'test:///dev/acd0');
	}
	
	function tearDown()
	{
		
	}
}

header('Content-type: text/plain');

//$suite  = new PHPUnit_Framework_TestSuite('Net_CDDBTest_HTTP');
//$result = PHPUnit::run($suite);

//$suite = new PHPUnit_Framework_TestSuite(new ReflectionClass('Net_CDDBTest_HTTP'));
//$result = $suite->run();

//print($result->toString());


?>