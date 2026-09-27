<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class GeneratedPersonnelExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return new Collection(session('generated_accounts', []));
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Full Name',
            'Email Address',
            'Password'
        ];
    }
}
