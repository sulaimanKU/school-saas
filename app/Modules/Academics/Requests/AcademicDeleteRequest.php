<?php

namespace App\Modules\Academics\Requests;

use App\Modules\Academics\AcademicPolicy;
use Illuminate\Foundation\Http\FormRequest;

class AcademicDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AcademicPolicy::class)->allows();
    }

    public function rules(): array
    {
        return ['school_id' => ['prohibited']];
    }
}
