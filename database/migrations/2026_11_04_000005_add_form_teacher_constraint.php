<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX teacher_one_form_class ON teacher_assignments (school_id, academic_session_id, teacher_id) WHERE kind = 'form_teacher'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX teacher_one_form_class');
    }
};
