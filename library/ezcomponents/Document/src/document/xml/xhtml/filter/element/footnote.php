<?php
/**
 * File containing the ezcDocumentXhtmlFootnoteElementFilter class
 *
 * @package Document
 * @version 1.3.1
 * @copyright Copyright (C) 2005-2010 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 * @access private
 */

/**
 * Filter for XHtml footnotes, as generated by ezcDocument.
 *
 * Footnotes, generated by the Document component itself, are links with the
 * class footnote and a list at the bottom of the document, also with the class
 * footnotes. Those should be converted back to Docbook footnotes.
 *
 * It is nearly impossible to generally filter footnote markup - if you come
 * across some, feel free to provide another filter for that.
 *
 * @package Document
 * @version 1.3.1
 * @access private
 */
class ezcDocumentXhtmlFootnoteElementFilter extends ezcDocumentXhtmlElementBaseFilter
{
    /**
     * Array with extracted footnotes
     *
     * @var array
     */
    protected $footnotes = null;

    /**
     * Filter a single element
     *
     * @param DOMElement $element
     * @return void
     */
    public function filterElement( DOMElement $element )
    {
        // Extract footnotes, if not done yet.
        if ( $this->footnotes === null )
        {
            $this->extractFootnotes( $element );

            if ( $this->footnotes === null )
            {
                // No footnotes could be extracted from the document, so we
                // can't fetch the footnote text, just leave the element to the
                // other filters.
                return;
            }
        }

        $footnoteName = trim( $element->textContent );
        if ( !isset( $this->footnotes[$footnoteName] ) )
        {
            // We could ont find a footnote target for the current footnode, so
            // just skip this one.
            return;
        }

        // Get link text content, which is the footnote label, and remove it,
        // so it does not show up as part of the footnote
        $label = $element->textContent;
        while ( $element->firstChild )
        {
            $element->removeChild( $element->firstChild );
        }

        // Finally create footnote from element
        $element->setProperty( 'type', 'footnote' );
        $element->setProperty( 'attributes', array(
            'label' => $label,
        ) );

        $paragraph = new ezcDocumentPropertyContainerDomElement( 'span', $this->footnotes[$footnoteName] );
        $element->appendChild( $paragraph );
        $paragraph->setProperty( 'type', 'para' );
    }

    /**
     * Extract footnotes
     *
     * Extract footnotes from the given XHtml document.
     *
     * @param DOMElement $element
     * @return void
     */
    protected function extractFootnotes( DOMElement $element )
    {
        $doc = $element->ownerDocument;

        $xpath = new DOMXPath( $doc );
        // We cannot use the normal way for finding elements (//ul), as the
        // element may be part of some XHtml namespace or no namespace, which
        // may vary. We just use the localname and check for the attribute,
        // which still may give us false positives from unwanted namespaces. As
        // those external namespaces are seldomly integrated with XHtml / HTML
        // this should work in most cases.
        $nodes = $xpath->query( '//*[( local-name() = "ul" ) and contains( @class, "footnote" )]' );

        // From the found footnote lists, we extract content in a way, which
        // should work in most cases. The name is expected to be embedded in an
        // a-tag, and the contents in paragraphs. After the extraction, we
        // remove the list, as the contents will be embedded later at the
        // footnote references.
        //
        // The footnote extraction is still quite volatile as it is.
        foreach ( $nodes as $node )
        {
            // Extract all paragraphs, to get the footnote contents
            foreach ( $xpath->query( '*[local-name() = "li"]', $node ) as $footnote )
            {
                // The footnote name should be embedded in the first a-tag, as
                // it is a link target.
                $footnoteRef  = $xpath->query( './/*[local-name() = "a"]', $footnote )->item( 0 );
                $footnoteName = trim( $footnoteRef->textContent );
                $footnoteRef->parentNode->removeChild( $footnoteRef );

                // All text embedded in paragraphs
                $footnoteText = '';
                foreach ( $xpath->query( '*[local-name() = "p"]', $footnote ) as $paragraph )
                {
                    $footnoteText .= $paragraph->textContent;
                }

                // Add footnote to list of footnotes
                $this->footnotes[$footnoteName] = trim( $footnoteText );
            }

            // Remove node
            $node->parentNode->removeChild( $node );
        }
    }

    /**
     * Check if filter handles the current element
     *
     * Returns a boolean value, indicating weather this filter can handle
     * the current element.
     *
     * @param DOMElement $element
     * @return void
     */
    public function handles( DOMElement $element )
    {
        return ( ( $element->tagName === 'a' ) &&
                 $this->hasClass( $element, 'footnote' ) );
    }
}

?>
