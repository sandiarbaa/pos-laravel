<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionGroup extends Model
{
    protected $fillable = ['product_id', 'name'];

    public function choices()
    {
        return $this->hasMany(ProductOptionChoice::class, 'group_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
