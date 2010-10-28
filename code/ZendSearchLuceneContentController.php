<?php

/**
 * Extension to provide a search interface when applied to ContentController
 * 
 * @package lucene-silverstripe-plugin
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
 
class ZendSearchLuceneContentController extends Extension { 

	static $allowed_actions = array(
		'ZendSearchLuceneForm',
		'ZendSearchLuceneResults',
	);
	
	/**
	 * Returns the Lucene-powered search Form object.
	 *
	 * @return  Form    A Form object representing the search form.
	 */
	function ZendSearchLuceneForm() {
		$searchText = isset($_REQUEST['Search']) ? $_REQUEST['Search'] : '';
		$fields = new FieldSet(
			new TextField(
			    'Search', 
			    _t('ZendSearchLuceneForm.SearchButtonText', 'Search'), 
			    $searchText
			)
		);
		$actions = new FieldSet(
			new FormAction('ZendSearchLuceneResults', 'Go')
		);
		$form = new Form($this->owner, 'ZendSearchLuceneForm', $fields, $actions);
        $form->disableSecurityToken();
        $form->setFormMethod('get');
		return $form;
	}

	/**
	 * Process and render search results.
	 * 
	 * @param   array           $data       The raw request data submitted by user
	 * @param   Form            $form       The form instance that was submitted
	 * @param   SS_HTTPRequest  $request    Request generated for this action
	 * @return  String                      The rendered form, for inclusion into the page template.
	 */
	function ZendSearchLuceneResults($data, $form, $request) {
		$query = $form->dataFieldByName('Search')->dataValue();
		$hits = ZendSearchLuceneWrapper::find($query);
        $data = $this->getDataArrayFromHits($hits, $request);
		return $this->owner->customise($data)->renderWith(array('Lucene_results', 'Page'));
	}

    /**
     * Returns a data array suitable for customising a Page with, containing
     * search result and pagination information.
     * 
     * @param   Array           $hits       An array of Zend_Search_Lucene_Search_QueryHit objects
     * @param   SS_HTTPRequest  $request    The request that generated the search
     * @return  Array                       A custom array containing pagination data.
     */
    protected function getDataArrayFromHits($hits, $request) {
		$data = array(
			'Results' => null,
			'Query' => null,
			'Title' => 'Search Results',
			'TotalResults' => null,
			'TotalPages' => null,
			'ThisPage' => null,
			'StartResult' => null,
			'EndResult' => null,
			'PrevUrl' => DBField::create('Text', 'false'),
			'NextUrl' => DBField::create('Text', 'false'),
			'SearchPages' => new DataObjectSet()
		);
		
        $pageLength = ZendSearchLuceneSearchable::$pageLength;              // number of results per page
        $alwaysShowPages = ZendSearchLuceneSearchable::$alwaysShowPages;    // always show this many pages in pagination
        $maxShowPages = ZendSearchLuceneSearchable::$maxShowPages;          // maximum number of pages shown in pagination

		$start = $request->requestVar('start') ? (int)$request->requestVar('start') : 0;
		$currentPage = floor( $start / $pageLength ) + 1;
		$totalPages = ceil( count($hits) / $pageLength );
		if ( $totalPages == 0 ) $totalPages = 1;
		if ( $currentPage > $totalPages ) $currentPage = $totalPages;

        // Assemble final results after page number mangling
        $results = new DataObjectSet();
		foreach($hits as $k => $hit) {
		    if ( $k < ($currentPage-1)*$pageLength || $k >= ($currentPage*$pageLength) ) continue;
			$className = $hit->ClassName;
			$vars = new $className();
			$vars = $vars->getSearchedVars();
			$obj = new DataObject();
			foreach ( $vars as $var ) {
			    try {
			        $obj->$var = $hit->$var;
                } catch (Exception $e) { }
			}
			$obj->score = $hit->score;
			$obj->Number = $k + 1; // which number result it is...
			$results->push($obj);
		}
		// filter by permission
/*
		if($results) foreach($results as $result) {
			if(!$result->canView()) $results->remove($result);
		}
*/
		
	    $data['Results'] = $results;
	    $data['Query']   = DBField::create('Text', $request->requestVar('Search'));
	    $data['TotalResults'] = DBField::create('Text', count($hits));
        $data['TotalPages'] = DBField::create('Text', $totalPages);
        $data['ThisPage'] = DBField::create('Text', $currentPage);
        $data['StartResult'] = $start + 1;
        $data['EndResult'] = $start + count($results);

        // Helper to get the pagination URLs
        function build_search_url($params) {
	        $url = parse_url($_SERVER['REQUEST_URI']);
	        if ( ! array_key_exists('query', $url) ) $url['query'] = '';
            parse_str($url['query'], $url['query']);
            if ( ! is_array($url['query']) ) $url['query'] = array();
            // Remove 'start parameter if it exists
            if ( array_key_exists('start', $url['query']) ) unset( $url['query']['start'] );
            // Add extra parameters from argument
            $url['query'] = array_merge($url['query'], $params);
            $url['query'] = http_build_query($url['query']);
            $url = $url['path'] . ($url['query'] ? '?'.$url['query'] : '');
            return $url;
        }

        // Pagination links
        if ( $currentPage > 1 ) {
            $data['PrevUrl'] = DBField::create('Text', 
                build_search_url(array('start' => ($currentPage - 2) * $pageLength))
            );
        }
        if ( $currentPage < $totalPages ) {
            $data['NextUrl'] = DBField::create('Text', 
                build_search_url(array('start' => $currentPage * $pageLength))
            );
        }
        if ( $totalPages > 1 ) {
            // Always show a certain number of pages at the start
            for ( $i = 1; $i <= min($totalPages, $alwaysShowPages ); $i++ ) {
                $obj = new DataObject();
                $obj->IsEllipsis = false;
                $obj->PageNumber = $i;
                $obj->Link = build_search_url(array(
                    'start' => ($i - 1) * $pageLength
                ));
                $obj->Current = false;
                if ( $i == $currentPage ) $obj->Current = true;
                $data['SearchPages']->push($obj);
            }
            if ( $totalPages > $alwaysShowPages ) {
                // Start showing pages from 
                $extraPagesStart = max($currentPage-1, $alwaysShowPages+1);
                if ( $totalPages <= $maxShowPages ) {
                    $extraPagesStart = $alwaysShowPages + 1;
                }
                $extraPagesEnd = min($extraPagesStart + ($maxShowPages - $alwaysShowPages) - 1, $totalPages);
                if ( $extraPagesStart > ($alwaysShowPages+1) ) {
                    // Ellipsis to denote that there are more pages in the middle
                    $obj = new DataObject();
                    $obj->IsEllipsis = true;
                    $obj->Link = false;
                    $obj->Current = false;
                    $data['SearchPages']->push($obj);                    
                }
                for ( $i = $extraPagesStart; $i <= $extraPagesEnd; $i++ ) {
                    $obj = new DataObject();
                    $obj->IsEllipsis = false;
                    $obj->PageNumber = $i;
                    $obj->Link = build_search_url(array(
                        'start' => ($i - 1) * $pageLength
                    ));
                    $obj->Current = false;
                    if ( $i == $currentPage ) $obj->Current = true;
                    $data['SearchPages']->push($obj);                    
                }
                if ( $extraPagesEnd < $totalPages ) {
                    // Ellipsis to denote that there are more pages after
                    $obj = new DataObject();
                    $obj->IsEllipsis = true;
                    $obj->Link = false;
                    $obj->Current = false;
                    $data['SearchPages']->push($obj);                    
                }                
            }
        }
        
        return $data;
    }

}
