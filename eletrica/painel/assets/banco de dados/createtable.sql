

CREATE TABLE `adicionarcategoria` (
  `id_categoria` INT AUTO_INCREMENT PRIMARY KEY, 
  `nome_categoria` varchar(255) NOT NULL,
  `icone_categoria` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 
 

CREATE TABLE `adicionarprodutos` (
  `id_produto` INT AUTO_INCREMENT PRIMARY KEY,
  `nome_produto` varchar(255) NOT NULL,
  `quantidade_produto` int(11) NOT NULL,
  `preco_produto` decimal(10,2) NOT NULL,
  `imagem_produto` varchar(255) DEFAULT NULL,
  `descricao_produto` text DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp(),
  `id_categoria` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  
-- --------------------------------------------------------

--
-- Estrutura para tabela `carrinhotemporario`
--

CREATE TABLE `carrinhotemporario` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_produto` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `preco` decimal(10,2) NOT NULL,
  `data_adicionado` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) NOT NULL,
  `id_carrinho` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `carrinhotemporario`
--

INSERT INTO `carrinhotemporario` (`id`, `id_produto`, `quantidade`, `preco`, `data_adicionado`, `id_usuario`, `id_carrinho`) VALUES
(0, 4, 5, 4000.00, '2025-04-01 18:33:03', 0, 'carrinho_67ec2c20e3a4f3.91033313'),
(0, 1, 1, 180.00, '2025-04-02 12:20:58', 0, 'carrinho_67ec31b54b4980.52017691'),
(0, 4, 1, 800.00, '2025-04-02 12:38:57', 0, 'carrinho_67ed2fdd70fcb9.78270322');


CREATE TABLE `itens_carrinho` (
  `id` int(11) NOT NULL,
  `id_carrinho` int(11) DEFAULT NULL,
  `id_produto` int(11) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `preco` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `cpf` varchar(11) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

