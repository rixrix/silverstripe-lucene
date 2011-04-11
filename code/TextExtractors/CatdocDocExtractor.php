<?php

/**
 * Extracts text from a DOC format Microsoft Word document.  Uses the catdoc
 * command-line utility to do so.  Catdoc can be downloaded for Linux at:
 *
 * {@link http://wagner.pp.ru/~vitus/software/catdoc/}
 *
 * The path to the catdoc binary will be detected automatically if it lives at
 * /usr/bin/catdoc or /usr/local/bin/catdoc.  If your catdoc binary is in a 
 * non-standard place, you can set it in your _ss_environment.php file like so:
 *
 * <code>
 * define('CATDOC_BINARY_LOCATION', '/home/username/bin/catdoc');
 * </code>
 *
 * Or, if using _config.php, you can also set it directly on the class:
 *
 * <code>
 * CatdocDocExtractor::$binary_location = '/home/username/bin/catdoc';
 * </code>
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class CatdocDocExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'doc'
    );

    /**
     * Holds the location of the catdoc binary.  Should be a full filesystem 
     * path.
     * @static
     */
    public static $binary_location; 
    
    /**
     * Returns a string containing the text in the given Microsoft Word DOC
     * document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! file_exists($filename) ) return '';
        $binary = self::get_binary_path();
        if ( !$binary ) return '';
        return shell_exec($binary.' -a '.escapeshellarg($filename));
    }


    /**
     * Try to detect where the catdoc binary has been installed.
     *
     * @access private
     * @return  String|Boolean  Returns the path to the catdoc binary, or 
     *                          boolean false if it cannot be found.
     */
    protected static function get_binary_path() {
        if ( self::$binary_location ) return self::$binary_location;
        if ( defined('CATDOC_BINARY_LOCATION') ) {
            self::$binary_location = CATDOC_BINARY_LOCATION;
        } else if ( file_exists('/usr/bin/catdoc') ) {
            self::$binary_location = '/usr/bin/catdoc';
        } else if ( file_exists('/usr/local/bin/catdoc') ) {
            self::$binary_location = '/usr/local/bin/catdoc';
        }
        return self::$binary_location;        
    }


}


