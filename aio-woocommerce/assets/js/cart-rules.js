(function($) {
    'use strict';

    var data = window.aioWcCartRulesFrontend || {};
    var minEnabled = !!data.enabled;
    var maxQtyEnabled = parseInt(data.product_max_qty, 10) > 0;
    if (!minEnabled && !maxQtyEnabled) {
        return;
    }

    function formatNotice(min) {
        var template = data.notice_template || '{min_qty}';
        var name = data.product_name || data.product_placeholder || '';
        return template.replace('{product}', name).replace('{min_qty}', min);
    }

    function formatMaxNotice(max) {
        var template = data.max_notice_template || '{max_qty}';
        var name = data.product_name || data.product_placeholder || '';
        return template.replace('{product}', name).replace('{max_qty}', max);
    }

    function showNotice(message) {
        var $wrapper = $('.single-product .woocommerce-notices-wrapper');
        if (!$wrapper.length) {
            $wrapper = $('<div class="woocommerce-notices-wrapper"></div>').insertBefore('.single-product .product');
        }
        $wrapper.html('<div class="woocommerce-error" role="alert">' + message + '</div>');
    }

    function syncQuantityValue($input, value) {
        $input.val(value);
        var $form = $input.closest('form.cart');
        if ($form.length) {
            $form.find('input[name="quantity"]').not($input).val(value);
            $form.find('button[name="add-to-cart"]').attr('data-quantity', value);
        }
    }

    function enforceMinimum($input, min, verbose) {
        if (!minEnabled) {
            return false;
        }
        var value = parseInt($input.val(), 10);
        if (isNaN(value) || value < min) {
            syncQuantityValue($input, min);
            if (verbose) {
                showNotice(formatNotice(min));
            }
            return true;
        }
        syncQuantityValue($input, value);
        return false;
    }

    function enforceMaximum($input, max, verbose) {
        if (!max || max < 1) {
            return false;
        }
        var value = parseInt($input.val(), 10);
        if (isNaN(value) || value > max) {
            syncQuantityValue($input, max);
            if (verbose) {
                showNotice(formatMaxNotice(max));
            }
            return true;
        }
        syncQuantityValue($input, value);
        return false;
    }

    function blockMinus(min) {
        if (!minEnabled) {
            $('.single-product form.cart .quantity .minus').removeClass('disabled').removeAttr('disabled');
            return;
        }
        $('.single-product form.cart .quantity .minus').each(function() {
            var $btn = $(this);
            var $input = $btn.closest('.quantity').find('input.qty');
            var current = parseInt($input.val(), 10) || 0;
            if (current <= min) {
                $btn.addClass('disabled').attr('disabled', true);
            } else {
                $btn.removeClass('disabled').removeAttr('disabled');
            }
        });
    }

    function blockPlus(max) {
        if (!max || max < 1) {
            $('.single-product form.cart .quantity .plus').removeClass('disabled').removeAttr('disabled');
            return;
        }
        $('.single-product form.cart .quantity .plus').each(function() {
            var $btn = $(this);
            var $input = $btn.closest('.quantity').find('input.qty');
            var current = parseInt($input.val(), 10) || 0;
            if (current >= max) {
                $btn.addClass('disabled').attr('disabled', true);
            } else {
                $btn.removeClass('disabled').removeAttr('disabled');
            }
        });
    }

    function initProductPage() {
        if (!$('body').hasClass('single-product')) {
            return;
        }

        var min = parseInt(data.product_min_qty, 10);
        if (!min || min < 1) {
            min = 1;
        }

        var max = parseInt(data.product_max_qty, 10);
        if (isNaN(max) || max < 1) {
            max = 0;
        }
        if (max && max < min) {
            max = min;
        }

        var $inputs = $('.single-product form.cart .quantity input.qty');
        if (!$inputs.length) {
            return;
        }

        $inputs.attr({ min: 1, step: 1 });
        $inputs.each(function() {
            enforceMinimum($(this), min, false);
            enforceMaximum($(this), max, false);
        });
        blockMinus(min);
        blockPlus(max);

        $(document).on('input change blur', '.single-product form.cart .quantity input.qty', function() {
            var $input = $(this);
            var changed = enforceMinimum($input, min, true);
            enforceMaximum($input, max, !changed);
            blockMinus(min);
            blockPlus(max);
        });

        $(document).on('click', '.single-product form.cart .quantity .minus', function(event) {
            var $btn = $(this);
            var $input = $btn.closest('.quantity').find('input.qty');
            if (minEnabled) {
                var current = parseInt($input.val(), 10) || 0;
                if (current <= min) {
                    event.preventDefault();
                    enforceMinimum($input, min, true);
                    blockMinus(min);
                    blockPlus(max);
                    return false;
                }
            }
            setTimeout(function() {
                enforceMinimum($input, min, true);
                enforceMaximum($input, max, true);
                blockMinus(min);
                blockPlus(max);
            }, 0);
        });

        $(document).on('click', '.single-product form.cart .quantity .plus', function(event) {
            if (!max || max < 1) {
                return;
            }
            var $btn = $(this);
            var $input = $btn.closest('.quantity').find('input.qty');
            var current = parseInt($input.val(), 10) || 0;
            if (current >= max) {
                event.preventDefault();
                enforceMaximum($input, max, true);
                blockPlus(max);
                return false;
            }
            setTimeout(function() {
                enforceMinimum($input, min, true);
                enforceMaximum($input, max, true);
                blockMinus(min);
                blockPlus(max);
            }, 0);
        });

        $(document).on('submit', '.single-product form.cart', function() {
            var $qty = $(this).find('.quantity input.qty').first();
            if ($qty.length) {
                enforceMinimum($qty, min, true);
                enforceMaximum($qty, max, true);
                blockMinus(min);
                blockPlus(max);
            }
        });
    }

    $(document).ready(function() {
        initProductPage();
    });

})(jQuery);