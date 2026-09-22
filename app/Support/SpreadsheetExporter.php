<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One place that turns a heading row plus a row iterator into a download.
 *
 * Both formats stream: CSV straight to the output buffer, XLSX through
 * OpenSpout (which spools sheet XML to disk and zips it on close), so a
 * 50,000-row scan export never has to sit in memory as an array.
 */
class SpreadsheetExporter
{
    public const FORMATS = ['xlsx', 'csv'];

    /** Anything unrecognised falls back to Excel, which is what most people want. */
    public static function normaliseFormat(?string $format): string
    {
        $format = strtolower(trim((string) $format));

        return in_array($format, self::FORMATS, true) ? $format : 'xlsx';
    }

    /**
     * @param  string  $basename  Filename without extension; the format adds it.
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<int, float>|null  $columnWidths  Per-column width, XLSX only.
     */
    public static function download(
        ?string $format,
        string $basename,
        array $headings,
        iterable $rows,
        ?array $columnWidths = null,
    ): Response {
        return self::normaliseFormat($format) === 'csv'
            ? self::csv($basename.'.csv', $headings, $rows)
            : self::xlsx($basename.'.xlsx', $headings, $rows, $columnWidths);
    }

    /** @param array<int, string> $headings */
    public static function csv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 (accented names, country names) correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, self::stringify($row), ',', '"', '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, float>|null  $columnWidths
     */
    public static function xlsx(
        string $filename,
        array $headings,
        iterable $rows,
        ?array $columnWidths = null,
    ): BinaryFileResponse {
        $options = new Options;

        foreach ($columnWidths ?? self::defaultWidths($headings) as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);

        // XLSX is a zip and is only valid once finalised, so it is built to a
        // temp file and streamed from there rather than to php://output.
        $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        $writer->openToFile($path);

        $writer->addRow(Row::fromValuesWithStyle($headings, new Style(
            fontBold: true,
            fontColor: Color::BLACK,
            backgroundColor: 'F1F5F9',
        )));

        foreach ($rows as $row) {
            // Counts stay numeric so they can be summed in Excel; everything
            // else is written as text to stop codes being mangled into numbers.
            $writer->addRow(Row::fromValues(array_map(
                fn ($value) => is_int($value) || is_float($value) ? $value : self::text($value),
                array_values($row),
            )));
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Roughly fit each column to its heading, with extra room for the columns
     * that hold links.
     *
     * @param  array<int, string>  $headings
     * @return array<int, float>
     */
    private static function defaultWidths(array $headings): array
    {
        return array_map(function (string $heading) {
            $wide = preg_match('/link|url|destination|email|label|notes/i', $heading) === 1;

            return (float) max(12, min($wide ? 48 : 24, strlen($heading) + 4));
        }, array_values($headings));
    }

    /** @param array<int, mixed> $row */
    private static function stringify(array $row): array
    {
        return array_map(fn ($value) => self::text($value), $row);
    }

    private static function text(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value === true) {
            return 'Yes';
        }

        return (string) $value;
    }
}
