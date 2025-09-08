<?php

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/nfe_errors.log');

if (php_sapi_name() === 'cli') {
    die("Este script deve ser executado através de um servidor web.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$vendorPath = realpath(__DIR__ . '/../../frentedeloja/caixa/vendor/autoload.php');
if (!file_exists($vendorPath)) {
    $vendorPath = realpath(__DIR__ . '/vendor/autoload.php');
    if (!file_exists($vendorPath)) {
        die("Erro: Arquivo autoload.php não encontrado.");
    }
}

require $vendorPath;

use NFePHP\DA\NFe\Danfce;

// Dados da empresa
$dadosEmpresa = [
    'cnpj' => '12345678000195',
    'razao_social' => 'LOJA DE TESTE LTDA',
    'nome_fantasia' => 'LOJA TESTE',
    'inscricao_estadual' => '123456789',
    'logradouro' => 'RUA TESTE',
    'numero' => '123',
    'bairro' => 'CENTRO',
    'codigo_municipio' => '3550308',
    'municipio' => 'SAO PAULO',
    'uf' => 'SP',
    'cep' => '01001000',
    'telefone' => '1133334444',
    'ambiente' => 2,
    'serie' => 1, // número, não string
    'numero_nfce' => 566, // número, não string
    'csc' => 'TESTE1234',
    'csc_id' => '1'
];

// Dados do cliente
$dadosCliente = [
    'cpf' => '12345678909'
];

// Produtos
$produtos = [
    [
        'codigo' => '001',
        'descricao' => 'PRODUTO TESTE 1',
        'quantidade' => 1,
        'unidade' => 'UN',
        'valor_unitario' => 10.00,
        'valor_total' => 10.00,
        'ncm' => '21069090',
        'cfop' => '5102',
        'cest' => '1709600'
    ],
    [
        'codigo' => '002',
        'descricao' => 'PRODUTO TESTE 2',
        'quantidade' => 2,
        'unidade' => 'UN',
        'valor_unitario' => 15.50,
        'valor_total' => 31.00,
        'ncm' => '21069090',
        'cfop' => '5102',
        'cest' => '1709600'
    ]
];

// Calcula total
$totalProdutos = array_sum(array_column($produtos, 'valor_total'));
$totalNota = $totalProdutos;

$dataHoraEmissao = new DateTime();
$dhEmissao = $dataHoraEmissao->format('Y-m-d\TH:i:sP');
$dhRecbto = $dataHoraEmissao->format('Y-m-d\TH:i:sP');

// Geração da chave de acesso NFCe (simplificado)
$cnpj = preg_replace('/\D/', '', $dadosEmpresa['cnpj']);
$serie = str_pad($dadosEmpresa['serie'], 3, '0', STR_PAD_LEFT);
$numero = str_pad($dadosEmpresa['numero_nfce'], 9, '0', STR_PAD_LEFT);
$ano = $dataHoraEmissao->format('y');
$mes = $dataHoraEmissao->format('m');
$codigoNumerico = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

$chaveAcesso = "35{$ano}{$mes}{$cnpj}65{$serie}{$numero}1{$codigoNumerico}1";

// Cálculo do dígito verificador (módulo 11)
function calcularDV($chave) {
    $peso = 2;
    $soma = 0;
    for ($i = strlen($chave) - 1; $i >= 0; $i--) {
        $soma += intval($chave[$i]) * $peso;
        $peso = ($peso == 9) ? 2 : $peso + 1;
    }
    $mod = $soma % 11;
    $dv = 11 - $mod;
    if ($dv >= 10) $dv = 0;
    return $dv;
}

$dv = calcularDV($chaveAcesso);
$chaveAcessoCompleta = $chaveAcesso . $dv;

// Conteúdo QRCode
$qrCodeContent = sprintf(
    'http://homologacao.sefaz.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p=%s|2|1|1|%.2f|%s|%s|%s|%s',
    $chaveAcessoCompleta,
    $totalNota,
    $dataHoraEmissao->format('dmY'),
    $cnpj,
    $dadosEmpresa['csc_id'],
    substr(md5($dadosEmpresa['csc'] . $chaveAcessoCompleta), 0, 32)
);

// Montagem do XML simplificado (exemplo)
// Em um sistema real, você deve usar uma biblioteca para montar o XML oficial

$xmlString = '<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe xmlns="http://www.portalfiscal.inf.br/nfe">
    <infNFe Id="NFe' . $chaveAcessoCompleta . '" versao="4.00">
      <ide>
        <cUF>35</cUF>
        <cNF>' . substr($codigoNumerico, 0, 8) . '</cNF>
        <natOp>VENDA</natOp>
        <mod>65</mod>
        <serie>' . intval($dadosEmpresa['serie']) . '</serie>
        <nNF>' . intval($dadosEmpresa['numero_nfce']) . '</nNF>
        <dhEmis>' . $dhEmissao . '</dhEmis>
        <tpNF>1</tpNF>
        <idDest>1</idDest>
        <cMunFG>' . intval($dadosEmpresa['codigo_municipio']) . '</cMunFG>
        <tpImp>4</tpImp>
        <tpEmis>1</tpEmis>
        <cDV>' . $dv . '</cDV>
        <tpAmb>' . intval($dadosEmpresa['ambiente']) . '</tpAmb>
        <finNFe>1</finNFe>
        <indFinal>1</indFinal>
        <indPres>1</indPres>
        <procEmi>0</procEmi>
        <verProc>1.0</verProc>
      </ide>
      <emit>
        <CNPJ>' . $cnpj . '</CNPJ>
        <xNome>' . htmlspecialchars($dadosEmpresa['razao_social'], ENT_XML1, 'UTF-8') . '</xNome>
        <xFant>' . htmlspecialchars($dadosEmpresa['nome_fantasia'], ENT_XML1, 'UTF-8') . '</xFant>
        <enderEmit>
          <xLgr>' . htmlspecialchars($dadosEmpresa['logradouro'], ENT_XML1, 'UTF-8') . '</xLgr>
          <nro>' . htmlspecialchars($dadosEmpresa['numero'], ENT_XML1, 'UTF-8') . '</nro>
          <xBairro>' . htmlspecialchars($dadosEmpresa['bairro'], ENT_XML1, 'UTF-8') . '</xBairro>
          <cMun>' . intval($dadosEmpresa['codigo_municipio']) . '</cMun>
          <xMun>' . htmlspecialchars($dadosEmpresa['municipio'], ENT_XML1, 'UTF-8') . '</xMun>
          <UF>' . htmlspecialchars($dadosEmpresa['uf'], ENT_XML1, 'UTF-8') . '</UF>
          <CEP>' . preg_replace('/\D/', '', $dadosEmpresa['cep']) . '</CEP>
          <cPais>1058</cPais>
          <xPais>BRASIL</xPais>
          <fone>' . preg_replace('/\D/', '', $dadosEmpresa['telefone']) . '</fone>
        </enderEmit>
        <IE>' . htmlspecialchars($dadosEmpresa['inscricao_estadual'], ENT_XML1, 'UTF-8') . '</IE>
        <CRT>1</CRT>
      </emit>
      <dest>
        <CPF>' . preg_replace('/\D/', '', $dadosCliente['cpf']) . '</CPF>
      </dest>';

foreach ($produtos as $index => $produto) {
    $item = $index + 1;
    $xmlString .= '
      <det nItem="' . $item . '">
        <prod>
          <cProd>' . htmlspecialchars($produto['codigo'], ENT_XML1, 'UTF-8') . '</cProd>
          <xProd>' . htmlspecialchars($produto['descricao'], ENT_XML1, 'UTF-8') . '</xProd>
          <NCM>' . htmlspecialchars($produto['ncm'], ENT_XML1, 'UTF-8') . '</NCM>
          <CEST>' . htmlspecialchars($produto['cest'], ENT_XML1, 'UTF-8') . '</CEST>
          <CFOP>' . htmlspecialchars($produto['cfop'], ENT_XML1, 'UTF-8') . '</CFOP>
          <uCom>' . htmlspecialchars($produto['unidade'], ENT_XML1, 'UTF-8') . '</uCom>
          <qCom>' . number_format($produto['quantidade'], 2, '.', '') . '</qCom>
          <vUnCom>' . number_format($produto['valor_unitario'], 4, '.', '') . '</vUnCom>
          <vProd>' . number_format($produto['valor_total'], 2, '.', '') . '</vProd>
          <indTot>1</indTot>
        </prod>
        <imposto>
          <vTotTrib>0.00</vTotTrib>
          <ICMS>
            <ICMS00>
              <orig>0</orig>
              <CST>00</CST>
              <modBC>0</modBC>
              <vBC>0.00</vBC>
              <pICMS>0.00</pICMS>
              <vICMS>0.00</vICMS>
            </ICMS00>
          </ICMS>
          <PIS>
            <PISNT>
              <CST>07</CST>
            </PISNT>
          </PIS>
          <COFINS>
            <COFINSNT>
              <CST>07</CST>
            </COFINSNT>
          </COFINS>
        </imposto>
      </det>';
}

$xmlString .= '
      <total>
        <ICMSTot>
          <vBC>0.00</vBC>
          <vICMS>0.00</vICMS>
          <vICMSDeson>0.00</vICMSDeson>
          <vFCP>0.00</vFCP>
          <vBCST>0.00</vBCST>
          <vST>0.00</vST>
          <vFCPST>0.00</vFCPST>
          <vFCPSTRet>0.00</vFCPSTRet>
          <vProd>' . number_format($totalProdutos, 2, '.', '') . '</vProd>
          <vFrete>0.00</vFrete>
          <vSeg>0.00</vSeg>
          <vDesc>0.00</vDesc>
          <vII>0.00</vII>
          <vIPI>0.00</vIPI>
          <vIPIDevol>0.00</vIPIDevol>
          <vPIS>0.00</vPIS>
          <vCOFINS>0.00</vCOFINS>
          <vOutro>0.00</vOutro>
          <vNF>' . number_format($totalNota, 2, '.', '') . '</vNF>
          <vTotTrib>0.00</vTotTrib>
        </ICMSTot>
      </total>
      <transp><modFrete>9</modFrete></transp>
      <pag>
        <detPag><tPag>01</tPag><vPag>' . number_format($totalNota, 2, '.', '') . '</vPag></detPag>
      </pag>
      <infAdic><infCpl>Via do consumidor</infCpl></infAdic>
      <infNFeSupl>
        <qrCode><![CDATA[' . $qrCodeContent . ']]></qrCode>
        <urlChave>' . htmlspecialchars($qrCodeContent, ENT_XML1, 'UTF-8') . '</urlChave>
      </infNFeSupl>
    </infNFe>
  </NFe>
  <protNFe versao="4.00">
    <infProt>
      <tpAmb>' . intval($dadosEmpresa['ambiente']) . '</tpAmb>
      <verAplic>1.0</verAplic>
      <chNFe>' . $chaveAcessoCompleta . '</chNFe>
      <dhRecbto>' . $dhRecbto . '</dhRecbto>
      <nProt>' . substr($chaveAcessoCompleta, 25, 15) . '</nProt>
      <digVal>' . md5($chaveAcessoCompleta) . '</digVal>
      <cStat>100</cStat>
      <xMotivo>Autorizado o uso da NFC-e</xMotivo>
    </infProt>
  </protNFe>
</nfeProc>';

try {
    while (ob_get_level()) ob_end_clean();

    $danfe = new Danfce($xmlString);
    $danfe->debugMode(false);
    $danfe->setPaperWidth(80); // largura do papel em mm
    $danfe->setMargins(2);    // CORREÇÃO: margens entre 0 e 4 mm (antes estava 5)
    $danfe->setFont('arial');
    $pdfContent = $danfe->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="NFCe-' . str_pad($dadosEmpresa['numero_nfce'], 9, '0', STR_PAD_LEFT) . '.pdf"');
    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $pdfContent;
    exit;

} catch (Exception $e) {
    while (ob_get_level()) ob_end_clean();
    error_log("Erro NFC-e: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());

    echo '<html><head><meta charset="utf-8"><title>Erro NFC-e</title></head><body>';
    echo '<h2>Erro na Geração da NFC-e</h2>';
    echo '<p>Ocorreu um erro. Tente novamente.</p>';
    if ($dadosEmpresa['ambiente'] == 2) {
        echo '<pre>' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    echo '</body></html>';
    exit;
}
?>