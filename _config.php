<?php

define('ZEND_SEARCH_LUCENE_BASE_PATH', dirname(__FILE__));

set_include_path( 
    get_include_path() 
    . PATH_SEPARATOR 
    . ZEND_SEARCH_LUCENE_BASE_PATH . DIRECTORY_SEPARATOR . 'thirdparty'
);

ZendSearchLuceneSearchable::$cacheDirectory = TEMP_FOLDER;
ZendSearchLuceneSearchable::$encoding = 'utf-8';

Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding(ZendSearchLuceneSearchable::$encoding);
Zend_Search_Lucene_Analysis_Analyzer::setDefault( 
    new StandardAnalyzer_Analyzer_Standard_English() 
);

