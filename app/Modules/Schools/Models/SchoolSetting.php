<?php

namespace App\Modules\Schools\Models;

use App\Modules\Shared\BelongsToSchool;
use Database\Factories\SchoolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    protected static function newFactory(): SchoolSettingFactory
    {
        return SchoolSettingFactory::new();
    }

    use BelongsToSchool;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
