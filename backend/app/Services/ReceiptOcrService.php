<?php

declare(strict_types=1);

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

class ReceiptOcrService
{
    /**
     * @return array{
     *     amount: string|null,
     *     payee_name: string|null,
     *     account_number: string|null,
     *     phone: string|null,
     *     transaction_reference: string|null,
     *     ocr_raw_text: string,
     *     ocr_status: string
     * }
     */
    public function parse(string $absolutePath): array
    {
        try {
            $text = (new TesseractOCR($absolutePath))
                ->lang('eng')
                ->run();
        } catch (\Throwable) {
            return [
                'amount' => null,
                'payee_name' => null,
                'account_number' => null,
                'phone' => null,
                'transaction_reference' => null,
                'ocr_raw_text' => '',
                'ocr_status' => 'failed',
            ];
        }

        $normalized = preg_replace("/[ \t]+/", ' ', str_replace("\r", '', (string) $text)) ?? '';

        return [
            'amount' => $this->extractAmount($normalized),
            'payee_name' => $this->extractPayee($normalized),
            'account_number' => $this->extractAccount($normalized),
            'phone' => $this->extractPhone($normalized),
            'transaction_reference' => $this->extractReference($normalized),
            'ocr_raw_text' => $normalized,
            'ocr_status' => 'parsed',
        ];
    }

    private function extractAmount(string $text): ?string
    {
        if (preg_match('/(?:ETB|Br\.?|Birr|Amount|Paid)\s*[:.]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+\.[0-9]{2})/i', $text, $match)) {
            return number_format((float) str_replace(',', '', $match[1]), 2, '.', '');
        }

        if (preg_match('/\b([0-9]{2,6}\.[0-9]{2})\b/', $text, $match)) {
            return number_format((float) $match[1], 2, '.', '');
        }

        return null;
    }

    private function extractPhone(string $text): ?string
    {
        if (preg_match('/(?:\+251|0)9[0-9]{8}/', $text, $match)) {
            return $match[0];
        }

        return null;
    }

    private function extractAccount(string $text): ?string
    {
        if (preg_match('/(?:Account|Acc(?:ount)?\s*(?:No|Number)?|A\/C)\s*[:.]?\s*([0-9]{6,20})/i', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/\b([0-9]{10,16})\b/', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    private function extractReference(string $text): ?string
    {
        if (preg_match('/\b(FT[A-Z0-9]{6,})\b/i', $text, $match)) {
            return strtoupper($match[1]);
        }

        if (preg_match('/(?:Ref(?:erence)?|Txn|Transaction)\s*(?:No|Number|#)?\s*[:.]?\s*([A-Z0-9\-]{6,})/i', $text, $match)) {
            return strtoupper($match[1]);
        }

        return null;
    }

    private function extractPayee(string $text): ?string
    {
        if (preg_match('/(?:Beneficiary|Payee|To|Recipient)\s*[:.]?\s*([A-Za-z][A-Za-z .\'-]{2,60})/i', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }
}
