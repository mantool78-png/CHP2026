<?php

declare(strict_types=1);

function pdf_export_font_path(): ?string
{
    $candidates = [
        dirname(__DIR__) . '/lib/fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    ];

    if (PHP_OS_FAMILY === 'Windows') {
        $candidates[] = 'C:/Windows/Fonts/arial.ttf';
        $candidates[] = 'C:/Windows/Fonts/segoeui.ttf';
    }

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function pdf_export_can_render(): bool
{
    return extension_loaded('gd')
        && function_exists('imagettftext')
        && function_exists('imagejpeg')
        && pdf_export_font_path() !== null;
}

function pdf_export_short_text(string $text, int $maxChars): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text, 'UTF-8') <= $maxChars) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, max(1, $maxChars - 1), 'UTF-8')) . '…';
}

/** @return list<string> */
function pdf_export_wrap_lines(string $font, int $fontSize, string $text, int $maxWidth): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (str_contains($text, "\n")) {
        $lines = [];
        foreach (explode("\n", $text) as $part) {
            foreach (pdf_export_wrap_lines($font, $fontSize, $part, $maxWidth) as $line) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($words === []) {
        return [''];
    }

    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (pdf_export_text_width($font, $fontSize, $candidate) <= $maxWidth) {
            $current = $candidate;
            continue;
        }

        if ($current !== '') {
            $lines[] = $current;
            $current = $word;
            continue;
        }

        $lines[] = pdf_export_short_text($word, mb_strlen($word, 'UTF-8'));
        $current = '';
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines === [] ? [''] : $lines;
}

function pdf_export_text_width(string $font, int $fontSize, string $text): float
{
    $box = imagettfbbox($fontSize, 0, $font, $text);
    if ($box === false) {
        return (float) (mb_strlen($text, 'UTF-8') * ($fontSize * 0.55));
    }

    return (float) ($box[2] - $box[0]);
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $rows
 * @param list<int> $colWidths
 * @return list<string> PNG binary strings
 */
function pdf_export_table_images(
    string $title,
    array $headers,
    array $rows,
    array $colWidths,
    int $pageWidth = 1754,
    int $pageHeight = 1240
): array {
    $font = pdf_export_font_path();
    if ($font === null) {
        return [];
    }

    $margin = 36;
    $titleHeight = 52;
    $headerHeight = 64;
    $rowHeight = 42;
    $fontSize = 11;
    $fontSizeSmall = 9;
    $fontSizeTitle = 18;

    $tableWidth = array_sum($colWidths);
    $usableHeight = $pageHeight - $margin * 2 - $titleHeight - $headerHeight;
    $rowsPerPage = max(1, (int) floor($usableHeight / $rowHeight));

    $pages = [];
    $rowChunks = $rows === [] ? [[]] : array_chunk($rows, $rowsPerPage);

    foreach ($rowChunks as $chunkIndex => $chunkRows) {
        $im = imagecreatetruecolor($pageWidth, $pageHeight);
        if ($im === false) {
            continue;
        }

        $white = imagecolorallocate($im, 255, 255, 255);
        $textColor = imagecolorallocate($im, 24, 24, 32);
        $mutedColor = imagecolorallocate($im, 110, 110, 120);
        $borderColor = imagecolorallocate($im, 205, 205, 214);
        $headerBg = imagecolorallocate($im, 242, 242, 248);

        imagefilledrectangle($im, 0, 0, $pageWidth, $pageHeight, $white);

        $titleY = $margin + 24;
        imagettftext($im, $fontSizeTitle, 0, $margin, $titleY, $textColor, $font, $title);
        if (count($rowChunks) > 1) {
            $pageLabel = 'Страница ' . ($chunkIndex + 1) . ' из ' . count($rowChunks);
            $labelWidth = pdf_export_text_width($font, $fontSizeSmall, $pageLabel);
            imagettftext(
                $im,
                $fontSizeSmall,
                0,
                (int) ($pageWidth - $margin - $labelWidth),
                $titleY,
                $mutedColor,
                $font,
                $pageLabel
            );
        }

        $originX = $margin + (int) max(0, floor(($pageWidth - $margin * 2 - $tableWidth) / 2));
        $originY = $margin + $titleHeight;

        imagefilledrectangle($im, $originX, $originY, $originX + $tableWidth, $originY + $headerHeight, $headerBg);
        imagerectangle($im, $originX, $originY, $originX + $tableWidth, $originY + $headerHeight, $borderColor);

        $x = $originX;
        foreach ($headers as $index => $header) {
            $width = $colWidths[$index] ?? 120;
            $lines = pdf_export_wrap_lines($font, $fontSizeSmall, $header, $width - 12);
            $lineY = $originY + 22;
            foreach (array_slice($lines, 0, 3) as $line) {
                imagettftext($im, $fontSizeSmall, 0, $x + 6, $lineY, $textColor, $font, $line);
                $lineY += 16;
            }
            if ($index > 0) {
                imageline($im, $x, $originY, $x, $originY + $headerHeight + count($chunkRows) * $rowHeight, $borderColor);
            }
            $x += $width;
        }

        $y = $originY + $headerHeight;
        foreach ($chunkRows as $row) {
            imagerectangle($im, $originX, $y, $originX + $tableWidth, $y + $rowHeight, $borderColor);
            $x = $originX;
            foreach ($row as $index => $cell) {
                $width = $colWidths[$index] ?? 120;
                $lines = pdf_export_wrap_lines($font, $fontSize, (string) $cell, $width - 12);
                $lineY = $y + 24;
                foreach (array_slice($lines, 0, 2) as $line) {
                    imagettftext($im, $fontSize, 0, $x + 6, $lineY, $textColor, $font, $line);
                    $lineY += 16;
                }
                $x += $width;
            }
            $y += $rowHeight;
        }

        ob_start();
        imagepng($im);
        imagedestroy($im);
        $pages[] = (string) ob_get_clean();
    }

    return $pages;
}

/** @param list<string> $pngPages */
function pdf_export_png_pages_to_pdf(array $pngPages): string
{
    if ($pngPages === []) {
        return '';
    }

    $pageWidthPt = 842.0;
    $pageHeightPt = 595.0;
    $objects = [];
    $pageKids = [];

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

    foreach ($pngPages as $pageIndex => $png) {
        $jpeg = pdf_export_png_to_jpeg($png);
        if ($jpeg === '') {
            continue;
        }

        $imageObjNum = 3 + ($pageIndex * 3);
        $contentObjNum = $imageObjNum + 1;
        $pageObjNum = $imageObjNum + 2;
        $pageKids[] = $pageObjNum . ' 0 R';

        $objects[$imageObjNum] = '<< /Type /XObject /Subtype /Image /Width 1754 /Height 1240 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg) . ' >>' . "\nstream\n" . $jpeg . "\nendstream";
        $content = "q\n{$pageWidthPt} 0 0 {$pageHeightPt} 0 0 cm\n/Img{$pageIndex} Do\nQ\n";
        $objects[$contentObjNum] = '<< /Length ' . strlen($content) . ' >>' . "\nstream\n" . $content . "\nendstream";
        $objects[$pageObjNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidthPt} {$pageHeightPt}] /Resources << /XObject << /Img{$pageIndex} {$imageObjNum} 0 R >> >> /Contents {$contentObjNum} 0 R >>";
    }

    $kids = implode(' ', $pageKids);
    $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageKids) . ' >>';

    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $maxObj = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObj; $i++) {
        $offset = $offsets[$i] ?? 0;
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";

    return $pdf;
}

function pdf_export_png_to_jpeg(string $png): string
{
    $im = imagecreatefromstring($png);
    if ($im === false) {
        return '';
    }

    ob_start();
    imagejpeg($im, null, 88);
    imagedestroy($im);

    return (string) ob_get_clean();
}

function pdf_export_send(string $binary, string $filename): void
{
    if (headers_sent()) {
        return;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . (string) strlen($binary));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $binary;
}

function pdf_export_site_title(): string
{
    return (string) config('app.name', 'CHP2026');
}

function pdf_export_matrix_match_header(array $match): string
{
    $home = pdf_export_short_text((string) $match['home_team'], 14);
    $away = pdf_export_short_text((string) $match['away_team'], 14);
    $date = date('d.m', strtotime((string) $match['starts_at']));

    return $home . "\n—\n" . $away . "\n" . $date;
}

function pdf_export_matrix_cell(array $match, ?array $cell): string
{
    if ($cell === null) {
        return '—';
    }

    $text = (int) $cell['home_score'] . ' : ' . (int) $cell['away_score'];
    if ($match['home_score'] !== null && $match['away_score'] !== null) {
        $text .= "\n+" . (int) $cell['points'];
    }

    return $text;
}

/**
 * @param array{participants: list<array<string,mixed>>, matches: list<array<string,mixed>>, cells: array<int, array<int, array<string,mixed>>>} $matrix
 */
function pdf_export_predictions_matrix(array $matrix, string $stageLabel): void
{
    $matches = $matrix['matches'];
    $participants = $matrix['participants'];
    if ($matches === [] || $participants === []) {
        http_response_code(404);
        echo 'Нет данных для экспорта.';
        return;
    }

    $nameColWidth = 220;
    $matchColWidth = 92;
    $pageInnerWidth = 1754 - 72;
    $matchColsPerPage = max(1, (int) floor(($pageInnerWidth - $nameColWidth) / $matchColWidth));
    $matchChunks = array_chunk($matches, $matchColsPerPage);

    $titleBase = pdf_export_site_title() . ' · Матрица прогнозов · ' . $stageLabel . ' · ' . date('d.m.Y H:i');
    $pngPages = [];

    foreach ($matchChunks as $chunkIndex => $matchChunk) {
        $headers = ['Участник'];
        foreach ($matchChunk as $match) {
            $headers[] = pdf_export_matrix_match_header($match);
        }

        $rows = [];
        foreach ($participants as $participant) {
            $userId = (int) $participant['id'];
            $row = [(string) $participant['name']];
            foreach ($matchChunk as $match) {
                $matchId = (int) $match['id'];
                $cell = $matrix['cells'][$userId][$matchId] ?? null;
                $row[] = pdf_export_matrix_cell($match, $cell);
            }
            $rows[] = $row;
        }

        $colWidths = [$nameColWidth];
        foreach ($matchChunk as $_) {
            $colWidths[] = $matchColWidth;
        }

        $chunkTitle = $titleBase;
        if (count($matchChunks) > 1) {
            $chunkTitle .= ' · матчи ' . ($chunkIndex + 1) . '/' . count($matchChunks);
        }

        foreach (pdf_export_table_images($chunkTitle, $headers, $rows, $colWidths) as $png) {
            $pngPages[] = $png;
        }
    }

    $pdf = pdf_export_png_pages_to_pdf($pngPages);
    if ($pdf === '') {
        http_response_code(503);
        echo 'PDF недоступен на сервере (GD или шрифт).';
        return;
    }

    pdf_export_send($pdf, 'matrica-prognozov-' . date('Y-m-d') . '.pdf');
}

/** @param list<array<string,mixed>> $predictions */
function pdf_export_match_predictions(array $match, array $predictions): void
{
    if ($predictions === []) {
        http_response_code(404);
        echo 'Нет прогнозов для экспорта.';
        return;
    }

    $hasResult = $match['home_score'] !== null && $match['away_score'] !== null;
    $headers = ['Участник', 'Прогноз'];
    $colWidths = [360, 120];
    if ($hasResult) {
        $headers[] = 'Очки';
        $headers[] = 'Статус';
        $colWidths[] = 90;
        $colWidths[] = 220;
    }

    $rows = [];
    foreach ($predictions as $prediction) {
        $row = [
            (string) $prediction['name'],
            (int) $prediction['home_score'] . ' : ' . (int) $prediction['away_score'],
        ];
        if ($hasResult) {
            $row[] = (string) (int) $prediction['points'];
            $row[] = (string) ($prediction['reason'] ?: 'Нет очков');
        }
        $rows[] = $row;
    }

    $title = pdf_export_site_title()
        . ' · ' . (string) $match['home_team'] . ' — ' . (string) $match['away_team']
        . ' · ' . date('d.m.Y H:i', strtotime((string) $match['starts_at']))
        . ' · ' . date('d.m.Y H:i');

    $pngPages = pdf_export_table_images($title, $headers, $rows, $colWidths);
    $pdf = pdf_export_png_pages_to_pdf($pngPages);
    if ($pdf === '') {
        http_response_code(503);
        echo 'PDF недоступен на сервере (GD или шрифт).';
        return;
    }

    $slug = 'match-' . (int) $match['id'] . '-prognozy-' . date('Y-m-d') . '.pdf';
    pdf_export_send($pdf, $slug);
}

/** @param list<array<string,mixed>> $leaders */
function pdf_export_leaderboard(array $leaders, bool $championPublic, array $championTeamsByUser): void
{
    if ($leaders === []) {
        http_response_code(404);
        echo 'Нет данных для экспорта.';
        return;
    }

    $headers = ['#', 'Участник'];
    $colWidths = [40, 320];
    if ($championPublic) {
        $headers[] = 'Прогноз';
        $colWidths[] = 180;
    }
    $headers = array_merge($headers, ['Очки', 'Точные', 'Исходы', 'Прогнозы', 'Очки чемп.', 'Итого']);
    $colWidths = array_merge($colWidths, [70, 70, 70, 80, 90, 70]);

    $rows = [];
    foreach ($leaders as $index => $leader) {
        $leaderId = (int) $leader['id'];
        $row = [
            (string) ($index + 1),
            (string) $leader['name'],
        ];
        if ($championPublic) {
            $row[] = (string) ($championTeamsByUser[$leaderId] ?? '—');
        }
        $row[] = (string) (int) $leader['match_points'];
        $row[] = (string) (int) $leader['exact_scores_count'];
        $row[] = (string) (int) $leader['outcomes_count'];
        $row[] = (string) (int) $leader['predictions_count'];
        $row[] = (string) (int) $leader['champion_points'];
        $row[] = (string) (int) $leader['total_points'];
        $rows[] = $row;
    }

    $title = pdf_export_site_title() . ' · Рейтинг участников · ' . date('d.m.Y H:i');
    $pngPages = pdf_export_table_images($title, $headers, $rows, $colWidths);
    $pdf = pdf_export_png_pages_to_pdf($pngPages);
    if ($pdf === '') {
        http_response_code(503);
        echo 'PDF недоступен на сервере (GD или шрифт).';
        return;
    }

    pdf_export_send($pdf, 'rejting-' . date('Y-m-d') . '.pdf');
}
