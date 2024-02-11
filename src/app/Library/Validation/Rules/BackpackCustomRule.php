<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Backpack\CRUD\app\Library\Validation\Rules\Support\ValidateArrayContract;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

abstract class BackpackCustomRule implements ValidationRule, DataAwareRule, ValidatorAwareRule
{
    use Support\HasFiles;

    /**
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    protected array $data;

    public array $fieldRules = [];

    public bool $implicit = true;

    public static function field(string|array|ValidationRule|Rule $rules = []): self
    {
        $instance = new static();
        $instance->fieldRules = self::getRulesAsArray($rules);

        if($instance->validatesArrays()) {
            if (! in_array('array', $instance->getFieldRules())) {
                $instance->fieldRules[] = 'array';
            }
        }
        return $instance;


        $instance = new static();
        $instance->fieldRules = self::getRulesAsArray($rules);

        if (! in_array('array', $instance->getFieldRules())) {
            $instance->fieldRules[] = 'array';
        }

        return $instance;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = $this->ensureValueIsValid($value);
      
        if ($value === false) {
            $fail('Invalid value for the attribute.')->translate();

            return;
        }

        $errors = $this->validateOnSubmit($attribute, $value);
        foreach ($errors as $error) {
            $fail($error)->translate();
        }
    }

    /**
     * Set the performing validator.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return $this
     */
    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getFieldRules(): array
    {
        return tap($this->fieldRules, function ($rule) {
            if (is_a($rule, BackpackCustomRule::class, true)) {
                $rule = $rule->getFieldRules();
            }

            return $rule;
        });
    }

    protected static function getRulesAsArray($rules)
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (! is_array($rules)) {
            $rules = [$rules];
        }

        return $rules;
    }

    protected function ensureValueIsValid($value)
    {
        if($this->validatesArrays() && ! is_array($value)) {
            try {
                $value = json_decode($value, true) ?? [];
            } catch(\Exception $e) {
                return false;
            }
        }

        return $value;
    }

    private function validatesArrays(): bool
    {
        return is_a($this, ValidateArrayContract::class);
    }

    private function validateAndGetErrors(string $attribute, mixed $value, array $rules): array
    {
        $validator = Validator::make($value, [
            $attribute => $rules,
        ], $this->validator->customMessages, $this->validator->customAttributes);
        return $validator->errors()->messages()[$attribute] ?? [];
    }

    protected function getValidationAttributeString(string $attribute)
    {
        return Str::substrCount($attribute, '.') > 1 ?
                Str::before($attribute, '.').'.*.'.Str::afterLast($attribute, '.') :
                $attribute;
    }

    protected function validateOnSubmit(string $attribute, mixed $value): array
    {
        return $this->validateRules($attribute, $value);
    }

    protected function validateFieldAndFile(string $attribute, null|array $data = null, array|null $customRules = null): array
    {
        $fieldErrors = $this->validateFieldRules($attribute, $data, $customRules);
        $fileErrors = $this->validateFileRules($attribute, $data);

        return array_merge($fieldErrors, $fileErrors);
    }

    /**
     * Implementation.
     */
    public function validateFieldRules(string $attribute, null|array|UploadedFile $data = null, array|null $customRules = null): array
    {
        $data = $data ?? $this->data;
        $validationRuleAttribute = $this->getValidationAttributeString($attribute);
       
        $data = $this->prepareValidatorData($data, $attribute);
       
        return $this->validateAndGetErrors($validationRuleAttribute, $data, $customRules ?? $this->getFieldRules());
    }

    protected function prepareValidatorData(array|UploadedFile $data, string $attribute): array
    {

        if($this->validatesArrays() && is_array($data)) {
            return Arr::has($data, $attribute) ? $data : [$attribute => Arr::get($data, $attribute)];
        }
        return [$attribute => $data];
    }

    protected function validateFileRules(string $attribute, mixed $data): array
    {
        $data = $data ?? $this->data;
        $items = is_array($data) && array_key_exists($attribute, $data) ? $data[$attribute] : $data;
        $items = is_array($items) ? $items : [$items];
        $errors = [];
        // we validate each file individually to avoid returning messages like: `field.0` is not a pdf.
        foreach ($items as $sentFiles) {
            if(!is_array($sentFiles)) {
                try {
                    if (is_file($sentFiles)) {
                        $errors[] = $this->validateAndGetErrors($attribute, [$attribute => $sentFiles], $this->getFileRules());
                    }
                    continue;
                }catch(\Exception) {
                    $errors[] = 'Unknown datatype, aborting upload process.';
                    break;
                }
            }
    
            if (is_multidimensional_array($sentFiles)) {
                foreach ($sentFiles as $key => $value) {
                    foreach ($value[$attribute] as $file) {
                        if (is_file($file)) {
                            $errors[] = $this->validateAndGetErrors($attribute, [$attribute => $file], $this->getFileRules());
                        }
                    }
                }
                continue;
            }

            foreach ($sentFiles as $key => $value) {
                if (is_file($value)) {
                    $errors[] = $this->validateAndGetErrors($attribute, [$attribute => $value], $this->getFileRules());
                }
            }            
        }
       
        return array_unique(array_merge(...$errors));
    }

    public function validateRules(string $attribute, mixed $value): array
    {
        return $this->validateFieldAndFile($attribute, $value);
    }
}
