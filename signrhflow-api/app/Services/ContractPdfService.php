<?php

namespace App\Services;

use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContractPdfService
{
    public function generateAndStore(Contract $contract): string
    {
        $contract->loadMissing('employee');

        $pdfBinary = Pdf::loadView('contracts.template', [
            'contract' => $contract,
            'employee' => $contract->employee,
            'generatedAt' => now(),
        ])->output();

        $relativePath = sprintf('contracts/contract-%s.pdf', $contract->id);
        Storage::disk('local')->put($relativePath, $pdfBinary);

        return $relativePath;
    }
}
