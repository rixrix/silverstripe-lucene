<?php

/**
 * The job description class for reindexing the search index via the Queued Jobs 
 * SilverStripe module.
 * 
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class ZendSearchLuceneReindexJob extends AbstractQueuedJob implements QueuedJob {

    public function getTitle() {
        return _t('ZendSearchLucene.ReindexJobTitle', 'Rebuild the Lucene search engine index');
    }

    public function getSignature() {
        return 'ZendSearchLuceneReindexJob';
    }

    public function setup() {
        // Wipe current index
        ZendSearchLuceneWrapper::getIndex(true);
        $indexed = ZendSearchLuceneWrapper::getAllIndexableObjects();
        $this->remainingDocuments = $indexed;
        $this->totalSteps = count($indexed);
    }

    public function process() {
		$remainingDocuments = $this->remainingDocuments;

		// if there's no more, we're done!
		if (!count($remainingDocuments)) {
			$this->isComplete = true;
			return;
		}
		
		$this->currentStep++;
		
		$item = array_shift($remainingDocuments);
	
		$obj = DataObject::get_by_id($item[0], $item[1]);

        ZendSearchLuceneWrapper::index($obj);

		// and now we store the new list of remaining children
		$this->remainingDocuments = $remainingDocuments;

		if (!count($remainingDocuments)) {
			$this->isComplete = true;
			return;
		}    
    }

}


