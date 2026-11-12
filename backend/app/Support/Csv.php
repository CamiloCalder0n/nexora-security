<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descarga CSV que Excel abre bien en español: separador ";" y BOM para las tildes. */
class Csv
{
    public static function descargar(array $filas, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            foreach ($filas as $fila) {
                fputcsv($salida, $fila, ';', '"', '');
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
