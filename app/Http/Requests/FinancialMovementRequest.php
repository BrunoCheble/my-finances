<?php

namespace App\Http\Requests;

use App\Enums\FinancialMovementType;
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
        $rules = [
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'wallet_id' => ['required', Rule::exists('wallets', 'id')->where('user_id', auth()->id())],
            'category_id' => ['nullable', Rule::exists('financial_categories', 'id')->where('user_id', auth()->id())],
            'amount' => 'required|numeric',
            'type' => [
                'required',
                'string',
                Rule::in([
                    FinancialMovementType::EXPENSE,
                    FinancialMovementType::INCOME,
                    FinancialMovementType::REFUND,
                    FinancialMovementType::DISCOUNT,
                    FinancialMovementType::TRANSFER,
                    FinancialMovementType::LOAN,
                    FinancialMovementType::ADJUSTMENT,
                ]),
            ],
            'include_alert' => 'nullable|numeric',
        ];

        if ($this->input('type') === FinancialMovementType::LOAN) {
            $rules['loan_person'] = ['required', 'string', 'max:255'];
            $rules['loan_direction'] = ['required', Rule::in(['lend', 'receive'])];
        }

        if ($this->input('type') === FinancialMovementType::TRANSFER) {
            $rules['transfer_to_wallet_id'] = [
                'required',
                'different:wallet_id',
                Rule::exists('wallets', 'id')->where('user_id', auth()->id()),
            ];
        }
        return $rules;
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
