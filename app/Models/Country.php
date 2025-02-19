<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    // 'name' => $country['name']['common'],
    // 'official_name' => $country['name']['official'],
    // 'iso_alpha_2' => $country['cca2'],
    // 'alt_spellings' => $country['altSpellings'],
    protected $fillable = ['name', 'official_name', 'iso_alpha_2', 'alt_spellings', 'description'];

    
    //
}
