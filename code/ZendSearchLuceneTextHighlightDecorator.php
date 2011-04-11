<?php

/**
 * Extension to StringField to add a Field.SearchTextHighlight template method
 * that extracts text for a given search term.
 *
 * @package lucene-silverstripe-module
 * @author Darren Inwood <darren.inwood@chrometoaster.com>
 */

class ZendSearchLuceneTextHighlightDecorator extends Extension {

    /**
     * Returns a part of the field's text with a word or words highlighted.
     * HTML is stripped, and words are highlighted using HTML strong tags.
     *
     * @param $numWords     Integer     Number of words to output. Default is
     *                                  25.
     * @param $addParaTags  Boolean     Whether to add HTML paragraph tags 
     *                                  around the output. Default is true.
     * @return An HTMLText object containing the highlighted text as HTML.
     */
    public function SearchTextHighlight($numWords = 25,$addParaTags=true) {
        $words = explode(' ',$_REQUEST['Search']);
        $text = strip_tags($this->owner->value);
        $orig = $text;
        $text_lower = explode(' ', strtolower($text));
         // Find the search words in the summary
        $found = array();
        $first = 0;
        foreach( $words as $word ) {
            $tmp = array_search($word, $text_lower);
            if ( $tmp !== false ) $found[] = $tmp;
        }
        if ( count($found) > 0 ) {
            $first = $found[0];
        }
        // Get 25 words, starting two words before a highlighted word.
        if ( count(explode(' ', $text)) > $numWords ) {
            $text = implode(' ', 
                array_slice(
                    explode(' ', $text), 
                    max(0, $first - 2),
                    $numWords
                )
            );
            if ( substr($orig,-10) != substr($text,-10) && strlen($text) < strlen($orig) ) $text .= '...';
            if ( $first != 0 ) $text = '...' . $text;
        }
        foreach( $words as $word ) {
            $word = preg_quote($word);
            $text = preg_replace("/\b($word)\b/i", '<strong>\1</strong>', $text);
        }
        if ( $addParaTags ) $text = '<p>'.$text.'</p>';
        return DBField::create('HTMLText', $text, $this->owner->name);
    }

}

