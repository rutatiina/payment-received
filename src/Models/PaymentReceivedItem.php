<?php

namespace Rutatiina\PaymentReceived\Models;

use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentReceivedItem extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logName = 'TxnItem';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_payment_received_items';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);
    }

    public function getTaxesAttribute($value)
    {
        $_array_ = json_decode($value);
        if (is_array($_array_)) {
            return $_array_;
        } else {
            return [];
        }
    }

    public function payment_received()
    {
        return $this->hasOne('Rutatiina\PaymentReceived\Models\PaymentReceived', 'id', 'payment_received_id');
    }

    public function invoice()
    {
        return $this->hasOne('Rutatiina\Invoice\Models\Invoice', 'id', 'invoice_id');
    }

    public function taxes()
    {
        return $this->hasMany('Rutatiina\PaymentReceived\Models\PaymentReceivedItemTax', 'payment_received_item_id', 'id');
    }

}
