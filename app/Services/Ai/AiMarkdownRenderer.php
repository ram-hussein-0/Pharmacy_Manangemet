<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class AiMarkdownRenderer
{
    public function render(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $this->decorateDirection($html);
    }

    private function decorateDirection(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Laravel/CommonMark has already produced safe HTML above. Add only
        // fixed dir/rel attributes to known generated tags; never pass model
        // HTML through directly.
        foreach (['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'th'] as $tag) {
            $html = $this->decorateTag($html, $tag);
        }

        foreach (['ol', 'ul', 'blockquote'] as $tag) {
            $html = $this->decorateTag($html, $tag);
        }

        $html = preg_replace_callback(
            '/<pre(\\s[^>]*)?>/iu',
            fn (array $matches): string => '<pre'.($matches[1] ?? '').' dir="ltr">',
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<a(\\s[^>]*)?>/iu',
            function (array $matches): string {
                $attributes = $matches[1] ?? '';

                if (preg_match('/\\srel\\s*=/iu', $attributes) !== 1) {
                    $attributes .= ' rel="nofollow noopener noreferrer"';
                }

                return '<a'.$attributes.'>';
            },
            $html,
        ) ?? $html;

        return $html;
    }

    private function decorateTag(string $html, string $tag): string
    {
        $quoted = preg_quote($tag, '/');
        $pattern = '/<'.$quoted.'(\\s[^>]*)?>(.*?)<\\/'.$quoted.'>/isu';

        return preg_replace_callback(
            $pattern,
            function (array $matches) use ($tag): string {
                $attributes = $matches[1] ?? '';
                $inner = $matches[2] ?? '';

                if (preg_match('/\\sdir\\s*=/iu', $attributes) !== 1) {
                    $text = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $attributes .= ' dir="'.$this->directionFor($text).'"';
                }

                return '<'.$tag.$attributes.'>'.$inner.'</'.$tag.'>';
            },
            $html,
        ) ?? $html;
    }

    private function directionFor(string $text): string
    {
        $arabic = preg_match_all('/\\p{Arabic}/u', $text) ?: 0;
        $latin = preg_match_all('/\\p{Latin}/u', $text) ?: 0;

        if ($arabic === 0 && $latin === 0) {
            return 'auto';
        }

        // Mixed pharmacy text often starts with an English medicine name and
        // continues in Arabic. Treat a meaningful Arabic share as RTL while
        // keeping genuinely English-dominant blocks LTR. LI elements inherit
        // their OL/UL direction so numeric list markers remain on the correct side.
        if ($arabic > 0 && ($latin === 0 || $arabic >= (int) ceil($latin * 0.45))) {
            return 'rtl';
        }

        return 'ltr';
    }
}
