<?php

/**
 * Provides a wrapper to Zend Search Lucene.
 *
 * @package lucene-silverstripe-plugin
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
        echo get_class( $index );
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
     * If the index does not exist, it is created.
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

        $index = self::getIndex();

        // Remove currently indexed data for this object
        self::delete($object);

        $fields = explode(',', $object->getSearchFields());
        $fields = array_merge($object->getExtraSearchFields(), $fields);

        $doc = new Zend_Search_Lucene_Document();
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
        $term = new Zend_Search_Lucene_Index_Term($object->ID, 'ID');
        foreach ($index->termDocs($term) as $id) {
            $doc = $index->getDocument($id);
            if ( $doc->ClassName != $object->ClassName ) continue;
            $index->delete($id);
        }
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

}


