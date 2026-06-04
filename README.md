# Magrao Agro Pet

Site institucional e catalogo online para a Magrao Agro Pet, com painel administrativo em PHP/MySQL para gerenciar produtos, estoque, categorias, horarios, agendamentos e configuracoes da loja.

## Destaques

- Home responsiva com carrossel, categorias, ofertas e atalhos comerciais.
- Catalogo com busca, filtros, marcas, favoritos e pagina individual de produto.
- Carrinho com finalizacao via WhatsApp.
- Produto com controle de estoque, produto sem estoque e exibicao opcional da quantidade.
- Compra mensal de racao com mensagem estruturada para WhatsApp.
- Agendamento de banho, tosa e atendimento veterinario.
- Painel administrativo com login, CRUD de produtos/categorias, estoque, configuracoes, horarios e agendamentos.
- Importacao e exportacao de produtos por XML.
- Exportacao CSV protegida contra formula injection.
- Estrutura pronta para hospedagem PHP/MySQL, como Hostinger.

## Stack

- HTML5, CSS3 e JavaScript puro.
- PHP 8+ com PDO.
- MySQL/MariaDB.
- Apache com `.htaccess`.

## Estrutura

```text
admin/      Painel administrativo
api/        Endpoints publicos JSON
assets/     Logo e imagens do site
config/     Conexao e configuracoes PHP
css/        Estilos desktop e mobile
database/   Scripts SQL de instalacao/atualizacao
js/         Interacoes do catalogo, carrinho e formularios
uploads/    Pasta segura para imagens enviadas pelo painel
```

## Como Rodar Localmente

1. Copie a pasta para o servidor local, por exemplo XAMPP.
2. Crie um banco MySQL chamado `magrao_agropet`.
3. Importe `database/install.sql`.
4. Confira as credenciais em `config/database.php`.
5. Acesse o site pelo Apache ou pelo servidor embutido do PHP:

```bash
php -S 127.0.0.1:8080
```

6. Acesse `/admin/setup.php` para criar o primeiro administrador.

## Publicacao Em Hospedagem

1. Envie os arquivos para `public_html`.
2. Crie banco e usuario MySQL no painel da hospedagem.
3. Importe `database/install.sql`.
4. Atualize `config/database.php` com os dados reais do banco.
5. Acesse `/admin/setup.php` e crie o administrador.
6. Remova ou renomeie `admin/setup.php` depois do primeiro acesso.
7. Mantenha os arquivos `.htaccess` no envio.

## Seguranca Implementada

- Login administrativo com senha via `password_hash`.
- CSRF nas acoes administrativas.
- SQL com prepared statements nos fluxos de entrada.
- Upload de imagem validado por tamanho, MIME real e extensao.
- XML com limite de tamanho e `LIBXML_NONET`.
- Pastas `config`, `database` e `uploads` protegidas por `.htaccess`.
- Arquivos internos do admin iniciados por `_` bloqueados no Apache.

## Status Da Auditoria

Auditoria final executada antes da publicacao:

- PHP lint: 32 arquivos OK.
- JavaScript syntax check: 5 arquivos OK.
- Instalacao limpa do SQL validada.
- Site publico desktop e mobile validado.
- Painel administrativo validado.
- Importacao/exportacao XML e CSV validadas.
- Carrinho, favoritos, busca, filtros e WhatsApp validados.

## Observacao

Este projeto foi desenvolvido para uma loja real e pode exigir substituicao de imagens, produtos e credenciais antes da publicacao final.
