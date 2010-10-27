<?php

class ZendSearchLuceneSiteConfigTest extends SapphireTest {

    function testUpdateCMSActions() {
        // Setup
        Object::remove_extension('ContentController', 'ZendSearchLuceneContentController');
        Object::remove_extension('SiteConfig', 'ZendSearchLuceneSiteConfig');
        Object::remove_extension('LeftAndMain', 'ZendSearchLuceneCMSDecorator');
        Object::remove_extension('SiteTree', 'ZendSearchLuceneSearchable');
        Object::remove_extension('File', 'ZendSearchLuceneSearchable');
        
        ZendSearchLuceneSearchable::$pageLength = 10;
        ZendSearchLuceneSearchable::$alwaysShowPages = 3;   
        ZendSearchLuceneSearchable::$maxShowPages = 8;   
        ZendSearchLuceneSearchable::$encoding = 'utf-8';
        ZendSearchLuceneSearchable::$cacheDirectory = TEMP_FOLDER;
        ZendSearchLuceneWrapper::$indexName = 'Test';
        
        ZendSearchLuceneSearchable::enable(array());

        $config = SiteConfig::current_site_config();        
        $this->assertTrue( is_object($config->getCMSActions()->fieldByName('rebuildZendSearchLuceneIndex')) );
    
    }

}

