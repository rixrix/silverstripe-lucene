<?php

class ZendSearchLuceneCMSDecoratorTest extends SapphireTest {

    static $fixture_file = 'lucene-silverstripe-plugin/tests/ZendSearchLuceneSearchableTest.yml';

    function testRebuildZendSearchLuceneIndex() {
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

        ZendSearchLuceneSearchable::enable();
        
        $index = ZendSearchLuceneWrapper::getIndex(true);
        
        // Blank database
        $this->assertEquals( 0, $index->count() );

        // Count number of SiteTree and File objects
        $SiteTreeCount = DataObject::get('SiteTree')->count();
        $FileCount = DataObject::get('File')->count();
        $IndexableCount = $SiteTreeCount + $FileCount;

        // Re-index database
        $obj = new ZendSearchLuceneCMSDecorator();
        $obj->rebuildZendSearchLuceneIndex();

        // Has correct number of items?
        $this->assertEquals( $IndexableCount, ZendSearchLuceneWrapper::getIndex()->count() );
        
    }

}

