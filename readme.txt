=== Custom Discount ===
Contributors: Pluralweb
Tags: woocommerce, discount, kit, progressive discount
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin personalizado para aplicar descontos progressivos baseados na quantidade de produtos no carrinho.

== Description ==

O Custom Discount é um plugin para WooCommerce que permite criar kits de produtos com quantidades específicas e aplicar descontos progressivos baseados na quantidade de itens no carrinho.

Características:
* Criação de kits com produtos específicos
* Definição de quantidade para cada produto no kit
* Mensagens personalizadas para diferentes estados do carrinho
* Interface amigável para configuração dos kits
* Configuração simplificada de desconto

== Installation ==

1. Faça upload dos arquivos do plugin para a pasta `/wp-content/plugins/custom-discount`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure o desconto em WooCommerce > Configurações > Custom Discount

== Changelog ==

= 2.9 =
* Adicionada nova variável {product_discounted_price} para exibir o preço do produto individual com o desconto aplicado
* Adicionada nova variável {kit_rotulo_price} para exibir o preço por rótulo individual em um kit com desconto aplicado
* Corrigido o cálculo do preço com desconto para kits, considerando a quantidade total de rótulos no kit
* Melhorada a documentação das variáveis disponíveis para as mensagens

= 2.8 =
* Adicionada personalização visual das mensagens de desconto
* Adicionados campos para configurar cor de fundo, cor da borda e cor do texto
* Adicionados campos para configurar tipo e tamanho da fonte
* Melhorada a aplicação de estilos personalizados via JavaScript

= 2.7 =
* Corrigido problema de mensagens duplicadas na página do produto
* Adicionada exibição responsiva das mensagens: no topo para dispositivos móveis e acima do botão de adicionar ao carrinho para desktop
* Adicionada opção para configurar o ponto de quebra para dispositivos móveis no painel administrativo

= 2.6 =
* Adicionada opção "Centro" para posicionamento horizontal e vertical da notificação toast
* Melhorada a lógica de posicionamento para centralizar corretamente a notificação

= 2.5 =
* Adicionada possibilidade de configurar a posição da notificação toast (superior/inferior, esquerda/direita)
* Criada nova aba "Notificação Toast" no painel administrativo para configurações avançadas

= 2.4 =
* Adicionado campo para personalizar a mensagem da notificação toast
* Modificada a notificação toast para permanecer fixa na tela até ser fechada pelo usuário
* Melhorada a aparência visual da notificação toast

= 2.3 =
* Corrigido problema que impedia a exibição do campo de imagem destacada no cadastro de produtos

= 2.2 =
* Removida seção duplicada "Produtos do Kit" na página de cadastro de produtos
* Removido campo "Mensagem quando tem próximo nível" na interface de administração
* Adicionadas legendas explicativas para cada mensagem
* Adicionada variável {admin_discount} na documentação
* Atualização da marca para Pluralweb
* Correções de bugs diversos

= 2.1 =
* Simplificação do sistema de desconto para um único nível
* Correção na exibição de mensagens em páginas de produtos individuais
* Melhorias na interface de configuração
* Correções de bugs diversos

= 2.0 =
* Nova interface para criação de kits com quantidades específicas por produto
* Melhorias nas mensagens de desconto para kits
* Correção na lógica de cálculo de produtos no carrinho
* Melhor feedback visual no admin
* Correções de bugs diversos

= 1.1 =
* Adicionado suporte a kits de produtos
* Melhorias na interface do admin
* Correções de bugs

= 1.0 =
* Versão inicial do plugin
