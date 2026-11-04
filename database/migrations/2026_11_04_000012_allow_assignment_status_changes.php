<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP TRIGGER finance_immutable ON student_fee_assignments');
        DB::statement('DROP TRIGGER finance_context_guard ON student_fee_assignments');
        DB::statement('CREATE TRIGGER finance_context_guard BEFORE INSERT ON student_fee_assignments FOR EACH ROW EXECUTE FUNCTION finance_context_guard()');
        DB::unprepared(<<<'SQL'
CREATE FUNCTION finance_assignment_status_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Fee assignment history cannot be deleted'; END IF;
 IF (to_jsonb(NEW) - ARRAY['active','updated_at']) IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['active','updated_at']) THEN RAISE EXCEPTION 'Assignment snapshot is immutable'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER finance_assignment_status BEFORE UPDATE OR DELETE ON student_fee_assignments FOR EACH ROW EXECUTE FUNCTION finance_assignment_status_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP TRIGGER finance_assignment_status ON student_fee_assignments');
        DB::statement('DROP FUNCTION finance_assignment_status_guard()');
        DB::statement('DROP TRIGGER finance_context_guard ON student_fee_assignments');
        DB::statement('CREATE TRIGGER finance_context_guard BEFORE INSERT OR UPDATE ON student_fee_assignments FOR EACH ROW EXECUTE FUNCTION finance_context_guard()');
        DB::statement('CREATE TRIGGER finance_immutable BEFORE UPDATE OR DELETE ON student_fee_assignments FOR EACH ROW EXECUTE FUNCTION finance_immutable()');
    }
};
