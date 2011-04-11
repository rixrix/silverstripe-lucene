<?php

/**
 * Extracts text from a XLSX format Microsoft Excel document.  Uses the Zend 
 * text extraction class to do so.
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class ZendSearchLuceneXlsxExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'xlsx'
    );

    /**
     * Returns a string containing the text in the given Microsoft Excel XLSX 
     * document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! extension_loaded('zip') ) return '';
        if ( ! file_exists($filename) ) return '';
        try {
            $doc = Zend_Search_Lucene_Document_Xlsx::loadXlsxFile(
                $filename,
                true
            );
        } catch (Exception $e) {
            return '';
        }
        return $doc->body;
    }

}


