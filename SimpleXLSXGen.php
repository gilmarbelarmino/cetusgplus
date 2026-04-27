<?php
/**
 * SimpleXLSXGen - Gerador simples de arquivos XLSX
 */
class SimpleXLSXGen {
    private $sheets = [];
    
    public function addSheet($data, $name = 'Sheet1') {
        $this->sheets[] = ['name' => $name, 'data' => $data];
    }
    
    public function saveAs($filename) {
        $zip = new ZipArchive();
        if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        
        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->getContentTypes());
        
        // _rels/.rels
        $zip->addFromString('_rels/.rels', $this->getRels());
        
        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRels());
        
        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->getWorkbook());
        
        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->getStyles());
        
        // xl/worksheets/sheet*.xml
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->getSheet($sheet['data']));
        }
        
        $zip->close();
        return true;
    }
    
    private function getContentTypes() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '</Types>';
        return $xml;
    }
    
    private function getRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }
    
    private function getWorkbookRels() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }
    
    private function getWorkbook() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<sheet name="' . htmlspecialchars($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }
    
    private function getStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></styleSheet>';
    }
    
    private function getSheet($data) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';
        
        foreach ($data as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 1) . '">';
            $colIndex = 0;
            foreach ($row as $cell) {
                $cellRef = $this->getCellRef($colIndex, $rowIndex);
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . htmlspecialchars($cell ?? '') . '</t></is></c>';
                $colIndex++;
            }
            $xml .= '</row>';
        }
        
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }
    
    private function getCellRef($col, $row) {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = floor($col / 26) - 1;
        }
        return $letter . ($row + 1);
    }
}
?>
