<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Backpack\CRUD\app\Library\Validation\Rules\Support\HasFiles;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

abstract class ValidFileArray extends BackpackCustomRule
{
    public static function field(string|array|ValidationRule|Rule $rules = []): self
    {
        $instance = new static();
        $instance->fieldRules = self::getRulesAsArray($rules);

        if (! in_array('array', $instance->getFieldRules())) {
            $instance->fieldRules[] = 'array';
        }

        return $instance;
    }

    protected function validateFileRules(string $attribute, mixed $items): array
    {
        $cleanAttribute = Str::afterLast($attribute, '.');
        $errors = [];
        
        // we validate each file individually to avoid returning messages like: `field.0` is not a pdf. 
        foreach ($items as $file) {
            if(is_file($file)) {
                $validator = Validator::make(
                    [
                        $cleanAttribute => $file
                    ], 
                    [
                        $cleanAttribute => $this->getFileRules(),
                    ], 
                    $this->validator->customMessages, 
                    $this->validator->customAttributes
                );

                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() ?? [] as $attr => $message) {
                        foreach ($message as $messageText) {
                            $errors[] = $messageText;
                        }
                    }
                }
            }
        }
        return $errors;
    }

    protected function ensureValueIsValid($value)
    {
        if (! is_array($value)) {
            try {
                $value = json_decode($value, true) ?? [];
            } catch(\Exception $e) {
                return false;
            }
        }
        return $value;
    }

    protected function prepareValidatorData(array $data, $attribute): array
    {
        return Arr::has($data, $attribute) ? [$attribute => Arr::get($data, $attribute)] : $data;
    }

   /*  public function validateFieldRules(string $attribute, mixed $value = null, array|null $data = null, array|null $customRules = null): array
    {
        
        $validatorData = $this->prepareValidatorData($data, $attribute);

        $validator = Validator::make($validatorData, [
            $attribute => $rules,
        ], $this->validator->customMessages, $this->validator->customAttributes);

        $errors = [];
        if ($validator->fails()) {
            foreach ($validator->errors()->messages()[$attribute] as $message) {
                $errors[] = $message;
            }
        }
        
        return $errors;
    } */

    /**
     * Run both field and file validations. 
     */
    protected function validateFieldAndFile(string $attribute, mixed $value = null, ?array $data = null, array|null $customRules = null): array
    {
        $fieldErrors = $this->validateFieldRules($attribute, $value, $data, $customRules);
        $fileErrors = $this->validateFileRules($attribute, $value);
        return array_merge($fieldErrors, $fileErrors);
    }
}
