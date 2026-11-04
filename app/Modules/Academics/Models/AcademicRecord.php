<?php

namespace App\Modules\Academics\Models;

use App\Modules\Shared\BelongsToSchool;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class AcademicRecord extends Model
{
    use BelongsToSchool;
    use HasFactory;

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\Academic\\'.class_basename(static::class).'Factory';

        return $class::new();
    }

    protected $guarded = ['id', 'school_id', 'user_id', 'admission_number'];

    protected static function booted(): void
    {
        static::addGlobalScope('school', fn (Builder $query) => $query->where($query->getModel()->qualifyColumn('school_id'), app(TenantContext::class)->school()->id));
        static::creating(function (self $model) {
            $model->school_id = app(TenantContext::class)->school()->id;
        });
        static::saving(function (self $model) {
            if ($model->exists && ($model->school_id !== app(TenantContext::class)->school()->id || $model->isDirty('school_id'))) {
                throw new LogicException('Academic record belongs to another school.');
            }
        });
        static::deleting(function (self $model) {
            if ($model->school_id !== app(TenantContext::class)->school()->id) {
                throw new LogicException('Academic record belongs to another school.');
            }
        });
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_current' => 'boolean'];
    }
}
