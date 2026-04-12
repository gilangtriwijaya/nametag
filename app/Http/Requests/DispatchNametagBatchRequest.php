<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchNametagBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required','array','min:1'],
            'ids.*' => ['integer','min:1'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        return $key ? ($data[$key] ?? $default) : $data;
    }
}
