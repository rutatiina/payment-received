<?php

namespace Rutatiina\PaymentsReceived\Classes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Rutatiina\Invoice\Models\Annex as InvoiceAnnex;
use Rutatiina\Invoice\Models\Invoice;
use Rutatiina\PaymentsReceived\Models\PaymentsReceived;
use Rutatiina\PaymentsReceived\Models\PaymentsReceivedItem;
use Rutatiina\PaymentsReceived\Models\PaymentsReceivedItemTax;
use Rutatiina\PaymentsReceived\Models\PaymentsReceivedLedger;
use Rutatiina\PaymentsReceived\Traits\Init as TxnTraitsInit;
use Rutatiina\PaymentsReceived\Traits\TxnItemsContactsIdsLedgers as TxnTraitsTxnItemsContactsIdsLedgers;
use Rutatiina\PaymentsReceived\Traits\TxnItemsJournalLedgers as TxnTraitsTxnItemsJournalLedgers;
use Rutatiina\PaymentsReceived\Traits\TxnTypeBasedSpecifics as TxnTraitsTxnTypeBasedSpecifics;
use Rutatiina\PaymentsReceived\Traits\Validate as TxnTraitsValidate;
use Rutatiina\PaymentsReceived\Traits\AccountBalanceUpdate as TxnTraitsAccountBalanceUpdate;
use Rutatiina\PaymentsReceived\Traits\ContactBalanceUpdate as TxnTraitsContactBalanceUpdate;
use Rutatiina\PaymentsReceived\Traits\Approve as TxnTraitsApprove;

class Store
{
    use TxnTraitsInit;
    use TxnTraitsTxnItemsContactsIdsLedgers;
    use TxnTraitsTxnItemsJournalLedgers;
    use TxnTraitsTxnTypeBasedSpecifics;
    use TxnTraitsValidate;
    use TxnTraitsAccountBalanceUpdate;
    use TxnTraitsContactBalanceUpdate;
    use TxnTraitsApprove;

    public function __construct()
    {
    }

    public function run()
    {
        //print_r($this->txnInsertData); exit;

        $verifyWebData = $this->validate();
        if ($verifyWebData === false) return false;

        //check if inventory is affected and if its available
        //for the mean time inventory functions are disabled for estimates
        //$inventoryAvailability = $this->inventoryAvailability();
        //if ($inventoryAvailability === false) return false;

        //Log::info($this->txn);
        //var_dump($this->txn); exit;
        //print_r($this->txn); exit;
        //echo json_encode($this->txn); exit;

        //print_r($this->txn); exit; //$this->txn, $this->txn['items'], $this->txn[number], $this->txn[ledgers], $this->txn[recurring]

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            //print_r($this->txn); exit;
            $Txn = new Receipt;
            $Txn->tenant_id = $this->txn['tenant_id'];
            //$Txn->app_id = $this->txn['app_id'];
            $Txn->created_by = $this->txn['created_by'];
            $Txn->document_name = $this->txn['document_name'];
            $Txn->number_prefix = $this->txn['number_prefix'];
            $Txn->number = $this->txn['number'];
            $Txn->number_length = $this->txn['number_length'];
            $Txn->number_postfix = $this->txn['number_postfix'];
            $Txn->date = $this->txn['date'];
            $Txn->debit_financial_account_code = $this->txn['debit_financial_account_code'];
            $Txn->credit_financial_account_code = $this->txn['credit_financial_account_code'];
            $Txn->debit_contact_id = $this->txn['debit_contact_id'];
            $Txn->credit_contact_id = $this->txn['credit_contact_id'];
            $Txn->contact_name = $this->txn['contact_name'];
            $Txn->contact_address = $this->txn['contact_address'];
            $Txn->reference = $this->txn['reference'];
            $Txn->base_currency = $this->txn['base_currency'];
            $Txn->quote_currency = $this->txn['quote_currency'];
            $Txn->exchange_rate = $this->txn['exchange_rate'];
            $Txn->taxable_amount = $this->txn['taxable_amount'];
            $Txn->total = $this->txn['total'];
            $Txn->balance = $this->txn['balance'];
            $Txn->branch_id = $this->txn['branch_id'];
            $Txn->store_id = $this->txn['store_id'];
            $Txn->due_date = $this->txn['due_date'];
            $Txn->expiry_date = $this->txn['expiry_date'];
            $Txn->terms_and_conditions = $this->txn['terms_and_conditions'];
            $Txn->external_ref = $this->txn['external_ref'];
            $Txn->payment_mode = $this->txn['payment_mode'];
            $Txn->payment_terms = $this->txn['payment_terms'];
            $Txn->status = $this->txn['status'];

            $Txn->save();
            $this->txn['id'] = $Txn->id;

            //Create items to be posted under the parent txn
            //update status of invoice being paid off
            foreach ($this->txn['items'] as &$item)
            {
                $item['receipt_id'] = $this->txn['id'];

                $txnInReference = Invoice::findOrFail($item['invoice_id']);

                $totalPaid = $item['total']  + $item['amount_withheld'];
                $txnInReference->increment('total_paid', $totalPaid);

                InvoiceAnnex::insert([
                    'tenant_id' => $this->txn['tenant_id'],
                    'invoice_id' => $item['invoice_id'],
                    'name' => 'receipt',
                    'model' => 'Rutatiina\PaymentsReceived\Models\PaymentsReceived',
                    'model_id' => $this->txn['id'],
                ]);

                //save the item and taxes
                $itemTaxes = (is_array($item['taxes'])) ? $item['taxes'] : [] ;
                unset($item['taxes']);

                $itemModel = ReceiptItem::create($item);

                foreach ($itemTaxes as $tax)
                {
                    //save the taxes attached to the item
                    $itemTax = new ReceiptItemTax;
                    $itemTax->tenant_id = $item['tenant_id'];
                    $itemTax->receipt_id = $item['receipt_id'];
                    $itemTax->receipt_item_id = $itemModel->id;
                    $itemTax->tax_code = $tax['code'];
                    $itemTax->amount = $tax['total'];
                    $itemTax->inclusive = $tax['inclusive'];
                    $itemTax->exclusive = $tax['exclusive'];
                    $itemTax->save();
                }
                unset($tax);
            }

            //print_r($this->txn['items']); exit;

            //Save the ledgers >> $this->txn['ledgers']; and update the balances
            foreach ($this->txn['ledgers'] as &$ledger)
            {
                $ledger['receipt_id'] = $this->txn['id'];
                ReceiptLedger::create($ledger);
            }
            unset($ledger);

            $this->approve();

            DB::connection('tenant')->commit();

            return (object)[
                'id' => $this->txn['id'],
            ];

        }
        catch (\Exception $e)
        {

            DB::connection('tenant')->rollBack();
            //print_r($e); exit;
            if (App::environment('local'))
            {
                $this->errors[] = 'Error: Failed to save transaction to database.';
                $this->errors[] = 'File: ' . $e->getFile();
                $this->errors[] = 'Line: ' . $e->getLine();
                $this->errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                $this->errors[] = 'Fatal Internal Error: Failed to save transaction to database. Please contact Admin';
            }

            return false;
        }

    }

}
