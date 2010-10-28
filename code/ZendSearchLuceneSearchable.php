<?php

/**
 * Decorates a DataObject, giving it the ability to be indexed and searched by 
 * Zend_Search_Lucene.
 * 
 * To define custom columns, you should this line into your site _config.php:
 *
 * <code>
 * ZendSearchLuceneSearchable::enable(array());
 * </code>
 *
 * This sets up things without defining columns for any classes.
 *
 * After this, you can define non-default columns to index for each object by using:
 *
 * <code>
 * Object::add_extension(
 *      'SiteTree',
 *      "ZendSearchLuceneSearchable('Title,MenuTitle,Content,MetaTitle,MetaDescription,MetaKeywords')"
 * );
 * </code>
 *
 * @package lucene-silverstripe-plugin
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class ZendSearchLuceneSearchable extends DataObjectDecorator {

    /**
     * Number of results per pagination page 
     * @static
     */
    static $pageLength = 10;        

    /**
     * Always show this many pages in pagination (can be zero)
     * @static
     */
    static $alwaysShowPages = 3;    

    /** 
     * Maximum number of pages shown in pagination (ellipsis are used to indicate more pages)
     * @static
     */
    static $maxShowPages = 8;       

    /** 
     * The fields which can be searched for each DataObject class.
     */
	protected $searchFields;

    /**
     * Encoding in which indexes are created and searches made.
     * @static
     */
    public static $encoding = 'utf-8';
    
    /** 
     * Full filesystem path to the directory where the index files should live 
     * @static
     */
    public static $cacheDirectory = TEMP_FOLDER;
    
    /**
     * Fields which are also indexed in addition to content fields.
     * @static
     */
    private static $extraSearchFields = array('ID','ClassName','LastEdited');

    /**
     * Default fields to index for each Searchable decorated class.
     * @static
     */
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
	 * 
	 * @param   Array   $searchableClasses  An array of classnames to scan.  Can 
	 *                                      choose from SiteTree and/or File.
	 *                                      To not scan any classes, for example
	 *                                      if we will define custom fields to scan,
	 *                                      pass in an empty array.
	 *                                      Defaults to scan SiteTree and File.
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
     * Removes the object from the search index after it has been deleted.
     */
    function onAfterDelete() {
        ZendSearchLuceneWrapper::delete($this->owner);
    }

    /**
     * Return an array of search field names.
     * 
     * @return  Array   An array of strings, each being the name of a field that 
     *                  is searched.
     */
    public function getSearchedVars() {
        return array_merge(
            self::$extraSearchFields,
            explode(',',$this->searchFields),
            array('Link')
        );
    }

    /**
     * Access the class' search fields from other classes.
     *
     * @return  Array   An array of strings, each being the name of a field that
     *                  is indexed.
     */
    public function getSearchFields() {
        return $this->searchFields;
    }

    /**
     * Access the class' extra search fields from other classes.
     *
     * @return  Array   An array of strings, each being the name of a field that 
     *                  is indexed but not searched.
     */
    public function getExtraSearchFields() {
        return self::$extraSearchFields;
    }

}

