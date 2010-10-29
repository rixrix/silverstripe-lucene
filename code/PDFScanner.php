<?php

/**
 * Scans a PDF file and returns the text file content.
 *
 * Will use command line pdftotext if available.  If not, falls back to the 
 * PDF2TXT class by Joeri Stegeman.
 */

class PDFScanner {

    /**
     * Returns a string containing the detected text for a given file.
     *
     * @param   String  $filename   An absolute filesystem path to the PDF file
     *                              to read.
     * @return  String              The text extracted from the PDF file. If the
     *                              file is not found or cannot be read, returns
     *                              an empty string.
     */
    public static function getText($filename) {
        if ( ! file_exists($filename) ) return '';
        if ( trim(shell_exec('which pdftotext')) !== '' ) {
            return self::commandline($filename);
        }
        return self::pdf2text($filename);
    }


    private static function commandline($filename) {
        $pdftotext = trim(shell_exec('which pdftotext'));
        return shell_exec($pdftotext.' '.escapeshellarg($filename).' -'); 
    }
    
    
    private static function pdf2text($filename) {
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

}

