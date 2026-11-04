<?php

namespace App\Modules\Schools\Models;

use App\Modules\Shared\BelongsToSchool;
use Database\Factories\SchoolDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolDomain extends Model
{
    use HasFactory;

    protected static function newFactory(): SchoolDomainFactory
    {
        return SchoolDomainFactory::new();
    }

    use BelongsToSchool;

    protected $fillable = ['hostname', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
