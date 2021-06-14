<?php

namespace Rutatiina\PaymentsReceived\Http\Controllers;

use Illuminate\Support\Facades\URL;
use Rutatiina\PaymentsReceived\Models\Setting;
use Rutatiina\Invoice\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Rutatiina\FinancialAccounting\Models\Account;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;
use Rutatiina\PaymentsReceived\Models\PaymentsReceived;
use Rutatiina\Contact\Traits\ContactTrait;
use Rutatiina\PaymentsReceived\Services\PaymentsReceivedService;
use Rutatiina\RetainerInvoice\Services\RetainerInvoiceService;
use Yajra\DataTables\Facades\DataTables;

//controller not in use
class PaymentsReceivedController extends Controller
{
    use FinancialAccountingTrait;
    use ContactTrait;

    public function __construct()
    {
        $this->middleware('permission:receipts.view');
        $this->middleware('permission:receipts.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:receipts.update', ['only' => ['edit', 'update']]);
        $this->middleware('permission:receipts.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $query = Receipt::query();

        if ($request->contact)
        {
            $query->where(function ($q) use ($request)
            {
                $q->where('debit_contact_id', $request->contact);
                $q->orWhere('credit_contact_id', $request->contact);
            });
        }

        $txns = $query->latest()->paginate($request->input('per_page', 20));

        $txns->load('debit_financial_account');

        return [
            'tableData' => $txns
        ];
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new Receipt())->rgGetAttributes();

        $txnAttributes['number'] = ReceiptService::nextNumber();
        $txnAttributes['status'] = 'approved';
        $txnAttributes['contact_id'] = '';
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');
        $txnAttributes['base_currency'] = $tenant->base_currency;
        $txnAttributes['quote_currency'] = $tenant->base_currency;
        $txnAttributes['taxes'] = json_decode('{}');
        $txnAttributes['contact_notes'] = null;
        $txnAttributes['terms_and_conditions'] = null;
        $txnAttributes['items'] = [];

        unset($txnAttributes['debit_contact_id']); //!important
        unset($txnAttributes['credit_contact_id']); //!important

        $data = [
            'pageTitle' => 'Create Receipt', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/receipts', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;

    }

    public function store(Request $request)
    {
        //return $request->all();

        $storeService = ReceiptService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => ReceiptService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Receipt saved'],
            'number' => 0,
            'callback' => URL::route('receipts.show', [$storeService->id], false)
        ];
    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txn = Receipt::findOrFail($id);
        $txn->load('contact', 'items.taxes');
        $txn->setAppends([
            'taxes',
            'number_string',
            'total_in_words',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txnAttributes = ReceiptService::edit($id);

        $data = [
            'pageTitle' => 'Edit receipt', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/receipts/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function update(Request $request)
    {
        //return $request->all();

        $storeService = ReceiptService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => ReceiptService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Receipt updated'],
            'number' => 0,
            'callback' => URL::route('receipts.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
    {
        $destroy = ReceiptService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Receipt deleted'],
                'callback' => URL::route('receipts.index', [], false)
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => ReceiptService::$errors
            ];
        }
    }

    #-----------------------------------------------------------------------------------

    public function approve($id)
    {
        $approve = RetainerInvoiceService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => RetainerInvoiceService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Receipts approved'],
        ];
    }

    public function debitAccounts()
    {
        //list accounts that are either payment accounts or bank accounts
        return Account::where('payment', 1)->orWhere('bank_account_id', '>', 0)->get();
    }

    public function datatables(Request $request)
    {
        $txns = Transaction::setRoute('show', route('accounting.sales.receipts.show', '_id_'))
            ->setRoute('edit', route('accounting.sales.receipts.edit', '_id_'))
            ->setSortBy($request->sort_by)
            ->paginate(false)
            ->findByEntree($this->txnEntreeSlug);

        return Datatables::of($txns)->make(true);
    }

    public function exportToExcel(Request $request)
    {
        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT #',
            'REFERENCE',
            'CUSTOMER',
            'TOTAL',
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
                $txn->total,
                $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-receipts-export-' . date('Y-m-d-H-m-s') . '.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

    public function invoices(Request $request)
    {
        $contact_ids = $request->contact_ids;

        $validator = Validator::make($request->all(), [
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['numeric'],
        ]);

        if ($validator->fails())
        {
            $response = ['status' => false, 'message' => ''];

            foreach ($validator->errors()->all() as $field => $messages)
            {
                $response['message'] .= "\n" . $messages;
            }

            return json_encode($response);
        }

        /*
         * array with empty value was being posted e.g. array(1) { [0]=> NULL }
         * so to correct that, loop through and delete non values
         */
        foreach ($contact_ids as $key => $contact_id)
        {
            if (!is_numeric($contact_id))
            {
                unset($contact_ids[$key]);
            }
        }

        //var_dump($contact_ids); exit;


        if (empty($contact_id) && empty($invoice_id))
        {
            return [
                'currencies' => [],
                'invoices' => [],
                'notes' => ''
            ];
        }

        $query = Invoice::query();
        $query->orderBy('date', 'ASC');
        $query->orderBy('id', 'ASC');
        $query->whereIn('debit_contact_id', $contact_ids);
        $query->whereColumn('total_paid', '<', 'total');

        $txns = $query->get();

        $currencies = [];
        $items = [];

        foreach ($txns as $index => $txn)
        {
            //$txns[$index] = Transaction::transaction($txn);
            $currencies[$txn['base_currency']][] = $txn['id'];

            $itemTxn = [
                'id' => $txn->id,
                'date' => $txn->date,
                'due_date' => $txn->due_date,
                'number' => $txn->number,
                'base_currency' => $txn->base_currency,
                'contact_name' => $txn->contact_name,
                'total' => $txn->total,
                'balance' => $txn->balance,
            ];

            $items[] = [
                'invoice' => $itemTxn,
                'paidInFull' => false,

                'txn_contact_id' => $txn->debit_contact_id,
                'txn_number' => $txn->number,
                'max_receipt_amount' => $txn->balance,
                'txn_exchange_rate' => $txn->exchange_rate,

                'invoice_id' => $txn->id,
                'contact_id' => $txn->debit_contact_id,
                'description' => 'Invoice #' . $txn->number,
                'amount' => 0,
                'taxable_amount' => 0,
                'displayTotal' => 0,
                'selectedItem' => json_decode('{}'),
                'selectedTaxes' => [],
                'taxes' => [],
            ];
        }

        $notes = '';
        foreach ($currencies as $currency => $txn_ids)
        {
            $notes .= count($txn_ids) . ' ' . $currency . ' invoice(s). ';
        }

        $contactSelect2Options = [];


        /*
        foreach ($contact_ids as $contact_id) {

			$contact = Contact::find($contact_id);

			foreach ($contact->currencies as $currency) {

				$contactSelect2Options[] = array(
					'id' => $currency,
					'text' => $currency,
					'exchange_rate' => Forex::exchangeRate($currency, Auth::user()->tenant->base_currency),
				);
			}

		}
        */

        return [
            'status' => true,
            'items' => $items,
            'currencies' => $contactSelect2Options,
            'txns' => $txns->toArray(),
            'notes' => $notes
        ];
    }

    public function invoice(Request $request)
    {
        $invoice_id = $request->invoice_id; //used by receipt on invoice form

        $validator = Validator::make($request->all(), [
            'invoice_id' => ['numeric'],
        ]);

        if ($validator->fails())
        {
            $response = ['status' => false, 'message' => ''];

            foreach ($validator->errors()->all() as $field => $messages)
            {
                $response['message'] .= "\n" . $messages;
            }

            return json_encode($response);
        }

        $invoice = Invoice::find($invoice_id);

        $notes = '';

        $last_receipt = Receipt::latest()->first();
        $settings = Setting::first();

        $fields = [
            'internal_ref' => $invoice->id,
            'number' => $settings->number_prefix . (str_pad((optional($last_receipt)->number + 1), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix,
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
            'exchange_rate' => floatval($invoice->exchange_rate),
            'items' => [
                [
                    'txn' => [
                        'id' => $invoice->id,
                        'date' => $invoice->date,
                        'due_date' => $invoice->due_date,
                        'number' => $invoice->number,
                        'base_currency' => $invoice->base_currency,
                        'contact_name' => $invoice->contact_name,
                        'total' => $invoice->total,
                        'balance' => $invoice->balance,
                    ],
                    'paidInFull' => false,

                    'txn_contact_id' => $invoice->debit_contact_id,
                    'txn_number' => $invoice->number,
                    'max_receipt_amount' => $invoice->balance,
                    'txn_exchange_rate' => $invoice->exchange_rate,

                    'invoice_id' => $invoice->id,
                    'contact_id' => $invoice->debit_contact_id,
                    'description' => 'Invoice #' . $invoice->number,
                    'displayTotal' => 0,
                    'name' => 'Invoice #' . $invoice->number,
                    'quantity' => 1,
                    'rate' => floatval($invoice->balance),
                    'selectedItem' => json_decode('{}'),
                    'selectedTaxes' => [],
                    'tax_id' => null,
                    'taxes' => [],
                    'total' => 0,
                ]
            ],
        ];

        return [
            'status' => true,
            'fields' => $fields, //this parameter is used when creating a receipt on an invoice
            'notes' => ''
        ];
    }
}
