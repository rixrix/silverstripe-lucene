<?php

/**
 * Adds a function to LeftAndMain to rebuild the Lucene search index.
 * Possibly uses way too much memory...
 */
class ZendSearchLuceneCMSDecorator extends LeftAndMainDecorator {

    public function rebuildZendSearchLuceneIndex() {
        set_time_limit(600);
        $index = ZendSearchLuceneWrapper::getIndex(/*true*/); // Wipes current index
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

        FormResponse::status_message( 'The search engine has been rebuilt. '.$count.' entries were indexed.', 'good' );
        return FormResponse::respond();
    }

}

