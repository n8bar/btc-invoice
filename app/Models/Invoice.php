<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{

    protected $fillable = [
        'user_id','client_id','number','description',
        'amount_usd','btc_rate','amount_btc','btc_address',
        'status','txid','due_date','paid_at',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }





}
