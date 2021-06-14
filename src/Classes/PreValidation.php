<?php

namespace Rutatiina\PaymentReceived\Classes;

use Illuminate\Support\Facades\Auth;
use Rutatiina\Item\Models\Item;
use Rutatiina\FinancialAccounting\Models\InventoryPurchase;

class PreValidation
{
    public function run($data)
    {
        $data['items'] = (empty($data['items'])) ? [] : $data['items'];

        $data['forex_gain'] = 0;
        $data['forex_loss'] = 0;

        //Add the missing parameters to the items array
        foreach ($data['items'] as $txn_id => &$item)
        {
            $maxReceivable = $item['rate'] + $item['amount_withheld'];

            //If the user has entered more amount to receipt (Only applies to receipts)
            if ($maxReceivable > $item['max_receipt_amount'])
            {
                return [
                    'status' => false,
                    'messages' => ['Amount received is more than total Invoice balance.']
                ];
            }

            if (empty($item['rate']))
            {
                unset($data['items'][$txn_id]);
                continue;
            }

            //note: invoice id is already sent by the form so no need to set it
            //$item['contact_id']     = $item['txn_contact_id']; //removed this so that receipts work properly in vue
            $item['name'] = 'Receipt on invoice no. ' . $item['txn_number'];
            $item['description'] = null;
            $item['quantity'] = 1;
            //$item['total']          = $item['rate']; //removed this so that receipts work properly in vue

            //Calculate the forex gain or loss
            if ($data['exchange_rate'] > $item['txn_exchange_rate'])
            {
                //This is a gain (because you get more)
                $data['forex_gain'] += $item['rate'] * ($data['exchange_rate'] - $item['txn_exchange_rate']);
            }

            if ($data['exchange_rate'] < $item['txn_exchange_rate'])
            { //This is a loss (because you get less)
                $data['forex_gain'] += $item['rate'] * ($data['txn_exchange_rate'] - $item['exchange_rate']);
            }

            /*
            unset($item['txn_number']);
            unset($item['txn_exchange_rate']);
            unset($item['txn_contact_id']);
            unset($item['max_receipt_amount']);
            */

            unset($item['txn']); #vue
            unset($item['paidInFull']); #vue

        }
        unset($item);

        //Auth the total received
        //print_r($data); exit;

        $total = 0;
        foreach ($data['items'] as $item)
        {
            $total += $item['rate'];
        }

        if ($total != $data['total'])
        {
            return [
                'status' => false,
                'messages' => ['Amount received is less than total Invoice payments.']
            ];
        }

        $data['total'] = $total;
        $data['taxable_amount'] = $total;

        return [
            'status' => true,
            'data' => $data
        ];
    }
}
