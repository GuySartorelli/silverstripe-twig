<?php

namespace GuySartorelli\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;

/**
 * Parser that allows interaction with the Silverstripe CMS Requirements API from inside a twig template
 *
 * Simply use `{% require %}` the same way you'd use `<% require %>` e.g. `{% require themedCSS("some-file.css") %}`
 */
class RequireTokenParser extends AbstractTokenParser
{
    /**
     * @inheritDoc
     */
    public function parse(Token $token): RequireNode
    {
        // Parse the require tag
        $stream = $this->parser->getStream();
        $method = $stream->expect(Token::NAME_TYPE)->getValue();
        $stream->expect(Token::PUNCTUATION_TYPE, '(');
        $args = $this->parseArgs($stream);
        $stream->expect(Token::PUNCTUATION_TYPE, ')');
        $stream->expect(Token::BLOCK_END_TYPE);

        return new RequireNode($token->getLine(), $method, $args, $this->getTag());
    }

    /**
     * @inheritDoc
     */
    public function getTag()
    {
        return 'require';
    }

    private function parseArgs(TokenStream $stream): array
    {
        $valuableTokenTypes = [Token::STRING_TYPE, Token::NUMBER_TYPE];
        $args = [];
        $token = $stream->next();

        while (in_array($token->getType(), $valuableTokenTypes)) {
            $args[] = [
                'value' => $token->getValue(),
                'isString' => $token->getType() === Token::STRING_TYPE,
            ];
            if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                // If the next token isn't a comma, we've got no more args.
                break;
            }
            // Next arg
            $token = $stream->next();
        }

        return $args;
    }
}
