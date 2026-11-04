<?php

namespace App\Modules\Schools\Models;

use App\Models\User;
use App\Modules\Shared\BelongsToSchool;
use Database\Factories\SchoolMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolMembership extends Model
{
    use HasFactory;

    protected static function newFactory(): SchoolMembershipFactory
    {
        return SchoolMembershipFactory::new();
    }

    use BelongsToSchool;

    protected $fillable = ['user_id', 'role', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
