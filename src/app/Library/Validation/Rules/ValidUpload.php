<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class ValidUpload extends BackpackCustomRule
{
    /**
     * Run the validation rule and return the array of errors.
     */
    public function validateRules(string $attribute, mixed $value): array
    {
        $entry = CrudPanelFacade::getCurrentEntry();

        if (! Arr::has($this->data, $attribute)) {
            $requestAttributeValue = Arr::get($this->data, '_order_'.$attribute);
            $attributeValueForDataArray = $entry ? $requestAttributeValue : null;
            Arr::set($this->data, $attribute, $attributeValueForDataArray);
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
