<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Operations\Models\AcademicAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicAuditLogFactory extends Factory
{
    protected $model = AcademicAuditLog::class;

    public function definition(): array
    {
        return ['operation' => 'test.created', 'entity_type' => 'test', 'entity_id' => 1, 'actor_context' => ['mode' => 'local-development', 'authenticated' => false], 'changes' => ['before' => [], 'after' => []]];
    }
}
