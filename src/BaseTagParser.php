<?php

namespace GuySartorelli\Twig;

use SilverStripe\View\SSViewer;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Allows usage of `{% base_tag %}` as an equivalent of ss template's `<% base_tag %>`
 *
 * Use `{% base_tag xhtml %}` if your DOCTYPE indicate xhtml, or simply `{% base_tag %}` for regular html.
 */
class BaseTagParser extends AbstractTokenParser
{
    /**
     * @inheritDoc
     */
    public function parse(Token $token): Node
    {
        // Parse the require tag
        $stream = $this->parser->getStream();
        if ($stream->look(0)->getType() === Token::NAME_TYPE) {
            $type = $stream->expect(Token::NAME_TYPE)->getValue();
        } else {
            $type = 'html';
        }
        $stream->expect(Token::BLOCK_END_TYPE);

        $isXhtml = strtolower($type) === 'xhtml';
        return new TextNode(SSViewer::get_base_tag($isXhtml), $token->getLine());
    }

    /**
     * @inheritDoc
     */
    public function getTag()
    {
        return 'base_tag';
    }
}
