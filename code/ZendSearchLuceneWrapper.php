<?php

/**
 * Provides a wrapper to Zend Search Lucene.
 *
 * @package lucene-silverstripe-plugin
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class ZendSearchLuceneWrapper {

    /**
     * The name of the index. 
     * @static
     */
    public static $indexName = 'Silverstripe';

    private static $lastReindexTime = false;

    /** 
     * Stores a handle to the search index so we don't have to keep recreating it. 
     * @static
     */
    private static $index = false;

    /**
     * Stores callbacks to be run after search index creation.
     * @static
     */
    private static $createIndexCallbacks = array();

    /**
     * Returns a set of results from Zend Search Lucene from the given search
     * parameters.
     *
     * It is possible to either pass in a string (which is what the default 
     * form provided by this package does), or build your own query using the 
     * Zend_Search_Lucene query building API.
     *
     * @link http://zendframework.com/manual/en/zend.search.lucene.searching.html
     *
	 * @param   Mixed           $query  String or object to pass to the find() method
	 *                                  of the index.
     * @link http://framework.zend.com/apidoc/core/Zend_Search_Lucene/Zend_Search_Lucene_Proxy.html#find
	 * @return  Array           An array of Zend_Search_Lucene_Search_QueryHit 
	 *                          objects representing the results of the search.
     */
    public static function find($query) {
        $index = self::getIndex();
        try {
            $hits = $index->find($query);
        } catch ( Exception $e) {
            $hits = array();
        }
		return $hits;
    }

    /**
     * Retrieves a Zend_Search_Lucene_Interface object connected to the search
     * index.
     * 
     * If the index does not exist, it is created.  On creation, any callbacks 
     * added using ZendSearchLuceneWrapper::addCreateIndexCallback() are run.
     * To use this feature, add your callback registration to your _config.php:
     *
     * <code>
     * function create_index_callback() {
     *     $index = ZendSeachLuceneWrapper::getIndex();
     *     $index->setMaxBufferedDocs(20);
     * }
     * ZendSearchLuceneWrapper::addCreateIndexCallback('create_index_callback');
     * </code>
     * 
     * The index lives in the directory specified by the $cacheDirectory static.
     * If the index already exists, it is opened, unless $forceCache is set
     * true, in which case the existing index is blanked and a new one created
     * in its place.  This is useful for re-indexing a site.
     * 
     * @param $forceCreate (String) Whether to force creation of the index even
     *          if it already exists.  Defaults to false, which opens the index
     *          if it exists.
     * @return Zend_Search_Lucene_Interface
     * @link http://zendframework.com/apidoc/1.10/Zend_Search_Lucene/Index/Zend_Search_Lucene_Interface.html
     */
    public static function getIndex($forceCreate=false) {
        if ( !$forceCreate && !empty(self::$index) ) {
            return self::$index;
        }
        $indexFilename = ZendSearchLuceneSearchable::$cacheDirectory . DIRECTORY_SEPARATOR . self::$indexName;
        if ( !$forceCreate && file_exists($indexFilename) ) {
            self::$index = Zend_Search_Lucene::open($indexFilename);
        } else {
            self::$index = Zend_Search_Lucene::create($indexFilename);
            // Call all callbacks registered via 
            // ZendSearchLuceneWrapper::addCreateIndexCallback()
            foreach( self::$createIndexCallbacks as $callback ) {
                call_user_func($callback);
            }
        }
        return self::$index;
    }

    /**
     * Indexes a DataObject.
     *
     * @param   DataObject  $object     The DataObject to index.  If the DataObject
     *                                  does not have the ZendSearchLuceneSearchable
     *                                  extension, it is not indexed.
     */
    public static function index($object) {
        if ( ! Object::has_extension($object->ClassName, 'ZendSearchLuceneSearchable') ) {
            return;
        }
        if ( $object->hasField('ShowInSearch') and !$object->ShowInSearch ) {
            self::delete($object);
            return;
        }

        $index = self::getIndex();

        // Remove currently indexed data for this object
        self::delete($object);

        $fields = explode(',', $object->getSearchFields());
        $fields = array_merge($object->getExtraSearchFields(), $fields);
        $doc = null;
        // Decide what sort of Document to use. Files that can be scanned, are.
        if ( $object->ClassName == 'File' || ClassInfo::is_subclass_of($object->ClassName, 'File') ) {
            if ( $object->ClassName == 'Folder' || ClassInfo::is_subclass_of($object->ClassName, 'Folder') ) {
                return;
            }
            switch( strtolower($object->getExtension()) ) {
                // Newer versions of Word/Excel/Powerpoint use Zend text extraction 
                case 'xlsx':
                    if ( extension_loaded('zip') ) { 
                        $doc = Zend_Search_Lucene_Document_Xlsx::loadXlsxFile(
                            Director::baseFolder().'/'.$object->Filename, 
                            true
                        );
                    }
                    break;
                case 'docx':
                    if ( extension_loaded('zip') ) {
                        $doc = Zend_Search_Lucene_Document_Docx::loadDocxFile(
                            Director::baseFolder().'/'.$object->Filename, 
                            true
                        );
                    }
                    break;
                case 'pptx':
                    if ( extension_loaded('zip') ) {
                        $doc = Zend_Search_Lucene_Document_Pptx::loadPptxFile(
                            Director::baseFolder().'/'.$object->Filename, 
                            true
                        );
                    }
                    break;
                // Older versions of Word/Excel/Powerpoint use the 'catdoc' commandline utilities if installed.
                case 'doc':
                    $catdoc = trim(shell_exec('which catdoc'));
                    if ( $catdoc ) {
                        $content = shell_exec($catdoc.' -a '.escapeshellarg(Director::baseFolder().'/'.$object->Filename));
                        $doc = new Zend_Search_Lucene_Document();
                        $doc->addField(Zend_Search_Lucene_Field::Text('body', $content, ZendSearchLuceneSearchable::$encoding));
                    }
                    break;
                case 'xls':
                    $xls2csv = trim(shell_exec('which xls2csv'));
                    if ( $xls2csv ) {
                        $content = shell_exec($xls2csv.' -q0 '.escapeshellarg(Director::baseFolder().'/'.$object->Filename));
                        $doc = new Zend_Search_Lucene_Document();
                        $doc->addField(Zend_Search_Lucene_Field::Text('body', $content, ZendSearchLuceneSearchable::$encoding));
                    }
                    break;
                case 'ppt':
                    $catppt = trim(shell_exec('which catppt'));
                    if ( $catppt ) {
                        $content = shell_exec($catppt.' '.escapeshellarg(Director::baseFolder().'/'.$object->Filename));
                        $doc = new Zend_Search_Lucene_Document();
                        $doc->addField(Zend_Search_Lucene_Field::Text('body', $content, ZendSearchLuceneSearchable::$encoding));
                    }
                    break;
                // HTML files use Zend HTML scanner
                case 'htm':
                case 'html':
                    $doc = Zend_Search_Lucene_Document_Html::loadHTMLFile(Director::baseFolder().'/'.$object->Filename, true);
                    break;
                // PDF files use either pdf2text if it's installed, or a PHP class
                case 'pdf':
                    $content = PDFScanner::getText(Director::baseFolder().'/'.$object->Filename);                   
                    $doc = new Zend_Search_Lucene_Document();
                    $doc->addField(Zend_Search_Lucene_Field::Text('body', $content, ZendSearchLuceneSearchable::$encoding));
                    break;
                // Text files are easy
                case 'txt':
                    $content = file_get_contents(Director::baseFolder().'/'.$object->Filename);
                    $doc = new Zend_Search_Lucene_Document();
                    $doc->addField(Zend_Search_Lucene_Field::Text('body', $content, ZendSearchLuceneSearchable::$encoding));
                    break;
            }
        } else {
            $doc = new Zend_Search_Lucene_Document();
        }
        if ( $doc === null ) return;

        foreach( $fields as $fieldName ) {
            if ( strpos($fieldName, '.') !== false ) {
                // Dot notation
                list($fieldName, $relationFieldName) = explode('.', $fieldName, 2);
                if ( in_array($fieldName, array_keys($object->has_one())) ) {
                    // has_one
                    $field = self::getZendField($object->getComponent($fieldName), $relationFieldName);     
                } else 
                if ( in_array($fieldName, array_keys($object->has_many())) ) {
                    // has_many - construct an aggregate object to extract text from
                    $tmp = '';
                    $components = $object->getComponents($fieldName);
                    foreach( $components as $component ) {
                        $tmp .= $component->$relationFieldName;
                    }
                    $tmp_obj = Object::create('DataObject');
                    $tmp_obj->$relationFieldName = $tmp;
                    $field = self::getZendField($tmp_obj, $fieldName.'.'.$relationFieldName);
                    unset($components);
                    unset($tmp_obj);
                    unset($tmp);
                } else 
                if ( in_array($fieldName, array_keys($object->many_many())) ) {
                    // many_many - construct an aggregate object to extract text from
                    $tmp = '';
                    $components = $object->$fieldName();
                    foreach( $components as $component ) {
                        $tmp .= $component->$relationFieldName;
                    }
                    $tmp_obj = Object::create('DataObject');
                    $tmp_obj->$relationFieldName = $tmp;
                    $field = self::getZendField($tmp_obj, $fieldName.'.'.$relationFieldName);
                    unset($components);
                    unset($tmp_obj);
                    unset($tmp);
                }
            } else {
                // Normal database field or function call
                $field = self::getZendField($object, $fieldName);
            }
            if ( ! $field ) continue;
            $doc->addField($field);
        }

        // Add URL
        if ( method_exists(get_class($object), 'Link') ) {
            $doc->addField(Zend_Search_Lucene_Field::UnIndexed('Link', $object->Link()));
        }

        $index->addDocument($doc);
        $index->commit();
    }

    /**
     * Delete a DataObject from the search index.
     *
     * @param   DataObject  $object     The DataObject to delete from the index.  If the DataObject
     *                                  does not have the ZendSearchLuceneSearchable
     *                                  extension, it is not deleted.
     */
    public static function delete($object) {
        if ( ! Object::has_extension($object->ClassName, 'ZendSearchLuceneSearchable') ) {
            return;
        }
        $index = self::getIndex();
        foreach ($index->find('ObjectID:'.$object->ID) as $hit) {
            if ( $hit->ClassName != $object->ClassName ) continue;
            $index->delete($hit->id);
        }
        $index->commit();
    }


    /**
     * Builder method for returning a Zend_Search_Lucene_Field object based on 
     * the DataObject field.
     *
     * Keyword - Data that is searchable and stored in the index, but not 
     *      broken up into tokens for indexing. This is useful for being 
     *      able to search on non-textual data such as IDs or URLs.
     *
     * UnIndexed – Data that isn’t available for searching, but is stored 
     *      with our document (eg. article teaser, article URL  and timestamp 
     *      of creation)
     *
     * UnStored – Data that is available for search, but isn’t stored in 
     *      the index in full (eg. the document content)
     *
     * Text – Data that is available for search and is stored in full 
     *      (eg. title and author)
     *
     * @param   DataObject  $object     The DataObject from which to extract a
     *                                  Zend field.
     * @param   String      $fieldName  The name of the field to fetch a Zend field for.
     * @return  Zend_Search_Lucene_Field
     */
    private static function getZendField($object, $fieldName) {
        $encoding = ZendSearchLuceneSearchable::$encoding;
        $unstoredFields = array(
            'MenuTitle', 'MetaTitle', 'MetaDescription', 'MetaKeywords'
        );
        $unindexedFields = array(
            'LastEdited', 'Created'
        );

        if ( $object->hasMethod($fieldName) ) {
            $value = $object->$fieldName();
        } else {
            $value = $object->$fieldName;
        }

        if ( in_array($fieldName, $unstoredFields) ) {
            return Zend_Search_Lucene_Field::UnStored($fieldName, $value, $encoding);
        }
        if ( in_array($fieldName, $unindexedFields) ) {
            return Zend_Search_Lucene_Field::UnIndexed($fieldName, $value, $encoding);
        }

        $keywordFields = array(
            'ID', 'ClassName'
        );
        if ( in_array($fieldName, $keywordFields) ) {
            $keywordFieldName = $fieldName;
            if ( $keywordFieldName == 'ID' ) $keywordFieldName = 'ObjectID'; // Don't use 'ID' as it's used by Zend Lucene
            return Zend_Search_Lucene_Field::Keyword($keywordFieldName, $value, $encoding);
        }

        // Default - index and store
        return Zend_Search_Lucene_Field::Text($fieldName, $value, $encoding);
    }

    public static function addCreateIndexCallback($callback) {
        self::$createIndexCallbacks[] = $callback;
    }

    /**
     * Rebuilds the search index.
     * @return  Integer     Returns the number of documents that were indexed.
     */
    public static function rebuildIndex() {
        set_time_limit(600);
        $start_time = microtime(true);
        $index = self::getIndex(true); // Wipes current index
        $count = 0;
        $indexed = array();

        $possibleClasses = ClassInfo::subclassesFor('DataObject');
        $extendedClasses = array();
        foreach( $possibleClasses as $possibleClass ) {
            if ( Object::has_extension($possibleClass, 'ZendSearchLuceneSearchable') ) {
                $extendedClasses[] = $possibleClass;
            }
        }

        foreach( $extendedClasses as $className ) {
            $objects = DataObject::get($className);
            if ( $objects === null ) continue;
            foreach( $objects as $object ) {
                // Only re-index if we haven't already indexed this DataObject
                if ( ! array_key_exists($object->ClassName, $indexed) ) $indexed[$object->ClassName] = array();
                if ( ! array_key_exists($object->ID, $indexed[$object->ClassName]) ) {
                    self::index($object);
                    $indexed[$object->ClassName][$object->ID] = true;
                    $count++;
                }
            }
        }
        $end_time = microtime(true);
        self::$lastReindexTime = $end_time - $start_time;
        return $count;
    }

    /**
     * @return  Integer    If the index has been rebuilt, returns how many seconds this took.
     * Otherwise returns false.
     */    
    public static function getLastReindexTime() {
        return round(self::$lastReindexTime, 1);
    }

}


