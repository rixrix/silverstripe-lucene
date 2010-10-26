<?php

/*******************************************************************************
 * Decorates a DataObject, giving it the ability to be indexed and searched by 
 * Zend_Search_Lucene.
 * To define custom columns, you should this line into your site _config.php:
 * ZendSearchLuceneSearchable::enable(array());
 * This sets up things without defining columns for any classes.
 * After this, you can define non-default columns to index for each object by using:
 * Object::add_extension(
 *      'SiteTree',
 *      "ZendSearchLuceneSearchable('Title,MenuTitle,Content,MetaTitle,MetaDescription,MetaKeywords')"
 * );
 *******************************************************************************/

class ZendSearchLuceneSearchable extends DataObjectDecorator {

    /* Number of results per page */
    static $pageLength = 10;        

    /* Always show this many pages in pagination */
    static $alwaysShowPages = 3;    

    /* Maximum number of pages shown in pagination */
    static $maxShowPages = 8;       

    /* The fields which can be searched for each dataobject class */
	protected $searchFields;

    /* Encoding in which indexes are created and searches made */
    public static $encoding = 'utf-8';
    
    /* Full filesystem path to the directory where the index files should live */
    public static $cacheDirectory = TEMP_FOLDER;
    
    /**
     * Fields which are also indexed in addition to content fields.
     */
    private static $extraSearchFields = array('ID','ClassName','LastEdited');

    private static $defaultColumns = array(
		'SiteTree' => 'Title,MenuTitle,Content,MetaTitle,MetaDescription,MetaKeywords',
		'File' => 'Filename,Title,Content'
	);

	function __construct($searchFields) {
		if(is_array($searchFields)) $this->searchFields = implode(',', $searchFields);
		else $this->searchFields = $searchFields;
		parent::__construct();
	}

	/**
	 * Enable the default configuration of Zend Search Lucene searching on the 
	 * given data classes.
	 */
	public static function enable($searchableClasses = array('SiteTree', 'File')) {
		
		if(!is_array($searchableClasses)) $searchableClasses = array($searchableClasses);
		foreach($searchableClasses as $class) {
			if(isset(self::$defaultColumns[$class])) {
				Object::add_extension($class, "ZendSearchLuceneSearchable('".self::$defaultColumns[$class]."')");
			} else {
				throw new Exception("ZendSearchLuceneSearchable::enable() I don't know the default search columns for class '$class'");
			}
		}
		Object::add_extension('ContentController', 'ZendSearchLuceneContentController');
		DataObject::add_extension('SiteConfig', 'ZendSearchLuceneSiteConfig');
		Object::add_extension('LeftAndMain', 'ZendSearchLuceneCMSDecorator');
	}

    /**
     * Indexes the object after it has been written to the database.
     */
    public function onAfterWrite() {
        ZendSearchLuceneWrapper::index($this->owner);
    }

    /**
     * Return an array of search field names.
     */
    public function getSearchedVars() {
        return array_merge(
            self::$extraSearchFields,
            explode(',',$this->searchFields),
            array('Link')
        );
    }

    /**
     * Access the class' search fields from other classes
     */
    public function getSearchFields() {
        return $this->searchFields;
    }

    /**
     * Access the class' extra search fields from other classes
     */
    public function getExtraSearchFields() {
        return self::$extraSearchFields;
    }

}

