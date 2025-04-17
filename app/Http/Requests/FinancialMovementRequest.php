<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialMovementRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'wallet_id' => 'required|exists:wallets,id',
            'category_id' => 'nullable|exists:financial_categories,id',
            'amount' => 'required|numeric',
            'type' => [
                'required',
                'string',
                Rule::in(['expense', 'income', 'refund', 'discount', 'transfer']),
            ]
        ];
    }

    public function messages()
    {
        return [
            'description.required' => 'Description is required.',
            'date.required' => 'Date is required.',
            'wallet_id.required' => 'Wallet is required.',
            'wallet_id.exists' => 'The specified wallet does not exist.',
            'category_id.exists' => 'The specified category does not exist.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be numeric.',
            'type.required' => 'Movement type is required.',
            'type.in' => 'Movement type must be one of the following: expense, income, refund, discount, transfer.',
        ];
    }
}
