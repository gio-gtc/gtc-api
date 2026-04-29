<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'billing_address', 'city', 'state', 'zip', 'country_id', 
    'discount_rate', 'credit_limit', 'pay_email', 'rec_email', 'copy_email', 
    'telephone', 'fax_number', 'bank_account_number', 'routing_number', 
    'rec_name', 'rec_tel'
])]
class Organisation extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Automatically encrypt/decrypt these values in the database
            'bank_account_number' => 'encrypted',
            'routing_number' => 'encrypted',
            
            // Ensure these always come out as floats (numbers) in your JSON API
            'discount_rate' => 'float',
            'credit_limit' => 'float',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}