<?php

/**
 * Extracts text from an HTML document.  Uses the Zend HTML text extraction 
 * class to do so.
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class ZendSearchLuceneHtmlExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'htm',
        'html'
    );

    /**
     * Returns a string containing the text in the given HTML document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! file_exists($filename) ) return '';
        try {
            $doc = Zend_Search_Lucene_Document_Html::loadHTMLFile(
                $filename, 
                true
            );
        } catch (Exception $e) {
            return '';
        }
        return $doc->body;
    }

}


