<?php

/**
 * Extracts text from a PDF document.  Tries to use the pdftotext command-line 
 * utility if it is installed, otherwise falls back to the PDF2Text class.
 *
 * The pdftotext utility gives superior text extraction, and should be used 
 * wherever possible.  It can be found for Windows and Mac OS X at:
 *
 * {@link http://www.foolabs.com/xpdf/}
 *
 * If you are using Linux, the utility is part of both the xpdf and 
 * poppler-utils packages.  On Ubuntu and Debian:
 *
 * <code>
 * apt-get install poppler-utils
 * </code>
 *
 * The path to the pdftotext binary will be detected automatically if it lives 
 * at /usr/bin/pdftotext or /usr/local/bin/pdftotext.  If your catdoc binary is 
 * in a non-standard place, you can set it in your _ss_environment.php file like
 * so:
 *
 * <code>
 * define('PDFTOTEXT_BINARY_LOCATION', '/home/username/bin/pdftotext');
 * </code>
 *
 * Or, if using _config.php, you can also set it directly on the class:
 *
 * <code>
 * PdfDocumentExtractor::$binary_location = '/home/username/bin/pdftotext';
 * </code>
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */
class PdfDocumentExtractor extends ZendSearchLuceneTextExtractor {

    /**
     * The extensions that can be handled by this text extractor.
     * @static
     */
    public static $extensions = array(
        'pdf'
    );

    /**
     * Holds the location of the pdftotext binary.  Should be a full filesystem 
     * path.
     * @static
     */
    public static $binary_location; 

    /**
     * Returns a string containing the text in the given TXT document.
     *
     * @param   String  $filename   Full filesystem path to the file to process.
     * @return  String  Text extracted from the file.
     */
    public static function extract($filename) {
        if ( ! file_exists($filename) ) return '';
        if ( trim(shell_exec('which pdftotext')) !== '' ) {
            return self::commandline($filename);
        }
        return self::pdf2text($filename);
    }


    /**
     * @access private
     */
    protected static function commandline($filename) {
        $pdftotext = trim(shell_exec('which pdftotext'));
        return shell_exec($pdftotext.' '.escapeshellarg($filename).' -'); 
    }
    
    
    /**
     * @access private
     */
    protected static function pdf2text($filename) {
        $pdf = new PDF2Text();
        $pdf->setFilename($filename);
        $pdf->decodePDF();
        $content = $pdf->output();
        if ( $content == '' ) {
            // try with different multibyte setting
            $pdf->setUnicode(true);
            $pdf->decodePDF();
            $content = $pdf->output();
        }
        return $content;    
    }

    /**
     * Try to detect where the pdftptext binary has been installed.
     *
     * @access private
     * @return  String|Boolean  Returns the path to the pdftotext binary, or 
     *                          boolean false if it cannot be found.
     */
    protected static function get_binary_path() {
        if ( self::$binary_location ) return self::$binary_location;
        if ( defined('PDFTOTEXT_BINARY_LOCATION') ) {
            self::$binary_location = PDFTOTEXT_BINARY_LOCATION;
        } else if ( file_exists('/usr/bin/pdftotext') ) {
            self::$binary_location = '/usr/bin/pdftotext';
        } else if ( file_exists('/usr/local/bin/pdftotext') ) {
            self::$binary_location = '/usr/local/bin/pdftotext';
        }
        return self::$binary_location;        
    }

}


