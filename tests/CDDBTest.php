<?php
require_once 'Net/CDDB/Client.php';
require_once 'MDB2.php';
require_once 'PHPUnit/Framework/TestCase.php';

class Net_CDDBTest extends PHPUnit_Framework_TestCase
{

    function drivers() {
        $connection = MDB2::factory('mdb2.mysql://root@localhost/freedb');
  
        $data = array();
        if (!PEAR::isError($connection)) {
           $data["MDB2"] = array(new Net_CDDB_Client('mdb2.mysql://root@localhost/freedb', 'test:///dev/acd0'));
        }
        $data["HTTP"] = array(new Net_CDDB_Client('http://freedb.org:80', 'test:///dev/acd0'));
        
        if (is_dir('/Users/keith/Sites/Net/docs/FreeDB') && is_readable('/Users/keith/Sites/Net/docs/FreeDB')) {
            $data["Filesystem"] = array(new Net_CDDB_Client('filesystem:///Users/keith/Sites/Net/docs/FreeDB', 'test:///dev/acd0'));
        }

        $data["CDDBP"] = array(new Net_CDDB_Client('cddbp://freedb.org:8880', 'test:///dev/acd0'));

        return $data;
    }

    /** @dataProvider drivers */
    function testConnect($cddb_driver)
    {
        $this->assertTrue($cddb_driver->connect($cddb_driver));
    }
    
    /** @dataProvider drivers */
    function testCreateDisc($cddb_driver)
    {
        $disc = new Net_CDDB_Disc('xxxxyyyy', 'Keith Palmer', 'Test Title');
        
        $this->assertEquals('Test Title', $disc->getTitle($cddb_driver));
    }
    
    /** @dataProvider drivers */
    function testCreateDiscArray($cddb_driver)
    {
        $disc = new Net_CDDB_Disc(array('discid' => 'xxxxyyyy', 'dartist' => 'Keith Palmer', 'dtitle' => 'Test Title'));
        
        $this->assertEquals('Test Title', $disc->getTitle($cddb_driver));
    }
    
    /** @dataProvider drivers */
    function testGetDetails($cddb_driver)
    {
        $discs = $cddb_driver->searchDatabaseForCD($cddb_driver);
        $disc = $cddb_driver->getDetails($discs[0]);
        
        $this->assertTrue($disc instanceof Net_CDDB_Disc, get_class($disc));
        $this->assertEquals('Caring Is Creepy', $disc->getTrackTitle(0));
    }
    
    /** @dataProvider drivers */
    function testGetGenres($cddb_driver)
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
        
        $this->assertEquals($genres, $cddb_driver->getCategories($cddb_driver));
    }
    
    /** @dataProvider drivers */
    function testSearchDatabaseForCD($cddb_driver)
    {
        $discs = $cddb_driver->searchDatabaseForCD($cddb_driver);
        $this->assertTrue(!empty($discs));
        $this->assertTrue($discs[0] instanceof Net_CDDB_Disc);
        $this->assertEquals('Oh, Inverted World', $discs[0]->getTitle($cddb_driver));
    }
    
    /** @dataProvider drivers */
    function testCalculateDiscIdForCD($cddb_driver)
    {
        $this->assertEquals('d50dd30f', $cddb_driver->calculateDiscIdForCD('/dev/acd1'));
    }
    
    /** @dataProvider drivers */
    function testCalculateLengthForCD($cddb_driver)
    {
        $this->assertEquals(4121, $cddb_driver->calculateLengthForCD('/dev/acd2'));
    }
    
    /** @dataProvider drivers */
    function testCalculateTrackOffsetsForCD($cddb_driver)
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
        
        $this->assertEquals($offsets, $cddb_driver->calculateTrackOffsetsForCD('/dev/acd2'));
    }
    
    /** @dataProvider drivers */
    function testVersion($cddb_driver)
    {
        $version = $cddb_driver->version($cddb_driver);
        $this->assertContains('cddbd', $version);
    }

    /** @dataProvider drivers */
    function testStatistics($cddb_driver)
    {
        $stats = $cddb_driver->statistics($cddb_driver);
        
        $this->assertTrue(isset($stats['current_proto']));
    }
    

    /** @dataProvider drivers */
    /*
    function testProtocolResponses($cddb_driver)
    {
        require_once(' ');
        require_once(' ');
        
        $http = new Net_CDDB_HTTP($cddb_driver);
        $cddb = new Net_CDDB_CDDBP($cddb_driver);
        
        $http->send('cddb lscat');
        $cddb->send('cddb lscat');
        
        $this->assertEquals($http->recieve($cddb_driver), $cddb->recieve($cddb_driver));
    }
    */

    /** @dataProvider drivers */
    /*
    function testCdromResponses($cddb_driver)
    {
        
    }
    */
}

?>
