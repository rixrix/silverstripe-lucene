<?php

/**
 * Adds a function to LeftAndMain to rebuild the Lucene search index.
 *
 * @package lucene-silverstripe-plugin
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class ZendSearchLuceneCMSDecorator extends LeftAndMainDecorator {

    /**
     * Enables the extra button added via ZendSearchLuceneSiteConfig
     */
    public static $allowed_actions = array(
        'rebuildZendSearchLuceneIndex'
    );

    /**
     * Blanks the search index, and rebuilds it from scratch.
     * Gets a list of all objects that have the Searchable extension, and indexes all of them.
     *
     * @return      String          The AJAX response to send to the CMS.
     */
    public function rebuildZendSearchLuceneIndex() {
        $count = ZendSearchLuceneWrapper::rebuildIndex();
        FormResponse::status_message( 
            sprintf(
                _t('ZendSearchLuceneCMSDecorator.SuccessMessage', 'The search engine index has been rebuilt. %s entries were indexed in %s seconds.'),
                (int)$count,
                ZendSearchLuceneWrapper::getLastReindexTime()
            ),
            'good' 
        );
        return FormResponse::respond();
    }

}

