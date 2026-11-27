<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pembersih HTML sisi server untuk teks kaya yang tampil di website (deskripsi
 * produk, blok konten). Daftar tag/atribut sama dengan editor admin, tapi
 * pemeriksaan di browser bisa dilewati, jadi yang dicetak mentah ke halaman
 * harus lewat sini: tanpa <script>, atribut on*, javascript: atau CSS url().
 */
class SafeHtml
{
    private const TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'ul', 'ol', 'li', 'a', 'br', 'span',
        'blockquote', 'hr', 'div', 'sub', 'sup', 'table', 'thead', 'tbody', 'tr', 'td', 'th'];

    /** Elemen yang isinya ikut dibuang (bukan sekadar dibuka bungkusnya). */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'textarea', 'select', 'svg', 'math'];

    private const STYLES = ['font-size', 'font-family', 'font-weight', 'font-style', 'color', 'background-color', 'text-align', 'line-height',
        'margin-bottom', 'text-decoration', 'padding-left', 'margin-left'];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Teks biasa tanpa tag: cukup di-escape, tidak perlu diurai.
        if (! str_contains($html, '<') && ! str_contains($html, '&')) {
            return e($html);
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="safe-html-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('safe-html-root');
        if (! $root) {
            return e(strip_tags($html));
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! in_array($tag, self::TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;

            if ($tag === 'a' && $name === 'href' && self::isSafeUrl($value)) {
                continue;
            }

            if ($name === 'style' && ($style = self::cleanStyle($value)) !== '') {
                $element->setAttribute('style', $style);

                continue;
            }

            $element->removeAttribute($attribute->name);
        }

        if ($tag === 'a' && $element->hasAttribute('href') && preg_match('#^\s*https?://#i', $element->getAttribute('href'))) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        // Buang karakter kontrol/spasi yang dipakai menyamarkan "java\tscript:".
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)));

        if (! preg_match('/^([a-z][a-z0-9+.-]*):/', $normalized, $match)) {
            return true; // relatif, #anchor, /path
        }

        return in_array($match[1], ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function cleanStyle(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if ($value === '' || ! in_array($property, self::STYLES, true)) {
                continue;
            }

            if (preg_match('/url\s*\(|expression|javascript:|[<>"\\\\]|@import/i', $value)) {
                continue;
            }

            $kept[] = $property.': '.$value;
        }

        return implode('; ', $kept);
    }
}
