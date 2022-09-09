<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('payments-received')->group(function () {

        Route::post('debit-accounts', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@debitAccounts')->name('payments-received.debit-accounts');
        Route::post('invoices', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@invoices')->name('payments-received.invoices');
        Route::post('invoice', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@invoice')->name('payments-received.invoice');

        Route::post('export-to-excel', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@approve');
        //Route::get('{id}/copy', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@copy'); // to be deleted

        Route::post('on-invoice/create/data', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedOnInvoiceController@createData'); //todo this is not being used
        Route::resource('on-invoice', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedOnInvoiceController');

    });

    Route::resource('payments-received/accounts', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedAccountController');
    Route::resource('payments-received/settings', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedSettingsController');
    Route::resource('payments-received', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController');

});
