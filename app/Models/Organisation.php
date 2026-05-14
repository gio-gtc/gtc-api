<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organisation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'billing_address', 'city', 'state', 'zip', 'country_id', 
        'currency_id', 'discount_rate', 'credit_limit', 'credit_terms', 
        'accounts_payable_contact', 'accounts_payable_emails', 
        'pay_email', 'rec_email', 'copy_email', 
        'phone_number', 'fax_number', 'bank_account_number', 'routing_number', 
        'rec_name', 'rec_tel'
    ];

    protected function casts(): array
    {
        return [
            'accounts_payable_emails' => 'array',
            'bank_account_number' => 'encrypted',
            'routing_number' => 'encrypted',
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

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(OrganisationType::class, 'organisations_otypes');
    }
}