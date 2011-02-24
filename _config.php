<?php

define('ZEND_SEARCH_LUCENE_BASE_PATH', dirname(__FILE__));

set_include_path(
    get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/thirdparty'
);

ZendSearchLuceneSearchable::$cacheDirectory = TEMP_FOLDER;
ZendSearchLuceneSearchable::$encoding = 'utf-8';

