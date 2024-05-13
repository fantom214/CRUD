<?php

namespace Backpack\CRUD\Tests\config\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUpload;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUploadMultiple;

class UploaderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'upload' => ValidUpload::field('required')->file(['mimes:pdf', 'max:1024']),
            'upload_multiple' => ValidUploadMultiple::field(['required', 'min:2'])->file(['mimes:pdf', 'max:1024']),
        ];
    }
}
