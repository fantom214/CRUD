<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @method static static itemRules()
 */
abstract class BackpackCustomRule implements ValidationRule, DataAwareRule, ValidatorAwareRule
{
    use \Backpack\CRUD\app\Library\Validation\Rules\Support\HasFiles;

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

    // from our POV, value is always valid, each validator may have their own
    // validation needs. This is the first step in the validation process.
    protected function ensureValueIsValid($value)
    {
        return $value;
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

    protected function validateFieldAndFile(string $attribute, mixed $value = null, array|null $data = null, array|null $customRules = null): array
    {
        $fieldErrors = $this->validateFieldRules($attribute, $value, $data, $customRules);
        $fileErrors = $this->validateFileRules($attribute, $value);

        return array_merge($fieldErrors, $fileErrors);
    }

    /**
     * Implementation.
     */
    public function validateFieldRules(string $attribute, mixed $data = null, array|null $customRules = null): array
    {
        $data = $data ?? $this->data;
        $validationRuleAttribute = $this->getValidationAttributeString($attribute);
        $data = $this->prepareValidatorData($data, $attribute);

        return $this->validateAndGetErrors($validationRuleAttribute, $data, $customRules ?? $this->getFieldRules());
    }

    protected function prepareValidatorData(array $data, string $attribute): array
    {
        return $data;
    }

    protected function validateFileRules(string $attribute, mixed $value): array
    {
        return $this->validateAndGetErrors($attribute, $value, $this->getFileRules());
    }

    public function validateRules(string $attribute, mixed $value): array
    {
        return $this->validateFieldAndFile($attribute, $value);
    }
}
