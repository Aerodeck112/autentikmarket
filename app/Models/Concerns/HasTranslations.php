<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\App;

/**
 * Coloane bilingve: `title_ro` / `title_hu`. `$model->t('title')` returnează varianta
 * în limba curentă, cu fallback pe română dacă traducerea lipsește.
 */
trait HasTranslations
{
    public function t(string $field, ?string $locale = null): ?string
    {
        $locale ??= App::getLocale();
        $value = $this->getAttribute("{$field}_{$locale}");

        if ($value === null || $value === '') {
            $value = $this->getAttribute("{$field}_ro");
        }

        return $value;
    }
}
