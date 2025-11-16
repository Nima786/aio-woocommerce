(function() {
    'use strict';

    if (typeof window.aioWcCartRulesData === 'undefined') {
        return;
    }

    var data = window.aioWcCartRulesData;
    var tableBody = document.querySelector('.aio-wc-cart-rules__rows');
    var hiddenField = document.getElementById('aio_wc_cart_rules');
    var addButton = document.querySelector('.aio-wc-cart-rules__add');

    var thousandSep = typeof data.thousand_sep === 'string' ? data.thousand_sep : ',';
    var decimalSep = typeof data.decimal_sep === 'string' ? data.decimal_sep : '.';
    var decimals = typeof data.decimals === 'number' ? data.decimals : parseInt(data.decimals || 0, 10);
    if (isNaN(decimals) || decimals < 0) {
        decimals = 0;
    }

    var rules = Array.isArray(data.rules) ? JSON.parse(JSON.stringify(data.rules)) : [];

    if (!rules.length) {
        rules = [
            { price_min: 0, price_max: 30000, min_qty: 12 },
            { price_min: 30000, price_max: 50000, min_qty: 6 },
            { price_min: 50000, price_max: '', min_qty: 1 }
        ];
    }

    rules = rules.map(function(rule) {
        return {
            price_min: sanitizePrice(rule.price_min),
            price_max: rule.price_max === '' ? '' : sanitizePrice(rule.price_max),
            min_qty: sanitizeInteger(rule.min_qty)
        };
    });

    function escapeRegExp(string) {
        return string.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&');
    }

    var digitMapPersian = {
        '\u06F0': '0', '\u06F1': '1', '\u06F2': '2', '\u06F3': '3', '\u06F4': '4',
        '\u06F5': '5', '\u06F6': '6', '\u06F7': '7', '\u06F8': '8', '\u06F9': '9'
    };
    var digitMapArabic = {
        '\u0660': '0', '\u0661': '1', '\u0662': '2', '\u0663': '3', '\u0664': '4',
        '\u0665': '5', '\u0666': '6', '\u0667': '7', '\u0668': '8', '\u0669': '9'
    };

    function replaceLocaleDigits(str) {
        if (typeof str !== 'string') {
            return str;
        }
        return str
            .replace(/[\u06F0-\u06F9]/g, function(ch) { return digitMapPersian[ch] || ch; })
            .replace(/[\u0660-\u0669]/g, function(ch) { return digitMapArabic[ch] || ch; });
    }

    function sanitizeInteger(value) {
        if (value === null || value === undefined) {
            return 1;
        }
        var normalized = replaceLocaleDigits(String(value));
        var parsed = parseInt(normalized, 10);
        if (isNaN(parsed) || parsed < 1) {
            parsed = 1;
        }
        return parsed;
    }

    function normalizeDecimalString(value) {
        if (value === null || value === undefined) {
            return '';
        }

        var str = String(value).trim();
        if (str === '') {
            return '';
        }

        str = replaceLocaleDigits(str);

        if (thousandSep) {
            var thousandRegex = new RegExp(escapeRegExp(thousandSep), 'g');
            str = str.replace(thousandRegex, '');
        }

        if (decimalSep && decimalSep !== '.') {
            var decimalRegex = new RegExp(escapeRegExp(decimalSep), 'g');
            str = str.replace(decimalRegex, '.');
        }

        str = str.replace(/[^0-9.+-]/g, '');

        var parsed = parseFloat(str);
        return isNaN(parsed) ? '' : parsed;
    }

    function sanitizePrice(value) {
        var numeric = normalizeDecimalString(value);
        if (numeric === '') {
            return '';
        }
        var factor = Math.pow(10, decimals);
        numeric = Math.round(numeric * factor) / factor;
        if (numeric < 0) {
            numeric = 0;
        }
        return numeric;
    }

    function formatPrice(value) {
        if (value === '' || value === null || typeof value === 'undefined') {
            return '';
        }
        var numeric = parseFloat(value);
        if (isNaN(numeric)) {
            return '';
        }
        var fixed = numeric.toFixed(decimals);
        var parts = fixed.split('.');
        var integerPart = parts[0];
        var fractionPart = parts[1] || '';

        var rgx = /(\d+)(\d{3})/;
        while (rgx.test(integerPart)) {
            integerPart = integerPart.replace(rgx, '$1' + thousandSep + '$2');
        }

        if (decimals > 0) {
            return integerPart + decimalSep + fractionPart;
        }
        return integerPart;
    }

    function plainPrice(value) {
        if (value === '' || value === null || typeof value === 'undefined') {
            return '';
        }
        var numeric = parseFloat(value);
        if (isNaN(numeric)) {
            return '';
        }
        var fixed = numeric.toFixed(decimals);
        if (decimals > 0 && decimalSep !== '.') {
            fixed = fixed.replace('.', decimalSep);
        }
        if (decimals === 0) {
            return String(parseInt(fixed, 10));
        }
        return fixed;
    }

    function updateHiddenField() {
        if (hiddenField) {
            hiddenField.value = JSON.stringify(rules);
        }
    }

    function renderRows() {
        tableBody.innerHTML = '';

        rules.forEach(function(rule, index) {
            var row = document.createElement('tr');
            row.className = 'aio-wc-cart-rules__row';
            row.dataset.index = index;

            var minCell = document.createElement('td');
            var minInput = document.createElement('input');
            minInput.type = 'text';
            minInput.inputMode = 'decimal';
            minInput.autocomplete = 'off';
            minInput.dir = 'ltr';
            minInput.className = 'aio-wc-input';
            minInput.dataset.field = 'price_min';
            minInput.value = rule.price_min === '' ? '' : formatPrice(rule.price_min);
            if (rule.price_min !== '') {
                minInput.setAttribute('data-raw-value', plainPrice(rule.price_min));
            }
            minCell.appendChild(minInput);

            var maxCell = document.createElement('td');
            var maxInput = document.createElement('input');
            maxInput.type = 'text';
            maxInput.inputMode = 'decimal';
            maxInput.autocomplete = 'off';
            maxInput.dir = 'ltr';
            maxInput.placeholder = '-';
            maxInput.className = 'aio-wc-input';
            maxInput.dataset.field = 'price_max';
            maxInput.value = rule.price_max === '' ? '' : formatPrice(rule.price_max);
            if (rule.price_max !== '') {
                maxInput.setAttribute('data-raw-value', plainPrice(rule.price_max));
            }
            maxCell.appendChild(maxInput);

            var qtyCell = document.createElement('td');
            var qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.min = '1';
            qtyInput.step = '1';
            qtyInput.className = 'aio-wc-input';
            qtyInput.dataset.field = 'min_qty';
            qtyInput.value = rule.min_qty;
            qtyCell.appendChild(qtyInput);

            var actionsCell = document.createElement('td');
            actionsCell.className = 'aio-wc-cart-rules__actions-cell';
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'aio-wc-cart-rules__remove';
            removeBtn.setAttribute('aria-label', data.i18nRemove);
            removeBtn.innerHTML = '&times;';
            actionsCell.appendChild(removeBtn);

            row.appendChild(minCell);
            row.appendChild(maxCell);
            row.appendChild(qtyCell);
            row.appendChild(actionsCell);

            tableBody.appendChild(row);
        });

        updateHiddenField();
    }

    function handleTableClick(event) {
        var removeBtn = event.target.closest('.aio-wc-cart-rules__remove');
        if (!removeBtn) {
            return;
        }
        event.preventDefault();
        var row = removeBtn.closest('tr');
        if (!row) {
            return;
        }
        var index = parseInt(row.dataset.index, 10);
        if (isNaN(index)) {
            return;
        }
        rules.splice(index, 1);
        if (!rules.length) {
            rules.push({ price_min: 0, price_max: '', min_qty: 1 });
        }
        renderRows();
    }

    function handleInputChange(event) {
        var input = event.target;
        if (!input.matches('.aio-wc-cart-rules__row input')) {
            return;
        }

        var row = input.closest('.aio-wc-cart-rules__row');
        if (!row) {
            return;
        }
        var index = parseInt(row.dataset.index, 10);
        if (isNaN(index) || !rules[index]) {
            return;
        }

        var field = input.getAttribute('data-field');
        var value = input.value;

        if (field === 'min_qty') {
            if (value === '') {
                input.value = '';
                return;
            }
            rules[index].min_qty = sanitizeInteger(value);
            input.value = rules[index].min_qty;
        } else if (field === 'price_min' || field === 'price_max') {
            var sanitized = value === '' ? '' : sanitizePrice(value);
            rules[index][field] = sanitized;
            if (sanitized === '') {
                input.value = '';
                input.removeAttribute('data-raw-value');
            } else {
                var plain = plainPrice(sanitized);
                input.value = plain;
                input.setAttribute('data-raw-value', plain);
            }
        }

        updateHiddenField();
    }

    function handleInputFocus(event) {
        var input = event.target;
        if (!input.matches('.aio-wc-cart-rules__row input[data-field="price_min"], .aio-wc-cart-rules__row input[data-field="price_max"]')) {
            return;
        }
        var row = input.closest('.aio-wc-cart-rules__row');
        if (!row) {
            return;
        }
        var index = parseInt(row.dataset.index, 10);
        if (isNaN(index) || !rules[index]) {
            return;
        }
        var field = input.getAttribute('data-field');
        var stored = rules[index][field];
        if (stored === '' || stored === null || typeof stored === 'undefined') {
            input.value = '';
            input.removeAttribute('data-raw-value');
        } else {
            var plain = plainPrice(stored);
            input.value = plain;
            input.setAttribute('data-raw-value', plain);
        }
        input.select();
    }

    function handleInputBlur(event) {
        var input = event.target;
        if (!input.matches('.aio-wc-cart-rules__row input[data-field="price_min"], .aio-wc-cart-rules__row input[data-field="price_max"]')) {
            return;
        }
        var row = input.closest('.aio-wc-cart-rules__row');
        if (!row) {
            return;
        }
        var index = parseInt(row.dataset.index, 10);
        if (isNaN(index) || !rules[index]) {
            return;
        }
        var field = input.getAttribute('data-field');
        var stored = rules[index][field];

        if (field === 'price_min' || field === 'price_max') {
            var min = rules[index].price_min;
            var max = rules[index].price_max;

            if (field === 'price_min' && max !== '' && min !== '' && max < min) {
                rules[index].price_max = '';
                var maxInput = row.querySelector('input[data-field="price_max"]');
                if (maxInput) {
                    maxInput.value = '';
                    maxInput.removeAttribute('data-raw-value');
                }
                max = '';
            }

            if (field === 'price_max' && max !== '' && min !== '' && max < min) {
                rules[index].price_max = '';
                stored = '';
            }
        }
        if (stored === '' || stored === null || typeof stored === 'undefined') {
            input.value = '';
            input.removeAttribute('data-raw-value');
        } else {
            var plain = plainPrice(stored);
            input.setAttribute('data-raw-value', plain);
            input.value = formatPrice(stored);
        }

        updateHiddenField();
    }

    function addRule(event) {
        event.preventDefault();
        var lastRule = rules.length ? rules[rules.length - 1] : null;
        var baseMin = lastRule ? (lastRule.price_max !== '' ? lastRule.price_max : lastRule.price_min) : 0;
        if (baseMin === '') {
            baseMin = 0;
        }
        var newRule = {
            price_min: sanitizePrice(baseMin),
            price_max: '',
            min_qty: 1
        };
        rules.push(newRule);
        renderRows();
    }

    if (tableBody && hiddenField && addButton) {
        tableBody.addEventListener('click', handleTableClick);
        tableBody.addEventListener('input', handleInputChange);
        tableBody.addEventListener('change', handleInputChange);
        tableBody.addEventListener('focus', handleInputFocus, true);
        tableBody.addEventListener('blur', handleInputBlur, true);
        addButton.addEventListener('click', addRule);
        renderRows();
    }

    var cartMinimumInput = document.getElementById('cart_minimum_amount');
    if (cartMinimumInput) {
        cartMinimumInput.type = 'text';
        cartMinimumInput.inputMode = 'decimal';
        cartMinimumInput.autocomplete = 'off';
        cartMinimumInput.dir = 'ltr';

        function setCartMinimumRaw(raw) {
            if (raw === '' || raw === null || typeof raw === 'undefined') {
                cartMinimumInput.removeAttribute('data-raw-value');
            } else {
                cartMinimumInput.setAttribute('data-raw-value', raw);
            }
        }

        function sanitizeCartMinimum(value) {
            if (value === '' || value === null || typeof value === 'undefined') {
                return '';
            }
            return sanitizePrice(value);
        }

        function setCartMinimumDisplay(amount) {
            if (amount === '' || amount === null || typeof amount === 'undefined') {
                cartMinimumInput.value = '';
            } else {
                cartMinimumInput.value = formatPrice(amount);
            }
        }

        var initialValue = sanitizeCartMinimum(cartMinimumInput.value);
        if (initialValue !== '') {
            setCartMinimumDisplay(initialValue);
            setCartMinimumRaw(plainPrice(initialValue));
        }

        cartMinimumInput.addEventListener('focus', function() {
            var raw = cartMinimumInput.getAttribute('data-raw-value');
            cartMinimumInput.value = raw ? raw : '';
            cartMinimumInput.select();
        });

        cartMinimumInput.addEventListener('input', function() {
            var sanitized = sanitizeCartMinimum(cartMinimumInput.value);
            if (sanitized === '') {
                cartMinimumInput.value = '';
                cartMinimumInput.removeAttribute('data-raw-value');
            } else {
                var plain = plainPrice(sanitized);
                cartMinimumInput.value = plain;
                setCartMinimumRaw(plain);
            }
        });

        cartMinimumInput.addEventListener('blur', function() {
            var raw = cartMinimumInput.getAttribute('data-raw-value');
            if (!raw) {
                cartMinimumInput.value = '';
                cartMinimumInput.removeAttribute('data-raw-value');
                return;
            }
            var sanitized = sanitizeCartMinimum(raw);
            setCartMinimumDisplay(sanitized);
        });
    }

    var settingsForm = document.getElementById('aio-wc-settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function() {
            if (tableBody) {
                var priceInputs = tableBody.querySelectorAll('input[data-field="price_min"], input[data-field="price_max"]');
                priceInputs.forEach(function(input) {
                    var raw = input.getAttribute('data-raw-value');
                    if (raw !== null) {
                        input.value = raw;
                    }
                });
            }
            if (cartMinimumInput) {
                var rawMin = cartMinimumInput.getAttribute('data-raw-value');
                if (rawMin !== null) {
                    cartMinimumInput.value = rawMin;
                }
            }
        });
    }
})();
