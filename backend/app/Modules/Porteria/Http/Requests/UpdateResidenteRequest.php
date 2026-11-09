<?php

namespace App\Modules\Porteria\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateResidenteRequest extends StoreResidenteRequest
{
    protected function documentoUnico(): Unique
    {
        return Rule::unique('residentes', 'documento')->ignore($this->route('residente'));
    }
}
