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
        'currency_code', 'discount_rate', 'credit_limit', 'credit_terms', 
        'accounts_payable_contact', 'accounts_payable_emails', 
        'pay_email', 'rec_email', 'copy_email', 
        'phone_number', 'fax_number', 'bank_account_number', 'routing_number', 
        'rec_name', 'rec_tel'
    ];

    // Inject these virtual properties whenever an organization is sent to the frontend
    protected $appends = [
        'country_code',
        'is_international'
    ];

    /**
     * Virtual Accessor: Extracts the raw country code string.
     */
    public function getCountryCodeAttribute(): ?string
    {
        if (!$this->relationLoaded('country')) {
            return null;
        }
        return $this->country?->code;
    }

    /**
     * Virtual Accessor: Computes internationality based on the corporate country entity.
     */
    public function getIsInternationalAttribute(): bool
    {
        if (!$this->relationLoaded('country') || !$this->country) {
            return false;
        }
        return $this->country->code !== 'US';
    }

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
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(OrganisationType::class, 'organisations_otypes');
    }
}