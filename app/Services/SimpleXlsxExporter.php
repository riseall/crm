<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SimpleXlsxExporter
{
    /**
     * Create a native OpenXML XLSX file and return the path to the temporary file.
     *
     * @param array $headers List of string headers
     * @param array $rows List of row arrays (indexed or associative)
     * @param string $sheetName Sheet name (max 31 chars)
     * @return string Path to temporary .xlsx file
     */
    public static function create(array $headers, array $rows, string $sheetName = 'Sheet1'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file arsip zip untuk XLSX.');
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $cleanSheetName = htmlspecialchars(substr($sheetName, 0, 31), ENT_QUOTES, 'UTF-8');
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="' . $cleanSheetName . '" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF3699FF"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1">'
            . '<border><left/><right/><top/><bottom/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // xl/worksheets/sheet1.xml
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        $rowIndex = 1;

        // Header Row (styled with s="1")
        if (!empty($headers)) {
            $sheetXml .= '<row r="' . $rowIndex . '">';
            $colIndex = 0;
            foreach ($headers as $header) {
                $cellRef = self::columnLetter($colIndex) . $rowIndex;
                $val = htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8');
                $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . $val . '</t></is></c>';
                $colIndex++;
            }
            $sheetXml .= '</row>';
            $rowIndex++;
        }

        // Data Rows
        foreach ($rows as $row) {
            $sheetXml .= '<row r="' . $rowIndex . '">';
            $colIndex = 0;
            foreach ($row as $cell) {
                $cellRef = self::columnLetter($colIndex) . $rowIndex;
                $val = htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8');
                $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $val . '</t></is></c>';
                $colIndex++;
            }
            $sheetXml .= '</row>';
            $rowIndex++;
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        return $tempFile;
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(($index % 26) + 65) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }
}
