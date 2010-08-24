<?php

class ZendSearchLuceneSiteConfig extends DataObjectDecorator {

    public function updateCMSActions(&$actions) {
        $actions->push(
            new InlineFormAction(
                'rebuildZendSearchLuceneIndex',
                'Rebuild Search Index'
            )
        );
    }

}

