<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('receipts')->group(function () {

        Route::post('debit-accounts', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@debitAccounts')->name('receipts.debit-accounts');
        Route::post('invoices', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@invoices')->name('receipts.invoices');
        Route::post('invoice', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@invoice')->name('receipts.invoice');

        Route::post('export-to-excel', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@approve');
        //Route::get('{id}/copy', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController@copy'); // to be deleted

        Route::post('on-invoice/create/data', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedOnInvoiceController@createData'); //todo this is not being used
        Route::resource('on-invoice', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedOnInvoiceController');

    });

    Route::resource('receipts/settings', 'Rutatiina\PaymentReceived\Http\Controllers\SettingsController');
    Route::resource('receipts', 'Rutatiina\PaymentReceived\Http\Controllers\PaymentReceivedController');

});
