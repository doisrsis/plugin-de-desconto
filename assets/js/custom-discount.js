jQuery(document).ready(function($) {
    // Função para criar e exibir a notificação toast
    function showDiscountToast(message, type = 'success') {
        // Remove qualquer toast existente
        $('.custom-discount-toast').remove();

        // Cria o elemento toast
        const toast = $(`
            <div class="custom-discount-toast custom-discount-toast-${type}">
                <div class="custom-discount-toast-content">
                    ${message}
                </div>
                <button class="custom-discount-toast-close">×</button>
            </div>
        `);

        // Adiciona o toast ao body
        $('body').append(toast);

        // Fecha o toast ao clicar no botão
        toast.find('.custom-discount-toast-close').on('click', function() {
            toast.remove();
        });
    }

    // Variável para armazenar o último nível de desconto
    let lastDiscountLevel = null;

    // Função para verificar mudanças no desconto
    function checkDiscountChanges() {
        $.ajax({
            url: customDiscount.ajax_url,
            type: 'POST',
            data: {
                action: 'get_current_discount_level'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const currentLevel = response.data.current_level;
                    const nextLevel = response.data.next_level;

                    // Se é a primeira verificação, apenas armazena o nível atual
                    if (lastDiscountLevel === null) {
                        lastDiscountLevel = currentLevel;
                        return;
                    }

                    // Se atingiu um novo nível de desconto
                    if (currentLevel && (!lastDiscountLevel || currentLevel.percentage > lastDiscountLevel.percentage)) {
                        // Usa a mensagem personalizada do toast se estiver disponivel
                        if (response.data.toast_message) {
                            showDiscountToast(response.data.toast_message);
                        } else {
                            // Fallback para a mensagem padrão
                            showDiscountToast(`
                                <strong>Parabéns!</strong><br>
                                Você atingiu ${currentLevel.percentage}% de desconto!
                                ${nextLevel ? `<br>Adicione mais ${response.data.remaining_items} produtos para ${nextLevel.percentage}%` : ''}
                            `);
                        }
                    }

                    lastDiscountLevel = currentLevel;
                }
            }
        });
    }

    // Verifica mudanças quando houver alterações no carrinho
    $(document.body).on('added_to_cart removed_from_cart updated_cart_totals', function() {
        checkDiscountChanges();
    });

    // Verifica na carga inicial da página
    checkDiscountChanges();
});
