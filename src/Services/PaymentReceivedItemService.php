<?php

namespace Rutatiina\PaymentReceived\Services;

use Rutatiina\Invoice\Models\Invoice;
use Rutatiina\PaymentReceived\Models\PaymentReceivedItem;
use Rutatiina\PaymentReceived\Models\PaymentReceivedItemTax;

class PaymentReceivedItemService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        //print_r($data['items']); exit;

        //Save the items >> $data['items']
        foreach ($data['items'] as &$item)
        {
            $item['payment_received_id'] = $data['id'];

            $itemTaxes = (is_array($item['taxes'])) ? $item['taxes'] : [] ;
            unset($item['taxes']);

            $itemModel = PaymentReceivedItem::create($item);

            foreach ($itemTaxes as $tax)
            {
                //save the taxes attached to the item
                $itemTax = new PaymentReceivedItemTax;
                $itemTax->tenant_id = $item['tenant_id'];
                $itemTax->payment_received_id = $item['payment_received_id'];
                $itemTax->payment_received_item_id = $itemModel->id;
                $itemTax->tax_code = $tax['code'];
                $itemTax->amount = $tax['total'];
                $itemTax->taxable_amount = $tax['total']; //todo >> this is to be updated in future when taxes are propelly applied to payment_receiveds
                $itemTax->inclusive = $tax['inclusive'];
                $itemTax->exclusive = $tax['exclusive'];
                $itemTax->save();
            }
            unset($tax);

            //update the invoice total_paid
            if (isset($item['invoice_id']) && is_numeric($item['invoice_id']) )
            {
                Invoice::where('id', $item['invoice_id'])->increment('total_paid', $item['amount']);
            }
        }
        unset($item);

    }

}
