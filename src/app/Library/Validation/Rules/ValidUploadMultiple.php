<?php

namespace Backpack\CRUD\app\Library\Validation\Rules;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade;

class ValidUploadMultiple extends ValidFileArray
{
    public function validateRules(string $attribute, mixed $value): array
    {
        $entry = CrudPanelFacade::getCurrentEntry() !== false ? CrudPanelFacade::getCurrentEntry() : null;

        // `upload_multiple` sends [[0 => null]] when user doesn't upload anything
        // assume that nothing changed on field so nothing is sent on the request.
        if (count($value) === 1 && empty($value[0])) {
            if ($entry) {
                unset($this->data[$attribute]);
            } else {
                $this->data[$attribute] = [];
            }
            $value = [];
        }

        $previousValues = $entry?->{$attribute} ?? [];
        if (is_string($previousValues)) {
            $previousValues = json_decode($previousValues, true) ?? [];
        }

        $value = array_merge($previousValues, $value);

        if ($entry) {
            $filesDeleted = CrudPanelFacade::getRequest()->input('clear_'.$attribute) ?? [];

            $data = $this->data;
            $data[$attribute] = array_diff($value, $filesDeleted);

            return $this->validateFieldAndFile($attribute, $value, $data);
        }

        return $this->validateFieldAndFile($attribute, $value);
    }
}
