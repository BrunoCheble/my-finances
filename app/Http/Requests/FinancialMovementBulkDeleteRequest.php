<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancialMovementBulkDeleteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'ids' => 'required|array',
        ];
        return $rules;
    }

    public function messages()
    {
        return [
            'ids.required' => 'At least one Financial Movement must be selected for deletion.',
            'ids.array' => 'Invalid data format for Financial Movements.',
        ];
    }
}
