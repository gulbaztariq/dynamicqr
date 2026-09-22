<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams CSV downloads without building the whole file in memory. */
class CsvExporter
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>|Collection  $rows
     */
    public static function stream(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 (accented names, country names) correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($v) => $v instanceof \Stringable ? (string) $v : $v, $row), ',', '"', '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
