<?php

namespace App\Modules\Platform\Preview;

final class DemoCatalog
{
    public const SCHOOLS = [
        'demo-maplewood' => ['name' => 'Maplewood Academy', 'city' => 'Lahore', 'plan' => 'demo-growth', 'students' => 840, 'teachers' => 48, 'classrooms' => 32, 'color' => '#635bff', 'trend' => [560, 610, 645, 710, 765, 840]],
        'demo-crescent' => ['name' => 'Crescent International', 'city' => 'Karachi', 'plan' => 'demo-scale', 'students' => 1420, 'teachers' => 86, 'classrooms' => 54, 'color' => '#10a890', 'trend' => [1020, 1090, 1180, 1260, 1330, 1420]],
        'demo-oakridge' => ['name' => 'Oakridge School', 'city' => 'Islamabad', 'plan' => 'demo-starter', 'students' => 280, 'teachers' => 19, 'classrooms' => 12, 'color' => '#ed9b40', 'trend' => [160, 180, 205, 220, 250, 280]],
        'demo-beacon' => ['name' => 'Beacon Learning Campus', 'city' => 'Rawalpindi', 'plan' => 'demo-growth', 'students' => 620, 'teachers' => 36, 'classrooms' => 24, 'color' => '#478bd3', 'trend' => [390, 440, 480, 530, 580, 620]],
    ];

    public const PLANS = [
        'demo-starter' => ['name' => 'Starter', 'monthly_price_minor' => 1500000, 'school_capacity' => 400],
        'demo-growth' => ['name' => 'Growth', 'monthly_price_minor' => 3500000, 'school_capacity' => 1000],
        'demo-scale' => ['name' => 'Scale', 'monthly_price_minor' => 6500000, 'school_capacity' => 2500],
    ];
}
