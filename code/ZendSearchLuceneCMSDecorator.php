<?php

/**
 * Adds functions to LeftAndMain to rebuild the Lucene search index.
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class ZendSearchLuceneCMSDecorator extends LeftAndMainDecorator {

    /**
     * Enables the extra button added via ZendSearchLuceneSiteConfig.
     * @static
     * @access public
     */
    public static $allowed_actions = array(
        'rebuildZendSearchLuceneIndex',
        'reindex'
    );

    /**
     * Receives the form submission which tells the index rebuild process to 
     * begin.
     *
     * @access public
     * @return      String          The AJAX response to send to the CMS.
     */
    public function rebuildZendSearchLuceneIndex() {
        ZendSearchLuceneWrapper::rebuildIndex();
        FormResponse::status_message( 
            _t('ZendSearchLucene.SuccessMessage', 'A Lucene search index rebuild job has been added to the Jobs queue.'),
            'good'
        );
        return FormResponse::respond();
    }

    /**
     * Debug method to allow manual reindexing with output via the URL 
     * /Lucene/reindex
     *
     * @access public
     * Note that this should NOT be used as a reindexing
     * process in production, as it doesn't allow for out of memory or script 
     * execution time problems.
     */
    public function reindex() {
        set_time_limit(600);
        $start = microtime(true);
        echo '<h1>Reindexing</h1>'."\n"; flush();
        echo 'Note that this process may die due to time limit or memory '
            .'exhaustion, and is purely for debugging purposes.  Use the '
            .'Queued Jobs reindex process for production indexing.'
            ."<br />\n<br />\n"; flush();
        ZendSearchLuceneWrapper::getIndex(true);
        $indexable = ZendSearchLuceneWrapper::getAllIndexableObjects();
        foreach( $indexable as $item ) {
            $obj = DataObject::get_by_id($item[0], $item[1]);
            if ( $obj ) {
                $obj_start = microtime(true);
                echo $item[0].' '.$item[1].' ('.$obj->class.')'; flush();
                ZendSearchLuceneWrapper::index($obj);
                echo ' - '.round(microtime(true)-$obj_start, 3).' seconds'."<br />\n"; flush();
            } else {
                echo 'Object '.$item[0].' '.$item[1].' was not found.'."<br />\n"; flush();
            }
        }
        echo "<br />\n".'Finished ('.round(microtime(true)-$start, 3).' seconds)'."<br />\n"; flush();
    }


}

