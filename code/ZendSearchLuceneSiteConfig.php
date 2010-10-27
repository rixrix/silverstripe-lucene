<?php

/**
 * Adds a button the Site Config page of the CMS to rebuild the Lucene search index.
 * 
 * @package lucene-silverstripe-plugin
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
 
class ZendSearchLuceneSiteConfig extends DataObjectDecorator {

    /**
     * Adds a button the Site Config page of the CMS to rebuild the Lucene search index.
     */
    public function updateCMSActions(&$actions) {
        $actions->push(
            new InlineFormAction(
                'rebuildZendSearchLuceneIndex',
                _t('ZendSearchLuceneSiteConfig.RebuildIndexButtonText', 'Rebuild Search Index')
            )
        );
    }

}

