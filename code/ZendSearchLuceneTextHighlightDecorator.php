<?php

class ZendSearchLuceneTextHighlightDecorator extends Extension {

    /**
     * Returns a part of the field's text with a word or words highlighted.
     */
    public function SearchTextHighlight() {
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
        $text = implode(' ', 
            array_slice(
                explode(' ', $text), 
                max(0, $first - 2),
                25
            )
        );
        if ( substr($orig,-10) != substr($text,-10) && strlen($text) < strlen($orig) ) $text .= '...';
        if ( $first != 0 ) $text = '...' . $text;
        foreach( $words as $word ) {
            $word = preg_quote($word);
            $text = preg_replace("/\b($word)\b/i", '<strong>\1</strong>', $text);
        }
        $text = '<p>'.$text.'</p>';
        return DBField::create('HTMLText', $text, $this->owner->name);
    }



}

