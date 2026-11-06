<?php

namespace App\Services\Documents;

use App\Exceptions\BusinessRuleException;
use App\Models\Annex;
use App\Models\Contract;
use App\Support\Settings;

/**
 * Numerotarea contractelor și anexelor, configurabilă din CRM → Setări ca să continue registrul existent
 * (ex. „125/2026” sau „AM-C-2026-0125”).
 *
 * Format: {prefix} = prefixul din setări, {an} = anul curent, {nr} = numărul, {nr:4} = numărul pe 4 cifre (0125).
 * „Următorul număr” se poate seta de mână (de ex. după ultimul contract pe hârtie); după fiecare document
 * generat sau încărcat crește automat. Opțional, numărătoarea repornește de la 1 în fiecare an.
 */
class DocumentNumbering
{
    public const DEFAULT_FORMATS = ['contract' => '{prefix}-C-{an}-{nr:4}', 'annex' => '{prefix}-A-{an}-{nr:4}'];

    public static function formatFor(string $kind): string
    {
        $format = trim((string) Settings::get("documents.{$kind}_format", ''));

        return $format !== '' && str_contains($format, '{nr') ? $format : self::DEFAULT_FORMATS[$kind];
    }

    public function format(string $kind, int $nr, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return preg_replace_callback('/\{(prefix|an|nr)(?::(\d))?\}/', fn ($m) => match ($m[1]) {
            'prefix' => (string) Settings::get('contracts.number_prefix', 'AM'),
            'an' => (string) $year,
            'nr' => isset($m[2]) && $m[2] !== '' ? str_pad((string) $nr, (int) $m[2], '0', STR_PAD_LEFT) : (string) $nr,
        }, self::formatFor($kind));
    }

    /** Numărul din „AM-C-2026-0125” / „125/2026”, dacă respectă formatul (null altfel). */
    public function parse(string $kind, string $number, ?int $year = null): ?int
    {
        $regex = preg_quote(self::formatFor($kind), '/');
        $regex = str_replace(preg_quote('{prefix}', '/'), preg_quote((string) Settings::get('contracts.number_prefix', 'AM'), '/'), $regex);
        $regex = str_replace(preg_quote('{an}', '/'), $year ? (string) $year : '\d{4}', $regex);
        $regex = preg_replace('/\\\\\{nr(?:\\\\:\d)?\\\\\}/', '(?<nr>\d+)', $regex);

        return preg_match('/^'.$regex.'$/i', trim($number), $m) ? (int) $m['nr'] : null;
    }

    /** Următorul număr care va fi folosit. */
    public function peek(string $kind): int
    {
        $year = (int) now()->year;
        $stored = (int) Settings::get("documents.{$kind}_next", 0);
        $storedYear = (int) Settings::get("documents.{$kind}_year", 0);
        $resetYearly = Settings::get('documents.reset_yearly', '0') === '1';

        if ($stored > 0 && ! ($resetYearly && $storedYear && $storedYear !== $year)) {
            return $stored;
        }

        // Automat: după cel mai mare număr existent în format (din anul curent, dacă numărătoarea e anuală).
        $max = 0;
        $model = $kind === 'contract' ? Contract::class : Annex::class;
        foreach ($model::query()->pluck('number') as $number) {
            $max = max($max, (int) $this->parse($kind, $number, $resetYearly ? $year : null));
        }

        return $max + 1;
    }

    /**
     * Rezervă numărul și apelează $create($number); la un număr deja folosit încearcă următorul.
     *
     * @template T
     *
     * @param  callable(string): T  $create
     * @return T
     */
    public function next(string $kind, callable $create)
    {
        $nr = $this->peek($kind);
        $model = $kind === 'contract' ? Contract::class : Annex::class;
        for ($attempt = 0; $attempt < 50; $attempt++, $nr++) {
            $number = $this->format($kind, $nr);
            if ($model::where('number', $number)->exists()) {
                continue;
            }
            $result = $create($number);
            $this->advance($kind, $nr);

            return $result;
        }
        throw new BusinessRuleException(__('Nu s-a putut genera un număr unic de document.'), 'number_conflict');
    }

    /** Un document existent (încărcat) cu număr în format mută numărătoarea după el. */
    public function registerExisting(string $kind, string $number): void
    {
        $nr = $this->parse($kind, $number);
        if ($nr !== null && $nr >= $this->peek($kind)) {
            $this->advance($kind, $nr);
        }
    }

    protected function advance(string $kind, int $used): void
    {
        Settings::setMany(["documents.{$kind}_next" => (string) ($used + 1), "documents.{$kind}_year" => (string) now()->year]);
    }
}
