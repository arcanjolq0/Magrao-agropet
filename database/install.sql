SET NAMES utf8mb4;
SET time_zone = '-03:00';

DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS loja_config;
DROP TABLE IF EXISTS admin_users;

CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE loja_config (
  chave VARCHAR(80) NOT NULL PRIMARY KEY,
  valor TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO loja_config (chave, valor) VALUES
('horarios_funcionamento', '{"timezone":"America/Sao_Paulo","days":[{"key":"sun","label":"Domingo","short":"Dom","open":false,"from":"08:00","to":"14:00"},{"key":"mon","label":"Segunda-feira","short":"Seg","open":true,"from":"08:00","to":"19:00"},{"key":"tue","label":"Terça-feira","short":"Ter","open":true,"from":"08:00","to":"19:00"},{"key":"wed","label":"Quarta-feira","short":"Qua","open":true,"from":"08:00","to":"19:00"},{"key":"thu","label":"Quinta-feira","short":"Qui","open":true,"from":"08:00","to":"19:00"},{"key":"fri","label":"Sexta-feira","short":"Sex","open":true,"from":"08:00","to":"19:00"},{"key":"sat","label":"Sábado","short":"Sáb","open":true,"from":"08:00","to":"14:00"}]}'),
('dados_loja', '{"name":"Magrão Agro Pet","whatsapp":"5547996329281","whatsappDisplay":"(47) 99632-9281","instagram":"https://www.instagram.com/magraoagropet/","address":"R. Ten. Antônio João, 1136 - Bom Retiro, Joinville - SC, 89222-401","mapsLink":"https://www.google.com/maps/search/?api=1&query=R.%20Ten.%20Ant%C3%B4nio%20Jo%C3%A3o%2C%201136%20-%20Bom%20Retiro%2C%20Joinville%20-%20SC%2C%2089222-401","mapsEmbed":"https://www.google.com/maps?q=R.%20Ten.%20Ant%C3%B4nio%20Jo%C3%A3o%2C%201136%20-%20Bom%20Retiro%2C%20Joinville%20-%20SC%2C%2089222-401&output=embed","footerDescription":"Loja online da Magrão Agro Pet para vender produtos da loja física com catálogo, páginas de produto, carrinho e pedidos pelo WhatsApp.","contactIntro":"Olá, estou entrando em contato pelo site da Magrão Agro Pet.","checkoutIntro":"Olá, gostaria de fazer este pedido pelo site da Magrão Agro Pet:","monthlyIntro":"Olá, gostaria de agendar uma compra mensal de ração pela Magrão Agro Pet."}');


CREATE TABLE agendamentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  servico VARCHAR(60) NOT NULL,
  nome_cliente VARCHAR(140) NOT NULL,
  whatsapp VARCHAR(40) NOT NULL,
  pet_nome VARCHAR(120) NULL,
  tipo_pet VARCHAR(80) NULL,
  porte_pet VARCHAR(80) NULL,
  data_preferida DATE NULL,
  periodo_preferido VARCHAR(80) NULL,
  observacoes TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'novo',
  origem VARCHAR(80) NOT NULL DEFAULT 'site',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_agendamentos_status (status, created_at),
  INDEX idx_agendamentos_data (data_preferida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  filtro VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  icone VARCHAR(20) NULL,
  subcategorias_json TEXT NULL,
  ordem_exibicao INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_categorias_ativo_ordem (ativo, ordem_exibicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produtos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT UNSIGNED NOT NULL,
  sku VARCHAR(80) NULL UNIQUE,
  nome VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  subcategoria VARCHAR(120) NULL,
  marca VARCHAR(120) NULL,
  peso VARCHAR(80) NULL,
  descricao TEXT NOT NULL,
  descricao_longa TEXT NULL,
  preco_antigo DECIMAL(10,2) NULL,
  preco_atual DECIMAL(10,2) NULL,
  preco_sob_consulta TINYINT(1) NOT NULL DEFAULT 0,
  selo VARCHAR(80) NULL,
  imagem VARCHAR(255) NULL,
  galeria_json TEXT NULL,
  icone VARCHAR(20) NULL,
  estoque INT NOT NULL DEFAULT 0,
  estoque_minimo INT NOT NULL DEFAULT 0,
  ideal_para VARCHAR(160) NULL,
  beneficios_json TEXT NULL,
  tags_json TEXT NULL,
  especificacoes_json TEXT NULL,
  popularidade INT NOT NULL DEFAULT 50,
  destaque TINYINT(1) NOT NULL DEFAULT 0,
  oferta TINYINT(1) NOT NULL DEFAULT 0,
  mostrar_home TINYINT(1) NOT NULL DEFAULT 1,
  compra_mensal TINYINT(1) NOT NULL DEFAULT 0,
  em_estoque TINYINT(1) NOT NULL DEFAULT 1,
  mostrar_quantidade_estoque TINYINT(1) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ordem_exibicao INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON UPDATE CASCADE,
  INDEX idx_produtos_publico (ativo, ordem_exibicao),
  INDEX idx_produtos_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorias (id, nome, filtro, slug, icone, subcategorias_json, ordem_exibicao, ativo) VALUES
(1, 'Cachorros', 'Cães', 'caes', '🐶', '["Rações","Petiscos","Brinquedos","Camas","Guias e coleiras","Higiene","Farmácia"]', 10, 1),
(2, 'Gatos', 'Gatos', 'gatos', '🐱', '["Rações","Areias","Sachês","Arranhadores","Brinquedos","Transporte"]', 20, 1),
(3, 'Farmácia Pet', 'Farmácia Pet', 'farmacia-pet', '💊', '["Antipulgas","Vermífugos","Shampoos","Suplementos","Higiene","Cuidados"]', 30, 1),
(4, 'Pássaros', 'Aves', 'aves', '🦜', '["Rações","Gaiolas","Vitaminas","Acessórios","Comedouros"]', 40, 1),
(5, 'Peixes', 'Peixes', 'peixes', '🐟', '["Rações","Aquários","Filtros","Tratamento de água","Decoração"]', 50, 1),
(6, 'Agro & Jardim', 'Agro & Jardim', 'agro-jardim', '🌱', '["Adubos","Sementes","Ferramentas","Jardinagem","Utilidades","Controle de pragas"]', 60, 1);

INSERT INTO produtos
(id, categoria_id, sku, nome, slug, subcategoria, marca, peso, descricao, descricao_longa, preco_antigo, preco_atual, preco_sob_consulta, selo, imagem, galeria_json, icone, estoque, estoque_minimo, ideal_para, beneficios_json, tags_json, especificacoes_json, popularidade, destaque, oferta, mostrar_home, compra_mensal, em_estoque, ativo, ordem_exibicao)
VALUES
(1, 1, 'MAG-CAO-RAC-001', 'Ração Premium Cães Adultos 15kg', 'racao-premium-caes-adultos-15kg', 'Rações', 'Linha Premium', '15kg', 'Produto de alta saída para venda recorrente, ideal para cães adultos.', 'Ração seca para cães adultos, indicada para clientes que buscam praticidade, bom rendimento e compra recorrente.', 209.90, 189.90, 0, 'Mais vendido', NULL, '[]', '🐶', 10, 3, 'cães adultos', '["Produto de recompra frequente","Atendimento direto com a loja","Pedido rapido pelo WhatsApp"]', '["ração","cachorro","cães","adulto","15kg","premium"]', '["Indicado para cães adultos","Embalagem econômica","Produto de recompra","Pedido pelo WhatsApp"]', 100, 1, 1, 1, 1, 1, 1, 10),
(2, 2, 'MAG-GAT-ARE-002', 'Areia Higiênica para Gatos 4kg', 'areia-higienica-para-gatos-4kg', 'Areias', 'Linha Gatos', '4kg', 'Produto essencial para recompra frequente.', 'Areia higiênica para gatos com boa saída no varejo pet, ideal para destacar em combos e campanhas de recompra.', 35.90, 29.90, 0, 'Oferta', NULL, '[]', '🐱', 7, 2, 'gatos', '["Produto essencial para rotina","Boa opcao para recompra","Pode compor combos"]', '["areia","gato","gatos","higiênica","4kg"]', '["Uso diário","Produto recorrente","Boa opção para combos","Consultar disponibilidade"]', 94, 1, 1, 1, 1, 1, 1, 20),
(3, 3, 'MAG-FAR-ANT-003', 'Antipulgas e Carrapatos', 'antipulgas-e-carrapatos', 'Antipulgas', 'Farmácia Pet', 'Consultar porte', 'Ideal para campanhas de proteção pet.', 'Produto para a área de farmácia pet. A venda deve ser orientada por peso, idade e necessidade do animal.', NULL, 64.90, 0, 'Farmácia', NULL, '[]', '💊', 4, 2, 'proteção pet', '["Atendimento especializado","Ajuda na protecao do pet","Consulta pelo WhatsApp"]', '["antipulgas","carrapatos","farmácia","proteção","pet"]', '["Proteção pet","Consultar indicação","Atendimento especializado","Confirmação pelo WhatsApp"]', 91, 1, 0, 1, 0, 1, 1, 30),
(4, 1, 'MAG-CAO-PET-004', 'Petisco Natural para Cães 500g', 'petisco-natural-para-caes-500g', 'Petiscos', 'Petiscos', '500g', 'Produto complementar para aumentar ticket médio.', 'Petisco para cães, ideal para vendas adicionais junto com rações, brinquedos e itens de higiene.', NULL, 24.90, 0, 'Petisco', NULL, '[]', '🦴', 12, 4, 'cães', '["Venda complementar","Ideal para presentear o pet","Pode compor combos"]', '["petisco","cães","cachorro","natural","500g"]', '["Venda complementar","Ideal para cães","Pode compor combos","Produto de giro"]', 84, 1, 0, 1, 0, 1, 1, 40),
(5, 2, 'MAG-GAT-SAC-005', 'Sachê para Gatos Sabores Variados', 'sache-para-gatos-sabores-variados', 'Sachês', 'Linha Gatos', 'Unidade', 'Pode ser vendido em unidade, combo ou caixa.', 'Sachê para gatos com sabores variados. Produto ideal para oferta por unidade, combo promocional ou caixa fechada.', 4.99, 3.99, 0, 'Combo', NULL, '[]', '🥫', 20, 6, 'gatos', '["Sabores variados","Otimo para combos","Compra rapida pelo WhatsApp"]', '["sachê","gato","gatos","combo","úmido"]', '["Sabores variados","Venda unitária","Combo promocional","Pedido pelo WhatsApp"]', 90, 1, 1, 1, 0, 1, 1, 50),
(6, 6, 'MAG-AGR-ADU-006', 'Adubo Orgânico para Jardim 5kg', 'adubo-organico-para-jardim-5kg', 'Adubos', 'Agro & Jardim', '5kg', 'Linha agro e jardim para ampliar o público.', 'Adubo orgânico para jardinagem, ideal para campanhas sazonais e para reforçar a linha agro da loja.', NULL, 39.90, 0, 'Agro', NULL, '[]', '🌱', 6, 2, 'jardins e plantas', '["Uso domestico","Linha agro da loja","Consulta de disponibilidade"]', '["adubo","jardim","jardinagem","agro","5kg"]', '["Jardinagem","Uso doméstico","Linha agro","Consultar disponibilidade"]', 74, 1, 0, 1, 0, 1, 1, 60),
(7, 3, 'MAG-FAR-SHA-007', 'Shampoo Pet Neutro 500ml', 'shampoo-pet-neutro-500ml', 'Shampoos', 'Higiene Pet', '500ml', 'Produto de higiene e cuidado para pets.', 'Shampoo pet neutro para cuidados de higiene, indicado para a seção de banho e cuidados.', NULL, 22.90, 0, 'Higiene', NULL, '[]', '🧴', 8, 3, 'banho e higiene', '["Cuidados de rotina","Produto complementar","Atendimento pelo WhatsApp"]', '["shampoo","pet","higiene","banho","500ml"]', '["Higiene","Cuidados pet","Produto complementar","Consultar disponibilidade"]', 72, 1, 0, 1, 0, 1, 1, 70),
(8, 1, 'MAG-CAO-BRI-008', 'Brinquedo Mordedor Resistente', 'brinquedo-mordedor-resistente', 'Brinquedos', 'Acessórios', 'Unidade', 'Acessório forte para vitrine e campanhas.', 'Brinquedo mordedor para cães, ideal para vitrine visual, campanhas de acessórios e vendas adicionais.', 44.90, 34.90, 0, 'Promo', NULL, '[]', '🎾', 5, 5, 'cães ativos', '["Acessorio divertido","Boa opcao de presente","Produto promocional"]', '["brinquedo","mordedor","cachorro","cães","acessório"]', '["Brinquedo","Acessório pet","Promoção","Compra rápida"]', 77, 1, 1, 1, 0, 1, 1, 80),
(9, 4, 'MAG-AVE-RAC-009', 'Ração para Pássaros Selecionada', 'racao-para-passaros-selecionada', 'Rações', 'Pássaros', 'Pacote', 'Categoria pronta para expansão do catálogo.', 'Ração para pássaros, indicada para a categoria de aves e acessórios relacionados.', NULL, 18.90, 0, 'Pássaros', NULL, '[]', '🦜', 0, 2, 'aves', '["Alimentacao de rotina","Produto recorrente","Consulta de estoque"]', '["pássaros","aves","ração","alimentação"]', '["Aves","Alimentação","Venda recorrente","Consultar estoque"]', 63, 0, 0, 0, 1, 0, 1, 90),
(10, 5, 'MAG-PEI-RAC-010', 'Ração para Peixes Ornamentais', 'racao-para-peixes-ornamentais', 'Rações', 'Aquarismo', 'Pote', 'Produto para aquarismo e cuidados básicos.', 'Ração para peixes ornamentais, ideal para desenvolver a seção de aquarismo dentro do e-commerce.', NULL, 16.90, 0, 'Peixes', NULL, '[]', '🐟', 3, 2, 'peixes ornamentais', '["Produto de rotina","Linha aquarismo","Consulta rapida"]', '["peixes","aquarismo","ração","ornamentais"]', '["Aquarismo","Peixes ornamentais","Produto recorrente","Consultar estoque"]', 58, 0, 0, 0, 1, 1, 1, 100),
(11, 1, 'MAG-CAO-HIG-011', 'Tapete Higiênico Pacote Econômico', 'tapete-higienico-pacote-economico', 'Higiene', 'Higiene Pet', 'Pacote', 'Item recorrente para rotina de limpeza.', 'Tapete higiênico para cães, item de recompra frequente e ótimo para ofertas combinadas com rações.', 59.90, 49.90, 0, 'Economia', NULL, '[]', '🧻', 9, 3, 'higiene canina', '["Item recorrente","Ajuda na rotina de limpeza","Pode compor combos"]', '["tapete","higiênico","cães","cachorro","higiene"]', '["Higiene","Pacote econômico","Produto recorrente","Combo com ração"]', 88, 1, 1, 1, 1, 1, 1, 110),
(12, 1, 'MAG-CAO-ACE-012', 'Guia e Coleira Ajustável', 'guia-e-coleira-ajustavel', 'Guias e coleiras', 'Acessórios', 'Unidade', 'Acessório de uso diário com boa saída.', 'Guia e coleira ajustável para cães, indicada para a área de acessórios e passeio.', NULL, 31.90, 0, 'Acessório', NULL, '[]', '🦮', 2, 4, 'passeio com cães', '["Uso diario","Acessorio essencial","Consultar tamanhos"]', '["guia","coleira","cães","passeio","acessório"]', '["Passeio","Acessório","Uso diário","Consultar tamanhos"]', 70, 0, 0, 0, 0, 1, 1, 120);

-- Apos importar este SQL, acesse /admin/setup.php para criar o primeiro usuario administrador.
-- A senha sera gravada com password_hash(), usando o algoritmo seguro disponivel na versao do PHP da hospedagem.
