<?php

namespace Rutatiina\PaymentsReceived\Services;

use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

trait ReceiptApprovalService
{
    public static function run($data)
    {
        if (strtolower($data['status']) == 'draft')
        {
            //cannot update balances for drafts
            return false;
        }

        if ($data['balances_where_updated'] == 1)
        {
            //cannot update balances for task already completed
            return false;
        }

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currentlly inventory update for estimates is disabled

        //Update the account balances
        AccountBalanceUpdateService::doubleEntry($data['ledgers']);

        //Update the contact balances
        ContactBalanceUpdateService::doubleEntry($data['ledgers']);

        return true;
    }

}
