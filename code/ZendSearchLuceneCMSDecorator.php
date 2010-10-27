<?php

/**
 * Adds a function to LeftAndMain to rebuild the Lucene search index.
 *
 * @package lucene-silverstripe-plugin
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class ZendSearchLuceneCMSDecorator extends LeftAndMainDecorator {

    /**
     * Blanks the search index, and rebuilds it from scratch.
     * Gets a list of all objects that have the Searchable extension, and indexes all of them.
     *
     * @return      String          The AJAX response to send to the CMS.
     */
    public function rebuildZendSearchLuceneIndex() {
        set_time_limit(600);
        $index = ZendSearchLuceneWrapper::getIndex(true); // Wipes current index
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
                    ZendSearchLuceneWrapper::index($object);
                    $indexed[$object->ClassName][$object->ID] = true;
                    $count++;
                }
            }
        }

        FormResponse::status_message( 
            sprintf(
                _t('ZendSearchLuceneCMSDecorator.SuccessMessage', 'The search engine has been rebuilt. %s entries were indexed.'),
                (int)$count
            ),
            'good' 
        );
        return FormResponse::respond();
    }

}

