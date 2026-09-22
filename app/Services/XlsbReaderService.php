<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

class XlsbReaderService
{
    /**
     * Determine if a given file is a valid XLSB workbook.
     */
    public function isXlsb(string $filePath): bool
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return false;
        }

        $hasWorkbook = $zip->locateName('xl/workbook.bin') !== false;
        $zip->close();

        return $hasWorkbook;
    }

    /**
     * Get list of all worksheets in the XLSB workbook.
     *
     * @return array<int, array{name: string, relId: string, target: string}>
     */
    public function getWorksheets(string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException("Spreadsheet file not found or not readable: {$filePath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Unable to open the file as a valid XLSB workbook archive.');
        }

        // 1. Extract relationships from xl/_rels/workbook.bin.rels
        $relsXml = $zip->getFromName('xl/_rels/workbook.bin.rels');
        $relationships = [];
        if ($relsXml !== false) {
            $xml = @simplexml_load_string($relsXml);
            if ($xml !== false) {
                foreach ($xml->Relationship as $rel) {
                    $id = (string) $rel['Id'];
                    $target = (string) $rel['Target'];
                    $type = (string) $rel['Type'];
                    if (str_contains($type, 'worksheet')) {
                        $relationships[$id] = 'xl/'.ltrim($target, '/');
                    }
                }
            }
        }

        // 2. Extract sheet names and relationship IDs from xl/workbook.bin
        $wbBin = $zip->getFromName('xl/workbook.bin');
        if ($wbBin === false) {
            $zip->close();
            throw new RuntimeException('Workbook definition (xl/workbook.bin) missing in XLSB archive.');
        }

        $sheets = [];
        $offset = 0;
        $len = strlen($wbBin);

        while ($offset < $len) {
            $record = $this->readRecord($wbBin, $offset, $len);
            if ($record === null) {
                break;
            }

            // Record type 156 (0x009C) is BrtBundleSh (sheet metadata)
            if ($record['type'] === 156) {
                $payload = $record['data'];
                $foundRelId = null;
                $sheetName = null;

                // Inspect potential offsets for strRelID (XLNullableWideString)
                for ($testOffset = 4; $testOffset <= 16; $testOffset += 4) {
                    if (strlen($payload) >= $testOffset + 4) {
                        $charCount = unpack('V', substr($payload, $testOffset, 4))[1];
                        if ($charCount > 0 && $charCount < 50 && strlen($payload) >= $testOffset + 4 + ($charCount * 2)) {
                            $candidate = mb_convert_encoding(substr($payload, $testOffset + 4, $charCount * 2), 'UTF-8', 'UTF-16LE');
                            if (str_starts_with($candidate, 'rId')) {
                                $foundRelId = $candidate;
                                $nameOffset = $testOffset + 4 + ($charCount * 2);
                                if (strlen($payload) >= $nameOffset + 4) {
                                    $nameCharCount = unpack('V', substr($payload, $nameOffset, 4))[1];
                                    if ($nameCharCount > 0 && strlen($payload) >= $nameOffset + 4 + ($nameCharCount * 2)) {
                                        $sheetName = mb_convert_encoding(substr($payload, $nameOffset + 4, $nameCharCount * 2), 'UTF-8', 'UTF-16LE');
                                    }
                                }
                                break;
                            }
                        }
                    }
                }

                if ($foundRelId !== null) {
                    $target = $relationships[$foundRelId] ?? 'xl/worksheets/sheet'.(count($sheets) + 1).'.bin';
                    $sheets[] = [
                        'name' => $sheetName ?? ('Sheet '.(count($sheets) + 1)),
                        'relId' => $foundRelId,
                        'target' => $target,
                    ];
                }
            }
        }

        $zip->close();

        return $sheets;
    }

    /**
     * Read tabular data for a specific worksheet target in the XLSB workbook.
     *
     * @return array<int, array<int, mixed>> Normalized 2D array of rows
     */
    public function readWorksheet(string $filePath, string $sheetTarget): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Unable to open the file as a valid XLSB workbook archive.');
        }

        // 1. Read shared strings from xl/sharedStrings.bin
        $strings = $this->loadSharedStrings($zip);

        // 2. Read the worksheet binary stream
        $wsBin = $zip->getFromName($sheetTarget);
        if ($wsBin === false) {
            $zip->close();
            throw new RuntimeException("Worksheet part '{$sheetTarget}' not found in XLSB archive.");
        }

        $offset = 0;
        $len = strlen($wsBin);
        $currentRow = 0;
        $rawRows = [];
        $maxCol = 0;

        while ($offset < $len) {
            $record = $this->readRecord($wsBin, $offset, $len);
            if ($record === null) {
                break;
            }

            $type = $record['type'];
            $payload = $record['data'];

            switch ($type) {
                case 0: // ROW_HDR
                    if (strlen($payload) >= 4) {
                        $currentRow = unpack('V', substr($payload, 0, 4))[1];
                    }
                    break;

                case 1: // CELL_BLANK
                    if (strlen($payload) >= 4) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $rawRows[$currentRow][$col] = '';
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 2: // CELL_RK
                    if (strlen($payload) >= 12) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $rk = unpack('V', substr($payload, 8, 4))[1];
                        $rawRows[$currentRow][$col] = $this->decodeRk($rk);
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 4: // CELL_BOOL
                    if (strlen($payload) >= 9) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $rawRows[$currentRow][$col] = ord($payload[8]) !== 0 ? 'TRUE' : 'FALSE';
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 5: // CELL_REAL (64-bit IEEE-754 double)
                    if (strlen($payload) >= 16) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $val = unpack('d', substr($payload, 8, 8))[1];
                        $rawRows[$currentRow][$col] = $val;
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 6: // CELL_ST (inline string)
                    if (strlen($payload) >= 12) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $charCount = unpack('V', substr($payload, 8, 4))[1];
                        $str = '';
                        if ($charCount > 0 && strlen($payload) >= 12 + ($charCount * 2)) {
                            $str = mb_convert_encoding(substr($payload, 12, $charCount * 2), 'UTF-8', 'UTF-16LE');
                        }
                        $rawRows[$currentRow][$col] = $str;
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 7: // CELL_ISST (shared string index)
                    if (strlen($payload) >= 12) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $sstIdx = unpack('V', substr($payload, 8, 4))[1];
                        $rawRows[$currentRow][$col] = $strings[$sstIdx] ?? '';
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 8: // FMLA_STRING (formula result: string)
                    if (strlen($payload) >= 12) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $charCount = unpack('V', substr($payload, 8, 4))[1];
                        $str = '';
                        if ($charCount > 0 && strlen($payload) >= 12 + ($charCount * 2)) {
                            $str = mb_convert_encoding(substr($payload, 12, $charCount * 2), 'UTF-8', 'UTF-16LE');
                        }
                        $rawRows[$currentRow][$col] = $str;
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 9: // FMLA_NUM (formula result: number)
                    if (strlen($payload) >= 16) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $val = unpack('d', substr($payload, 8, 8))[1];
                        $rawRows[$currentRow][$col] = $val;
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 10: // FMLA_BOOL (formula result: boolean)
                    if (strlen($payload) >= 9) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $rawRows[$currentRow][$col] = ord($payload[8]) !== 0 ? 'TRUE' : 'FALSE';
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;

                case 11: // FMLA_ERROR
                    if (strlen($payload) >= 9) {
                        $col = unpack('V', substr($payload, 0, 4))[1];
                        $rawRows[$currentRow][$col] = '#ERROR';
                        if ($col > $maxCol) {
                            $maxCol = $col;
                        }
                    }
                    break;
            }
        }

        $zip->close();

        if (empty($rawRows)) {
            return [];
        }

        // Sort rows by row index
        ksort($rawRows);

        // Normalize rows: fill empty cells with "" so all rows have uniform width
        $normalized = [];
        foreach ($rawRows as $rowIdx => $cols) {
            $normalizedRow = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $normalizedRow[$c] = $cols[$c] ?? '';
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * Load all shared strings from xl/sharedStrings.bin.
     *
     * @return array<int, string>
     */
    private function loadSharedStrings(ZipArchive $zip): array
    {
        $sstBin = $zip->getFromName('xl/sharedStrings.bin');
        if ($sstBin === false) {
            return [];
        }

        $strings = [];
        $offset = 0;
        $len = strlen($sstBin);

        while ($offset < $len) {
            $record = $this->readRecord($sstBin, $offset, $len);
            if ($record === null) {
                break;
            }

            // Record type 19 (0x0013) is SST_ITEM
            if ($record['type'] === 19) {
                $payload = $record['data'];
                if (strlen($payload) >= 5) {
                    $charCount = unpack('V', substr($payload, 1, 4))[1];
                    if ($charCount > 0 && strlen($payload) >= 5 + ($charCount * 2)) {
                        $strings[] = mb_convert_encoding(substr($payload, 5, $charCount * 2), 'UTF-8', 'UTF-16LE');
                    } else {
                        $strings[] = '';
                    }
                } else {
                    $strings[] = '';
                }
            }
        }

        return $strings;
    }

    /**
     * Read a single BIFF12 record from the binary stream.
     *
     * @return array{type: int, size: int, data: string}|null
     */
    private function readRecord(string $data, int &$offset, int $len): ?array
    {
        if ($offset >= $len) {
            return null;
        }

        // 1. Read record type (1 or 2 bytes variable-length)
        $b1 = ord($data[$offset++]);
        if ($b1 & 0x80) {
            if ($offset >= $len) {
                return null;
            }
            $type = ($b1 & 0x7F) | ((ord($data[$offset++]) & 0x7F) << 7);
        } else {
            $type = $b1;
        }

        // 2. Read record size (1 to 4 bytes variable-length)
        $size = 0;
        $shift = 0;
        do {
            if ($offset >= $len) {
                return null;
            }
            $b = ord($data[$offset++]);
            $size |= (($b & 0x7F) << $shift);
            $shift += 7;
        } while (($b & 0x80) && $shift < 28);

        // 3. Extract payload
        if ($offset + $size > $len) {
            $size = max(0, $len - $offset);
        }

        $payload = substr($data, $offset, $size);
        $offset += $size;

        return [
            'type' => $type,
            'size' => $size,
            'data' => $payload,
        ];
    }

    /**
     * Decode an Excel RK number into float or integer.
     */
    private function decodeRk(int $rk): float|int
    {
        $fX100 = ($rk & 0x01) !== 0;
        $fInt = ($rk & 0x02) !== 0;

        if ($fInt) {
            // Signed 30-bit integer
            $val = ($rk >> 2);
            if ($val & 0x20000000) {
                $val |= ~0x3FFFFFFF;
            }
        } else {
            // 30-bit IEEE-754 double: high 30 bits stored, low 34 bits zero
            $floatBits = pack('V2', 0, $rk & 0xFFFFFFFC);
            $val = unpack('d', $floatBits)[1];
        }

        if ($fX100) {
            $val /= 100;
        }

        return $val;
    }
}
