<?php

namespace Rutatiina\PaymentReceived\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Rutatiina\Item\Traits\ItemsVueSearchSelect;
use Rutatiina\FinancialAccounting\Models\Account;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Rutatiina\PaymentReceived\Models\PaymentReceivedSetting;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;

class PaymentReceivedAccountController extends Controller
{
    use FinancialAccountingTrait;
    use ItemsVueSearchSelect;

    public function __construct()
    {
        //$this->middleware('permission:estimates.view');
        //$this->middleware('permission:estimates.create', ['only' => ['create','store']]);
        //$this->middleware('permission:estimates.update', ['only' => ['edit','update']]);
        //$this->middleware('permission:estimates.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $query = Account::setCurrency(Auth::user()->tenant->base_currency)->query();
        $query->with('financial_account_category');
        $query->where('receipt', 1);
        $query->orderBy('name', 'asc');

        if ($request->search)
        {
            $request->request->remove('page');

            $query->where(function($q) use ($request) {
                $columns = (new Account)->getSearchableColumns();
                foreach($columns as $column)
                {
                    $q->orWhere($column, 'like', '%'.Str::replace(' ', '%', $request->search).'%');
                }
            });
        }

        $AccountPaginate = $query->paginate(20);


        $financialAccounts = Account::select(['code', 'name', 'type'])
        //->whereIn('type', ['expense', 'equity']) //'liability', was remove because Account payables is a liability
        ->orderBy('name', 'asc')
        ->limit(100)
        ->get()
        ->each->setAppends([])
        ->groupBy('type');

        return [
            'tableData' => $AccountPaginate,
            'financialAccounts' => $financialAccounts
        ];
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //print_r($request->all()); exit;

        //validate data posted
        $validator = Validator::make($request->all(), [
            'document_name' => ['required', 'string', 'max:50'],
            'number_prefix' => ['string', 'max:20', 'nullable'],
            'number_postfix' => ['string', 'max:20', 'nullable'],
            'minimum_number_length' => ['required', 'numeric'],
            'minimum_number' => ['required', 'numeric'],
            //'maximum_number' => ['required', 'numeric'],
        ]);

        if ($validator->fails())
        {
            return ['status' => false, 'messages' => $validator->errors()->all()];
        }

        //save data posted
        $settings = PaymentReceivedSetting::first();
        $settings->document_name = $request->document_name;
        $settings->number_prefix = $request->number_prefix;
        $settings->number_postfix = $request->number_postfix;
        $settings->minimum_number_length = $request->minimum_number_length;
        $settings->minimum_number = $request->minimum_number;
        $settings->credit_financial_account_code = $request->credit_financial_account_code;
        $settings->save();

        return [
            'status' => true,
            'messages' => ['Settings updated'],
        ];

    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request)
    {
        //
    }

    public function destroy($id)
    {
        //
    }

    #-----------------------------------------------------------------------------------
}
