<?php

namespace Database\Seeders;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\FeeCategory;
use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

final class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || config('preview.enabled') !== true || config('preview.finance') !== true) {
            throw new \LogicException('Finance configuration seeding requires explicitly enabled local finance.');
        }
        foreach (School::whereIn('slug', array_keys(DemoCatalog::SCHOOLS))->where('is_demo', true)->where('status', 'active')->get() as $school) {
            app(TenantContext::class)->set($school);
            foreach (['Admission Fee', 'Tuition Fee', 'Examination Fee', 'Transport Fee', 'Library Fee', 'Laboratory Fee', 'Annual Charges', 'Sports Fee', 'Miscellaneous Fee', 'Custom School Fee'] as $name) {
                FeeCategory::firstOrCreate(['name' => $name], ['active' => true]);
            }
            foreach (['Utilities', 'Supplies', 'Maintenance', 'Transport', 'Other'] as $name) {
                ExpenseCategory::firstOrCreate(['name' => $name], ['active' => true]);
            }
        }
    }
}
