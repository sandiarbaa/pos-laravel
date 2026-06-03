<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionChoice extends Model
{
    protected $fillable = ['group_id', 'label'];

    public function group()
    {
        return $this->belongsTo(ProductOptionGroup::class, 'group_id');
    }
}
