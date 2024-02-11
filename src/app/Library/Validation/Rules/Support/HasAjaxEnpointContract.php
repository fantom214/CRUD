<?php

namespace Backpack\CRUD\app\Library\Validation\Rules\Support;

interface HasAjaxEnpointContract
{
    public function validateFileUploadEndpoint($attribute, $value): array;
}
