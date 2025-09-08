<?php

namespace NFePHP\DA\NFe\Traits;

/**
 * Bloco totais da NFCe
 */
trait TraitBlocoIV
{
    protected function blocoIV($y)
    {
        $qtd = $this->det->length;
        $valor = (float) $this->getTagValue($this->ICMSTot, 'vNF');
        $desconto = (float) $this->getTagValue($this->ICMSTot, 'vDesc');
        $frete = (float) $this->getTagValue($this->ICMSTot, 'vFrete');
        $bruto = $valor + $desconto - $frete;

        $aFont = ['font' => $this->fontePadrao, 'size' => 8, 'style' => ''];
        $texto = "Qtde total de itens";
        $this->pdf->textBox(
            $this->margem,
            $y,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'L',
            false,
            '',
            false
        );
        $y1 = $this->pdf->textBox(
            $this->margem + $this->wPrint / 2,
            $y,
            $this->wPrint / 2,
            3,
            $qtd,
            $aFont,
            'T',
            'R',
            false,
            '',
            false
        );

        $texto = "Valor Total R$";
        $this->pdf->textBox(
            $this->margem,
            $y + $y1,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'L',
            false,
            '',
            false
        );
        $texto = number_format($bruto, 2, ',', '.');
        $y2 = $this->pdf->textBox(
            $this->margem + $this->wPrint / 2,
            $y + $y1,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'R',
            false,
            '',
            false
        );

        $texto = "Desconto R$";
        $this->pdf->textBox(
            $this->margem,
            $y + $y1 + $y2,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'L',
            false,
            '',
            false
        );
        $texto = number_format($desconto, 2, ',', '.');
        $y3 = $this->pdf->textBox(
            $this->margem + $this->wPrint / 2,
            $y + $y1 + $y2,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'R',
            false,
            '',
            false
        );

        $texto = "Frete R$";
        $this->pdf->textBox(
            $this->margem,
            $y + $y1 + $y2 + $y3,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'L',
            false,
            '',
            false
        );
        $texto = number_format($frete, 2, ',', '.');
        $y4 = $this->pdf->textBox(
            $this->margem + $this->wPrint / 2,
            $y + $y1 + $y2 + $y3,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'R',
            false,
            '',
            false
        );
        $fsize = 10;
        if ($this->paperwidth < 70) {
            $fsize = 8;
        }
        $aFont = ['font' => $this->fontePadrao, 'size' => $fsize, 'style' => 'B'];
        $texto = "Valor a Pagar R$";
        $this->pdf->textBox(
            $this->margem,
            $y + $y1 + $y2 + $y3 + $y4,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'L',
            false,
            '',
            false
        );
        $texto = number_format($valor, 2, ',', '.');
        $y4 = $this->pdf->textBox(
            $this->margem + $this->wPrint / 2,
            $y + $y1 + $y2 + $y3 + $y4,
            $this->wPrint / 2,
            3,
            $texto,
            $aFont,
            'T',
            'R',
            false,
            '',
            false
        );

        $this->pdf->dashedHLine($this->margem, $this->bloco4H + $y, $this->wPrint, 0.1, 30);
        return $this->bloco4H + $y;
    }
}

?>