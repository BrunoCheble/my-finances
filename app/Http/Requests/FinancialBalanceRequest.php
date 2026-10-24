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
