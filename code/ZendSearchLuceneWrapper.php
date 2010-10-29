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
        if ( $object->hasField('ShowInSearch') && $object->ShowInSearch != 1 ) {
            return;
        }

        $index = self::getIndex();

        // Remove currently indexed data for this object
        self::delete($object);

        $fields = explode(',', $object->getSearchFields());
        $fields = array_merge($object->getExtraSearchFields(), $fields);

        // Decide what sort of Document to use.
        // Files that can be scanned, are.
        if ( $object->ClassName == 'File' ) {
            switch( strtolower($object->getExtension()) ) {
                case 'xlsx':
                    $doc = Zend_Search_Lucene_Document_Xlsx::loadXlsxFile(Director::baseFolder().'/'.$object->Filename, true);
                    break;
                case 'docx':
                    $doc = Zend_Search_Lucene_Document_Docx::loadDocxFile(Director::baseFolder().'/'.$object->Filename, true);
                    break;
                case 'htm':
                case 'html':
                    $doc = Zend_Search_Lucene_Document_Html::loadHTMLFile(Director::baseFolder().'/'.$object->Filename, true);
                    break;
                case 'pptx':
                    $doc = Zend_Search_Lucene_Document_Pptx::loadPptxFile(Director::baseFolder().'/'.$object->Filename, true);
                    break;
                case 'pdf':
                    $content = PDFScanner::getText(Director::baseFolder().'/'.$object->Filename);                   
                    $doc = new Zend_Search_Lucene_Document();
                    $doc->addField(Zend_Search_Lucene_Field::Text('body', $content, ZendSearchLuceneSearchable::$encoding));
                    break;
                default:
                    $doc = new Zend_Search_Lucene_Document();
                    break;                    
            }
        } else {
            $doc = new Zend_Search_Lucene_Document();
        }

        foreach( $fields as $fieldName ) {
            $field = self::getZendField($object, $fieldName);
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
        foreach ($index->find('ID:'.$object->ID) as $hit) {
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
        $extraSearchFields = $object->getExtraSearchFields();
        $dbMap = $object->db();
        $encoding = ZendSearchLuceneSearchable::$encoding;

        if ( ! in_array($fieldName, $extraSearchFields) && ! array_key_exists($fieldName, $dbMap) ) {
            return false;
        }

        $unstoredFields = array(
            'MenuTitle', 'MetaTitle', 'MetaDescription', 'MetaKeywords'
        );
        if ( in_array($fieldName, $unstoredFields) ) {
            return Zend_Search_Lucene_Field::UnStored($fieldName, $object->$fieldName, $encoding);
        }

        $unindexedFields = array(
            'LastEdited', 'Created'
        );
        if ( in_array($fieldName, $unindexedFields) ) {
            return Zend_Search_Lucene_Field::UnIndexed($fieldName, $object->$fieldName, $encoding);
        }

        $keywordFields = array(
            'ID', 'ClassName'
        );
        if ( in_array($fieldName, $keywordFields) ) {
            return Zend_Search_Lucene_Field::Keyword($fieldName, $object->$fieldName, $encoding);
        }

        // Default - index and store
        return Zend_Search_Lucene_Field::Text($fieldName, $object->$fieldName, $encoding);
    }

    public static function addCreateIndexCallback($callback) {
        self::$createIndexCallbacks[] = $callback;
    }

}


