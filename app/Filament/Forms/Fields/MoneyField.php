<?php

namespace App\Filament\Forms\Fields;

use Filament\Forms\Components\TextInput;

class MoneyField
{
    public static function make(
        ?string $relation,
        string $field,
        string $label,
        bool $required = true
    ): TextInput {
        $fieldName = $relation ? "{$relation}.{$field}" : $field;

        return TextInput::make($fieldName)
            ->label($label)
            ->prefix('Rp')
            ->rules(['min:0'])
            ->minValue(0)
            ->required($required)
            ->placeholder('0')
            ->extraInputAttributes([
                'class' => 'money-input',
                'inputmode' => 'numeric',
                'pattern' => '[0-9.]*',
                'data-field-name' => $fieldName
            ])
            ->formatStateUsing(function ($record) use ($relation, $field) {
                if (!$record) return '';
                $value = $relation ? ($record->{$relation}->{$field} ?? 0) : ($record->{$field} ?? 0);
                return $value == 0 ? '' : number_format($value, 0, ',', '.');
            })
            ->dehydrateStateUsing(fn($state) => empty($state) ? 0 : (int) str_replace('.', '', $state))
            ->extraAttributes([
                'x-data' => '{
                    formatMoney(value) {
                        if (!value || value === "0") return "";
                        let numValue = value.toString().replace(/[^0-9]/g, "");
                        return numValue ? parseInt(numValue).toLocaleString("id-ID") : "";
                    },
                    init() {
                        const fieldName = this.$el.getAttribute("data-field-name");

                        // Format nilai saat ini jika ada
                        if (this.$el.value && this.$el.value !== "0") {
                            this.$el.value = this.formatMoney(this.$el.value);
                        }

                        // Listen untuk perubahan dari Livewire
                        window.addEventListener(`livewire:updated:${fieldName}`, (e) => {
                            if (this.$el.value && this.$el.value !== "0" && !this.$el.value.includes(".")) {
                                this.$el.value = this.formatMoney(this.$el.value);
                            }
                        });

                        // Listen untuk manual input
                        this.$el.addEventListener("input", (e) => {
                            let value = e.target.value.replace(/[^0-9]/g, "");
                            e.target.value = this.formatMoney(value);
                        });

                        this.$el.addEventListener("blur", (e) => {
                            if (e.target.value === "") e.target.value = "0";
                        });

                        // Observer untuk perubahan DOM
                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                if (mutation.type === "attributes" && mutation.attributeName === "value") {
                                    if (this.$el.value && this.$el.value !== "0" && !this.$el.value.includes(".")) {
                                        this.$el.value = this.formatMoney(this.$el.value);
                                    }
                                }
                            });
                        });

                        observer.observe(this.$el, {
                            attributes: true,
                            attributeFilter: ["value"]
                        });
                    }
                }'
            ])
            ->validationMessages([
                'numeric' => "$label harus berupa angka.",
                'min' => "$label tidak boleh kurang dari 0.",
                'required' => "$label wajib diisi.",
            ]);
    }
}
