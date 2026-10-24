<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancialBalanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Permite que todos os usuários possam fazer a requisição
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->isMethod('post')) {
            return [
                'wallets' => 'required|array|min:1',
                'wallets.*' => [\Illuminate\Validation\Rule::exists('wallets', 'id')->where('user_id', auth()->id())],
                'initial_balance' => 'required|numeric',
                'total_expense' => 'nullable|numeric|default:0',
                'total_income' => 'nullable|numeric|default:0',
                'calculated_balance' => 'nullable|numeric|default:0',
                'total_unidentified' => 'nullable|numeric|default:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ];
        }
        return [
            'real_balance' => 'required|numeric',
        ];
    }

    /**
     * Get the custom attributes for the validation errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'wallet_id' => 'Wallet',
            'initial_balance' => 'Initial Balance',
            'total_expense' => 'Total Expense',
            'total_income' => 'Total Income',
            'total_unidentified' => 'Total Unidentified',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
        ];
    }
}
