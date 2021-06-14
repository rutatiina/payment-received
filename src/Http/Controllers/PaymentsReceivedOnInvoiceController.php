<?php

namespace Rutatiina\PaymentsReceived\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Rutatiina\Invoice\Models\Invoice;
use Rutatiina\PaymentsReceived\Models\PaymentsReceived;
use Rutatiina\PaymentsReceived\Models\Setting;
use URL;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request as FacadesRequest;

use Illuminate\Support\Facades\View;
use Rutatiina\Accounting\Model\Account;
use Rutatiina\Accounting\Model\Txn;
use Rutatiina\Accounting\Classes\Transaction;
use Rutatiina\Accounting\Classes\Forex;
use Rutatiina\Accounting\Model\TxnEntree;
use Rutatiina\Banking\Model\Account as BankAccount;
use Rutatiina\Tenant\Traits\TenantTrait;
use Rutatiina\Contact\Traits\ContactTrait;
use Yajra\DataTables\Facades\DataTables;

class PaymentsReceivedOnInvoiceController extends Controller
{
    use ContactTrait;

    private $txnEntreeSlug = 'receipt';

    public function __construct()
    {
        $this->middleware('permission:invoices.view');
        $this->middleware('permission:invoices.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:invoices.update', ['only' => ['edit', 'update']]);
        $this->middleware('permission:invoices.delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }
    }

    public function create($invoiceId)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $invoice = Invoice::findOrFail($invoiceId);
        $txn = Receipt::latest()->first();

        $settings = Setting::first();

        $bankAccounts = collect([]); //BankAccount::with('bank')->get();
        $accountsGroupByType = collect([]); //Account::all()->groupBy('type');

        $accountsByType = [];

        foreach ($accountsGroupByType as $key => $value)
        {
            $accountsByType[ucfirst($key)] = $value;
        }

        $receiptFieldDefaults = [
            'internal_ref' => $invoice->id,
            'number' => $settings->number_prefix . (str_pad((optional($txn)->number + 1), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix,
            'date' => date('Y-m-d'),
            'contact_name' => $invoice->contact_name,
            'contact_id' => $invoice->debit_contact_id,
            'debit_contact_id' => $invoice->debit_contact_id,
            'credit_contact_id' => $invoice->credit_contact_id,
            'base_currency' => $invoice->base_currency,
            'payment_mode' => 'Cash',
            'payment_terms' => null,
            'reference' => $invoice->number,
            'debit' => null,
            'total' => floatval($invoice->balance),
            'taxable_amount' => floatval($invoice->balance),
            'amount_received' => floatval($invoice->balance),
            'items' => [
                [
                    'invoice_id' => $invoice->id,
                    'batch' => null,
                    'contact_id' => null,
                    'description' => 'Receipt on invoice no. ' . $invoice->number,
                    'name' => 'Cash',
                    'quantity' => 1,
                    'rate' => floatval($invoice->balance),
                    'tax_id' => null,
                    'taxes' => [
                        /*[
                            'id' => null,
                            'name' => null,
                            'total' => 0,
                            'inclusive' => 0,
                            'exclusive' => 0
                        ]*/
                    ],
                    'total' => floatval($invoice->balance),
                ]
            ]
        ];

        $taxes = collect([]); //self::taxes();

        $taxes->map(function ($tax)
        {
            $tax['text'] = $tax->name;
            return $tax;
        });

        return [
            'invoice' => $invoice,
            'receiptFieldDefaults' => $receiptFieldDefaults,
            'bankAccounts' => $bankAccounts,
            'accountsByType' => $accountsByType,
            'taxes' => $taxes
        ];
    }

    public function store(Request $request)
    {
        $data = $request->all();

        //$data['items'][0]['name'] = $data['payment_mode'];

        //return $data;

        //$data['txn_entree_id']  = $this->entree->id; //New Tax invoice txn_entree
        $data['txn_entree_name'] = 'receipt';
        $data['tenant_id'] = Auth::user()->tenant->id;
        $data['user_id'] = Auth::id();
        $data['taxes'] = $data['items'][0]['taxes']; //so that the taxes are processed by the transaction insert class

        //return $data;

        $process = Transaction::contactById($request->contact_id)->insert($data);
        $message = (isset($process->message)) ? $process->message : 'Receipt saved';

        if ($process == false)
        {
            return [
                'status' => false,
                'message' => implode('<br>', array_values(Transaction::$rg_errors))
            ];
        }

        return [
            'status' => true,
            'message' => $message,
            'number' => $process->next_number,
            //'callback'  => URL::route('accounting.sales.receipts.show', $process->id, false)
            'callback' => URL::route('accounting.sales.invoices.show', $data['internal_ref'], false)
        ];
    }

    public function show($txnId)
    {
        $txn = Transaction::transaction($txnId);
        //print_r($txn); exit;
        //var_dump($txn->items->contains('type', 'txn')); exit;
        return view('accounting::sales.invoices.show')->with([
            'txn' => $txn,
        ]);
    }

    public function edit($invoice)
    {
        $txn = Transaction::transaction($invoice);
        return view('accounting::sales.invoices.edit')->with([
            'txn' => $txn,
            'contacts' => static::contactsByTypes(['customer']),
            'taxes' => self::taxes()
        ]);
    }

    public function update(Request $request)
    {

        $data = $request->all();

        //$data['txn_entree_id']  = $this->entree->id; //New Tax invoice txn_entree
        $data['txn_entree_name'] = 'invoice';

        //In case the due date is not set, it should be the same as the date_time
        //$data['due_date']           = (empty($data['due_date'])) ? $data['date'] : $data['due_date'];

        //print_r($data); exit;
        $process = Transaction::contactById($request->contact_id)->update($request->id, $data);
        $message = 'Invoice updated';

        if ($process == false)
        {
            return [
                'status' => false,
                'message' => implode('<br>', array_values(Transaction::$rg_errors))
            ];
        }

        return [
            'status' => true,
            'message' => $message,
            'callback' => route('accounting.sales.receipts.show', $request->id)
        ];
    }

    public function destroy($id)
    {
        $delete = Transaction::delete($id);

        if ($delete)
        {
            return [
                'status' => true,
                'message' => 'Invoice deleted',
            ];
        }
        else
        {
            return [
                'status' => false,
                'message' => implode('<br>', array_values(Transaction::$rg_errors))
            ];
        }
    }

    #-----------------------------------------------------------------------------------

    public function createData(Request $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $txn = Receipt::latest()->first();

        $settings = Setting::first();

        $bankAccounts = collect([]); //BankAccount::with('bank')->get();
        $accountsGroupByType = collect([]); //Account::all()->groupBy('type');

        $accountsByType = [];

        foreach ($accountsGroupByType as $key => $value)
        {
            $accountsByType[ucfirst($key)] = $value;
        }

        $receiptFieldDefaults = [
            'internal_ref' => $invoice->id,
            'number' => $settings->number_prefix . (str_pad((optional($txn)->number + 1), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix,
            'date' => date('Y-m-d'),
            'contact_name' => $invoice->contact_name,
            'contact_id' => $invoice->debit_contact_id,
            'debit_contact_id' => $invoice->debit_contact_id,
            'credit_contact_id' => $invoice->credit_contact_id,
            'base_currency' => $invoice->base_currency,
            'payment_mode' => 'Cash',
            'payment_terms' => null,
            'reference' => $invoice->number,
            'debit' => null,
            'total' => floatval($invoice->balance),
            'taxable_amount' => floatval($invoice->balance),
            'amount_received' => floatval($invoice->balance),
            'items' => [
                [
                    'batch' => null,
                    'contact_id' => null,
                    'description' => 'Receipt on invoice no. ' . $invoice->number,
                    'max_receipt_amount' => $invoice->balance,
                    'name' => 'Cash',
                    'quantity' => 1,
                    'rate' => floatval($invoice->balance),
                    'tax_id' => null,
                    'taxes' => [
                        /*[
                            'id' => null,
                            'name' => null,
                            'total' => 0,
                            'inclusive' => 0,
                            'exclusive' => 0
                        ]*/
                    ],
                    'total' => floatval($invoice->balance),
                    'type' => 'txn',
                    'type_id' => $invoice->id,
                ]
            ]
        ];

        $taxes = collect([]); //self::taxes();

        $taxes->map(function ($tax)
        {
            $tax['text'] = $tax->name;
            return $tax;
        });

        return [
            'invoice' => $invoice,
            'receiptFieldDefaults' => $receiptFieldDefaults,
            'bankAccounts' => $bankAccounts,
            'accountsByType' => $accountsByType,
            'taxes' => $taxes
        ];
    }

    public function datatables(Request $request)
    {

        $txns = Transaction::setRoute('show', route('accounting.sales.invoices.show', '_id_'))
            ->setRoute('edit', route('accounting.sales.invoices.edit', '_id_'))
            ->setSortBy($request->sort_by)
            ->paginate(false)
            ->findByEntree('invoice');

        return Datatables::of($txns)->make(true);
    }

    public function exportToExcel(Request $request)
    {

        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT#',
            'REFERENCE',
            'CUSTOMER',
            'STATUS',
            'DUE DATE',
            'TOTAL',
            'BALANCE',
            ' ', //Currency
        ]);

        foreach (array_reverse($request->ids) as $id)
        {
            $txn = Transaction::transaction($id);

            $txns->push([
                $txn->date,
                $txn->number,
                $txn->reference,
                $txn->contact_name,
                $txn->status,
                $txn->expiry_date,
                $txn->total,
                'balance' => $txn->balance,
                'base_currency' => $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-invoices-export-' . date('Y-m-d-H-m-s') . '.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

}
