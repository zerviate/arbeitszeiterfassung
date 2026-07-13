<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class ArrayExport implements FromCollection
{
    public function __construct(
        private readonly array $rows,
    ) {
    }

    public function collection(): Collection
    {
        if ($this->rows === []) {
            return collect();
        }

        $header = array_keys($this->rows[0]);
        $data = collect([$header]);

        foreach ($this->rows as $row) {
            $data->push(array_values($row));
        }

        return $data;
    }
}
