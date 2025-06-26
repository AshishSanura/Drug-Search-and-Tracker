<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDrug extends Model
{
    protected $fillable = ['user_id', 'rxcui', 'drug_name'];
}
