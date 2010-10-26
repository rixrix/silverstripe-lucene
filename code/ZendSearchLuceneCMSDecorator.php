<?php

/**
 * Adds a function to LeftAndMain to rebuild the Lucene search index.
 * Possibly uses way too much memory...
 */
class ZendSearchLuceneCMSDecorator extends LeftAndMainDecorator {

    public function rebuildZendSearchLuceneIndex() {
        set_time_limit(600);
        ZendSearchLuceneWrapper::getIndex(true); // Wipes current index
        $count = 0;
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
                $object->onAfterWrite();
                $count++;
            }
        }
        FormResponse::status_message( 'The search engine has been rebuilt. '.$count.' entries were indexed.', 'good' );
        return FormResponse::respond();
    }

}

