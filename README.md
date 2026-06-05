# Magrao Agro Pet - Demo Estatica

Versao estatica do site Magrao Agro Pet preparada para publicacao na Vercel.

Esta versao usa catalogo local em JavaScript/JSON e nao depende de PHP, MySQL, XAMPP ou painel administrativo. Ela foi preparada para demonstracao publica do site, com foco em navegacao, catalogo, carrinho e contato pelo WhatsApp.

## O Que Funciona

- Home responsiva.
- Catalogo estatico de produtos.
- Busca e filtros.
- Ofertas.
- Marcas.
- Favoritos.
- Pagina individual do produto.
- Produto em estoque e produto indisponivel.
- Carrinho com finalizacao pelo WhatsApp.
- Compra mensal de racao pelo WhatsApp.
- Servicos e agendamento abrindo WhatsApp.
- Contato, mapa e links da loja.
- Layout desktop e mobile.

## Catalogo Estatico

Os dados da demonstracao ficam em:

```text
js/data.js
data/produtos.json
data/catalogo-demo.json
```

O arquivo `js/data.js` define `staticDemo: true`, desativando as chamadas para APIs PHP em ambiente HTTP/Vercel.

## Deploy Na Vercel

Este projeto nao precisa de comando de build.

Configuracao:

```text
Framework Preset: Other
Build Command: vazio
Output Directory: vazio / raiz do projeto
Install Command: vazio
```

A Vercel deve servir diretamente o `index.html` da raiz.

## Estrutura

```text
assets/   Imagens e logo
css/      Estilos desktop/mobile
data/     Catalogo estatico em JSON
js/       Interacoes do site
uploads/  Placeholders seguros
```

## Observacao

Esta demo nao inclui painel administrativo, banco de dados, importacao XML ou endpoints PHP. Para a versao administravel, use a versao PHP/MySQL do projeto.
