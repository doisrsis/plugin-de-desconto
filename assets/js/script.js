/*jQuery(document).ready(function($) {
    $('body').on('change', 'input.qty', function() {
        clearTimeout($.data(this, 'timer'));
        $(this).data('timer', setTimeout(function() {
            $('[name="update_cart"]').trigger('click');
        }, 500));
    });
});*/

jQuery(document).ready(function($) {
    function checkDiscountProgress() {
        $.ajax({
            url: descontoAutomatico.ajax_url,
            type: 'POST',
            data: { action: 'get_remaining_items_for_discount' },
            success: function(response) {
                if (response.success) {
                    let remaining = response.remaining;
                    if (remaining > 0) {
                        showDiscountPopup(`Faltam apenas ${remaining} produtos para ganhar um desconto!`);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro no Ajax:", error);
            }
        });
    }

    function showDiscountPopup(message) {
        let popup = $('<div class="discount-popup">' + message + '</div>');

        $('body').append(popup);
        popup.fadeIn(300);

        setTimeout(function() {
            popup.fadeOut(500, function() { $(this).remove(); });
        }, 3000);
    }

    // Monitorar eventos do WooCommerce
    $(document.body).on('added_to_cart wc_fragments_refreshed updated_cart_totals', function() {
        checkDiscountProgress();
    });

    // Dispara ao carregar a página do carrinho
    checkDiscountProgress();
});

