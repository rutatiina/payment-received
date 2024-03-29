<?php

namespace Rutatiina\PaymentReceived\Services;

use Illuminate\Support\Facades\Validator;
use Rutatiina\Contact\Models\Contact;
use Rutatiina\PaymentReceived\Models\PaymentReceivedSetting;

class PaymentReceivedValidateService
{
    public static $errors = [];

    public static function run($requestInstance)
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();

        $settings = PaymentReceivedSetting::has('financial_account_to_credit')
            ->with(['financial_account_to_debit', 'financial_account_to_credit'])
            ->firstOrFail();
        //Log::info($this->settings);

        $creditFinancialAccountCode = $settings->financial_account_to_credit->code;

        $requireCreditFinancialAccountCode = false;

        //if no invoice is tagged create the items parameter
        if (!$requestInstance->items)
        {
            $requestInstance->request->add(['exchange_rate' => 1]);
            $requestInstance->request->add([
                'items' => [
                    [
                        //'invoice' => 0,        
                        'txn_contact_id' => $requestInstance->contact_id,
                        'txn_number' => 0,
                        'max_receipt_amount' => $requestInstance->total,
                        //'txn_exchange_rate' => $txn->exchange_rate,
            
                        'invoice_id' => 0,
                        'contact_id' => $requestInstance->contact_id,
                        'description' => $requestInstance->description,
                        'amount' => $requestInstance->total,
                        'taxable_amount' => $requestInstance->total,
                        'taxes' => [],
                    ]
                ]
            ]);

            $creditFinancialAccountCode = $requestInstance->input('credit_financial_account_code');
            $requireCreditFinancialAccountCode = true;
        }


        // >> data validation >>------------------------------------------------------------

        //validate the data
        $customMessages = [
            'debit_financial_account_code.required' => "The deposit to field is required.",
            'items.*.taxes.*.code.required' => "Tax code is required.",
            'items.*.taxes.*.total.required' => "Tax total is required.",
            //'items.*.taxes.*.exclusive.required' => "Tax exclusive amount is required.",
            'credit_financial_account_code.required' => 'The revenue account is required',
            'credit_financial_account_code.numeric' => 'Invalid value for revenue account.'
        ];

        $rules = [
            'contact_id' => 'nullable|numeric',
            'date' => 'required|date',
            'payment_mode' => 'required',
            'debit_financial_account_code' => 'required',
            'base_currency' => 'required',
            'due_date' => 'date|nullable',
            'salesperson_contact_id' => 'numeric|nullable',
            'contact_notes' => 'string|nullable',
            'total' => 'required|gt:0|numeric',

            'items' => 'required|array',
            'items.*.description' => 'required',
            'items.*.amount' => 'required|numeric',
            'items.*.taxable_amount' => 'numeric',

            'items.*.taxes' => 'array|nullable',
            'items.*.taxes.*.code' => 'required',
            'items.*.taxes.*.total' => 'required|numeric',
            //'items.*.taxes.*.exclusive' => 'required|numeric',
        ];

        if ($requireCreditFinancialAccountCode)
        {
            $rules['credit_financial_account_code'] = 'required|numeric';
        }

        $validator = Validator::make($requestInstance->all(), $rules, $customMessages);

        if ($validator->fails())
        {
            self::$errors = $validator->errors()->all();
            return false;
        }

        // << data validation <<------------------------------------------------------------


        $contact = Contact::find($requestInstance->contact_id);


        $data['id'] = $requestInstance->input('id', null); //for updating the id will always be posted
        $data['user_id'] = $user->id;
        $data['tenant_id'] = $user->tenant->id;
        $data['created_by'] = $user->name;
        $data['app'] = 'web';
        $data['document_name'] = $settings->document_name;
        $data['number'] = $requestInstance->input('number');
        $data['date'] = $requestInstance->input('date');
        $data['debit_financial_account_code'] = $requestInstance->input('debit_financial_account_code');;
        $data['credit_financial_account_code'] = $creditFinancialAccountCode;
        $data['contact_id'] = $requestInstance->contact_id;
        $data['contact_name'] = optional($contact)->name;
        $data['contact_address'] = trim(optional($contact)->shipping_address_street1 . ' ' . optional($contact)->shipping_address_street2);
        $data['reference'] = $requestInstance->input('reference', null);
        $data['base_currency'] =  $requestInstance->input('base_currency');
        $data['quote_currency'] =  $requestInstance->input('quote_currency', $data['base_currency']);
        $data['exchange_rate'] = $requestInstance->input('exchange_rate', 1);
        $data['branch_id'] = $requestInstance->input('branch_id', null);
        $data['store_id'] = $requestInstance->input('store_id', null);
        $data['terms_and_conditions'] = $requestInstance->input('terms_and_conditions', null);
        $data['contact_notes'] = $requestInstance->input('contact_notes', null);
        $data['status'] = $requestInstance->input('status', null);
        $data['balances_where_updated'] = $requestInstance->input('balances_where_updated', null);
        $data['payment_mode'] = $requestInstance->input('payment_mode', null);


        //set the transaction total to zero
        $txnTotal = 0;
        $taxableAmount = 0;

        //Formulate the DB ready items array
        $data['items'] = [];
        foreach ($requestInstance->items as $key => $item)
        {
            $itemTaxes = $requestInstance->input('items.'.$key.'.taxes', []);

            $item['taxable_amount'] = $item['amount']; //todo >> this is to be updated in future when taxes are propelly applied to payment_receiveds

            $txnTotal           += $item['amount'];
            $taxableAmount      += ($item['taxable_amount']);
            $itemTaxableAmount   = $item['taxable_amount']; //calculate the item taxable amount

            foreach ($itemTaxes as $itemTax)
            {
                $txnTotal           += $itemTax['exclusive'];
                $taxableAmount      -= $itemTax['inclusive'];
                $itemTaxableAmount  -= $itemTax['inclusive']; //calculate the item taxable amount more by removing the inclusive amount
            }

            $data['items'][] = [
                'tenant_id' => $data['tenant_id'],
                'created_by' => $data['created_by'],
                'contact_id' => $item['contact_id'],
                'invoice_id' => $item['invoice_id'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'taxable_amount' => $itemTaxableAmount,
                'taxes' => $itemTaxes,
            ];
        }

        $data['taxable_amount'] = $taxableAmount;
        $data['total'] = $txnTotal;

        //print_r($data); exit;

        //Return the array of txns
        //print_r($data); exit;

        return $data;

    }

}
