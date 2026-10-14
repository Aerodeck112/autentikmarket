<?php

namespace App\Services\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV / XLSX în streaming (memorie redusă — potrivit pentru cPanel).
 */
class TableExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows, string $format = 'csv'): StreamedResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename).'.'.$format;

        if ($format === 'xlsx') {
            return new StreamedResponse(function () use ($headers, $rows) {
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');
                $writer->addRow(Row::fromValues($headers));
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues(array_map(fn ($v) => $v === null ? '' : (is_bool($v) ? ($v ? 1 : 0) : $v), array_values($row))));
                }
                $writer->close();
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-store',
            ]);
        }

        return new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pentru Excel (diacritice RO/HU)
            fputcsv($out, $headers, ';', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], array_values($row)), ';', '"', '\\');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Protecție CSV injection (=, +, -, @ la început de celulă). */
    public function safeCell(mixed $value): string
    {
        $value = $value === null ? '' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
