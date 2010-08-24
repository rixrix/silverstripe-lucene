<?php

define('ZEND_SEARCH_LUCENE_BASE_PATH', dirname(__FILE__));

set_include_path( 
    get_include_path() 
    . PATH_SEPARATOR 
    . ZEND_SEARCH_LUCENE_BASE_PATH . DIRECTORY_SEPARATOR . 'thirdparty'
);

ZendSearchLuceneSearchable::$cacheDirectory = TEMP_FOLDER;
ZendSearchLuceneSearchable::$encoding = 'utf-8';

// Results per page
ZendSearchLuceneSearchable::$pageLength = 10;
// Always show this many pages at the start of the pagination (can be 0)
ZendSearchLuceneSearchable::$alwaysShowPages = 3;
// Maximum number of pages to show in pagination (will show ellipses to indicate more pages)
ZendSearchLuceneSearchable::$maxShowPages = 10;

Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding(ZendSearchLuceneSearchable::$encoding);
Zend_Search_Lucene_Analysis_Analyzer::setDefault( 
    new StandardAnalyzer_Analyzer_Standard_English() 
);

