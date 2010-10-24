<?php
require_once 'Net/CDDB/Track.php';
require_once 'PHPUnit/Framework/TestCase.php';

class Net_CDDB_TrackTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        $this->track = new Net_CDDB_Track("Test track", "Bobby Test", 123, "Extra", 321);
    }

    public function testShouldConstruct() {
        $this->markTestIncomplete("Cover instantiate by array");
    }

    public function testShouldGetTitle() {
        $this->assertSame("Test track", $this->track->getTitle());
    }

    public function testShouldGetArtist() {
        $this->assertSame("Bobby Test", $this->track->getArtist());
    }


    public function testShouldGetExtraData() {
        $this->assertSame("Extra", $this->track->getExtraData());
    }

    public function testShouldGetLength() {
        $this->assertSame(321, $this->track->getLength());
        $this->assertSame("00:05:21", $this->track->getLength(true));
    }

    public function testShouldSetLength() {
        $this->assertSame(321, $this->track->getLength());
        $this->track->setLength(213);
        $this->assertSame(213, $this->track->getLength());
    }

    public function testShouldGetOffset() {
        $this->assertSame(123, $this->track->getOffset());
    }
}
