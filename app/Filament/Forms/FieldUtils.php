<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\TextInput;

class FieldUtils
{
    public static function applyNumericSanitizer(TextInput $field, string $fieldName, int $maxDigits = 20): TextInput
    {
        return $field
            ->reactive()
            ->extraAttributes([
                'inputmode' => 'numeric',
                'pattern' => '[0-9]*',
            ])
            ->afterStateUpdated(function ($set, $state) use ($fieldName, $maxDigits) {
                $cleaned = preg_replace('/\D/', '', $state);
                $set($fieldName, mb_substr($cleaned, 0, $maxDigits));
            })
            ->hint(function ($state) use ($maxDigits) {
                return strlen(preg_replace('/\D/', '', $state)) . '/' . $maxDigits;
            })
            ->hintColor('gray');
    }
}
