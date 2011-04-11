<?php

/**
 * Provides a wrapper to Zend Search Lucene.
 *
 * @package lucene-silverstripe-module
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
     * @access private
     * @static
     */
    private static $index = false;

    /**
     * Stores callbacks to be run after search index creation.
     * @access private
     * @static
     */
    private static $createIndexCallbacks = array();

    /**
     * The manifest of text extraction classes, in the order they should be run.
     * @access private
     * @static
     */
    private static $extractorClasses = false;

    /**
     * Returns a set of results from Zend Search Lucene from the given search
     * parameters.
     *
     * It is possible to either pass in a string (which is what the default 
     * form provided by this package does), or build your own query using the 
     * Zend_Search_Lucene query building API.
     *
     * {@link http://zendframework.com/manual/en/zend.search.lucene.searching.html}
     *
	 * @param   Mixed           $query  String or object to pass to the find() method
	 *                                  of the index.
     * @link http://framework.zend.com/apidoc/core/Zend_Search_Lucene/Zend_Search_Lucene_Proxy.html#find
	 * @return  Array           An array of Zend_Search_Lucene_Search_QueryHit 
	 *                          objects representing the results of the search.
     * @todo Add query logging
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
     * @param String $forceCreate Whether to force creation of the index even
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
     * File objects have text extracted from them using subclasses of the 
     * ZendSearchLuceneTextExtractor class.  Any number of text extractors can
     * attempt to scan a file of any given extension; the first successful one 
     * will be used.
     *
     * If the dataobject has a function called Link(), this will be added as an 
     * unindexed field. 
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

        $doc = new Zend_Search_Lucene_Document();
        if ( $object->is_a('File') ) {
            // Files get text extracted if possible
            if ( $object->class == 'Folder' ) {
                return;
            }
            // Loop through all file text extractors...
            foreach( self::getTextExtractorClasses() as $extractor_class ) {
                $extensions = new ReflectionClass($extractor_class);
                $extensions = $extensions->getStaticPropertyValue('extensions');
                if ( ! in_array(strtolower(File::get_file_extension($object->Filename)), $extensions) ) continue;
                // Try any that support the given file extension
                $content = call_user_func(
                    array($extractor_class, 'extract'), 
                    Director::baseFolder().'/'.$object->Filename
                );
                if ( ! $content ) continue;
                // Use the first extractor we find that gives us content.
                $doc = new Zend_Search_Lucene_Document();
                $doc->addField(
                    Zend_Search_Lucene_Field::Text(
                        'body',  // We're storing text in files in a field called 'body'.
                        $content, 
                        ZendSearchLuceneSearchable::$encoding
                    )
                );
                break;
            }
        }
        // Index the fields we've specified in the config
        $fields = array_merge($object->getExtraSearchFields(), $object->getSearchFields());
        foreach( $fields as $fieldName ) {
            // Normal database field or function call
            $field = self::getZendField($object, $fieldName);
            if ( ! $field ) continue;
            $doc->addField($field);
        }

        // Add URL if we have a function called Link().  We didn't use the 
        // extraSearchFields mechanism for this because it's not a property on 
        // all objects, so this is the most sensible place for it.
        if ( method_exists(get_class($object), 'Link') && ! in_array('Link', $fields) ) {
            $doc->addField(Zend_Search_Lucene_Field::UnIndexed('Link', $object->Link()));
        }

        $index->addDocument($doc);
        $index->commit();
    }

    /**
     * Returns the list of available subclasses of ZendSearchLuceneTextExtractor
     * in the order in which they should be processed.  Order is determined by
     * the $priority static on each class.  Default is 100 for all inbuilt 
     * classes, lower numbers get run first.
     *
     * @access private
     * @static
     * @return  Array   An array of strings containing classnames.
     */
    private static function getTextExtractorClasses() {
        if ( ! self::$extractorClasses ) {
            $all_classes = ClassInfo::subclassesFor('ZendSearchLuceneTextExtractor');
            usort(
                $all_classes,
                create_function('$a, $b', '
                    $pa = new ReflectionClass($a);
                    $pa = $pa->getStaticPropertyValue(\'priority\');
                    $pb = new ReflectionClass($b);
                    $pb = $pb->getStaticPropertyValue(\'priority\');
                    if ( $pa == $pb ) return 0;
                    return ($pa < $pb) ? -1 : 1;'
                )
            );
            self::$extractorClasses = $all_classes;
        }
        return self::$extractorClasses;
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
     * If the SilverStripe database field is a Date or a descendant of Date, 
     * stores the date as a Unix timestamp.  Make sure your timezone is set 
     * correctly!
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
     * @access private
     * @param   DataObject  $object     The DataObject from which to extract a
     *                                  Zend field.
     * @param   String      $fieldName  The name of the field to fetch a Zend field for.
     * @return  Zend_Search_Lucene_Field
     */
    private static function getZendField($object, $fieldName) {
        $encoding = ZendSearchLuceneSearchable::$encoding;
        $config = $object->getLuceneFieldConfig($fieldName);

        // Recurses through dot-notation.
        $value = self::getFieldValue($object, $fieldName);

        if ( $config['content_filter'] ) {
            // Run through the content filter, if we have one.
            $value = call_user_func($config['content_filter'], $value);
        }

        if ( $config['name'] ) {
            $fieldName = $config['name'];
        }

        if ( $config['type'] == 'unstored' ) {
            return Zend_Search_Lucene_Field::UnStored($fieldName, $value, $encoding);
        }
        if ( $config['type'] == 'unindexed' ) {
            return Zend_Search_Lucene_Field::UnIndexed($fieldName, $value, $encoding);
        }
        if ( $config['type'] == 'keyword' ) {
            $keywordFieldName = $fieldName;
            if ( $keywordFieldName == 'ID' ) $keywordFieldName = 'ObjectID'; // Don't use 'ID' as it's used by Zend Lucene
            return Zend_Search_Lucene_Field::Keyword($keywordFieldName, $value, $encoding);
        }
        // Default - index and store as text
        return Zend_Search_Lucene_Field::Text($fieldName, $value, $encoding);
    }
    
    /**
     * Function to reduce a nested dot-notated field name to a string value.
     * Recurses into itself, going as deep as the relation needs to until it
     * ends up with a string to return.
     *
     * If the fieldname can't be resolved for the given object, returns an empty
     * string.
     */
    public static function getFieldValue($object, $fieldName) {
        if ( strpos($fieldName, '.') === false ) {
            if ( $object->hasMethod($fieldName) ) {
                // Method on object
                return $object->$fieldName();
            } else {
                // Bog standard field
                return $object->$fieldName;
            }
        }
        // Using dot notation
        list($baseFieldName, $relationFieldName) = explode('.', $fieldName, 2);
        // has_one
        if ( in_array($baseFieldName, array_keys($object->has_one())) ) {
            $field = $object->getComponent($baseFieldName);
            return self::getFieldValue($field, $relationFieldName);
        }
        // has_many
        if ( in_array($baseFieldName, array_keys($object->has_many())) ) {
            // loop through and get string values for all components
            $tmp = '';
            $components = $object->getComponents($baseFieldName);
            foreach( $components as $component ) {
                $tmp .= self::getFieldValue($component, $relationFieldName)."\n";
            }
            return $tmp;
        }
        // many_many
        if ( in_array($baseFieldName, array_keys($object->many_many())) ) {
            // loop through and get string values for all components
            $tmp = '';
            $components = $object->getManyManyComponents($baseFieldName);
            foreach( $components as $component ) {
                $tmp .= self::getFieldValue($component, $relationFieldName)."\n";
            }
            return $tmp;
        }
        // Nope, not able to be indexed :-(
        return '';
    }

    /**
     * Register a callback to run when creating an index.  Useful for setting 
     * advanced index options.  For example:
     *
     * <code>
     * function create_index_callback() {
     *    $index = ZendSeachLuceneWrapper::getIndex();
     *    $index->setMaxBufferedDocs(20);
     * }
     * ZendSearchLuceneWrapper::addCreateIndexCallback('create_index_callback');
     * </code>
     *
     * @param String|Array $callback A PHP callback to call whenever the Search 
     *                               index is created.
     */
    public static function addCreateIndexCallback($callback) {
        self::$createIndexCallbacks[] = $callback;
    }

    /**
     * Rebuilds the search index.  Generally called via register_shutdown_function().
     * 
     * When the process starts, a lock file is created in the temp folder to prevent other 
     * reindexes from being started when one is already running.
     *
     * After each document is indexed, a count is updated in a file in the temp 
     * folder.  This enables the system to keep a count of how many documents 
     * have been indexed so far in a running process, or how many documents 
     * were indexed the last time the reindex process was run.
     *
     * When the process exits, a second file is created containing the time 
     * taken to reindex.
     *
     * @return  Integer     Returns the number of documents that were indexed.
     */
    public static function rebuildIndex() {
        singleton('QueuedJobService')->queueJob(
            new ZendSearchLuceneReindexJob()
        );
        return;
    }

    /**
     * Returns a data array of all indexable DataObjects.  For use when reindexing.
     */
    public static function getAllIndexableObjects($className='DataObject') {
        // We'll estimate that we'll be indexing the same number of things as last time...
        $possibleClasses = ClassInfo::subclassesFor($className);
        $extendedClasses = array();
        foreach( $possibleClasses as $possibleClass ) {
            if ( Object::has_extension($possibleClass, 'ZendSearchLuceneSearchable') ) {
                $extendedClasses[] = $possibleClass;
            }
        }
        $indexed = array();
        foreach( $extendedClasses as $className ) {
            $config = singleton($className)->getLuceneClassConfig();
            $objects = DataObject::get($className, $config['index_filter']);
            if ( $objects === null ) continue;
            foreach( $objects as $object ) {
                // SiteTree objects only get indexed if they're published...
                if ( $object->is_a('SiteTree') && ! $object->getExistsOnLive() ) continue;
                // Only re-index if we haven't already indexed this DataObject
                if ( ! array_key_exists($object->ClassName.' '.$object->ID, $indexed) ) {
                    $indexed[$object->ClassName.' '.$object->ID] = array(
                        $object->ClassName, 
                        $object->ID
                    );
                }
            }
        }
        return $indexed;
    }

}


