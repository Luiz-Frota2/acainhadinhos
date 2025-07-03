

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Estrutura para tabela `adicionarCategoria`
--

CREATE TABLE adicionarCategoria (
  id_categoria                        INT AUTO_INCREMENT PRIMARY KEY,
  nome_categoria                      VARCHAR(255) NOT NULL,
  empresa_id                          INT NOT NULL,
  tipo                                ENUM('principal', 'filial') NOT NULL,
  data_cadastro                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

  -- Evita categorias duplicadas para a mesma empresa e tipo
  UNIQUE KEY unique_categoria_empresa (nome_categoria, empresa_id, tipo),

  -- Índices para performance em buscas por empresa
  INDEX                               (empresa_id),
  INDEX                               (tipo),
  INDEX                               (empresa_id, tipo)
);

--
-- Estrutura para tabela `adicionarProdutos`
--

CREATE TABLE adicionarProdutos (
  id_produto                          INT AUTO_INCREMENT PRIMARY KEY,
  nome_produto                        VARCHAR(255) NOT NULL,
  quantidade_produto                  INT NOT NULL,
  preco_produto                       DECIMAL(10,2) NOT NULL,
  imagem_produto                      VARCHAR(255) DEFAULT NULL,
  descricao_produto                   TEXT DEFAULT NULL,
  data_cadastro                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  id_categoria                        INT NOT NULL,
  id_empresa                          INT NOT NULL
);

--
-- Estrutura para tabela `opcionais`
--

CREATE TABLE opcionais (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_produto                          INT NOT NULL,
  id_selecionado                      INT NOT NULL, 
  nome                                VARCHAR(255) NOT NULL,
  preco                               DECIMAL(10,2) NOT NULL
);

--
-- Estrutura para tabela `opcionais_selecoes`
--

CREATE TABLE opcionais_selecoes (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_produto                          INT NOT NULL,
  id_selecionado                      INT NOT NULL,
  titulo                              VARCHAR(255) NOT NULL,
  minimo                              INT NOT NULL,
  maximo                              INT NOT NULL
);

--
-- Estrutura para tabela `opcionais_opcoes`
--

CREATE TABLE opcionais_opcoes (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_selecao                          INT NOT NULL,
  id_selecionado                      INT NOT NULL, 
  nome                                VARCHAR(255) NOT NULL,
  preco                               DECIMAL(10,2) NOT NULL
);

--
-- Estrutura para tabela `configuracoes_retirada`
--

CREATE TABLE configuracoes_retirada (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa                          VARCHAR(20) NOT NULL,
  retirada                            TINYINT(1) NOT NULL DEFAULT 0,
  tempo_min                           INT NOT NULL,
  tempo_max                           INT NOT NULL,
  UNIQUE                              (id_empresa) 
);

--
-- Estrutura para tabela `entregas`
--

CREATE TABLE entregas (
  id_entrega                          INT PRIMARY KEY AUTO_INCREMENT,
  id_empresa                          VARCHAR(20) NOT NULL, 
  entrega                             TINYINT(1) NOT NULL DEFAULT 0,
  tempo_min                           INT NOT NULL DEFAULT 0,
  tempo_max                           INT NOT NULL DEFAULT 0
);

--
-- Estrutura para tabela `entrega_taxa`
--

CREATE TABLE entrega_taxas (
  id_taxa                             INT PRIMARY KEY AUTO_INCREMENT,
  id_entrega                          INT NOT NULL,
  idSelecionado                       VARCHAR(50) NOT NULL,
  sem_taxa                            TINYINT(1) NOT NULL DEFAULT 0,
  taxa_unica                          TINYINT(1) NOT NULL DEFAULT 0
);

--
-- Estrutura para tabela `entrega_taxas_unica`
--

CREATE TABLE entrega_taxas_unica (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_entrega                          INT NOT NULL,
  taxa_unica                          TINYINT(1) NOT NULL DEFAULT 0,
  valor_taxa                          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at                          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  id_selecionado                      VARCHAR(255) NULL 
);

--
-- Estrutura para tabela `formas_pagamento`
--

CREATE TABLE formas_pagamento (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          INT NOT NULL,
  dinheiro                            TINYINT(1) NOT NULL DEFAULT 0,
  pix                                 TINYINT(1) NOT NULL DEFAULT 0,
  cartaoDebito                        TINYINT(1) NOT NULL DEFAULT 0,
  cartaoCredito                       TINYINT(1) NOT NULL DEFAULT 0
);

--
-- Estrutura para tabela `endereco_empresa`
--

CREATE TABLE endereco_empresa (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          INT NOT NULL,
  cep                                 VARCHAR(9) NOT NULL,
  endereco                            VARCHAR(255) NOT NULL,
  bairro                              VARCHAR(100) NOT NULL,
  numero                              VARCHAR(10) NOT NULL,
  cidade                              VARCHAR(100) NOT NULL,
  complemento                         VARCHAR(255) NULL,
  uf                                  VARCHAR(2) NOT NULL,
  data_criacao                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao                    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `sobre_empresa`
--

CREATE TABLE sobre_empresa (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_selecionado                      VARCHAR(255) NOT NULL,
  nome_empresa                        VARCHAR(255) NOT NULL,
  sobre_empresa                       TEXT NOT NULL,
  imagem                              VARCHAR(255) DEFAULT NULL,
  data_criacao                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao                    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `horarios_funcionamento`
--

CREATE TABLE horarios_funcionamento (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          VARCHAR(255) NOT NULL,
  dia_de                              VARCHAR(20) NOT NULL,
  dia_ate                             VARCHAR(20) DEFAULT NULL,
  primeira_hora                       TIME NOT NULL,
  termino_primeiro_turno              TIME NOT NULL,
  comeco_segundo_turno                TIME DEFAULT NULL,
  termino_segundo_turno               TIME DEFAULT NULL
);

--
-- Estrutura para tabela `filiais`
--

CREATE TABLE filiais (
  id_filial                           INT AUTO_INCREMENT PRIMARY KEY,
  nome                                VARCHAR(255) NOT NULL,
  cnpj                                VARCHAR(20) NOT NULL,
  telefone                            VARCHAR(20) NOT NULL,
  email                               VARCHAR(255) NOT NULL,
  responsavel                         VARCHAR(255) NOT NULL,
  endereco                            VARCHAR(255) NOT NULL,
  data_abertura                       DATE NOT NULL,
  status                              VARCHAR(20) NOT NULL
);

--
-- Estrutura para tabela `contas_acesso`
--

CREATE TABLE contas_acesso (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  usuario                             VARCHAR(100) NOT NULL,
  cpf                                 VARCHAR(14) NOT NULL,
  email                               VARCHAR(150) NOT NULL,
  senha                               VARCHAR(64) NOT NULL,
  salt                                VARCHAR(32) NOT NULL,
  empresa_id                          INT NOT NULL,
  tipo                                ENUM('principal', 'filial') NOT NULL,
  nivel                               ENUM('Comum', 'Admin') NOT NULL,
  autorizado                          ENUM('sim', 'nao') NOT NULL DEFAULT 'nao',
  criado_em                           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  codigo_verificacao                  VARCHAR(6) NULL,
  codigo_verificacao_expires_at       INT(11) NOT NULL DEFAULT 0,

  -- Índices para performance e integridade
  INDEX                               (cpf),
  INDEX                               (usuario),
  INDEX                               (email),
  INDEX                               (empresa_id, tipo)
);

--
-- Estrutura para tabela `setores`
--

CREATE TABLE setores (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  nome                                VARCHAR(100) NOT NULL,
  gerente                             VARCHAR(100) NOT NULL,
  id_selecionado                      VARCHAR(50) NOT NULL, 
  criado_em                           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `escalas`
--

CREATE TABLE escalas (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  nome_escala                         VARCHAR(100) NOT NULL,
  data_escala                         DATETIME DEFAULT CURRENT_TIMESTAMP, 
  empresa_id                          VARCHAR(30) NOT NULL,                
  INDEX                               (empresa_id)
);

--
-- Estrutura para tabela `funcionarios`
--

CREATE TABLE funcionarios (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          VARCHAR(50) NOT NULL, 
  nome                                VARCHAR(150) NOT NULL,
  data_nascimento                     DATE NOT NULL,
  cpf                                 VARCHAR(14) NOT NULL UNIQUE,
  rg                                  VARCHAR(20) NOT NULL,
  cargo                               VARCHAR(100) NOT NULL,
  setor                               VARCHAR(50) NOT NULL, 
  salario                             DECIMAL(10,2) NOT NULL,
  escala                              VARCHAR(50) NOT NULL, 
  dia_inicio                          ENUM('domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado') NOT NULL,
  dia_folga                           ENUM('domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado') NOT NULL,

  entrada                             TIME NULL DEFAULT NULL,
  saida_intervalo                     TIME NULL DEFAULT NULL,
  retorno_intervalo                   TIME NULL DEFAULT NULL,
  saida_final                         TIME NULL DEFAULT NULL,

  email                               VARCHAR(150) NOT NULL,
  telefone                            VARCHAR(20) NOT NULL,
  endereco                            VARCHAR(255) NOT NULL,
  cidade                              VARCHAR(100) NOT NULL,
  criado_em                           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `funcionarios_acesso`
--

CREATE TABLE funcionarios_acesso (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  usuario                             VARCHAR(100) NOT NULL,
  cpf                                 VARCHAR(14) NOT NULL,
  email                               VARCHAR(150) NOT NULL,
  senha                               VARCHAR(64) NOT NULL,
  salt                                VARCHAR(32) NOT NULL,
  empresa_id                          INT NOT NULL,
  tipo                                ENUM('principal', 'filial') NOT NULL,
  nivel                               ENUM('Comum', 'Admin') NOT NULL,
  autorizado                          ENUM('sim', 'nao') NOT NULL DEFAULT 'nao',
  criado_em                           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  codigo_verificacao                  VARCHAR(6) NULL,
  codigo_verificacao_expires_at       INT(11) NOT NULL DEFAULT 0,

  -- Índices para performance e integridade
  INDEX                               (cpf),
  INDEX                               (usuario),
  INDEX                               (email),
  INDEX                               (empresa_id, tipo)
);

--
-- Estrutura para tabela `registros_ponto`
--

CREATE TABLE registros_ponto (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          VARCHAR(50) NOT NULL,
  cpf                                 VARCHAR(14) NOT NULL,
  data                                DATE NOT NULL,
  entrada                             TIME,
  saida                               TIME,
  status                              VARCHAR(20),
  horas_pendentes                     TIME,
  hora_extra                          TIME DEFAULT '00:00:00',
  foto_entrada                        LONGBLOB,
  foto_saida                          LONGBLOB,
  localizacao_entrada                 VARCHAR(100),
  localizacao_saida                   VARCHAR(100),
  criado_em                           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `atestados`
--

CREATE TABLE atestados (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  nome_funcionario                    VARCHAR(255) NOT NULL,
  cpf_usuario                         VARCHAR(14) NOT NULL,
  data_envio                          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atestado                       DATE NOT NULL,
  dias_afastado                       INT NOT NULL,
  medico                              VARCHAR(255) NOT NULL,
  observacoes                         TEXT,
  imagem_atestado                     VARCHAR(255) NOT NULL,
  id_empresa                          VARCHAR(20) NOT NULL
);

--
-- Estrutura para tabela `estoque`
--

CREATE TABLE estoque (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          VARCHAR(50) NOT NULL, -- ID da empresa (ex: 'principal_1', 'filial_2')
  codigo_produto                      VARCHAR(50) NOT NULL,
  nome_produto                        VARCHAR(100) NOT NULL,
  categoria_produto                   VARCHAR(100) NOT NULL,
  quantidade_produto                  INT NOT NULL,
  preco_produto                       DECIMAL(10, 2) NOT NULL,
  status_produto                      ENUM('estoque_alto', 'estoque_baixo') NOT NULL,
  criado_em                           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `pontos`
--

CREATE TABLE pontos (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  cpf                                 VARCHAR(15) NOT NULL,
  nome                                VARCHAR(150) NOT NULL,
  data                                DATE NOT NULL,
  
  entrada                             TIME DEFAULT NULL,
  foto_entrada LONGBLOB               DEFAULT NULL,
  localizacao_entrada                 VARCHAR(255) DEFAULT NULL,

  saida_intervalo                     TIME DEFAULT NULL,
  foto_saida_intervalo LONGBLOB       DEFAULT NULL,
  localizacao_saida_intervalo         VARCHAR(255) DEFAULT NULL,

  retorno_intervalo                   TIME DEFAULT NULL,
  foto_retorno_intervalo LONGBLOB     DEFAULT NULL,
  localizacao_retorno_intervalo       VARCHAR(255) DEFAULT NULL,

  saida_final                         TIME DEFAULT NULL,
  foto_saida_final LONGBLOB           DEFAULT NULL,
  localizacao_saida_final             VARCHAR(255) DEFAULT NULL,

  horas_pendentes                     TIME DEFAULT NULL,
  hora_extra                          TIME DEFAULT NULL,

  empresa_id                          VARCHAR(50) NOT NULL,
    
  UNIQUE                              (cpf, data, empresa_id)
);

--
-- Estrutura para tabela `folgas`
--

CREATE TABLE folgas (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  cpf                                 VARCHAR(20) NOT NULL,
  nome                                VARCHAR(100) NOT NULL,
  data_folga                          DATE NOT NULL
);

--
-- Estrutura para tabela `aberturas`
--

CREATE TABLE aberturas (
  id                                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  responsavel                         VARCHAR(255) NOT NULL,
  numero_caixa                        INT NOT NULL,
  valor_abertura                      DECIMAL(10,2) NOT NULL,
  valor_total                         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_sangrias                      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_suprimentos                   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_liquido                       DECIMAL(10,2) AS (valor_total + valor_suprimentos - valor_sangrias) STORED,
  abertura_datetime                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fechamento_datetime                 DATETIME DEFAULT NULL,
  quantidade_vendas                   INT NOT NULL DEFAULT 0,
  status                              ENUM('aberto', 'fechado') NOT NULL DEFAULT 'aberto',
  empresa_id                          VARCHAR(40) NOT NULL,
  cpf_responsavel                     VARCHAR(14) NOT NULL
);

--
-- Estrutura para tabela `itens_venda`
--

CREATE TABLE itens_venda (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  venda_id                            INT NOT NULL,
  responsavel                         VARCHAR(255) NOT NULL,
  cpf_responsavel                     VARCHAR(14) NOT NULL,
  id_caixa                            VARCHAR(40) NOT NULL,
  empresa_id                          VARCHAR(40) NOT NULL,
  nome_produto                        VARCHAR(255) NOT NULL,
  quantidade                          INT NOT NULL,
  preco_unitario                      DECIMAL(10,2) NOT NULL,
  preco_total                         DECIMAL(10,2) NOT NULL,
  id_produto                          INT,             -- ✅ Novo campo
  categoria                           VARCHAR(100),     -- ✅ Novo campo
  data_registro                       DATETIME DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `venda_rapida`
--

CREATE TABLE venda_rapida (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  cpf_responsavel                     VARCHAR(14) NOT NULL,
  responsavel                         VARCHAR(255) NOT NULL,
  produtos                            TEXT NOT NULL,
  total                               DECIMAL(10,2) NOT NULL,
  empresa_id                          VARCHAR(40) NOT NULL,
  id_caixa                            VARCHAR(40) NOT NULL,
  forma_pagamento                     VARCHAR(50) NOT NULL,
  data_venda                          DATETIME DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `suprimentos`
--

CREATE TABLE suprimentos (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  valor_suprimento                    DECIMAL(10,2) NOT NULL,
  empresa_id                          VARCHAR(40) NOT NULL,
  id_caixa                            INT NOT NULL,
  valor_liquido                       DECIMAL(10,2),
  responsavel                         VARCHAR(255),
  cpf_responsavel                     VARCHAR(14),
  data_registro                       DATETIME DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `sangrias`
--

CREATE TABLE sangrias (
  id                                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  valor                               DECIMAL(10,2) NOT NULL,
  empresa_id                          VARCHAR(40) NOT NULL,
  id_caixa                            INT NOT NULL,
  responsavel                         VARCHAR(255) NOT NULL,
  cpf_responsavel                     VARCHAR(14) NOT NULL,
  valor_liquido                       DECIMAL(10,2) NOT NULL,
  data_registro                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `fornecedores`
--

CREATE TABLE fornecedores (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          VARCHAR(200) NOT NULL, -- ID simbólico: 'principal_1', 'filial_1', etc.
  nome_fornecedor                     VARCHAR(100) NOT NULL,
  cnpj_fornecedor                     VARCHAR(18) NOT NULL,
  email_fornecedor                    VARCHAR(100) NOT NULL,
  telefone_fornecedor                 VARCHAR(20) NOT NULL,
  endereco_fornecedor                 VARCHAR(255) NOT NULL,
  created_at                          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE pedidos (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id                          VARCHAR(50) NOT NULL,  -- ID como string
  fornecedor                          VARCHAR(100) NOT NULL,
  produto                             VARCHAR(100) NOT NULL,
  quantidade                          INT NOT NULL,
  valor                               DECIMAL(10,2) NOT NULL,
  data_pedido                         DATE NOT NULL,
  status                              VARCHAR(20) NOT NULL,  -- Sem ENUM para maior flexibilidade
  data_cadastro                       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Estrutura para tabela `pagamentos_filiais`
--

CREATE TABLE pagamentos_filial (
  id                                  INT AUTO_INCREMENT PRIMARY KEY,
  id_selecionado                      VARCHAR(50) NOT NULL, -- ex: 'principal_1', 'filial_3'
  id_filial                           INT NOT NULL,              -- relaciona com a filial
  descricao                           VARCHAR(255) NOT NULL,
  valor                               DECIMAL(10,2) NOT NULL,
  data_vencimento                     DATE NOT NULL,
  status_pagamento                    ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
  criado_em                           DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em                       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);









