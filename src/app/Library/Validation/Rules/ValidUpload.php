<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Backpack\CRUD\app\Library\Validation\Rules\Support\HasFiles;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidUpload extends BackpackCustomRule
{
    use HasFiles;

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validateRules(string $attribute, mixed $value): array
    {
        $entry = CrudPanelFacade::getCurrentEntry();

        if (! array_key_exists($attribute, $this->data) && $entry) {
            return [];
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
