<?php

/**
 * Extracts text from a DOCX format Microsoft Word document.  Uses the Zend 
 * text extraction class to do so.
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class ZendSearchLuceneDocxExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'docx'
    );

    /**
     * Returns a string containing the text in the given Microsoft Word DOCX 
     * document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! extension_loaded('zip') ) return '';
        if ( ! file_exists($filename) ) return '';
        try {
            $doc = Zend_Search_Lucene_Document_Docx::loadDocxFile(
                $filename,
                true
            );
        } catch (Exception $e) {
            return '';
        }
        return $doc->body;
    }

}


