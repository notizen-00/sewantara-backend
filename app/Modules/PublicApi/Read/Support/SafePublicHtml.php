<?php

namespace App\Modules\PublicApi\Read\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use Throwable;

final class SafePublicHtml
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'a',
        'b',
        'blockquote',
        'br',
        'code',
        'em',
        'figcaption',
        'figure',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        'strong',
        'ul',
    ];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = [
        'base',
        'button',
        'embed',
        'form',
        'iframe',
        'input',
        'link',
        'math',
        'meta',
        'object',
        'option',
        'script',
        'select',
        'style',
        'svg',
        'textarea',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            return nl2br(htmlspecialchars(
                strip_tags($html),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ));
        }

        try {
            return $this->sanitizeWithDom($html);
        } catch (Throwable) {
            return nl2br(htmlspecialchars(
                strip_tags($html),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ));
        }
    }

    private function sanitizeWithDom(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><div data-public-root="1">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($root);
        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMComment) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($child);
                $this->unwrap($child);

                continue;
            }

            $this->sanitizeAttributes($child, $tag);

            if ($tag === 'img' && ! $child->hasAttribute('src')) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->sanitizeChildren($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $names = [];

        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            if (! in_array(strtolower($name), $allowed, true)) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $href = $this->safeUrl($element->getAttribute('href'), false);

            if ($href === null) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('href', $href);
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img' && $element->hasAttribute('src')) {
            $src = $this->safeUrl($element->getAttribute('src'), true);

            if ($src === null) {
                $element->removeAttribute('src');
            } else {
                $element->setAttribute('src', $src);
                $element->setAttribute('loading', 'lazy');
                $element->setAttribute('decoding', 'async');
            }
        }

        foreach (['width', 'height'] as $dimension) {
            if ($element->hasAttribute($dimension)
                && preg_match('/^[1-9]\d{0,3}$/', $element->getAttribute($dimension)) !== 1) {
                $element->removeAttribute($dimension);
            }
        }
    }

    private function safeUrl(string $url, bool $image): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowed = $image
            ? ['http', 'https']
            : ['http', 'https', 'mailto', 'tel'];

        return in_array($scheme, $allowed, true) ? $url : null;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
