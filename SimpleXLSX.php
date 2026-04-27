<?php
/**
 * SimpleXLSX - Leitor simples de arquivos XLSX
 */
class SimpleXLSX {
    private $sheets = [];
    
    public function __construct($filename) {
        $zip = new ZipArchive();
        if ($zip->open($filename) !== TRUE) {
            throw new Exception('Não foi possível abrir o arquivo');
        }
        
        // Ler workbook.xml para obter nomes das abas
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $sheetNames = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $sheetNames[] = (string)$sheet['name'];
        }
        
        // Ler cada planilha
        $sheetIndex = 1;
        foreach ($sheetNames as $name) {
            $sheetXML = $zip->getFromName("xl/worksheets/sheet{$sheetIndex}.xml");
            if ($sheetXML) {
                $this->sheets[$name] = $this->parseSheet($sheetXML);
            }
            $sheetIndex++;
        }
        
        $zip->close();
    }
    
    private function parseSheet($xml) {
        $sheet = simplexml_load_string($xml);
        $data = [];
        
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $value = '';
                if (isset($cell->v)) {
                    $value = (string)$cell->v;
                } elseif (isset($cell->is->t)) {
                    $value = (string)$cell->is->t;
                }
                $rowData[] = $value;
            }
            $data[] = $rowData;
        }
        
        return $data;
    }
    
    public function getSheets() {
        return $this->sheets;
    }
}
?>
