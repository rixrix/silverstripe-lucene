<?php

/**
 * Extracts text from a TXT text document.
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class TxtDocumentExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'txt'
    );

    /**
     * Returns a string containing the text in the given TXT document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! file_exists($filename) ) return '';
        return file_get_contents($filename);
    }

}


