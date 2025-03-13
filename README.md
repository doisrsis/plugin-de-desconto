# Plugin de Desconto Automático para WooCommerce

Plugin WordPress que oferece descontos automáticos baseados na quantidade de produtos no carrinho.

## Características

- Desconto progressivo baseado na quantidade de produtos
- Interface administrativa intuitiva com abas organizadas
- Mensagens personalizáveis com variáveis dinâmicas
- Seleção de categorias específicas para aplicação do desconto
- Valor máximo de desconto configurável
- Compatível com WooCommerce

## Funcionalidades

### Níveis de Desconto
- Configure múltiplos níveis de desconto
- Defina quantidade mínima de produtos e porcentagem para cada nível
- Estabeleça um valor máximo de desconto em reais

### Mensagens Personalizáveis
Personalize as mensagens usando variáveis dinâmicas:
- `{discount}` - Porcentagem atual de desconto
- `{next_discount}` - Próxima porcentagem de desconto
- `{remaining}` - Produtos restantes para próximo nível
- `{savings}` - Valor em reais que será economizado
- `{total_savings}` - Valor total em reais que será economizado
- `{current_items}` - Quantidade atual de produtos
- `{level_quantity}` - Quantidade mínima de produtos do nível

### Categorias
- Selecione quais categorias receberão desconto
- Fácil gerenciamento através de interface visual
- Exclusão automática de categorias não selecionadas

## Instalação

1. Faça o upload do plugin para a pasta `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure os níveis de desconto em WooCommerce > Configurações > Desconto Automático

## Requisitos

- WordPress 5.0 ou superior
- WooCommerce 3.0 ou superior
- PHP 7.2 ou superior

## Suporte

Para suporte ou sugestões, abra uma issue no GitHub.
