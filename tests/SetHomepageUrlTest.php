<?php
ini_set('display_errors', 'On');

class SetHomepageUrlTest extends PHPUnit_Framework_TestCase {

    public function testSetHomepageUrl() {
        
        $finder = new webignition\WebsiteSitemapFinder\WebsiteSitemapFinder();
        $this->assertEquals('', $finder->getHomepageUrl());
        
        $finder->setHomepageUrl('http://example.com');        
        $this->assertEquals('http://example.com/', $finder->getHomepageUrl());        
    }
    
}