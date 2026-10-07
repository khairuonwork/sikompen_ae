<?php

namespace App\Http\Requests;

class DownloadKompenResponHubDataRequest extends KompenResponHubTableRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'periode_semester' => ['required', 'string', 'max:50'],
        ];
    }
}
