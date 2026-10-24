<?php

namespace App\Http\Requests;

use App\Enums\FinancialMovementType;
use App\Models\FinancialMovement;
use App\Services\ReconciliationService;
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
            'confirm_reconciled' => 'nullable|boolean',
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

    /**
     * Changing a confirmed month needs an explicit confirmation.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || $this->boolean('confirm_reconciled')) {
                return;
            }
            $balance = self::confirmedBalanceAffected($this->positions());
            if ($balance) {
                $validator->errors()->add('confirm_reconciled', __('This changes the confirmed balance of :wallet in :month. Confirm to continue.', [
                    'wallet' => $balance->wallet?->name,
                    'month' => ucfirst(\Carbon\Carbon::parse($balance->start_date)->translatedFormat('F Y')),
                ]));
            }
        });
    }

    /**
     * Wallet/date pairs the save touches: the new ones and, when editing, the previous ones.
     */
    private function positions(): array
    {
        $positions = [['wallet_id' => $this->input('wallet_id'), 'date' => $this->input('date')]];
        if ($this->input('type') === FinancialMovementType::TRANSFER) {
            $positions[] = ['wallet_id' => $this->input('transfer_to_wallet_id'), 'date' => $this->input('date')];
        }
        $existing = $this->route('financial_movement');
        if ($existing instanceof FinancialMovement) {
            foreach ([$existing, $existing->destinationMovement] as $movement) {
                if ($movement) {
                    $positions[] = ['wallet_id' => $movement->wallet_id, 'date' => $movement->date];
                }
            }
        }
        return $positions;
    }

    public static function confirmedBalanceAffected(iterable $positions)
    {
        foreach ($positions as $position) {
            $balance = ReconciliationService::affectedBalance((int) $position['wallet_id'], (string) $position['date']);
            if ($balance) {
                return $balance;
            }
        }
        return null;
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
