<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
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
            'name' => 'required|string',
            'description' => 'nullable|string',
            'rolesCanUsedAccountType' => 'nullable|array',
            'rolesCanUsedAccountType.*' => 'array',
            'rolesCanUsedAccountType.*.key' => 'required|string',
            'rolesCanUsedAccountType.*.statusCode' => 'required|integer|' .  Rule::in([0, 440]),
        ];
    }

    public function messages()
    {
        return [
            // 'email.required' => 'Email is required!',
        ];
    }

    public function attributes()
    {
        return [
            // 'name' => 'tên',
        ];
    }
}
