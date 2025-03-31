jQuery(document).ready(function($) {
    console.log('Script de mensagens responsivas carregado!');
    
    // Obter o ponto de quebra configurado via AJAX
    $.ajax({
        url: custom_discount_params.ajax_url,
        type: 'POST',
        data: {
            action: 'get_mobile_breakpoint'
        },
        success: function(response) {
            console.log('Resposta do AJAX:', response);
            
            if (response.success) {
                // Aplicar o ponto de quebra personalizado
                var breakpoint = parseInt(response.data.breakpoint);
                console.log('Ponto de quebra configurado:', breakpoint);
                
                // Obter as configurações de estilo
                var styles = response.data.styles;
                
                // Aplicar estilos personalizados às mensagens
                $('.custom-discount-message-top, .custom-discount-message-bottom').css({
                    'background-color': styles.bg_color,
                    'color': styles.text_color,
                    'font-family': styles.font_family,
                    'font-size': styles.font_size + 'px'
                });
                
                // Aplicar cor da borda
                $('.custom-discount-message-top, .custom-discount-message-bottom').css({
                    'border': '1px solid ' + styles.border_color,
                    'border-left': '4px solid ' + styles.border_color
                });
                
                // Criar e adicionar regras CSS dinâmicas para responsividade
                var style = document.createElement('style');
                style.type = 'text/css';
                style.innerHTML = `
                    @media screen and (min-width: ${breakpoint+1}px) {
                        .custom-discount-message-top {
                            display: none;
                        }
                    }
                    
                    @media screen and (max-width: ${breakpoint}px) {
                        .custom-discount-message-bottom {
                            display: none;
                        }
                    }
                `;
                document.head.appendChild(style);
                console.log('Regras CSS aplicadas com sucesso!');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao obter ponto de quebra:', error);
        }
    });
});
