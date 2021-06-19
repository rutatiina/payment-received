<?php

namespace Rutatiina\PaymentReceived\Services;

use Rutatiina\PaymentReceived\Models\PaymentReceivedItem;
use Rutatiina\PaymentReceived\Models\PaymentReceivedItemTax;
use Rutatiina\PaymentReceived\Models\PaymentReceivedLedger;

class PaymentReceivedLedgerService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['payment_received_id'] = $data['id'];
            PaymentReceivedLedger::create($ledger);
        }
        unset($ledger);

    }

}
