<?php

define('ZEND_SEARCH_LUCENE_BASE_PATH', dirname(__FILE__));

set_include_path(
    get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/thirdparty'
);

Director::addRules(
    100, 
    array( 'Lucene' => 'LeftAndMain' )
);
