-- =======================
-- Tabela: aberturas
-- =======================
CREATE TABLE aberturas (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  responsavel                             VARCHAR(255) NOT NULL,
  numero_caixa                            INT NOT NULL,
  valor_abertura                          DECIMAL(10,2) NOT NULL,
  valor_total                             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_sangrias                          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_suprimentos                       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_liquido                           DECIMAL(10,2) GENERATED ALWAYS AS (valor_total + valor_suprimentos - valor_sangrias) STORED,
  abertura_datetime                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fechamento_datetime                     DATETIME DEFAULT NULL,
  quantidade_vendas                       INT NOT NULL DEFAULT 0,
  status                                  ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
  empresa_id                              VARCHAR(40) NOT NULL,
  cpf_responsavel                         VARCHAR(14) NOT NULL
);

-- =======================
-- Tabela: adicionarCategoria
-- =======================
CREATE TABLE adicionarCategoria (
  id_categoria                            INT AUTO_INCREMENT PRIMARY KEY,
  nome_categoria                          VARCHAR(255) NOT NULL,
  empresa_id                              VARCHAR(255) NOT NULL,
  data_cadastro                           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: adicionarProdutos
-- =======================
CREATE TABLE adicionarProdutos (
  id_produto                              INT AUTO_INCREMENT PRIMARY KEY,
  nome_produto                            VARCHAR(255) NOT NULL,
  quantidade_produto                      INT NOT NULL,
  preco_produto                           DECIMAL(10,2) NOT NULL,
  imagem_produto                          VARCHAR(255) DEFAULT NULL,
  descricao_produto                       TEXT DEFAULT NULL,
  data_cadastro                           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  id_categoria                            INT NOT NULL,
  id_empresa                              VARCHAR(255) NOT NULL
);

-- =======================
-- Tabela: atestados
-- =======================
CREATE TABLE atestados (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  nome_funcionario                        VARCHAR(255) NOT NULL,
  cpf_usuario                             VARCHAR(14) NOT NULL,
  data_envio                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  data_atestado                           DATE NOT NULL,
  dias_afastado                           INT NOT NULL,
  medico                                  VARCHAR(255) NOT NULL,
  observacoes                             TEXT DEFAULT NULL,
  imagem_atestado                         VARCHAR(255) NOT NULL,
  id_empresa                              VARCHAR(20) NOT NULL
);

-- =======================
-- Tabela: configuracoes_retirada
-- =======================
CREATE TABLE configuracoes_retirada (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa                              VARCHAR(20) NOT NULL,
  retirada                                TINYINT(1) NOT NULL DEFAULT 0,
  tempo_min                               INT NOT NULL,
  tempo_max                               INT NOT NULL
);

-- =======================
-- Tabela: contas
-- =======================
CREATE TABLE contas (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  id_selecionado                          VARCHAR(50) NOT NULL,
  descricao                               VARCHAR(255) NOT NULL,
  valorpago                               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  datatransacao                           DATE NOT NULL,
  responsavel                             VARCHAR(120) NOT NULL,
  statuss                                 ENUM('pago','pendente','futura') NOT NULL DEFAULT 'pendente',
  criado_em                               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em                           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: contas_acesso
-- =======================
CREATE TABLE contas_acesso (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  usuario                                 VARCHAR(100) NOT NULL,
  cpf                                     VARCHAR(14) NOT NULL,
  email                                   VARCHAR(100) NOT NULL,
  senha                                   VARCHAR(255) NOT NULL,
  salt                                    VARCHAR(64) NOT NULL,
  empresa_id                              VARCHAR(50) NOT NULL,
  tipo                                    ENUM('principal','unidade') NOT NULL,
  nivel                                   ENUM('Comum','Admin') NOT NULL,
  autorizado                              ENUM('sim','nao') DEFAULT 'nao',
  data_cadastro                           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `contas_acesso`
  (`id`, `usuario`, `cpf`, `email`, `senha`, `salt`, `empresa_id`, `tipo`, `nivel`, `autorizado`, `data_cadastro`)
VALUES
  (1, 'Suporte', '067.368.982-42', 'suportecodegeek@gmail.com', 'e56794087c15d18fdae660d31c5c6ac0cbc5a22544ef7b8206b4be8d78538383', '3c2708585fb0702dd73968cb6c401834', 'principal_1', 'principal', 'Admin', 'sim', '2025-05-11 16:55:25'),
  (2, 'Natália Régia dos Santos Queiroz Andrade', '08964433459', 'nataliaregiadossantos@gmail.com', '85799eac73048fa81555551cf165a67ed4ac69831f8f1c5ecf2daf6c0508e2c2', '9fdc29c3e22ace4ef9e1dc4a86222bed', 'principal_1', 'principal', 'Admin', 'sim', '2025-05-11 17:06:32'),
  (3, 'Ingrid da Silva Sales', '10609820486', 'salessilvaingrid@gmail.com', '329a4d9dc7a07b5c656621e311620a9fe9f05ee6716bc246f4490a86ceacbf66', '1b042d40e246393e19417e27a062a416', 'principal_1', 'principal', 'Comum', 'sim', '2025-05-11 19:46:49'),
  (4, 'Naiara Kaliane Da Silva Souza', '122.024.344-29', 'Naiaraguedesouza@gmail.com', '2957d3ef4a7c824b3822fea9e42180a523c5b9f3e29ea560d0fc8f3b3c77ca7d', 'cbd5c13da4416b951142960ca0e8dec6', 'principal_1', 'principal', 'Comum', 'sim', '2025-05-28 19:18:42');


-- =======================
-- Tabela: endereco_empresa
-- =======================
CREATE TABLE endereco_empresa (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(255) NOT NULL,
  cnpj                                    VARCHAR(20) NOT NULL,
  cep                                     VARCHAR(9) NOT NULL,
  endereco                                VARCHAR(255) NOT NULL,
  bairro                                  VARCHAR(100) NOT NULL,
  numero                                  VARCHAR(10) NOT NULL,
  cidade                                  VARCHAR(100) NOT NULL,
  complemento                             VARCHAR(255) DEFAULT NULL,
  uf                                      VARCHAR(2) NOT NULL,
  data_criacao                            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao                        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: entregas
-- =======================
CREATE TABLE entregas (
  id_entrega                              INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa                              VARCHAR(20) NOT NULL,
  entrega                                 TINYINT(1) NOT NULL DEFAULT 0,
  tempo_min                               INT NOT NULL DEFAULT 0,
  tempo_max                               INT NOT NULL DEFAULT 0
);


-- =======================
-- Tabela: entrega_taxas
-- =======================
CREATE TABLE entrega_taxas (
  id_taxa                                 INT AUTO_INCREMENT PRIMARY KEY,
  id_entrega                              INT NOT NULL,
  idSelecionado                           VARCHAR(50) NOT NULL,
  sem_taxa                                TINYINT(1) NOT NULL DEFAULT 0,
  taxa_unica                              TINYINT(1) NOT NULL DEFAULT 0
);

-- =======================
-- Tabela: entrega_taxas_unica
-- =======================
CREATE TABLE entrega_taxas_unica (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  id_entrega                              INT NOT NULL,
  taxa_unica                              TINYINT(1) NOT NULL DEFAULT 0,
  valor_taxa                              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  id_selecionado                          VARCHAR(255) DEFAULT NULL
);

-- =======================
-- Tabela: escalas
-- =======================
CREATE TABLE escalas (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  nome_escala                             VARCHAR(100) NOT NULL,
  data_escala                             DATETIME DEFAULT CURRENT_TIMESTAMP,
  empresa_id                              VARCHAR(30) NOT NULL
);

-- =======================
-- Tabela: estoque
-- =======================
CREATE TABLE estoque (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  fornecedor_id                           INT NOT NULL, 
  empresa_id                              VARCHAR(40) NOT NULL,
  codigo_produto                          VARCHAR(100) NOT NULL,
  nome_produto                            VARCHAR(255) NOT NULL,
  categoria_produto                       VARCHAR(100) DEFAULT NULL,
  quantidade_produto                      INT DEFAULT 0,
  preco_produto                           DECIMAL(10,2) NOT NULL,
  preco_custo                             DECIMAL(10,2) DEFAULT NULL,
  status_produto                          VARCHAR(50) NOT NULL,
  ncm                                     VARCHAR(10) NOT NULL,
  cest                                    VARCHAR(10) DEFAULT NULL,
  cfop                                    VARCHAR(10) NOT NULL,
  origem                                  VARCHAR(2) NOT NULL DEFAULT '0',
  tributacao                              VARCHAR(10) NOT NULL DEFAULT '102',
  unidade                                 VARCHAR(10) NOT NULL DEFAULT 'UN',
  codigo_barras                           VARCHAR(50) DEFAULT NULL,
  codigo_anp                              VARCHAR(10) DEFAULT NULL,
  informacoes_adicionais                  TEXT DEFAULT NULL,
  peso_bruto                              DECIMAL(10,3) DEFAULT NULL,
  peso_liquido                            DECIMAL(10,3) DEFAULT NULL,
  aliquota_icms                           DECIMAL(5,2) DEFAULT NULL,
  aliquota_pis                            DECIMAL(5,2) DEFAULT NULL,
  aliquota_cofins                         DECIMAL(5,2) DEFAULT NULL,
  created_at                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, 
  updated_at                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_estoque_fornecedor_id (fornecedor_id)
);

-- =======================
-- Tabela: folgas
-- =======================
CREATE TABLE folgas (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  cpf                                     VARCHAR(20) NOT NULL,
  nome                                    VARCHAR(100) NOT NULL,
  data_folga                              DATE NOT NULL
);

-- =======================
-- Tabela: formas_pagamento
-- =======================
CREATE TABLE formas_pagamento (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(255) NOT NULL,
  dinheiro                                TINYINT(1) NOT NULL DEFAULT 0,
  pix                                     TINYINT(1) NOT NULL DEFAULT 0,
  cartaoDebito                            TINYINT(1) NOT NULL DEFAULT 0,
  cartaoCredito                           TINYINT(1) NOT NULL DEFAULT 0
);

-- =======================
-- Tabela: fornecedores
-- =======================
CREATE TABLE fornecedores (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(200) NOT NULL,
  nome_fornecedor                         VARCHAR(100) NOT NULL,
  cnpj_fornecedor                         VARCHAR(18) NOT NULL,
  email_fornecedor                        VARCHAR(100) NOT NULL,
  telefone_fornecedor                     VARCHAR(20) NOT NULL,
  endereco_fornecedor                     VARCHAR(255) NOT NULL,
  created_at                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: funcionarios
-- =======================
CREATE TABLE funcionarios (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(50) NOT NULL,
  nome                                    VARCHAR(150) NOT NULL,
  data_nascimento                         DATE NOT NULL,
  cpf                                     VARCHAR(14) NOT NULL,
  rg                                      VARCHAR(20) NOT NULL,
  pis                                     VARCHAR(20) NOT NULL,
  matricula                               VARCHAR(20) NOT NULL,
  data_admissao                           DATE NOT NULL,
  cargo                                   VARCHAR(100) NOT NULL,
  setor                                   VARCHAR(50) NOT NULL,
  salario                                 DECIMAL(10,2) NOT NULL,
  escala                                  VARCHAR(50) NOT NULL,
  dia_inicio                              ENUM('domingo','segunda','terca','quarta','quinta','sexta','sabado') NOT NULL,
  dia_folga                               ENUM('domingo','segunda','terca','quarta','quinta','sexta','sabado') NOT NULL,
  entrada                                 TIME DEFAULT NULL,
  saida_intervalo                         TIME DEFAULT NULL,
  retorno_intervalo                       TIME DEFAULT NULL,
  saida_final                             TIME DEFAULT NULL,
  email                                   VARCHAR(150) NOT NULL,
  telefone                                VARCHAR(20) NOT NULL,
  endereco                                VARCHAR(255) NOT NULL,
  cidade                                  VARCHAR(100) NOT NULL,
  criado_em                               TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: funcionarios_acesso
-- =======================
CREATE TABLE funcionarios_acesso (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  usuario                                 VARCHAR(100) NOT NULL,
  cpf                                     VARCHAR(14) NOT NULL,
  email                                   VARCHAR(150) NOT NULL,
  senha                                   VARCHAR(64) NOT NULL,
  salt                                    VARCHAR(32) NOT NULL,
  empresa_id                              VARCHAR(30) NOT NULL,
  tipo                                    ENUM('principal','filial','franquia') NOT NULL,
  nivel                                   ENUM('Comum','Admin') NOT NULL,
  autorizado                              ENUM('sim','nao') NOT NULL DEFAULT 'nao',
  criado_em                               TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  codigo_verificacao                      VARCHAR(6) DEFAULT NULL,
  codigo_verificacao_expires_at           INT NOT NULL DEFAULT 0
);

INSERT INTO `funcionarios_acesso`
  (`id`, `usuario`, `cpf`, `email`, `senha`, `salt`, `empresa_id`, `tipo`, `nivel`, `autorizado`, `criado_em`, `codigo_verificacao`, `codigo_verificacao_expires_at`)
VALUES
  (1, 'Naiara Kaliane da Silva Souza', '12202434429', 'naiaraguedesouza@gmail.com', '82f90a87e828fa9ff7b37e522b6800ad7b0a6e71ecf408311171e1fcfc0c461f', '2573fea157b64722d0e6eb654d221a2f', 'principal_1', 'principal', 'Comum', 'sim', '2025-05-11 17:44:34', NULL, 0),
  (2, 'Fernanda Nilton de pontes',       '70250569442', 'fernandan0711@gmail.com', '6771799181811eceae0ea84da19b2025e21a5189d30706ca9247d9e963df7498', 'a419a7f2467a96d65be6b1b41b4a5f02', 'principal_1', 'principal', 'Comum', 'nao', '2025-05-11 20:45:30', NULL, 0),
  (3, 'Fábio Vinícius',                  '11328190404', 'fabiooawyi@gmail.com',    '8332ff0375c921f43d7f6d0d3413aa124944251ef7f29a5d87faaeb4489467c4', 'fb2c8b685941eee80e62e78fb86a0b16', 'principal_1', 'principal', 'Comum', 'nao', '2025-05-15 18:46:06', NULL, 0),
  (4, 'Maria Clara Marroque de Morais',  '11103951408', 'marroquemariaclara@gmail.com', '4d5fdb1e33be852f5414b4c4f423a0448197c6b5abfc42f53b71f72c9d3556b0', 'eac5d7d6243072d5778f57f1a39cd236', 'principal_1', 'principal', 'Comum', 'nao', '2025-05-22 19:39:42', NULL, 0),
  (5, 'funcionário TESTE',               '06736898242', 'lucaasscorrea0@gmail.com','de98c30f5c4f8c68fd93c7b0751cc3a4dd81aa41ac9da3eb44478185c52091f8', 'e58138ef264e53ee2aa3507432c3f266', 'principal_1', 'principal', 'Comum', 'sim', '2025-05-26 14:20:22', NULL, 0),
  (6, 'Ingrid',                          '10609820486', 'Ingridsilvasales36@gmail.com', 'a13b980fb0b1246a3362450d35ff8ebf9d5342ad467368b188d61c97f7b4729c', 'e251c6b006adacdc90b0da00cc873bbf', 'principal_1', 'principal', 'Comum', 'sim', '2025-05-28 18:44:53', NULL, 0),
  (7, 'Rita de Cássia',                  '70665323417', 'ribeirorita049@gmail.com','0699f7f5d595d8c351383a5daef32069f0ea09af28030e4cd2e8ce278ab64a98', 'f3ca7328809a9594281ef132cf60f5b5', 'principal_1', 'principal', 'Comum', 'sim', '2025-06-04 15:33:12', NULL, 0),
  (8, 'Luciane Pereira de Freitas',      '00393424251', 'walecianny@gmail.com',    'b5c1727ad176e8a68d78295245c9612e91c0a037213a7b1989bc49bbbc59165e', '68ddbb23a8d727c6aecd5487ce975108', 'principal_1', 'principal', 'Comum', 'sim', '2025-07-09 20:43:26', NULL, 0);


-- =======================
-- Tabela: horarios_funcionamento
-- =======================
CREATE TABLE horarios_funcionamento (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(255) NOT NULL,
  dia_de                                  VARCHAR(20) NOT NULL,
  dia_ate                                 VARCHAR(20) DEFAULT NULL,
  primeira_hora                           TIME NOT NULL,
  termino_primeiro_turno                  TIME NOT NULL,
  comeco_segundo_turno                    TIME DEFAULT NULL,
  termino_segundo_turno                   TIME DEFAULT NULL
);

-- =======================
-- Tabela: integracao_nfce
-- =======================
CREATE TABLE integracao_nfce (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(40) NOT NULL,
  cnpj                                    VARCHAR(14) NOT NULL,
  razao_social                            VARCHAR(255) NOT NULL,
  nome_fantasia                           VARCHAR(255) NOT NULL,
  inscricao_estadual                      VARCHAR(20) NOT NULL,
  inscricao_municipal                     VARCHAR(20) DEFAULT NULL,
  cep                                     VARCHAR(9) DEFAULT NULL,
  logradouro                              VARCHAR(255) DEFAULT NULL,
  numero_endereco                         VARCHAR(20) DEFAULT NULL,
  complemento                             VARCHAR(255) DEFAULT NULL,
  bairro                                  VARCHAR(100) DEFAULT NULL,
  cidade                                  VARCHAR(100) DEFAULT NULL,
  uf                                      CHAR(2) DEFAULT NULL,
  codigo_uf                               INT DEFAULT NULL,
  codigo_municipio                        VARCHAR(7) NOT NULL,
  telefone                                VARCHAR(20) DEFAULT NULL,
  certificado_digital                     VARCHAR(255) DEFAULT NULL,
  senha_certificado                       VARCHAR(100) DEFAULT NULL,
  ambiente                                TINYINT NOT NULL,
  regime_tributario                       TINYINT NOT NULL,
  serie_nfce                              INT DEFAULT 1,
  ultimo_numero_nfce                      INT DEFAULT 1,
  csc                                     VARCHAR(255) DEFAULT NULL,
  csc_id                                  VARCHAR(10) DEFAULT NULL,
  tipo_emissao                            TINYINT NOT NULL DEFAULT 1,
  finalidade                              TINYINT NOT NULL DEFAULT 1,
  ind_pres                                TINYINT NOT NULL DEFAULT 1,
  tipo_impressao                          TINYINT NOT NULL DEFAULT 4,
  criado_em                               DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em                           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: itens_venda
-- =======================
CREATE TABLE itens_venda (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  venda_id                                  INT NOT NULL,
  produto_id                                INT NOT NULL,
  produto_nome                              VARCHAR(255) NOT NULL,
  quantidade                                INT NOT NULL,
  preco_unitario                            DECIMAL(10,2) NOT NULL,
  ncm                                       VARCHAR(10) DEFAULT NULL,
  cest                                      VARCHAR(10) DEFAULT NULL,
  cfop                                      VARCHAR(10) DEFAULT NULL,
  origem                                    VARCHAR(2) DEFAULT NULL,
  tributacao                                VARCHAR(10) DEFAULT NULL,
  unidade                                   VARCHAR(10) DEFAULT NULL,
  informacoes_adicionais                    TEXT DEFAULT NULL
);

-- =======================
-- Tabela: nfce_emitidas
-- =======================
CREATE TABLE nfce_emitidas (
  id                                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id                                VARCHAR(40) NOT NULL,
  venda_id                                  BIGINT DEFAULT NULL,
  ambiente                                  TINYINT NOT NULL,
  serie                                     INT NOT NULL,
  numero                                    INT NOT NULL,
  chave                                     CHAR(44) NOT NULL,
  protocolo                                 VARCHAR(50) DEFAULT NULL,
  status_sefaz                              VARCHAR(10) NOT NULL,
  mensagem                                  VARCHAR(255) DEFAULT NULL,
  xml_nfeproc                               MEDIUMTEXT DEFAULT NULL,
  xml_envio                                 MEDIUMTEXT DEFAULT NULL,
  xml_retorno                               MEDIUMTEXT DEFAULT NULL,
  valor_total                               DECIMAL(12,2) DEFAULT 0.00,
  valor_troco                               DECIMAL(12,2) DEFAULT 0.00,
  tpag_json                                 LONGTEXT,
  created_at                                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: opcionais
-- =======================
CREATE TABLE opcionais (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  id_produto                                INT NOT NULL,
  id_selecionado                            VARCHAR(255) NOT NULL,
  nome                                      VARCHAR(255) NOT NULL,
  preco                                     DECIMAL(10,2) NOT NULL
);

-- =======================
-- Tabela: opcionais_opcoes
-- =======================
CREATE TABLE opcionais_opcoes (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  id_selecao                                INT NOT NULL,
  id_selecionado                            VARCHAR(255) NOT NULL,
  nome                                      VARCHAR(255) NOT NULL,
  preco                                     DECIMAL(10,2) NOT NULL
);

-- =======================
-- Tabela: opcionais_selecoes
-- =======================
CREATE TABLE opcionais_selecoes (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  id_produto                                INT NOT NULL,
  id_selecionado                            VARCHAR(255) NOT NULL,
  titulo                                    VARCHAR(255) NOT NULL,
  minimo                                    INT NOT NULL,
  maximo                                    INT NOT NULL
);

-- =======================
-- Tabela: pedidos
-- =======================
CREATE TABLE pedidos (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                                VARCHAR(50) NOT NULL,
  fornecedor                                VARCHAR(100) NOT NULL,
  produto                                   VARCHAR(100) NOT NULL,
  quantidade                                INT NOT NULL,
  valor                                     DECIMAL(10,2) NOT NULL,
  data_pedido                               DATE NOT NULL,
  status                                    VARCHAR(20) NOT NULL,
  data_cadastro                             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: pontos
-- =======================
CREATE TABLE pontos (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  cpf                                       VARCHAR(15) NOT NULL,
  nome                                      VARCHAR(150) NOT NULL,
  data                                      DATE NOT NULL,
  entrada                                   TIME DEFAULT NULL,
  foto_entrada                              LONGBLOB DEFAULT NULL,
  localizacao_entrada                       VARCHAR(255) DEFAULT NULL,
  saida_intervalo                           TIME DEFAULT NULL,
  foto_saida_intervalo                      LONGBLOB DEFAULT NULL,
  localizacao_saida_intervalo               VARCHAR(255) DEFAULT NULL,
  retorno_intervalo                         TIME DEFAULT NULL,
  foto_retorno_intervalo                    LONGBLOB DEFAULT NULL,
  localizacao_retorno_intervalo             VARCHAR(255) DEFAULT NULL,
  saida_final                               TIME DEFAULT NULL,
  foto_saida_final                          LONGBLOB DEFAULT NULL,
  localizacao_saida_final                   VARCHAR(255) DEFAULT NULL,
  horas_pendentes                           TIME DEFAULT NULL,
  hora_extra                                TIME DEFAULT NULL,
  empresa_id                                VARCHAR(50) NOT NULL
);

-- =======================
-- Tabela: produtos_estoque
-- =======================
CREATE TABLE produtos_estoque (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  nome_produto                              VARCHAR(255) NOT NULL,
  fornecedor_produto                        VARCHAR(255) DEFAULT NULL,
  quantidade_produto                        DECIMAL(10,2) NOT NULL,
  status_produto                            VARCHAR(50) NOT NULL,
  empresa_id                                VARCHAR(30) NOT NULL,
  data_cadastro                             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao                          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: sangrias
-- =======================
CREATE TABLE sangrias (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  valor                                     DECIMAL(10,2) NOT NULL,
  empresa_id                                VARCHAR(40) NOT NULL,
  id_caixa                                  INT NOT NULL,
  responsavel                               VARCHAR(255) NOT NULL,
  cpf_responsavel                           VARCHAR(14) NOT NULL,
  valor_liquido                             DECIMAL(10,2) NOT NULL,
  data_registro                             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: setores
-- =======================
CREATE TABLE setores (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  nome                                      VARCHAR(100) NOT NULL,
  gerente                                   VARCHAR(100) NOT NULL,
  id_selecionado                            VARCHAR(50) NOT NULL,
  criado_em                                 TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: sobre_empresa
-- =======================
CREATE TABLE sobre_empresa (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  id_selecionado                            VARCHAR(255) NOT NULL,
  nome_empresa                              VARCHAR(255) NOT NULL,
  sobre_empresa                             TEXT NOT NULL,
  imagem                                    VARCHAR(255) DEFAULT NULL,
  data_criacao                              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao                          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: solicitacoes_produtos
-- =======================
CREATE TABLE solicitacoes_produtos (
  id                                        INT AUTO_INCREMENT PRIMARY KEY,
  empresa_origem                            VARCHAR(50) NOT NULL,
  empresa_destino                           VARCHAR(50) NOT NULL,
  produto_id                                INT NOT NULL,
  quantidade                                INT NOT NULL,
  justificativa                             TEXT NOT NULL,
  resposta_matriz                           TEXT NOT NULL,
  status                                    ENUM('pendente','aprovada','recusada','entregue') DEFAULT 'pendente',
  data_solicitacao                          DATETIME NOT NULL,
  data_resposta                             
  DATETIME DEFAULT NULL
);

-- =======================
-- Tabela: suprimentos
-- =======================
CREATE TABLE suprimentos (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  valor_suprimento                        DECIMAL(10,2) NOT NULL,
  empresa_id                              VARCHAR(40) NOT NULL,
  id_caixa                                INT NOT NULL,
  valor_liquido                           DECIMAL(10,2) DEFAULT NULL,
  responsavel                             VARCHAR(255) DEFAULT NULL,
  cpf_responsavel                         VARCHAR(14) DEFAULT NULL,
  data_registro                           DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: unidades
-- =======================
CREATE TABLE unidades (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                              VARCHAR(50) NOT NULL,
  nome                                    VARCHAR(100) NOT NULL,
  tipo                                    ENUM('Franquia','Filial') NOT NULL,
  cnpj                                    VARCHAR(18) NOT NULL,
  telefone                                VARCHAR(15) NOT NULL,
  email                                   VARCHAR(100) NOT NULL,
  responsavel                             VARCHAR(100) NOT NULL,
  endereco                                TEXT NOT NULL,
  data_abertura                           DATE NOT NULL,
  status                                  ENUM('Ativa','Inativa') NOT NULL,
  data_cadastro                           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Tabela: vendas
-- =======================
CREATE TABLE vendas (
  id                                      INT AUTO_INCREMENT PRIMARY KEY,
  responsavel                             VARCHAR(255) NOT NULL,
  cpf_responsavel                         VARCHAR(14) NOT NULL,
  cpf_cliente                             VARCHAR(14) DEFAULT NULL,
  forma_pagamento                         VARCHAR(50) NOT NULL,
  valor_total                             DECIMAL(10,2) NOT NULL,
  valor_recebido                          DECIMAL(10,2) DEFAULT 0.00,
  troco                                   DECIMAL(10,2) DEFAULT 0.00,
  empresa_id                              VARCHAR(40) NOT NULL,
  id_caixa                                INT NOT NULL,
  data_venda                              DATETIME NOT NULL,
  chave_nfce                              VARCHAR(44) DEFAULT NULL,
  status_nfce                             VARCHAR(20) DEFAULT NULL
);

-- =======================
-- Tabela: solicitacoes_b2b
-- =======================
CREATE TABLE solicitacoes_b2b (
    id                                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_matriz                             VARCHAR(40)  NOT NULL,            -- ex.: 'principal_1'
    id_solicitante                        VARCHAR(40)  NOT NULL,            -- ex.: 'filial_2'/'franquia_3'/'unidade_2'
    criado_por_usuario_id                 INT UNSIGNED NOT NULL,
    status                                ENUM('pendente','aprovada','reprovada','em_transito','entregue','cancelada')
                                          NOT NULL DEFAULT 'pendente',
    total_estimado                        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    observacao                            TEXT,

    created_at                            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    aprovada_em                           DATETIME,
    enviada_em                            DATETIME,
    entregue_em                           DATETIME,

    -- Índices para navegação/joins
    INDEX idx_b2b_matriz (id_matriz),
    INDEX idx_b2b_solicitante (id_solicitante),
    INDEX idx_b2b_created (created_at),
    INDEX idx_b2b_status (status)
);

-- =======================
-- Tabela: solicitacoes_b2b_itens
-- =======================
CREATE TABLE solicitacoes_b2b_itens (
    id                                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id                        INT UNSIGNED NOT NULL,        -- referência lógica a solicitacoes_b2b.id
    produto_id                            INT UNSIGNED NOT NULL,        -- referência lógica a estoque.id
    codigo_produto                        VARCHAR(100) NOT NULL,
    nome_produto                          VARCHAR(255) NOT NULL,
    unidade                               VARCHAR(10)  NOT NULL DEFAULT 'UN',
    preco_unitario                        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantidade                            INT UNSIGNED  NOT NULL DEFAULT 0,
    subtotal                              DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    created_at                            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Índices “tipo FK” para performance e integridade via aplicação
    INDEX idx_itens_solicitacao (solicitacao_id),
    INDEX idx_itens_produto (produto_id),
    INDEX idx_itens_sol_prod (solicitacao_id, produto_id)
);

-- =======================
-- Tabela: SOLICITACOES_PAGAMENTO
-- =======================
CREATE TABLE solicitacoes_pagamento (
    ID                                      INT AUTO_INCREMENT PRIMARY KEY,
    id_matriz                               VARCHAR(50) NOT NULL,
    id_solicitante                          VARCHAR(50) NOT NULL,
    status                                  ENUM('pendente','aprovado','reprovado') DEFAULT 'pendente',
    fornecedor                              VARCHAR(150) NOT NULL,
    documento                               VARCHAR(80),
    descricao                               TEXT,
    obs_reprovacao                          TEXT,
    vencimento                              DATE NOT NULL,
    valor                                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    comprovante_url                         VARCHAR(300),
    created_at                              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at                              DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

