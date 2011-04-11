<?php

/**
 * Extracts text from a PPT format Microsoft Powerpoint document.  Uses the 
 * catppt command-line utility to do so.  This utility is part of the catdoc 
 * suite of command-line utilities, which can be downloaded at:
 *
 * {@link http://wagner.pp.ru/~vitus/software/catdoc/}
 *
 * The path to the catppt binary will be detected automatically if it lives at
 * /usr/bin/catppt or /usr/local/bin/catppt.  If your catppt binary is in a 
 * non-standard place, you can set it in your _ss_environment.php file like so:
 *
 * <code>
 * define('CATPPT_BINARY_LOCATION', '/home/username/bin/catppt');
 * </code>
 *
 * Or, if using _config.php, you can also set it directly on the class:
 *
 * <code>
 * CatdocPptExtractor::$binary_location = '/home/username/bin/catppt';
 * </code>
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class CatdocPptExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'ppt'
    );

    /**
     * Holds the location of the xls2csv binary.  Should be a full filesystem 
     * path.
     * @static
     */
    public static $binary_location; 
    
    /**
     * Returns a string containing the text in the given Microsoft Powerpoint 
     * PPT document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! file_exists($filename) ) return '';
        $binary = self::get_binary_path();
        if ( !$binary ) return '';
        return shell_exec($binary.' '.escapeshellarg($filename));
    }


    /**
     * Try to detect where the catppt binary has been installed.
     *
     * @access private
     * @return  String|Boolean  Returns the path to the catppt binary, or 
     *                          boolean false if it cannot be found.
     */
    protected static function get_binary_path() {
        if ( self::$binary_location ) return self::$binary_location;
        if ( defined('CATPPT_BINARY_LOCATION') ) {
            self::$binary_location = CATPPT_BINARY_LOCATION;
        } else if ( file_exists('/usr/bin/catppt') ) {
            self::$binary_location = '/usr/bin/catppt';
        } else if ( file_exists('/usr/local/bin/catppt') ) {
            self::$binary_location = '/usr/local/bin/catppt';
        }
        return self::$binary_location;        
    }


}


