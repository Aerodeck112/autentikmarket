<?php

namespace App\Services\Notifications;

/**
 * Înlocuiește placeholder-ele {{cheie}} într-un template.
 * Template-urile NU sunt Blade și nu pot executa cod. Valorile sunt escapate la randarea HTML.
 */
class TemplateRenderer
{
    public function renderText(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($data) {
            $value = data_get($data, $m[1]);

            return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
        }, $template) ?? $template;
    }

    /**
     * Text → HTML sigur: substituție, escapare, paragrafe și linkuri automate.
     */
    public function renderHtml(string $template, array $data): string
    {
        $text = $this->renderText($template, $data);
        $escaped = e($text);
        $linked = preg_replace(
            '~(https?://[^\s<]+)~u',
            '<a href="$1" style="color:#B4532A">$1</a>',
            $escaped
        );
        $paragraphs = preg_split("/\R{2,}/", trim((string) $linked)) ?: [];

        return implode("\n", array_map(
            fn ($p) => '<p style="margin:0 0 16px">'.nl2br($p, false).'</p>',
            $paragraphs
        ));
    }

    /** Lista placeholder-elor folosite (afișată în editorul de template-uri). */
    public function placeholders(string $template): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $template, $m);

        return array_values(array_unique($m[1]));
    }
}
