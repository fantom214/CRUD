<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ValidUpload extends BackpackCustomRule
{
    /**
     * Run the validation rule and return the array of errors.
     */
    public function validateRules(string $attribute, mixed $value): array
    {
        $entry = CrudPanelFacade::getCurrentEntry();

        if (! array_key_exists($attribute, $this->data) && $entry) {
            if (str_contains($attribute, '.') && get_class($entry) === get_class(CrudPanelFacade::getModel())) {
                $previousValue = Arr::get($this->data, '_order_'.Str::before($attribute, '.'));
                $previousValue = Arr::get($previousValue, Str::after($attribute, '.'));
            } else {
                $previousValue = Arr::get($entry, $attribute);
            }

            if ($previousValue && empty($value)) {
                return [];
            }
            Arr::set($this->data, $attribute, $previousValue ?? $value);
        }

        $fieldErrors = $this->validateFieldRules($attribute, $value);

        if (! empty($value) && ! empty($this->getFileRules())) {
            $fileErrors = $this->validateFileRules($attribute, $value);
        }

        return array_merge($fieldErrors, $fileErrors ?? []);
    }

    public static function field(string|array|ValidationRule|Rule $rules = []): self
    {
        return parent::field($rules);
    }
}
