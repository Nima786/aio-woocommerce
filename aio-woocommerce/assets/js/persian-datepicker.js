/**
 * Persian Date Picker for WooCommerce
 * Replaces jQuery UI datepicker with Persian calendar
 */

(function($) {
    'use strict';
    
    // Check if PersianDateConverter is available
    if (typeof PersianDateConverter === 'undefined') {
        console.error('PersianDateConverter is not loaded');
        return;
    }
    
    // Check if settings are available
    if (typeof aioWcDatePicker === 'undefined') {
        return;
    }
    
    var PersianDatePicker = {
        /**
         * Initialize Persian date picker
         */
        init: function() {
            this.replaceDatePickers();
            this.convertExistingDates();
            this.handleFormSubmissions();
        },
        
    /**
     * Replace jQuery UI datepicker with Persian calendar
     */
    replaceDatePickers: function() {
        var self = this;
        
        // Wait a bit for WooCommerce to initialize its date pickers
        setTimeout(function() {
            // Find all date picker inputs (WooCommerce uses various selectors)
            // Be more specific to avoid interfering with order table functionality
            var selectors = [
                'input.hasDatepicker:not(.woocommerce_table_items input)',
                'input.date-picker:not(.woocommerce_table_items input)',
                '.wc-date-range input[type="text"]',
                '.date-range input[type="text"]',
                '#order_date_from',
                '#order_date_to',
                '.woocommerce-reports-wide input.hasDatepicker',
                '.woocommerce-reports-wide input.date-picker'
            ];
            
            // Exclude inputs that are part of order table or other critical WooCommerce functionality
            var excludeSelectors = [
                '.woocommerce_table_items input',
                '.woocommerce_order_items input',
                'table.wp-list-table input',
                '.widefat input',
                'input[readonly]'
            ];
            
            var dateInputs = $(selectors.join(', '));
            
            // Filter out excluded inputs
            dateInputs = dateInputs.filter(function() {
                var $input = $(this);
                for (var i = 0; i < excludeSelectors.length; i++) {
                    if ($input.closest(excludeSelectors[i].replace('input', '')).length > 0) {
                        return false;
                    }
                }
                return true;
            });
            
            dateInputs.each(function() {
                var $input = $(this);
                
                // Skip if already initialized, hidden, or part of order table
                if ($input.data('persian-datepicker-initialized') || 
                    $input.is(':hidden') ||
                    $input.closest('table.wp-list-table').length > 0 ||
                    $input.closest('.woocommerce_order_items').length > 0 ||
                    $input.closest('.woocommerce_table_items').length > 0) {
                    return;
                }
                
                // Store original value
                var originalValue = $input.val();
                
                // Convert to Persian if value exists
                if (originalValue && originalValue.match(/^\d{4}-\d{2}-\d{2}/)) {
                    var persianDate = self.convertToPersian(originalValue);
                    if (persianDate) {
                        $input.val(persianDate);
                        $input.data('original-gregorian', originalValue);
                    }
                }
                
                // Destroy existing jQuery UI datepicker if present
                if ($input.hasClass('hasDatepicker')) {
                    try {
                        $input.datepicker('destroy');
                    } catch (e) {
                        // Datepicker might not be initialized
                    }
                }
                
                // Remove datepicker class to prevent re-initialization
                $input.removeClass('hasDatepicker');
                
                // Create custom Persian date picker
                self.createPersianDatePicker($input);
                
                $input.data('persian-datepicker-initialized', true);
            });
            
            // Also handle WooCommerce's custom date range pickers
            self.handleWooCommerceDateRanges();
            
            // Convert order date filter dropdown after a short delay to ensure it's rendered
            setTimeout(function() {
                self.convertOrderDateFilterDropdown();
            }, 300);
        }, 500);
    },
    
    /**
     * Handle WooCommerce date range pickers (Orders filter, Reports)
     */
    handleWooCommerceDateRanges: function() {
        var self = this;
        
        // Convert order date filter dropdown month names to Persian
        self.convertOrderDateFilterDropdown();
        
        // Handle date filter dropdown change (Works for WordPress Posts/Pages and WooCommerce)
        $(document).on('change', 'select[name="m"], select.order_date_filter, select.wc-order-date-filter, select.order-date-filter', function() {
            // The conversion happens when dropdown is rendered, but we ensure it's converted
            self.convertOrderDateFilterDropdown();
        });
        
        // Also convert when dropdown is opened (for WordPress Posts/Pages)
        $(document).on('focus', 'select[name="m"]', function() {
            setTimeout(function() {
                self.convertOrderDateFilterDropdown();
            }, 100);
        });
        
        // Handle custom date range inputs in Reports
        $('.wc-date-range, .date-range').each(function() {
            var $container = $(this);
            $container.find('input[type="text"]').each(function() {
                var $input = $(this);
                if (!$input.data('persian-datepicker-initialized')) {
                    self.createPersianDatePicker($input);
                    $input.data('persian-datepicker-initialized', true);
                }
            });
        });
    },
    
    /**
     * Convert order date filter dropdown to Persian calendar
     * Works on WooCommerce Orders, Reports, and WordPress Posts/Pages pages
     */
    convertOrderDateFilterDropdown: function() {
        // Find the date filter dropdown
        // WordPress Posts/Pages use: select[name="m"]
        // WooCommerce uses various selectors
        var $selects = $('select[name="m"], select.order_date_filter, select.wc-order-date-filter, select.order-date-filter, .woocommerce-orders-filter__date select');
        
        if ($selects.length === 0) {
            // Try to find by looking for selects with date options
            // Check all selects, but prioritize those in filter areas
            // IMPORTANT: Check for ANY month pattern, not just English (works with any language)
            $('.tablenav select, .wp-list-table .alignleft select, .woocommerce-orders-filter select').each(function() {
                var $select = $(this);
                var $firstOption = $select.find('option').first();
                if ($firstOption.length > 0) {
                    var text = $firstOption.text();
                    // Check if it looks like a date filter - match ANY month pattern (English, Persian, or numeric)
                    // Pattern: "Month Year" or "All dates" or contains 4-digit year
                    if (text.toLowerCase().indexOf('all dates') !== -1 || 
                        /(january|february|march|april|may|june|july|august|september|october|november|december|فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/i.test(text) ||
                        /\w+\s+\d{4}/.test(text)) { // Any word followed by 4-digit year
                        $selects = $selects.add($select);
                    }
                }
            });
            
            // If still not found, check all selects (as fallback)
            if ($selects.length === 0) {
                $('select').each(function() {
                    var $select = $(this);
                    var $firstOption = $select.find('option').first();
                    if ($firstOption.length > 0) {
                        var text = $firstOption.text();
                        // Check for ANY month pattern or date-like pattern
                        if (text.toLowerCase().indexOf('all dates') !== -1 || 
                            /(january|february|march|april|may|june|july|august|september|october|november|december|فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/i.test(text) ||
                            /\w+\s+\d{4}/.test(text)) {
                            $selects = $selects.add($select);
                        }
                    }
                });
            }
        }
        
        $selects.each(function() {
            var $select = $(this);
            
            // Skip if already converted
            if ($select.data('persian-date-converted')) {
                return;
            }
            
            var persianMonths = aioWcDatePicker.persian_months || [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ];
            
            var gregorianMonths = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            var gregorianMonthsShort = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];
            
            // Persian month names (for when WordPress is in Persian language)
            var persianMonthNames = [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ];
            
            // Convert each option
            $select.find('option').each(function() {
                var $option = $(this);
                var text = $option.text().trim();
                var value = $option.val();
                
                // Skip "All dates" option (check in multiple languages)
                if (text.toLowerCase().indexOf('all dates') !== -1 || 
                    text.indexOf('همه تاریخ') !== -1 || 
                    value === '0' || value === '') {
                    return;
                }
                
                // Skip if already converted (contains Persian month name)
                var alreadyPersian = false;
                for (var p = 0; p < persianMonthNames.length; p++) {
                    if (text.indexOf(persianMonthNames[p]) !== -1) {
                        // Check if it's already a Persian calendar month (not just Persian language month name)
                        // If it contains Persian month name AND a 4-digit year, it might be already converted
                        var yearMatch = text.match(/\d{4}/);
                        if (yearMatch) {
                            var year = parseInt(yearMatch[0], 10);
                            // If year is in Persian calendar range (1300-1500), it's already converted
                            if (year >= 1300 && year < 1500) {
                                // Already converted to Persian calendar - skip
                                return;
                            }
                        }
                        // It's a Persian language month name but might be Gregorian date - continue to convert
                        break;
                    }
                }
                
                // Try to match patterns like "November 2025", "Nov 2025", or "نوامبر 2025"
                // Match any word (English or Persian) followed by a 4-digit year
                var monthYearMatch = text.match(/([^\s\d]+)\s+(\d{4})/);
                if (monthYearMatch) {
                    var monthName = monthYearMatch[1];
                    var year = parseInt(monthYearMatch[2], 10);
                    
                    // Determine if this is a Gregorian year (1900-2100) or Persian year (1300-1500)
                    var isGregorianYear = (year >= 1900 && year < 2100);
                    var isPersianYear = (year >= 1300 && year < 1500);
                    
                    // Find the month index - check English months first
                    var monthIndex = -1;
                    for (var i = 0; i < gregorianMonths.length; i++) {
                        if (monthName.toLowerCase() === gregorianMonths[i].toLowerCase() ||
                            monthName.toLowerCase() === gregorianMonthsShort[i].toLowerCase()) {
                            monthIndex = i;
                            break;
                        }
                    }
                    
                    // If not found in English months, check if it's a Persian language month name
                    // (WordPress might show "نوامبر" for November when user language is Persian)
                    // In this case, we need to map Persian language month names to Gregorian months
                    if (monthIndex === -1) {
                        // Map Persian language month names to Gregorian months
                        // These are WordPress's Persian translations of Gregorian months
                        var persianLangMonths = {
                            'ژانویه': 0, 'فوریه': 1, 'مارس': 2, 'آوریل': 3, 'مه': 4, 'ژوئن': 5,
                            'جولای': 6, 'آگوست': 7, 'سپتامبر': 8, 'اکتبر': 9, 'نوامبر': 10, 'دسامبر': 11,
                            'ژانویهٔ': 0, 'فوریهٔ': 1, 'مارس': 2, 'آوریل': 3, 'مهٔ': 4, 'ژوئن': 5,
                            'جولای': 6, 'آگوست': 7, 'سپتامبر': 8, 'اکتبر': 9, 'نوامبر': 10, 'دسامبر': 11
                        };
                        
                        if (persianLangMonths.hasOwnProperty(monthName)) {
                            monthIndex = persianLangMonths[monthName];
                        }
                    }
                    
                    // If we found a month index and it's a Gregorian year, convert to Persian calendar
                    if (monthIndex >= 0 && isGregorianYear) {
                        // Convert the first day of the Gregorian month to Persian calendar
                        var gregorianDate = new Date(year, monthIndex, 1);
                        var jalali = PersianDateConverter.gregorianToJalali(
                            gregorianDate.getFullYear(),
                            gregorianDate.getMonth() + 1,
                            gregorianDate.getDate()
                        );
                        
                        // Update the option text with Persian calendar month name
                        var persianMonthName = persianMonths[jalali.month - 1];
                        var persianYear = jalali.year;
                        
                        // Convert numerals to Persian if available
                        var displayYear = persianYear;
                        if (typeof PersianNumerals !== 'undefined') {
                            displayYear = PersianNumerals.toPersian(String(persianYear));
                        }
                        
                        $option.text(persianMonthName + ' ' + displayYear);
                        
                        // Store original value for when filtering
                        if (!$option.data('original-text')) {
                            $option.data('original-text', text);
                            $option.data('original-value', value);
                        }
                    }
                    // If it's already a Persian calendar year (1300-1500), it might be already converted
                    // but check if the month name matches Persian calendar months
                    else if (isPersianYear) {
                        // Check if month name is already a Persian calendar month
                        var isPersianCalendarMonth = false;
                        for (var pm = 0; pm < persianMonths.length; pm++) {
                            if (text.indexOf(persianMonths[pm]) !== -1) {
                                isPersianCalendarMonth = true;
                                break;
                            }
                        }
                        // If it's already Persian calendar format, skip
                        if (isPersianCalendarMonth) {
                            return;
                        }
                    }
                }
            });
            
            $select.data('persian-date-converted', true);
        });
    },
    
    /**
     * Create Persian date picker for input
     */
    createPersianDatePicker: function($input) {
        var self = this;
        
        // Create Persian date picker HTML
        var pickerId = 'persian-datepicker-' + Math.random().toString(36).substr(2, 9);
        var $picker = $('<div class="aio-wc-persian-datepicker" id="' + pickerId + '"></div>');
        
        // Hide picker when clicking outside (use event delegation with unique identifier)
        var clickHandler = function(e) {
            if (!$(e.target).closest('.aio-wc-persian-datepicker#' + pickerId).length && 
                !$(e.target).is($input[0])) {
                $picker.hide();
                $(document).off('click', clickHandler);
            }
        };
        
        // Add click handler to show picker
        $input.off('focus click.persianpicker').on('focus click.persianpicker', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(document).on('click', clickHandler);
            self.showPersianDatePicker($input, $picker);
        });
    },
    
    /**
     * Show Persian date picker
     */
    showPersianDatePicker: function($input, $picker) {
        var self = this;
        var currentDate = $input.val();
        
        // Parse current date or use today
        var jalali;
        if (currentDate) {
            // Convert Persian numerals to Western numerals if needed
            var normalizedDate = currentDate;
            if (typeof PersianNumerals !== 'undefined') {
                normalizedDate = PersianNumerals.toWestern(currentDate);
            }
            
            // Remove time component if present (e.g., "1404/03/10 03:2")
            normalizedDate = normalizedDate.split(' ')[0].trim();
            
            // Try to parse as Persian date (Y/M/D format)
            var parts = normalizedDate.split('/');
            if (parts.length >= 3) {
                var year = parseInt(parts[0], 10);
                var month = parseInt(parts[1], 10);
                var day = parseInt(parts[2], 10);
                
                // Validate the parsed values
                if (!isNaN(year) && !isNaN(month) && !isNaN(day) && 
                    year > 1300 && year < 1500 && // Reasonable Persian year range
                    month >= 1 && month <= 12 && 
                    day >= 1 && day <= 31) {
                    jalali = {
                        year: year,
                        month: month,
                        day: day
                    };
                }
            }
            
            // If not Persian format, try Gregorian format
            if (!jalali && normalizedDate.match(/^\d{4}-\d{2}-\d{2}/)) {
                var dateParts = normalizedDate.split('-');
                var gYear = parseInt(dateParts[0], 10);
                var gMonth = parseInt(dateParts[1], 10);
                var gDay = parseInt(dateParts[2], 10);
                
                if (!isNaN(gYear) && !isNaN(gMonth) && !isNaN(gDay)) {
                    var gregorian = PersianDateConverter.gregorianToJalali(gYear, gMonth, gDay);
                    jalali = gregorian;
                }
            }
        }
        
        // Fallback to today's date if parsing failed
        if (!jalali || isNaN(jalali.year) || isNaN(jalali.month) || isNaN(jalali.day)) {
            var today = new Date();
            jalali = PersianDateConverter.gregorianToJalali(
                today.getFullYear(),
                today.getMonth() + 1,
                today.getDate()
            );
        }
        
        // Ensure we have valid values
        if (!jalali || isNaN(jalali.year) || isNaN(jalali.month) || isNaN(jalali.day)) {
            var today = new Date();
            jalali = {
                year: today.getFullYear() - 621, // Approximate Persian year
                month: 1,
                day: 1
            };
            // Convert to actual Persian date
            jalali = PersianDateConverter.gregorianToJalali(
                today.getFullYear(),
                today.getMonth() + 1,
                today.getDate()
            );
        }
        
        // Generate calendar HTML with validated values
        var year = Math.max(1300, Math.min(1500, parseInt(jalali.year, 10)));
        var month = Math.max(1, Math.min(12, parseInt(jalali.month, 10)));
        var day = Math.max(1, Math.min(31, parseInt(jalali.day, 10)));
        
        var calendarHTML = self.generatePersianCalendar(year, month, day);
        $picker.html(calendarHTML);
        $picker.data('year', year);
        $picker.data('month', month);
        
        // Position picker
        var offset = $input.offset();
        $picker.css({
            position: 'absolute',
            top: offset.top + $input.outerHeight() + 5,
            left: offset.left,
            zIndex: 10000
        });
        
        // Append to body if not already
        if (!$picker.parent().length) {
            $('body').append($picker);
        }
        
        $picker.show();
        
        // Handle date selection
        $picker.off('click', '.aio-wc-persian-datepicker-day').on('click', '.aio-wc-persian-datepicker-day:not(.empty)', function() {
            var year = parseInt($(this).data('year'), 10);
            var month = parseInt($(this).data('month'), 10);
            var day = parseInt($(this).data('day'), 10);
            
            if (isNaN(year) || isNaN(month) || isNaN(day)) {
                return;
            }
            
            var persianDate = year + '/' + String(month).padStart(2, '0') + '/' + String(day).padStart(2, '0');
            
            // Check if input has time fields (like WooCommerce order edit page)
            var $timeInput = $input.nextAll('input[name*="hour"], input[name*="minute"]').first();
            if ($timeInput.length === 0) {
                // Look for time inputs in the same container
                var $container = $input.closest('.form-field, .order_data, .form-field-wide');
                if ($container.length > 0) {
                    var timeStr = '';
                    var $hourInput = $container.find('input[name*="hour"]');
                    var $minuteInput = $container.find('input[name*="minute"]');
                    if ($hourInput.length > 0 && $minuteInput.length > 0) {
                        var hour = $hourInput.val() || '00';
                        var minute = $minuteInput.val() || '00';
                        timeStr = ' ' + String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                    }
                    $input.val(persianDate + timeStr);
                } else {
                    $input.val(persianDate);
                }
            } else {
                $input.val(persianDate);
            }
            
            // Convert to Gregorian and store for form submission
            var gregorian = PersianDateConverter.jalaliToGregorian(year, month, day);
            var gregorianDate = gregorian.year + '-' + String(gregorian.month).padStart(2, '0') + '-' + String(gregorian.day).padStart(2, '0');
            $input.data('original-gregorian', gregorianDate);
            
            $picker.hide();
            $input.trigger('change');
            $input.trigger('blur');
        });
        
        // Handle month/year navigation
        $picker.off('click', '.aio-wc-persian-datepicker-nav').on('click', '.aio-wc-persian-datepicker-nav', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var direction = $(this).data('direction');
            var currentYear = parseInt($picker.data('year'), 10) || jalali.year;
            var currentMonth = parseInt($picker.data('month'), 10) || jalali.month;
            
            if (direction === 'prev') {
                currentMonth--;
                if (currentMonth < 1) {
                    currentMonth = 12;
                    currentYear--;
                }
            } else {
                currentMonth++;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                }
            }
            
            var calendarHTML = self.generatePersianCalendar(currentYear, currentMonth);
            $picker.html(calendarHTML);
            $picker.data('year', currentYear);
            $picker.data('month', currentMonth);
        });
    },
    
    /**
     * Generate Persian calendar HTML
     */
    generatePersianCalendar: function(year, month, selectedDay) {
        var persianMonths = aioWcDatePicker.persian_months || ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        var persianDays = aioWcDatePicker.persian_day_abbr || aioWcDatePicker.persian_days || ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
        var jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        // Check if leap year (simplified)
        var daysInMonth = jDaysInMonth[month - 1];
        if (month === 12 && this.isLeapYear(year)) {
            daysInMonth = 30;
        }
        
        var html = '<div class="aio-wc-persian-datepicker-container">';
        html += '<div class="aio-wc-persian-datepicker-header">';
        html += '<button type="button" class="aio-wc-persian-datepicker-nav" data-direction="prev">‹</button>';
        html += '<span class="aio-wc-persian-datepicker-month-year">' + persianMonths[month - 1] + ' ' + year + '</span>';
        html += '<button type="button" class="aio-wc-persian-datepicker-nav" data-direction="next">›</button>';
        html += '</div>';
        html += '<div class="aio-wc-persian-datepicker-days-header">';
        for (var i = 0; i < 7; i++) {
            html += '<div class="aio-wc-persian-datepicker-day-name">' + persianDays[i] + '</div>';
        }
        html += '</div>';
        html += '<div class="aio-wc-persian-datepicker-days">';
        
        // Calculate first day of month (simplified)
        var firstDay = this.getFirstDayOfMonth(year, month);
        
        // Empty cells for days before month start
        for (var j = 0; j < firstDay; j++) {
            html += '<div class="aio-wc-persian-datepicker-day empty"></div>';
        }
        
        // Days of month
        for (var day = 1; day <= daysInMonth; day++) {
            var isSelected = (day === selectedDay) ? ' selected' : '';
            html += '<div class="aio-wc-persian-datepicker-day' + isSelected + '" data-year="' + year + '" data-month="' + month + '" data-day="' + day + '">' + day + '</div>';
        }
        
        html += '</div>';
        html += '</div>';
        
        return html;
    },
    
    /**
     * Get first day of month (0 = Saturday, 6 = Friday)
     */
    getFirstDayOfMonth: function(year, month) {
        // Convert first day of Persian month to Gregorian
        var gregorian = PersianDateConverter.jalaliToGregorian(year, month, 1);
        var date = new Date(gregorian.year, gregorian.month - 1, gregorian.day);
        var day = date.getDay();
        // Adjust for Persian week (Saturday = 0)
        return (day + 1) % 7;
    },
    
    /**
     * Check if year is leap year
     */
    isLeapYear: function(year) {
        var a = year - 979;
        return (a % 33) % 4 === 1;
    },
    
    /**
     * Convert date string to Persian format
     */
    convertToPersian: function(dateString) {
        if (!dateString) return '';
        
        // Convert Persian numerals to Western if needed
        var normalizedDate = dateString;
        if (typeof PersianNumerals !== 'undefined') {
            normalizedDate = PersianNumerals.toWestern(dateString);
        }
        
        // Remove time component if present (e.g., "November 13, 2025 at 2:49 AM")
        var timeMatch = normalizedDate.match(/\s+(در|at)\s+[\d:]+/i);
        normalizedDate = normalizedDate.split(/\s+(در|at)\s+/i)[0].trim();
        
        // Map Persian language month names to English (when site language is Persian)
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
            'دسامبر': 'December'
        };
        
        // Check if date contains Persian language month names and convert to English
        for (var persianMonth in persianToEnglishMonths) {
            if (normalizedDate.indexOf(persianMonth) !== -1) {
                normalizedDate = normalizedDate.replace(persianMonth, persianToEnglishMonths[persianMonth]);
                break;
            }
        }
        
        // Try to parse different date formats
        var date;
        if (normalizedDate.match(/^\d{4}-\d{2}-\d{2}/)) {
            // YYYY-MM-DD format
            date = new Date(normalizedDate);
        } else if (normalizedDate.match(/^\d{2}\/\d{2}\/\d{4}/)) {
            // MM/DD/YYYY format
            var parts = normalizedDate.split('/');
            date = new Date(parts[2], parts[0] - 1, parts[1]);
        } else if (normalizedDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
            // Already Persian format Y/M/D - check if it's valid Persian date
            var persianParts = normalizedDate.split('/');
            var pYear = parseInt(persianParts[0], 10);
            var pMonth = parseInt(persianParts[1], 10);
            var pDay = parseInt(persianParts[2], 10);
            
            if (!isNaN(pYear) && !isNaN(pMonth) && !isNaN(pDay) && 
                pYear > 1300 && pYear < 1500) {
                // It's already a Persian date, return as-is (but ensure proper format)
                return pYear + '/' + String(pMonth).padStart(2, '0') + '/' + String(pDay).padStart(2, '0');
            }
        } else {
            // Try parsing with normalized date (might have Persian month names converted to English)
            date = new Date(normalizedDate);
        }
        
        if (!date || isNaN(date.getTime())) return dateString;
        
        var jalali = PersianDateConverter.gregorianToJalali(
            date.getFullYear(),
            date.getMonth() + 1,
            date.getDate()
        );
        
        return jalali.year + '/' + String(jalali.month).padStart(2, '0') + '/' + String(jalali.day).padStart(2, '0');
    },
    
    /**
     * Convert existing dates on page load
     * Converts dates in filter inputs and list table date columns
     */
    convertExistingDates: function() {
        var self = this;
        
        // Convert order date filter dropdown
        self.convertOrderDateFilterDropdown();
        
        // Convert date inputs that are NOT part of order tables
        $('input.hasDatepicker:not(.woocommerce_table_items input), input.date-picker:not(.woocommerce_table_items input)').each(function() {
            var $input = $(this);
            
            // Skip if part of order table
            if ($input.closest('table.wp-list-table').length > 0 || 
                $input.closest('.woocommerce_order_items').length > 0 ||
                $input.closest('.woocommerce_table_items').length > 0) {
                return;
            }
            
            var value = $input.val();
            
            if (value && !value.match(/^\d{4}\/\d{2}\/\d{2}/) && value.match(/^\d{4}-\d{2}-\d{2}/)) {
                var persianDate = self.convertToPersian(value);
                if (persianDate && persianDate !== value) {
                    $input.data('original-gregorian', value);
                    $input.val(persianDate);
                }
            }
        });
        
        // Convert dates in list table date columns (for posts, pages, products, etc.)
        // This is a fallback if PHP filters don't catch all dates
        self.convertListTableDates();
    },
    
    /**
     * Convert dates in WordPress list table date columns
     */
    convertListTableDates: function() {
        var self = this;
        
        // Find all date columns in list tables (but exclude WooCommerce orders for now)
        $('table.wp-list-table td.column-date, table.wp-list-table .date, table.wp-list-table time, table.wp-list-table .post-date').each(function() {
            var $cell = $(this);
            
            // Skip if it's already converted (has persian-date class)
            if ($cell.hasClass('persian-date-converted') || $cell.data('persian-converted')) {
                return;
            }
            
            // Skip WooCommerce order tables - they're handled separately
            if ($cell.closest('table.wp-list-table').hasClass('woocommerce-orders-table') ||
                $cell.closest('.woocommerce-orders').length > 0 ||
                $cell.closest('body').find('.woocommerce-page').length > 0 && $cell.closest('table').find('th:contains("Order")').length > 0) {
                return;
            }
            
            var originalHtml = $cell.html();
            var text = $cell.text().trim();
            if (!text) {
                return;
            }
            
            // Skip relative dates like "18 hours ago", "2 days ago", etc.
            if (text.match(/(ago|hours?|days?|minutes?|seconds?|weeks?|months?|years?)\s*$/i)) {
                return;
            }
            
            // Try to parse and convert the date
            var dateObj = self.parseWordPressDate(text);
            if (dateObj && !isNaN(dateObj.getTime())) {
                // Convert to Persian
                var jalali = PersianDateConverter.gregorianToJalali(
                    dateObj.getFullYear(),
                    dateObj.getMonth() + 1,
                    dateObj.getDate()
                );
                
                // Format as Persian date
                var persianDate = jalali.year + '/' + String(jalali.month).padStart(2, '0') + '/' + String(jalali.day).padStart(2, '0');
                
                // Convert numerals to Persian if needed
                if (typeof PersianNumerals !== 'undefined') {
                    persianDate = PersianNumerals.toPersian(persianDate);
                }
                
                // Replace the date in the HTML while preserving the structure
                var newHtml = originalHtml;
                
                // Replace various date patterns
                newHtml = newHtml.replace(/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/g, persianDate);
                newHtml = newHtml.replace(/([A-Za-z]{3,9})\s+(\d{1,2}),?\s+(\d{4})/gi, persianDate);
                newHtml = newHtml.replace(/Published\s+([A-Za-z]{3,9})\s+(\d{1,2}),?\s+(\d{4})/gi, function(match) {
                    return match.replace(/([A-Za-z]{3,9})\s+(\d{1,2}),?\s+(\d{4})/i, persianDate);
                });
                
                // If the HTML changed, update it
                if (newHtml !== originalHtml) {
                    $cell.html(newHtml);
                    $cell.addClass('persian-date-converted');
                    $cell.data('persian-converted', true);
                } else if (text.match(/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/) || text.match(/[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}/i)) {
                    // If HTML replacement didn't work, try replacing the whole text
                    $cell.html(persianDate);
                    $cell.addClass('persian-date-converted');
                    $cell.data('persian-converted', true);
                }
            }
        });
    },
    
    /**
     * Parse WordPress date string to Date object
     * Handles both English and Persian month names
     */
    parseWordPressDate: function(dateString) {
        if (!dateString) return null;
        
        // Normalize Persian month names to English for parsing
        var normalizedDate = dateString;
        
        // Map Persian language month names (WordPress translations) to English
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
            'دسامبر': 'December'
        };
        
        for (var persian in persianToEnglishMonths) {
            if (persianToEnglishMonths.hasOwnProperty(persian)) {
                normalizedDate = normalizedDate.replace(new RegExp(persian, 'g'), persianToEnglishMonths[persian]);
            }
        }
        
        // Try various date formats
        var date = null;
        
        // Format: YYYY-MM-DD or YYYY/MM/DD
        if (normalizedDate.match(/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/)) {
            date = new Date(normalizedDate.replace(/\//g, '-'));
        }
        // Format: Month DD, YYYY (e.g., "Jan 15, 2025" or "January 15, 2025")
        else if (normalizedDate.match(/[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}/)) {
            date = new Date(normalizedDate);
        }
        // Format: DD/MM/YYYY or MM/DD/YYYY
        else if (normalizedDate.match(/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}/)) {
            var parts = normalizedDate.split(/[-\/]/);
            // Try MM/DD/YYYY first (US format)
            date = new Date(parts[2], parts[0] - 1, parts[1]);
            if (isNaN(date.getTime())) {
                // Try DD/MM/YYYY (European format)
                date = new Date(parts[2], parts[1] - 1, parts[0]);
            }
        }
        // Try generic Date parsing with normalized string
        else {
            date = new Date(normalizedDate);
        }
        
        // Validate the date
        if (!date || isNaN(date.getTime())) {
            return null;
        }
        
        return date;
    },
    
    /**
     * Handle form submissions - convert Persian dates back to Gregorian
     */
    handleFormSubmissions: function() {
        var self = this;
        
        // Before form submission, convert Persian dates back to Gregorian
        // But ONLY for forms with date inputs, not for order table forms
        $(document).on('submit', 'form:not(.woocommerce_order_items):not(.wp-list-table form)', function(e) {
            var $form = $(this);
            
            // Skip if this is an order table form
            if ($form.closest('table.wp-list-table').length > 0 || 
                $form.closest('.woocommerce_order_items').length > 0) {
                return;
            }
            
            $form.find('input[data-persian-datepicker-initialized]').each(function() {
                var $input = $(this);
                var persianDate = $input.val();
                
                if (persianDate && persianDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                    var parts = persianDate.split('/');
                    var gregorian = PersianDateConverter.jalaliToGregorian(
                        parseInt(parts[0], 10),
                        parseInt(parts[1], 10),
                        parseInt(parts[2], 10)
                    );
                    
                    var gregorianDate = gregorian.year + '-' + 
                                      String(gregorian.month).padStart(2, '0') + '-' + 
                                      String(gregorian.day).padStart(2, '0');
                    
                    // Update the input value to Gregorian format for submission
                    // WooCommerce expects dates in YYYY-MM-DD format
                    $input.val(gregorianDate);
                    $input.data('original-gregorian', gregorianDate);
                }
            });
        });
        
        // Handle filter button clicks (WooCommerce Orders/Reports page) - use delegation
        $(document).on('click', '.woocommerce-orders-filter__button, button[name="filter_action"], button[type="submit"][name="filter_action"]', function(e) {
            // Only convert dates in filter inputs, not order table inputs
            $('input[data-persian-datepicker-initialized]').not('.woocommerce_table_items input').each(function() {
                var $input = $(this);
                var persianDate = $input.val();
                
                if (persianDate && persianDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                    var parts = persianDate.split('/');
                    var gregorian = PersianDateConverter.jalaliToGregorian(
                        parseInt(parts[0], 10),
                        parseInt(parts[1], 10),
                        parseInt(parts[2], 10)
                    );
                    
                    var gregorianDate = gregorian.year + '-' + 
                                      String(gregorian.month).padStart(2, '0') + '-' + 
                                      String(gregorian.day).padStart(2, '0');
                    
                    $input.val(gregorianDate);
                }
            });
        });
        
        // Handle order edit page update button - convert dates before validation
        $(document).on('click', '#order_data input[type="submit"], #order_data button[type="submit"], .order_actions button, #post input[type="submit"][name="save"]', function(e) {
            // Convert all Persian dates to Gregorian before form submission
            $('input[data-persian-datepicker-initialized]').each(function() {
                var $input = $(this);
                var persianDate = $input.val();
                
                // Check if it's a Persian date format (YYYY/MM/DD)
                if (persianDate && persianDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                    var parts = persianDate.split('/');
                    var gregorian = PersianDateConverter.jalaliToGregorian(
                        parseInt(parts[0], 10),
                        parseInt(parts[1], 10),
                        parseInt(parts[2], 10)
                    );
                    
                    var gregorianDate = gregorian.year + '-' + 
                                      String(gregorian.month).padStart(2, '0') + '-' + 
                                      String(gregorian.day).padStart(2, '0');
                    
                    // Update the input value to Gregorian format for validation and submission
                    $input.val(gregorianDate);
                    $input.data('original-gregorian', gregorianDate);
                }
            });
        });
        
        // Also handle input blur events to convert dates before validation
        $(document).on('blur', 'input[data-persian-datepicker-initialized]', function() {
            var $input = $(this);
            var persianDate = $input.val();
            
            // Check if it's a Persian date format (YYYY/MM/DD)
            if (persianDate && persianDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                var parts = persianDate.split('/');
                var gregorian = PersianDateConverter.jalaliToGregorian(
                    parseInt(parts[0], 10),
                    parseInt(parts[1], 10),
                    parseInt(parts[2], 10)
                );
                
                var gregorianDate = gregorian.year + '-' + 
                                  String(gregorian.month).padStart(2, '0') + '-' + 
                                  String(gregorian.day).padStart(2, '0');
                
                // Store Gregorian date but keep Persian for display
                $input.data('original-gregorian', gregorianDate);
            }
        });
        
        // Handle HTML5 validation - convert before validation runs
        $(document).on('invalid', 'input[data-persian-datepicker-initialized], input[name*="date"], input.date-picker', function(e) {
            var $input = $(this);
            var persianDate = $input.val();
            
            // Check if it's a Persian date format (YYYY/MM/DD)
            if (persianDate && persianDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                var parts = persianDate.split('/');
                var gregorian = PersianDateConverter.jalaliToGregorian(
                    parseInt(parts[0], 10),
                    parseInt(parts[1], 10),
                    parseInt(parts[2], 10)
                );
                
                var gregorianDate = gregorian.year + '-' + 
                                  String(gregorian.month).padStart(2, '0') + '-' + 
                                  String(gregorian.day).padStart(2, '0');
                
                // Update the input value to Gregorian format for validation
                $input.val(gregorianDate);
                $input.data('original-gregorian', gregorianDate);
                
                // Clear the validation error and re-validate
                $input[0].setCustomValidity('');
                $input[0].checkValidity();
            }
        });
        
        // Also handle before validation check - convert on blur/change
        $(document).on('change blur', 'input[name*="date"], input.date-picker, input.hasDatepicker', function() {
            var $input = $(this);
            var value = $input.val();
            
            // Check if it's a Persian date format (YYYY/MM/DD)
            if (value && value.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                var parts = value.split('/');
                var gregorian = PersianDateConverter.jalaliToGregorian(
                    parseInt(parts[0], 10),
                    parseInt(parts[1], 10),
                    parseInt(parts[2], 10)
                );
                
                var gregorianDate = gregorian.year + '-' + 
                                  String(gregorian.month).padStart(2, '0') + '-' + 
                                  String(gregorian.day).padStart(2, '0');
                
                $input.val(gregorianDate);
                $input.data('original-gregorian', gregorianDate);
                
                // Trigger input event to update any validation
                $input.trigger('input');
            }
        });
        
        // Intercept validation before it happens - use a more aggressive approach
        var originalCheckValidity = HTMLInputElement.prototype.checkValidity;
        HTMLInputElement.prototype.checkValidity = function() {
            var $input = $(this);
            var value = $input.val();
            
            // Check if it's a Persian date format (YYYY/MM/DD) and convert before validation
            if (value && value.match(/^\d{4}\/\d{2}\/\d{2}/) && 
                ($input.attr('name') && $input.attr('name').indexOf('date') !== -1 || 
                 $input.hasClass('date-picker') || 
                 $input.hasClass('hasDatepicker'))) {
                var parts = value.split('/');
                var gregorian = PersianDateConverter.jalaliToGregorian(
                    parseInt(parts[0], 10),
                    parseInt(parts[1], 10),
                    parseInt(parts[2], 10)
                );
                
                var gregorianDate = gregorian.year + '-' + 
                                  String(gregorian.month).padStart(2, '0') + '-' + 
                                  String(gregorian.day).padStart(2, '0');
                
                $input.val(gregorianDate);
                $input.data('original-gregorian', gregorianDate);
            }
            
            return originalCheckValidity.call(this);
        };
        
        // Also intercept form validation - handle both #post form and order edit form
        $(document).on('submit', '#post, form#post', function(e) {
            // Convert all Persian dates to Gregorian before form submission
            // First, check initialized date pickers
            $('input[data-persian-datepicker-initialized]').each(function() {
                var $input = $(this);
                var persianDate = $input.val();
                
                // Check if it's a Persian date format (YYYY/MM/DD)
                if (persianDate && persianDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                    var parts = persianDate.split('/');
                    var gregorian = PersianDateConverter.jalaliToGregorian(
                        parseInt(parts[0], 10),
                        parseInt(parts[1], 10),
                        parseInt(parts[2], 10)
                    );
                    
                    var gregorianDate = gregorian.year + '-' + 
                                      String(gregorian.month).padStart(2, '0') + '-' + 
                                      String(gregorian.day).padStart(2, '0');
                    
                    // Update the input value to Gregorian format
                    $input.val(gregorianDate);
                    $input.data('original-gregorian', gregorianDate);
                }
            });
            
            // Also check for date inputs that might not have the initialized flag
            $('input[name*="date"], input[type="date"], input.date-picker, input.hasDatepicker').each(function() {
                var $input = $(this);
                var value = $input.val();
                
                // Check if it's a Persian date format (YYYY/MM/DD)
                if (value && value.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                    var parts = value.split('/');
                    var gregorian = PersianDateConverter.jalaliToGregorian(
                        parseInt(parts[0], 10),
                        parseInt(parts[1], 10),
                        parseInt(parts[2], 10)
                    );
                    
                    var gregorianDate = gregorian.year + '-' + 
                                      String(gregorian.month).padStart(2, '0') + '-' + 
                                      String(gregorian.day).padStart(2, '0');
                    
                    $input.val(gregorianDate);
                    $input.data('original-gregorian', gregorianDate);
                }
            });
            
            // Also check for any input with pattern attribute that might be a date
            $('input[pattern]').each(function() {
                var $input = $(this);
                var value = $input.val();
                var pattern = $input.attr('pattern');
                
                // If pattern looks like a date pattern and value is Persian format
                if (pattern && (pattern.indexOf('date') !== -1 || pattern.match(/\d{4}/)) && 
                    value && value.match(/^\d{4}\/\d{2}\/\d{2}/)) {
                    var parts = value.split('/');
                    var gregorian = PersianDateConverter.jalaliToGregorian(
                        parseInt(parts[0], 10),
                        parseInt(parts[1], 10),
                        parseInt(parts[2], 10)
                    );
                    
                    var gregorianDate = gregorian.year + '-' + 
                                      String(gregorian.month).padStart(2, '0') + '-' + 
                                      String(gregorian.day).padStart(2, '0');
                    
                    $input.val(gregorianDate);
                }
            });
        });
    }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Initialize date conversion
        PersianDatePicker.init();
        
        // Also convert list table dates after a short delay to ensure tables are rendered
        setTimeout(function() {
            PersianDatePicker.convertListTableDates();
        }, 500);
        
        // Re-convert after AJAX updates (for pagination, filters, etc.)
        setTimeout(function() {
            PersianDatePicker.convertListTableDates();
        }, 1500);
    });
    
    // Re-initialize after AJAX loads (for WooCommerce admin and list table updates)
    $(document).on('wc_fragments_refreshed wc_fragments_loaded DOMNodeInserted', function() {
        setTimeout(function() {
            PersianDatePicker.init();
            // Also convert date filter dropdown after AJAX updates
            PersianDatePicker.convertOrderDateFilterDropdown();
            // Convert list table dates after AJAX updates
            PersianDatePicker.convertListTableDates();
        }, 300);
    });
    
    // Watch for dropdown changes and re-convert if needed
    $(document).on('DOMNodeInserted', function(e) {
        var $target = $(e.target);
        if ($target.is('select[name="m"], select.order_date_filter, select.wc-order-date-filter, select') ||
            $target.find('select[name="m"]').length > 0) {
            setTimeout(function() {
                PersianDatePicker.convertOrderDateFilterDropdown();
            }, 100);
        }
    });
    
    // Also re-initialize when the page content changes (for dynamic content)
    // But be careful not to interfere with WooCommerce order table updates
    var observer = new MutationObserver(function(mutations) {
        var shouldReinit = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                // Check if the added nodes are part of order table - if so, skip
                for (var i = 0; i < mutation.addedNodes.length; i++) {
                    var node = mutation.addedNodes[i];
                    if (node.nodeType === 1) { // Element node
                        var $node = $(node);
                        // Skip if it's part of order table
                        if ($node.closest('table.wp-list-table').length > 0 ||
                            $node.closest('.woocommerce_order_items').length > 0 ||
                            $node.hasClass('woocommerce_order_items') ||
                            $node.is('table.wp-list-table')) {
                            continue;
                        }
                        // Only reinit for filter areas or reports
                        if ($node.closest('.woocommerce-reports-wide').length > 0 ||
                            $node.closest('.wc-date-range').length > 0 ||
                            $node.find('input.hasDatepicker, input.date-picker').length > 0) {
                            shouldReinit = true;
                            break;
                        }
                    }
                }
            }
        });
        if (shouldReinit) {
            setTimeout(function() {
                PersianDatePicker.replaceDatePickers();
                // Also convert list table dates when content changes
                PersianDatePicker.convertListTableDates();
            }, 300);
        }
    });
    
    // Start observing after DOM is ready - but only observe specific containers
    $(document).ready(function() {
        // Observe filter areas for WordPress Posts/Pages and WooCommerce
        var targetNodes = document.querySelectorAll('.woocommerce-reports-wide, .wc-date-range, .woocommerce-orders-filter, .postbox, .tablenav, .wp-list-table .alignleft');
        if (targetNodes.length > 0) {
            targetNodes.forEach(function(node) {
                observer.observe(node, {
                    childList: true,
                    subtree: true
                });
            });
        } else {
            // Fallback: observe body but with more careful checks
            observer.observe(document.body, {
                childList: true,
                subtree: false // Only direct children to avoid interfering with order table
            });
        }
    });
    
    // Expose to global scope
    window.PersianDatePicker = PersianDatePicker;
    
})(jQuery);

