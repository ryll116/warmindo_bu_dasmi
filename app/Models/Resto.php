<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['resto_name'])]
class Resto extends Model
{
    use HasFactory;

    protected $table = 'master_resto';

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
