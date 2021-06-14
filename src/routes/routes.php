<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('receipts')->group(function () {

        Route::post('debit-accounts', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController@debitAccounts')->name('receipts.debit-accounts');
        Route::post('invoices', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController@invoices')->name('receipts.invoices');
        Route::post('invoice', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController@invoice')->name('receipts.invoice');

        Route::post('export-to-excel', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController@approve');
        //Route::get('{id}/copy', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController@copy'); // to be deleted

        Route::post('on-invoice/create/data', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedOnInvoiceController@createData'); //todo this is not being used
        Route::resource('on-invoice', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedOnInvoiceController');

    });

    Route::resource('receipts/settings', 'Rutatiina\PaymentsReceived\Http\Controllers\SettingsController');
    Route::resource('receipts', 'Rutatiina\PaymentsReceived\Http\Controllers\PaymentsReceivedController');

});
