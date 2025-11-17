/**
 * WooCommerce Order Dates Conversion
 * Converts dates in WooCommerce order list when site language is Persian
 */

(function($) {
    'use strict';
    
    // Check if PersianDateConverter is available
    if (typeof PersianDateConverter === 'undefined') {
        return;
    }
    
    var WooCommerceOrderDates = {
        conversionStarted: false,
        initialized: false,
        
        /**
         * Initialize order date conversion
         */
        init: function() {
            var self = this;
            
            // Prevent multiple initializations
            if (self.initialized) {
                return;
            }
            self.initialized = true;
            
            // Run immediately if DOM is ready, otherwise wait
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    self.startConversion();
                });
            } else {
                // DOM already loaded, run immediately
                self.startConversion();
            }
        },
        
        /**
         * Start the conversion process
         */
        startConversion: function() {
            var self = this;
            
            // Flag to prevent multiple conversions
            if (self.conversionStarted) {
                return;
            }
            self.conversionStarted = true;
            
            // Wait a bit for page to fully load
            setTimeout(function() {
                self.convertOrderListDates();
            }, 100);
            
            // Run once more after a longer delay
            setTimeout(function() {
                self.convertOrderListDates();
            }, 1000);
            
            // Also run after AJAX updates (for order notes)
            setTimeout(function() {
                self.convertOrderListDates();
            }, 2000);
            
            // Use MutationObserver to catch dynamically added order notes
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    var shouldConvert = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === 1) { // Element node
                                    var $node = $(node);
                                    if ($node.closest('.woocommerce_order_notes, .order_notes').length > 0 ||
                                        $node.hasClass('woocommerce_order_notes') ||
                                        $node.hasClass('order_notes')) {
                                        shouldConvert = true;
                                        break;
                                    }
                                }
                            }
                        }
                    });
                    if (shouldConvert) {
                        setTimeout(function() {
                            self.convertOrderListDates();
                        }, 300);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        },
        
        /**
         * Convert dates in order list table and order edit page
         */
        convertOrderListDates: function() {
            var self = this;
            
            // Check if we're on order edit page (post.php with shop_order)
            var isOrderEditPage = false;
            
            // Method 1: Check pagenow variable
            if (typeof pagenow !== 'undefined' && pagenow === 'post.php') {
                // Check if post type is shop_order
                var postType = $('#post_type').val() || $('input[name="post_type"]').val();
                if (postType === 'shop_order') {
                    isOrderEditPage = true;
                }
            }
            
            // Method 2: Check by looking for WooCommerce order elements
            if ($('#woocommerce-order-data, .woocommerce_order_notes, .order_notes, #order_data').length > 0) {
                isOrderEditPage = true;
            }
            
            // Method 3: Check URL
            if (window.location.href.indexOf('post.php') !== -1 && 
                ($('#post_type').val() === 'shop_order' || $('input[name="post_type"]').val() === 'shop_order')) {
                isOrderEditPage = true;
            }
            
            // Convert dates in order notes sidebar (order edit page)
            if (isOrderEditPage) {
                self.convertOrderNotesDates();
            } else {
                // Even if not detected, try to convert if we find order notes
                if ($('.woocommerce_order_notes, .order_notes').length > 0) {
                    self.convertOrderNotesDates();
                }
            }
            
            // Find all date cells in order list table - try multiple selectors
            // First, try to find the date column specifically
            var $dateCells = $('.wp-list-table tbody tr td.column-date, .woocommerce_page_wc-orders tbody tr td.column-date, table.wp-list-table tbody tr td.column-date');
            
            // If no date column found, try finding by content
            if ($dateCells.length === 0) {
                $dateCells = $('table.wp-list-table tbody tr td, .woocommerce_page_wc-orders table tbody tr td').filter(function() {
                    var text = $(this).text().trim();
                    // Check if it looks like a date with Persian month names
                    return /(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)/.test(text) &&
                           /\d{4}/.test(text);
                });
            }
            
            // Skip if no date cells found and not on order edit page
            if ($dateCells.length === 0 && !isOrderEditPage) {
                return;
            }
            
            $dateCells.each(function() {
                var $cell = $(this);
                
                // Skip if already processed
                if ($cell.data('aio-wc-converted') || 
                    $cell.attr('data-aio-wc-converted') === 'true' || 
                    $cell.hasClass('aio-wc-date-converted')) {
                    return;
                }
                
                var originalText = $cell.text().trim();
                
                // Skip if empty
                if (!originalText || originalText.length < 5) {
                    return;
                }
                
                // Check if this cell is actually in the date column
                // Look for date-like patterns
                var looksLikeDate = /(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i.test(originalText) ||
                                   /\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/.test(originalText) ||
                                   /\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}/.test(originalText);
                
                if (!looksLikeDate) {
                    return;
                }
                
                // Check if date contains Persian language month names (Gregorian months in Persian)
                var hasPersianLangMonth = /(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)/.test(originalText);
                
                // Also check for English month names (when site language is Persian but dates show in English)
                var hasEnglishMonth = /(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i.test(originalText);
                
                // Check if it's already a Persian calendar date
                var hasPersianCalendarMonth = /(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/.test(originalText);
                
                // Skip if it's already converted
                if (hasPersianCalendarMonth) {
                    $cell.data('aio-wc-converted', true);
                    return;
                }
                
                // Skip if it doesn't have any recognizable month name
                if (!hasPersianLangMonth && !hasEnglishMonth) {
                    // Not a date we need to convert
                    $cell.data('aio-wc-converted', true);
                    return;
                }
                
                // Skip if already has weird format
                if (/\d{5,}/.test(originalText) || /[۰-۹]{5,}/u.test(originalText)) {
                    $cell.data('aio-wc-converted', true);
                    $cell.data('aio-wc-skipped', true);
                    return;
                }
                
                // Try to convert the date
                var converted = self.convertDateString(originalText);
                if (converted && converted !== originalText && !/\d{5,}/.test(converted)) {
                    // Validate the converted date looks correct (YYYY/MM/DD format)
                    var convertedMatch = converted.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})/);
                    if (convertedMatch) {
                        var convYear = parseInt(convertedMatch[1], 10);
                        var convMonth = parseInt(convertedMatch[2], 10);
                        var convDay = parseInt(convertedMatch[3], 10);
                        
                        // Validate: year should be 1300-1500, month 1-12, day 1-31
                        if (convYear >= 1300 && convYear < 1500 && 
                            convMonth >= 1 && convMonth <= 12 && 
                            convDay >= 1 && convDay <= 31) {
                            
                            // Get Persian month name for display
                            var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                            var persianMonthName = persianMonths[convMonth - 1];
                            
                            // Format as "Day Month Year" in Persian
                            var formattedDate = convDay + ' ' + persianMonthName + ' ' + convYear;
                            
                            // Convert numerals to Persian if available
                            if (typeof PersianNumerals !== 'undefined') {
                                formattedDate = PersianNumerals.toPersian(formattedDate);
                            }
                            
                            // Update the cell content, preserving any HTML structure
                            var $time = $cell.find('time, abbr');
                            if ($time.length > 0) {
                                $time.text(formattedDate);
                                $time.data('aio-wc-converted', true);
                            } else {
                                $cell.text(formattedDate);
                            }
                            
                            // Mark as converted
                            $cell.data('aio-wc-converted', true);
                            $cell.attr('data-aio-wc-converted', 'true');
                            $cell.addClass('aio-wc-date-converted');
                        }
                    }
                }
            });
        },
        
        /**
         * Convert dates in order notes sidebar (order edit page)
         */
        convertOrderNotesDates: function() {
            var self = this;
            
            // Find all date elements in order notes - try multiple selectors
            var noteSelectors = [
                '.woocommerce_order_notes .note_content',
                '.woocommerce_order_notes .note-date',
                '.order_notes .note_content',
                '.order_notes .note-date',
                '.woocommerce_order_notes p',
                '.order_notes p',
                '.woocommerce_order_notes',
                '.order_notes',
                '#woocommerce-order-notes .note_content',
                '#woocommerce-order-notes p',
                '.woocommerce_order_notes li',
                '.order_notes li',
                '.note_content',
                '.note-date'
            ];
            
            var $allNotes = $(noteSelectors.join(', '));
            
            // If no notes found with specific selectors, search more broadly
            if ($allNotes.length === 0) {
                $allNotes = $('body').find('*').filter(function() {
                    var text = $(this).text() || '';
                    return text.match(/(نوامبر|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|دسامبر|November|January|February|March|April|May|June|July|August|September|October|December)/) &&
                           text.match(/\d{4}/) &&
                           ($(this).closest('.woocommerce_order_notes, .order_notes, #woocommerce-order-notes').length > 0 ||
                            $(this).parent().hasClass('woocommerce_order_notes') ||
                            $(this).parent().hasClass('order_notes'));
                });
            }
            
            $allNotes.each(function() {
                var $element = $(this);
                var originalText = $element.html() || $element.text();
                
                // Skip if already processed
                if ($element.data('aio-wc-converted') || 
                    $element.attr('data-aio-wc-converted') === 'true' || 
                    $element.hasClass('aio-wc-date-converted')) {
                    return;
                }
                
                // Check if it contains dates with Persian language month names
                var hasPersianLangMonth = /(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)/.test(originalText);
                var hasEnglishMonth = /(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i.test(originalText);
                
                // Check if it's already a Persian calendar date
                var hasPersianCalendarMonth = /(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/.test(originalText);
                
                if (hasPersianCalendarMonth) {
                    $element.data('aio-wc-converted', true);
                    return;
                }
                
                if (!hasPersianLangMonth && !hasEnglishMonth) {
                    $element.data('aio-wc-converted', true);
                    return;
                }
                
                // Convert dates in the text - look for full date patterns
                var convertedText = originalText;
                
                // Pattern 1: "نوامبر ۱۳, ۲۰۲۵" or "November 13, 2025" (with comma)
                var datePattern1 = /((?:ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر|January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s,،]+[\d۰-۹]+[\s,،]+[\d۰-۹]{4})/gi;
                
                // Pattern 2: "۱۳ نوامبر, ۲۰۲۵" or "13 November, 2025" (with comma)
                var datePattern2 = /([\d۰-۹]+[\s,،]+(?:ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر|January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s,،]+[\d۰-۹]{4})/gi;
                
                // Pattern 3: "نوامبر ۱۳ ۲۰۲۵" or "November 13 2025" (without comma)
                var datePattern3 = /((?:ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر|January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s]+[\d۰-۹]+[\s]+[\d۰-۹]{4})/gi;
                
                // Pattern 4: "۱۳ نوامبر ۲۰۲۵" or "13 November 2025" (without comma)
                var datePattern4 = /([\d۰-۹]+[\s]+(?:ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر|January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s]+[\d۰-۹]{4})/gi;
                
                // Try pattern 1 first
                convertedText = convertedText.replace(datePattern1, function(match) {
                    var converted = self.convertDateString(match);
                    if (converted && converted !== match && !/\d{5,}/.test(converted)) {
                        // Validate the converted date
                        var convertedMatch = converted.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})/);
                        if (convertedMatch) {
                            var convYear = parseInt(convertedMatch[1], 10);
                            var convMonth = parseInt(convertedMatch[2], 10);
                            var convDay = parseInt(convertedMatch[3], 10);
                            
                            if (convYear >= 1300 && convYear < 1500 && 
                                convMonth >= 1 && convMonth <= 12 && 
                                convDay >= 1 && convDay <= 31) {
                                
                                var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                                var persianMonthName = persianMonths[convMonth - 1];
                                var formattedDate = convDay + ' ' + persianMonthName + ' ' + convYear;
                                
                                if (typeof PersianNumerals !== 'undefined') {
                                    formattedDate = PersianNumerals.toPersian(formattedDate);
                                }
                                
                                return formattedDate;
                            }
                        }
                    }
                    return match;
                });
                
                // Try pattern 2 if pattern 1 didn't match
                convertedText = convertedText.replace(datePattern2, function(match) {
                    var converted = self.convertDateString(match);
                    if (converted && converted !== match && !/\d{5,}/.test(converted)) {
                        // Validate the converted date
                        var convertedMatch = converted.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})/);
                        if (convertedMatch) {
                            var convYear = parseInt(convertedMatch[1], 10);
                            var convMonth = parseInt(convertedMatch[2], 10);
                            var convDay = parseInt(convertedMatch[3], 10);
                            
                            if (convYear >= 1300 && convYear < 1500 && 
                                convMonth >= 1 && convMonth <= 12 && 
                                convDay >= 1 && convDay <= 31) {
                                
                                var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                                var persianMonthName = persianMonths[convMonth - 1];
                                var formattedDate = convDay + ' ' + persianMonthName + ' ' + convYear;
                                
                                if (typeof PersianNumerals !== 'undefined') {
                                    formattedDate = PersianNumerals.toPersian(formattedDate);
                                }
                                
                                return formattedDate;
                            }
                        }
                    }
                    return match;
                });
                
                // Try pattern 3 if patterns 1 and 2 didn't match
                convertedText = convertedText.replace(datePattern3, function(match) {
                    var converted = self.convertDateString(match);
                    if (converted && converted !== match && !/\d{5,}/.test(converted)) {
                        var convertedMatch = converted.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})/);
                        if (convertedMatch) {
                            var convYear = parseInt(convertedMatch[1], 10);
                            var convMonth = parseInt(convertedMatch[2], 10);
                            var convDay = parseInt(convertedMatch[3], 10);
                            
                            if (convYear >= 1300 && convYear < 1500 && 
                                convMonth >= 1 && convMonth <= 12 && 
                                convDay >= 1 && convDay <= 31) {
                                
                                var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                                var persianMonthName = persianMonths[convMonth - 1];
                                var formattedDate = convDay + ' ' + persianMonthName + ' ' + convYear;
                                
                                if (typeof PersianNumerals !== 'undefined') {
                                    formattedDate = PersianNumerals.toPersian(formattedDate);
                                }
                                
                                return formattedDate;
                            }
                        }
                    }
                    return match;
                });
                
                // Try pattern 4 if patterns 1, 2, and 3 didn't match
                convertedText = convertedText.replace(datePattern4, function(match) {
                    var converted = self.convertDateString(match);
                    if (converted && converted !== match && !/\d{5,}/.test(converted)) {
                        var convertedMatch = converted.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})/);
                        if (convertedMatch) {
                            var convYear = parseInt(convertedMatch[1], 10);
                            var convMonth = parseInt(convertedMatch[2], 10);
                            var convDay = parseInt(convertedMatch[3], 10);
                            
                            if (convYear >= 1300 && convYear < 1500 && 
                                convMonth >= 1 && convMonth <= 12 && 
                                convDay >= 1 && convDay <= 31) {
                                
                                var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                                var persianMonthName = persianMonths[convMonth - 1];
                                var formattedDate = convDay + ' ' + persianMonthName + ' ' + convYear;
                                
                                if (typeof PersianNumerals !== 'undefined') {
                                    formattedDate = PersianNumerals.toPersian(formattedDate);
                                }
                                
                                return formattedDate;
                            }
                        }
                    }
                    return match;
                });
                
                if (convertedText !== originalText) {
                    $element.html(convertedText);
                    $element.data('aio-wc-converted', true);
                    $element.attr('data-aio-wc-converted', 'true');
                    $element.addClass('aio-wc-date-converted');
                    convertedCount++;
                } else {
                    $element.data('aio-wc-converted', true);
                }
            });
        },
        
        /**
         * Convert a date string to Persian
         */
        convertDateString: function(dateStr) {
            if (!dateStr || typeof dateStr !== 'string') {
                return dateStr;
            }
            
            // Map Persian language month names to English for conversion
            var persianToEnglishMonths = {
                'ژانویه': 'January', 'ژانویهٔ': 'January',
                'فوریه': 'February', 'فوریهٔ': 'February',
                'مارس': 'March',
                'آوریل': 'April',
                'مه': 'May', 'مهٔ': 'May',
                'ژوئن': 'June',
                'جولای': 'July',
                'آگوست': 'August',
                'سپتامبر': 'September',
                'اکتبر': 'October',
                'نوامبر': 'November',
                'دسامبر': 'December',
                // Also map English month names (for normalization)
                'January': 'January', 'Jan': 'January',
                'February': 'February', 'Feb': 'February',
                'March': 'March', 'Mar': 'March',
                'April': 'April', 'Apr': 'April',
                'May': 'May',
                'June': 'June', 'Jun': 'June',
                'July': 'July', 'Jul': 'July',
                'August': 'August', 'Aug': 'August',
                'September': 'September', 'Sep': 'September',
                'October': 'October', 'Oct': 'October',
                'November': 'November', 'Nov': 'November',
                'December': 'December', 'Dec': 'December'
            };
            
            // Extract date part - try multiple patterns
            // Pattern 1: "۱۳ نوامبر, ۲۰۲۵" or "۱۳ نوامبر ۲۰۲۵"
            var dateMatch = dateStr.match(/([\d۰-۹]+)\s+(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)[,،]?\s+([\d۰-۹]{4})/);
            
            if (!dateMatch) {
                // Pattern 2: "نوامبر ۱۳, ۲۰۲۵" or "نوامبر ۱۳ ۲۰۲۵"
                dateMatch = dateStr.match(/(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+([\d۰-۹]+)[,،]?\s+([\d۰-۹]{4})/);
            }
            
            if (!dateMatch) {
                // Pattern 3: "۱۳ نوامبر ۲۰۲۵" (no comma, no space after month)
                dateMatch = dateStr.match(/([\d۰-۹]{1,2})\s+(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+([\d۰-۹]{4})/);
            }
            
            if (!dateMatch) {
                // Pattern 4: "نوامبر ۱۳ ۲۰۲۵" (no comma)
                dateMatch = dateStr.match(/(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+([\d۰-۹]{1,2})\s+([\d۰-۹]{4})/);
            }
            
            // If no Persian month match, try English month names
            if (!dateMatch) {
                // Pattern 5: "November 13, 2025" or "November 13 2025"
                dateMatch = dateStr.match(/(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d+)[,،]?\s+(\d{4})/i);
            }
            
            if (!dateMatch) {
                // Pattern 6: "13 November, 2025" or "13 November 2025"
                dateMatch = dateStr.match(/(\d+)\s+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[,،]?\s+(\d{4})/i);
            }
            
            if (dateMatch) {
                try {
                    var monthName, day, year;
                    
                    // Determine which match group is the month name
                    var monthIndex = -1;
                    for (var i = 1; i <= 3; i++) {
                        if (dateMatch[i]) {
                            var testMonth = dateMatch[i];
                            // Check both exact match and case-insensitive match for English months
                            if (persianToEnglishMonths[testMonth] || 
                                (testMonth.length >= 3 && persianToEnglishMonths[testMonth.charAt(0).toUpperCase() + testMonth.slice(1).toLowerCase()])) {
                                monthIndex = i;
                                monthName = testMonth;
                                break;
                            }
                        }
                    }
                    
                    if (monthIndex === -1) {
                        return dateStr;
                    }
                    
                    // Extract day and year based on which group is the month
                    if (monthIndex === 2) {
                        // Format: "۱۳ نوامبر, ۲۰۲۵" or "13 November, 2025"
                        day = dateMatch[1].replace(/[۰-۹]/g, function(char) {
                            return String.fromCharCode(char.charCodeAt(0) - 1728);
                        });
                        year = dateMatch[3].replace(/[۰-۹]/g, function(char) {
                            return String.fromCharCode(char.charCodeAt(0) - 1728);
                        });
                    } else if (monthIndex === 1) {
                        // Format: "نوامبر ۱۳, ۲۰۲۵" or "November 13, 2025"
                        day = dateMatch[2].replace(/[۰-۹,،]/g, function(char) {
                            if (char === ',' || char === '،') return '';
                            return String.fromCharCode(char.charCodeAt(0) - 1728);
                        });
                        year = dateMatch[3].replace(/[۰-۹]/g, function(char) {
                            return String.fromCharCode(char.charCodeAt(0) - 1728);
                        });
                    } else {
                        return dateStr;
                    }
                    
                    // Convert month name to English (handle both Persian and English month names)
                    var englishMonth = persianToEnglishMonths[monthName];
                    if (!englishMonth) {
                        // Try case-insensitive match for English months
                        var monthLower = monthName.toLowerCase();
                        var monthMap = {
                            'jan': 'January', 'january': 'January',
                            'feb': 'February', 'february': 'February',
                            'mar': 'March', 'march': 'March',
                            'apr': 'April', 'april': 'April',
                            'may': 'May',
                            'jun': 'June', 'june': 'June',
                            'jul': 'July', 'july': 'July',
                            'aug': 'August', 'august': 'August',
                            'sep': 'September', 'september': 'September',
                            'oct': 'October', 'october': 'October',
                            'nov': 'November', 'november': 'November',
                            'dec': 'December', 'december': 'December'
                        };
                        englishMonth = monthMap[monthLower];
                    }
                    
                    if (!englishMonth) {
                        return dateStr;
                    }
                    
                    // Map to month number
                    var monthNames = {
                        'January': 1, 'February': 2, 'March': 3, 'April': 4,
                        'May': 5, 'June': 6, 'July': 7, 'August': 8,
                        'September': 9, 'October': 10, 'November': 11, 'December': 12
                    };
                    
                    var monthNum = monthNames[englishMonth];
                    if (!monthNum) {
                        return dateStr;
                    }
                    
                    var gYear = parseInt(year, 10);
                    var gMonth = parseInt(monthNum, 10);
                    var gDay = parseInt(day, 10);
                    
                    // Convert to Persian using gregorianToJalali
                    var persianDate = PersianDateConverter.gregorianToJalali(gYear, gMonth, gDay);
                    if (persianDate && persianDate.year && persianDate.month && persianDate.day) {
                        // Format as Y/m/d
                        var pad = function(num) {
                            return (num < 10 ? '0' : '') + num;
                        };
                        return persianDate.year + '/' + 
                               pad(persianDate.month) + '/' + 
                               pad(persianDate.day);
                    }
                } catch (e) {
                    // Silently fail
                }
            }
            
            return dateStr;
        }
    };
    
    // Initialize on page load
    WooCommerceOrderDates.init();
    
})(jQuery);

