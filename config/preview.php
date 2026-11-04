<?php

return ['enabled' => filter_var(env('LOCAL_PREVIEW_ENABLED', false), FILTER_VALIDATE_BOOLEAN), 'academic_crud' => filter_var(env('LOCAL_ACADEMIC_CRUD_ENABLED', false), FILTER_VALIDATE_BOOLEAN), 'finance' => filter_var(env('LOCAL_FINANCE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)];
