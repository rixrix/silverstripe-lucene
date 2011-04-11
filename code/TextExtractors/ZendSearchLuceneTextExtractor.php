<?php

/**
 * Abstract base class instructing the Lucene module on how to extract text from a 
 * given file type.
 * 
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
abstract class ZendSearchLuceneTextExtractor {
 
    /**
     * An array of strings representing file extensions that can be handled by 
     * this TextExtractor.  Do not include a dot in your extensions.  Extensions
     * should be in lower case, and will detect all case variations on scanned 
     * files.
     * @static
     */
    public static $extensions = array(); 

    /**
     * Controls the order in which text extractor classes are tried for a 
     * specific file extension.  Default is 100.  To make your custom extractor
     * run before an inbuilt one, set this to less than 100, or to make it run 
     * afterwards set it to more than 100.
     * @static
     */
    public static $priority = 100;

    /**
     * Returns text for a given full filesystem path.  If a file cannot be 
     * processed, you should return an empty string.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    abstract public static function extract($filename);
 
 
}

