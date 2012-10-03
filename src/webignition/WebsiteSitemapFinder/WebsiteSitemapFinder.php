<?php
namespace webignition\WebsiteSitemapFinder;

use webignition\NormalisedUrl\NormalisedUrl;

/**
 * Finds a website's sitemap(.xml|.txt) content given a website's root URL.
 * 
 * Order of preference when searching:
 *   1. Locate robots.txt in domain root (which may not be the root URL) and
 *      example robots.txt for URL of sitemap. Return URL from robots.txt.
 * 
 *   2. Check for {rootUrl}/sitemap.xml
 *   3. Check for {rootUrl}/sitemap.txt
 *  
 */
class WebsiteSitemapFinder {
    
    const ROBOTS_TXT_FILE_NAME = 'robots.txt';
    const DEFAULT_SITEMAP_XML_FILE_NAME = 'sitemap.xml';
    const DEFAULT_SITEMAP_TXT_FILE_NAME = 'sitemap.txt';
    const DEFAULT_CONTENT_TYPE_KEY = 'xml';
    
    /**
     *
     * @var \webignition\Http\Client\Client
     */
    private $httpClient = null;
    
    
    /**
     *
     * @var \webignition\NormalisedUrl\NormalisedUrl 
     */
    private $rootUrl = null;
    
    
    /**
     *
     * @var \webignition\WebsiteSitemapFinder\XmlSitemapIdentifier
     */
    private $sitemapIdentifier = null;
    
    
    /**
     *
     * @param string $rootUrl
     * @return \webignition\WebsiteSitemapFinder\WebsiteSitemapFinder 
     */
    public function setRootUrl($rootUrl) {        
        $this->rootUrl = new NormalisedUrl($rootUrl);
        return $this;
    }
    
    
    /**
     *
     * @return string
     */
    public function getRootUrl() {
        return (is_null($this->rootUrl)) ? '' : (string)$this->rootUrl;
    }
    
    
    /**
     *
     * @param \webignition\Http\Client\Client $client 
     */
    public function setHttpClient(\webignition\Http\Client\Client $client) {
        $this->httpClient = $client;
    }
    
    
    /**
     *
     * @return \webignition\Http\Client\Client 
     */
    private function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \webignition\Http\Client\Client();
            $this->httpClient->redirectHandler()->enable();
        }
        
        return $this->httpClient;
    }

    
    /**
     *
     * @return string
     */
    public function getSitemapUrl() {
        $possibleSitemapUrls = $this->getPossibleSitemapUrls();

        foreach ($possibleSitemapUrls as $possibleSitemapUrl) {
            $this->sitemapIdentifier()->setPossibleSitemapUrl($possibleSitemapUrl);              
            
            if ($this->sitemapIdentifier()->isSitemapUrl()) {
                return $possibleSitemapUrl;
            }
        }
        
        return false;
    }
    
    
    /**
     * Get the URL where we expect to find the robots.txt file
     * 
     * @return string
     */
    public function getExpectedRobotsTxtFileUrl() {
        if ($this->rootUrl->getRoot() == '') {            
            return (string)$this->rootUrl . self::DEFAULT_SITEMAP_TXT_FILE_NAME;
        }
        
        $rootUrl = new NormalisedUrl($this->rootUrl->getRoot());        
        $rootUrl->setPath('/'.self::ROBOTS_TXT_FILE_NAME);
        
        return (string)$rootUrl;
    }  
    
    
    private function getPossibleSitemapUrls() {
       $sitemapUrlFromRobotsTxt = $this->findSitemapUrlFromRobotsTxt();
       if ($sitemapUrlFromRobotsTxt === false) {
           return array(
               $this->getDefaultSitemapXmlUrl(),
               $this->getDefaultSitemapTxtUrl()
           );
       }
       
       return array($sitemapUrlFromRobotsTxt);
    }
    
    
    /**
     *
     * @return string
     */
    private function getDefaultSitemapXmlUrl() {
        $absoluteUrlDeriver = new \webignition\AbsoluteUrlDeriver\AbsoluteUrlDeriver(
               '/' . self::DEFAULT_SITEMAP_XML_FILE_NAME,
               $this->getRootUrl()
        );
        
        return (string)$absoluteUrlDeriver->getAbsoluteUrl();
    }
    
    
    /**
     *
     * @return string
     */
    private function getDefaultSitemapTxtUrl() {
        $absoluteUrlDeriver = new \webignition\AbsoluteUrlDeriver\AbsoluteUrlDeriver(
               '/' . self::DEFAULT_SITEMAP_TXT_FILE_NAME,
               $this->getRootUrl()
        );
        
        return (string)$absoluteUrlDeriver->getAbsoluteUrl();
    } 
    
    
    private function findSitemapUrlFromRobotsTxt() {        
        $robotsTxtParser = new \webignition\RobotsTxt\File\Parser();
        $robotsTxtParser->setSource($this->getRobotsTxtContent());        
        $robotsTxtFile = $robotsTxtParser->getFile();

        if ($robotsTxtFile->directiveList()->containsField('sitemap')) {
            return (string)$robotsTxtFile->directiveList()->filter(array('field', 'sitemap'))->first()->getValue();         
        }
        
        return false;
    }
    
    
    /**
     *
     * @return string 
     */
    private function getRobotsTxtContent() {                
        $request = new \HttpRequest($this->getExpectedRobotsTxtFileUrl());        
        
        try {
            $response = $this->getHttpClient()->getResponse($request); 
        } catch (\webignition\Http\Client\Exception $httpClientException) {
            return '';
        }
        
        if (!$response->getResponseCode() == 200) {
            return '';
        }
        
        $mediaTypeParser = new \webignition\InternetMediaType\Parser\Parser();
        $contentType = $mediaTypeParser->parse($response->getHeader('content-type'));
        
        if ($contentType->getTypeSubtypeString() != 'text/plain') {
            return '';
        }
        
        return $response->getBody();
    }
    
    
    /**
     *
     * @return \webignition\WebsiteSitemapFinder\SitemapIdentifier
     */
    private function sitemapIdentifier() {
        if (is_null($this->sitemapIdentifier)) {
            $this->sitemapIdentifier = new \webignition\WebsiteSitemapFinder\SitemapIdentifier();
            $this->sitemapIdentifier->setHttpClient($this->getHttpClient());        
            
            $sitemapsOrgXmlMatcher = new \webignition\WebsiteSitemapFinder\SitemapMatcher\SitemapsOrgXml();
            $sitemapsOrgXmlMatcher->setType('sitemaps.org.xml');            
            $this->sitemapIdentifier->addMatcher($sitemapsOrgXmlMatcher);
            
            $sitemapsOrgTxtMatcher = new \webignition\WebsiteSitemapFinder\SitemapMatcher\SitemapsOrgTxt();
            $sitemapsOrgTxtMatcher->setType('sitemaps.org.txt');            
            $this->sitemapIdentifier->addMatcher($sitemapsOrgTxtMatcher);            
            
            $rssFeedMatcher = new \webignition\WebsiteSitemapFinder\SitemapMatcher\RssFeed();
            $rssFeedMatcher->setType('application/rss+xml');            
            $this->sitemapIdentifier->addMatcher($rssFeedMatcher);   
            
            $atomFeedMatcher = new \webignition\WebsiteSitemapFinder\SitemapMatcher\AtomFeed();
            $atomFeedMatcher->setType('application/atom+xml');                        
            $this->sitemapIdentifier->addMatcher($atomFeedMatcher);            
            
            $applicationXGzipMatcher = new \webignition\WebsiteSitemapFinder\SitemapMatcher\ApplicationXGzip();
            $applicationXGzipMatcher->setType('application/x-gzip');                        
            $this->sitemapIdentifier->addMatcher($applicationXGzipMatcher);                
        }
        
        return $this->sitemapIdentifier;
    }
    
}