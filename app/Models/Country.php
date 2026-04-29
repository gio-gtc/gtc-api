<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'currency_code', 'dial_code'])]
class Country extends Model
{
    use HasFactory;

    public function companies()
    {
        return $this->hasMany(Company::class);
    }
}