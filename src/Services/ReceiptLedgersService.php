<?php

namespace Rutatiina\PaymentsReceived\Services;

use Rutatiina\PaymentsReceived\Models\PaymentsReceivedItem;
use Rutatiina\PaymentsReceived\Models\PaymentsReceivedItemTax;
use Rutatiina\PaymentsReceived\Models\PaymentsReceivedLedger;

class ReceiptLedgersService
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
            $ledger['receipt_id'] = $data['id'];
            ReceiptLedger::create($ledger);
        }
        unset($ledger);

    }

}
