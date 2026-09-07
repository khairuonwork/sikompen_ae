<?php

namespace App\Http\Requests;

class DownloadKompenResponHubDataRequest extends KompenResponHubTableRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'periode_semester' => ['required', 'string', 'max:50'],
        ];
    }
}
