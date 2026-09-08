<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCounter extends Model
{
    protected $fillable = ['document', 'period', 'last_number'];
}
