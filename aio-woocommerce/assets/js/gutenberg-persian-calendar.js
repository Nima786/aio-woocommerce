/**
 * Gutenberg Persian Calendar Support
 * Converts Gutenberg editor date pickers and date displays to Persian calendar
 * Works regardless of WordPress site language
 */

(function() {
    'use strict';
    
    // Check if required dependencies are available
    if (typeof PersianDateConverter === 'undefined') {
        console.error('AIO WC: PersianDateConverter is not loaded');
        return;
    }
    
    // Check if settings are available
    if (typeof aioWcDatePicker === 'undefined') {
        console.warn('AIO WC: aioWcDatePicker settings not available');
        return;
    }
    
    var persianMonths = aioWcDatePicker.persian_months || [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
    ];
    
    // Month name mappings (English and Persian language)
    var monthMap = {
        'january': 0, 'jan': 0, 'february': 1, 'feb': 1, 'march': 2, 'mar': 2,
        'april': 3, 'apr': 3, 'may': 4, 'june': 5, 'jun': 5,
        'july': 6, 'jul': 6, 'august': 7, 'aug': 7, 'september': 8, 'sep': 8,
        'october': 9, 'oct': 9, 'november': 10, 'nov': 10, 'december': 11, 'dec': 11,
        'ژانویه': 0, 'فوریه': 1, 'مارس': 2, 'آوریل': 3, 'مه': 4, 'ژوئن': 5,
        'جولای': 6, 'آگوست': 7, 'سپتامبر': 8, 'اکتبر': 9, 'نوامبر': 10, 'دسامبر': 11
    };
    
    // Track converted elements to avoid infinite loops
    var convertedElements = new WeakSet();
    var lastConversionTime = 0;
    var conversionInProgress = false;
    
    /**
     * Convert a date string to Persian format
     */
    function convertDateString(dateString) {
        if (!dateString || typeof dateString !== 'string') {
            return dateString;
        }
        
        var normalizedDate = dateString.trim();
        
        // Normalize Persian numerals to Western if needed
        if (typeof PersianNumerals !== 'undefined') {
            normalizedDate = PersianNumerals.toWestern(normalizedDate);
        }
        
        // Skip if already Persian format
        if (normalizedDate.match(/^\d{4}\/\d{2}\/\d{2}/)) {
            var parts = normalizedDate.split('/');
            var year = parseInt(parts[0], 10);
            if (year >= 1300 && year < 1500) {
                return dateString; // Already Persian
            }
        }
        
        // Skip ISO dates (YYYY-MM-DD) - these are used internally
        if (normalizedDate.match(/^\d{4}-\d{2}-\d{2}/)) {
            return dateString;
        }
        
        // Try to parse date
        var date = null;
        
        // Format: Month DD, YYYY (e.g., "November 2, 2025" or "نوامبر 2, 2025")
        var monthDayYearMatch = normalizedDate.match(/([A-Za-z]{3,9}|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+(\d{1,2}),?\s+(\d{4})/);
        if (monthDayYearMatch) {
            var monthName = monthDayYearMatch[1];
            var day = parseInt(monthDayYearMatch[2], 10);
            var year = parseInt(monthDayYearMatch[3], 10);
            
            var monthIndex = monthMap[monthName.toLowerCase()];
            if (monthIndex === undefined) {
                monthIndex = monthMap[monthName];
            }
            
            if (monthIndex !== undefined && day >= 1 && day <= 31 && year >= 1900 && year <= 2100) {
                date = new Date(year, monthIndex, day);
            }
        }
        // Format: DD Month YYYY
        else if (normalizedDate.match(/(\d{1,2})\s+([A-Za-z]{3,9}|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+(\d{4})/)) {
            date = new Date(normalizedDate);
        }
        // Format: YYYY/MM/DD (not ISO)
        else if (normalizedDate.match(/^\d{4}\/\d{1,2}\/\d{1,2}$/)) {
            date = new Date(normalizedDate.replace(/\//g, '-'));
        }
        // Try generic parsing
        else {
            date = new Date(normalizedDate);
        }
        
        // Validate date
        if (!date || isNaN(date.getTime())) {
            return dateString;
        }
        
        var year = date.getFullYear();
        if (year < 1900 || year > 2100) {
            return dateString;
        }
        
        // Convert to Persian
        var jalali = PersianDateConverter.gregorianToJalali(
            date.getFullYear(),
            date.getMonth() + 1,
            date.getDate()
        );
        
        // Format with Persian month name if original had month name
        var hasMonthName = normalizedDate.match(/[A-Za-z]{3,9}|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر/);
        var persianDate;
        
        if (hasMonthName) {
            var persianMonthName = persianMonths[jalali.month - 1];
            var persianYear = jalali.year;
            var persianDay = jalali.day;
            
            if (typeof PersianNumerals !== 'undefined') {
                persianYear = PersianNumerals.toPersian(String(persianYear));
                persianDay = PersianNumerals.toPersian(String(persianDay));
            }
            
            persianDate = persianMonthName + ' ' + persianDay + ', ' + persianYear;
        } else {
            persianDate = jalali.year + '/' + 
                String(jalali.month).padStart(2, '0') + '/' + 
                String(jalali.day).padStart(2, '0');
            
            if (typeof PersianNumerals !== 'undefined') {
                persianDate = PersianNumerals.toPersian(persianDate);
            }
        }
        
        return persianDate;
    }
    
    /**
     * Convert dates in a text node
     */
    function convertTextNode(textNode) {
        if (!textNode || textNode.nodeType !== 3) {
            return false;
        }
        
        var text = textNode.textContent;
        if (!text || text.trim().length === 0) {
            return false;
        }
        
        // Skip if already contains Persian month names
        if (text.match(/فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند/)) {
            return false;
        }
        
        // Skip relative dates
        if (text.match(/(ago|hours?|days?|minutes?|seconds?|weeks?|months?|years?)\s*$/i)) {
            return false;
        }
        
        // Skip ISO dates
        if (text.match(/^\d{4}-\d{2}-\d{2}/)) {
            return false;
        }
        
        // Find date patterns
        var datePatterns = [
            /([A-Za-z]{3,9}|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+(\d{1,2}),?\s+(\d{4})/gi,
            /(\d{1,2})\s+([A-Za-z]{3,9}|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+(\d{4})/gi
        ];
        
        var converted = false;
        var newText = text;
        
        datePatterns.forEach(function(pattern) {
            var matches = newText.match(pattern);
            if (matches) {
                matches.forEach(function(match) {
                    if (match.match(/^\d{4}-\d{2}-\d{2}/)) {
                        return;
                    }
                    
                    var convertedDate = convertDateString(match);
                    if (convertedDate && convertedDate !== match) {
                        newText = newText.replace(match, convertedDate);
                        converted = true;
                    }
                });
            }
        });
        
        if (converted && newText !== text) {
            textNode.textContent = newText;
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert month dropdown option
     */
    function convertMonthOption(option) {
        if (!option || option.tagName !== 'OPTION') {
            return false;
        }
        
        var text = option.text.trim();
        if (!text) {
            return false;
        }
        
        // Skip if already Persian
        for (var i = 0; i < persianMonths.length; i++) {
            if (text.indexOf(persianMonths[i]) !== -1) {
                var yearMatch = text.match(/\d{4}/);
                if (yearMatch) {
                    var year = parseInt(yearMatch[0], 10);
                    if (year >= 1300 && year < 1500) {
                        return false; // Already converted
                    }
                }
            }
        }
        
        // Removed debug logging to prevent console spam
        
        // Find month name - check both English and Persian language month names
        var monthIndex = -1;
        var foundMonthName = '';
        
        // Check English months first
        var englishMonths = ['january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december'];
        var englishMonthsShort = ['jan', 'feb', 'mar', 'apr', 'may', 'jun',
            'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        
        for (var i = 0; i < englishMonths.length; i++) {
            if (text.toLowerCase().indexOf(englishMonths[i]) !== -1 ||
                text.toLowerCase().indexOf(englishMonthsShort[i]) !== -1) {
                monthIndex = i;
                foundMonthName = englishMonths[i];
                break;
            }
        }
        
        // Check Persian language month names
        if (monthIndex === -1) {
            var persianLangMonths = ['ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن',
                'جولای', 'آگوست', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'];
            for (var j = 0; j < persianLangMonths.length; j++) {
                if (text.indexOf(persianLangMonths[j]) !== -1) {
                    monthIndex = j;
                    foundMonthName = persianLangMonths[j];
                    break;
                }
            }
        }
        
        // Also check monthMap
        if (monthIndex === -1) {
            for (var monthName in monthMap) {
                if (monthMap.hasOwnProperty(monthName)) {
                    if (text.toLowerCase().indexOf(monthName.toLowerCase()) !== -1 || text.indexOf(monthName) !== -1) {
                        monthIndex = monthMap[monthName];
                        foundMonthName = monthName;
                        break;
                    }
                }
            }
        }
        
        if (monthIndex >= 0) {
            var yearMatch = text.match(/\d{4}/);
            if (yearMatch) {
                var year = parseInt(yearMatch[0], 10);
                if (year >= 1900 && year < 2100) {
                    var gregorianDate = new Date(year, monthIndex, 1);
                    var jalali = PersianDateConverter.gregorianToJalali(
                        gregorianDate.getFullYear(),
                        gregorianDate.getMonth() + 1,
                        gregorianDate.getDate()
                    );
                    
                    var persianMonthName = persianMonths[jalali.month - 1];
                    var persianYear = jalali.year;
                    
                    if (typeof PersianNumerals !== 'undefined') {
                        persianYear = PersianNumerals.toPersian(String(persianYear));
                    }
                    
                    // Update the option text - use multiple methods to ensure it sticks
                    var newText = persianMonthName + ' ' + persianYear;
                    option.text = newText;
                    option.textContent = newText;
                    if (option.innerText !== undefined) {
                        option.innerText = newText;
                    }
                    if (option.innerHTML) {
                        option.innerHTML = newText;
                    }
                    
                    // Also update the value attribute if it contains month info
                    if (option.value && option.value.match(/november|december|january|february|march|april|may|june|july|august|september|october/i)) {
                        // Keep the original value for form submission, but we've updated the display
                    }
                    
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Convert calendar header (month/year)
     */
    function convertCalendarHeader(element) {
        if (!element) {
            return false;
        }
        
        var text = element.textContent || element.innerText || '';
        if (!text) {
            return false;
        }
        
        // Skip if already Persian
        if (text.match(/فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند/)) {
            return false;
        }
        
        var monthYearMatch = text.match(/([A-Za-z]{3,9}|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+(\d{4})/);
        if (monthYearMatch) {
            var monthName = monthYearMatch[1];
            var year = parseInt(monthYearMatch[2], 10);
            
            if (year >= 1900 && year < 2100) {
                var monthIndex = monthMap[monthName.toLowerCase()];
                if (monthIndex === undefined) {
                    monthIndex = monthMap[monthName];
                }
                
                if (monthIndex !== undefined) {
                    var gregorianDate = new Date(year, monthIndex, 1);
                    var jalali = PersianDateConverter.gregorianToJalali(
                        gregorianDate.getFullYear(),
                        gregorianDate.getMonth() + 1,
                        gregorianDate.getDate()
                    );
                    
                    var persianMonthName = persianMonths[jalali.month - 1];
                    var persianYear = jalali.year;
                    
                    if (typeof PersianNumerals !== 'undefined') {
                        persianYear = PersianNumerals.toPersian(String(persianYear));
                    }
                    
                    element.textContent = persianMonthName + ' ' + persianYear;
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Convert calendar day numbers to Persian numerals
     */
    function convertCalendarDay(dayElement) {
        if (!dayElement) {
            return false;
        }
        
        var text = dayElement.textContent || dayElement.innerText || '';
        if (!text) {
            return false;
        }
        
        // Skip if already Persian numeral
        if (text.match(/[۰-۹]/)) {
            return false;
        }
        
        var dayNumber = parseInt(text, 10);
        if (isNaN(dayNumber) || dayNumber < 1 || dayNumber > 31) {
            return false;
        }
        
        if (typeof PersianNumerals !== 'undefined') {
            dayElement.textContent = PersianNumerals.toPersian(String(dayNumber));
            return true;
        }
        
        return false;
    }
    
    /**
     * Main conversion function - runs continuously
     */
    function convertAll() {
        // Prevent infinite loops - don't run if already converting or too soon
        if (conversionInProgress) {
            return;
        }
        
        var now = Date.now();
        if (now - lastConversionTime < 100) {
            return; // Throttle to max once per 100ms
        }
        
        conversionInProgress = true;
        lastConversionTime = now;
        // Convert all text nodes in sidebar and modals - be very aggressive
        var containers = document.querySelectorAll(
            '.edit-post-sidebar, ' +
            '.interface-complementary-area, ' +
            '.components-popover, ' +
            '.components-popover__content, ' +
            '.components-modal__content, ' +
            '[role="dialog"], ' +
            'body > div[class*="popover"], ' +
            'body > div[class*="modal"], ' +
            '.components-datetime, ' +
            '.components-datetime__date, ' +
            '.components-calendar, ' +
            '.editor-post-schedule__panel'
        );
        
        // Also check entire body for datepicker modals
        if (containers.length === 0) {
            containers = [document.body];
        }
        
        containers.forEach(function(container) {
            if (!container) return;
            
            // Convert text nodes
            var walker = document.createTreeWalker(
                container,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            var textNode;
            while (textNode = walker.nextNode()) {
                if (!convertedElements.has(textNode)) {
                    if (convertTextNode(textNode)) {
                        convertedElements.add(textNode);
                    }
                }
            }
            
            // Convert month dropdowns - check ALL selects in document
            // Don't use WeakSet for selects - React re-renders them
            var selects = document.querySelectorAll('select');
            selects.forEach(function(select) {
                // Skip if already converted (check parent)
                if (convertedElements.has(select)) {
                    return;
                }
                
                var options = select.querySelectorAll('option');
                var needsConversion = false;
                
                // Check if any option needs conversion
                options.forEach(function(option) {
                    var text = option.text.trim();
                    if (text && text.match(/november|december|january|february|march|april|may|june|july|august|september|october|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر/i) &&
                        !text.match(/فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند/)) {
                        needsConversion = true;
                    }
                });
                
                if (needsConversion) {
                    options.forEach(function(option) {
                        convertMonthOption(option);
                    });
                    convertedElements.add(select);
                }
                
                // Also check the selected option's display value
                if (select.selectedIndex >= 0) {
                    var selectedOption = select.options[select.selectedIndex];
                    if (selectedOption && selectedOption.text) {
                        var selectedText = selectedOption.text.trim();
                        // If selected option shows Gregorian month, convert it
                        if (selectedText.match(/november|december|january|february|march|april|may|june|july|august|september|october|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر/i) && 
                            !selectedText.match(/فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند/)) {
                            convertMonthOption(selectedOption);
                        }
                    }
                }
            });
            
            // CRITICAL: Target the date picker month selector in the header specifically
            // This is the dropdown/select that shows "June" in the screenshot
            var monthSelectors = document.querySelectorAll(
                'select[aria-label*="onth"], ' +
                'select[id*="month"], ' +
                'select[name*="month"], ' +
                'select[class*="month"], ' +
                '.components-datetime__time-field-month select, ' +
                '.components-datetime__date select, ' +
                '.components-popover select'
            );
            monthSelectors.forEach(function(select) {
                var options = select.querySelectorAll('option');
                var needsConversion = false;
                
                // Check if options need conversion
                options.forEach(function(option) {
                    var text = option.textContent.trim();
                    if (text.match(/January|February|March|April|May|June|July|August|September|October|November|December/i)) {
                        needsConversion = true;
                    }
                });
                
                if (needsConversion) {
                    // Get year and day context for accurate conversion
                    var container = select.closest('.components-datetime, .components-popover, form');
                    var yearInput = container ? container.querySelector('input[type="number"]') : null;
                    var dayInput = container ? container.querySelector('input[aria-label*="ay"], input[id*="day"]') : null;
                    
                    var year = new Date().getFullYear();
                    var day = 15; // Use middle of month for conversion
                    
                    if (yearInput && yearInput.value) {
                        var yearValue = yearInput.value;
                        if (typeof PersianNumerals !== 'undefined') {
                            yearValue = PersianNumerals.toWestern(yearValue);
                        }
                        var parsedYear = parseInt(yearValue, 10);
                        // Check if it's a Persian year (convert to Gregorian) or Gregorian year
                        if (parsedYear >= 1300 && parsedYear < 1500) {
                            // It's already showing Persian year, use current Georgian year for now
                            year = new Date().getFullYear();
                        } else if (parsedYear >= 2000 && parsedYear <= 2100) {
                            year = parsedYear;
                        }
                    }
                    
                    if (dayInput && dayInput.value) {
                        var dayValue = dayInput.value;
                        if (typeof PersianNumerals !== 'undefined') {
                            dayValue = PersianNumerals.toWestern(dayValue);
                        }
                        day = parseInt(dayValue, 10) || 15;
                    }
                    
                    var gregorianMonthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                               'July', 'August', 'September', 'October', 'November', 'December'];
                    
                    options.forEach(function(option) {
                        var text = option.textContent.trim();
                        var monthIndex = gregorianMonthNames.findIndex(function(m) {
                            return text === m;
                        });
                        
                        if (monthIndex >= 0) {
                            // Convert this Gregorian month to Persian using the year/day context
                            var jalali = PersianDateConverter.gregorianToJalali(year, monthIndex + 1, day);
                            var persianMonth = persianMonths[jalali.month - 1];
                            
                            if (persianMonth) {
                                option.textContent = persianMonth;
                                option.label = persianMonth;
                                // Store original for form functionality
                                if (!option.getAttribute('data-original-text')) {
                                    option.setAttribute('data-original-text', text);
                                }
                            }
                        }
                    });
                }
            });
            
            // Convert year input fields - Use visual overlay approach
            // This doesn't modify React's state, just the visual display
            var yearInputs = document.querySelectorAll(
                'input[type="number"][aria-label*="ear"], ' +
                'input[type="number"][id*="year"], ' +
                'input[type="number"][name*="year"], ' +
                'input[type="text"][id*="year"], ' +
                'input[class*="year"], ' +
                '.components-datetime__time-field-year input'
            );
            
            yearInputs.forEach(function(input) {
                // Skip if already processed
                if (input.getAttribute('data-persian-year-overlay')) {
                    return;
                }
                
                // Create a wrapper if not already wrapped
                if (!input.parentElement.classList.contains('persian-year-wrapper')) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'persian-year-wrapper';
                    wrapper.style.position = 'relative';
                    wrapper.style.display = 'inline-block';
                    input.parentNode.insertBefore(wrapper, input);
                    wrapper.appendChild(input);
                    
                    // Create overlay element
                    var overlay = document.createElement('div');
                    overlay.className = 'persian-year-overlay';
                    overlay.style.position = 'absolute';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.right = '0';
                    overlay.style.bottom = '0';
                    overlay.style.pointerEvents = 'none';
                    overlay.style.display = 'flex';
                    overlay.style.alignItems = 'center';
                    overlay.style.justifyContent = 'center';
                    overlay.style.color = input.style.color || '#1e1e1e';
                    overlay.style.fontSize = input.style.fontSize || '13px';
                    overlay.style.fontFamily = input.style.fontFamily || 'inherit';
                    overlay.style.background = 'white';
                    overlay.style.padding = '0 8px';
                    
                    wrapper.appendChild(overlay);
                    
                    // Hide the actual input text
                    input.style.color = 'transparent';
                    input.style.caretColor = 'black';
                    
                    // Function to update the overlay
                    function updateOverlay() {
                        var value = input.value;
                        if (!value) {
                            overlay.textContent = '';
                            return;
                        }
                        
                        var westernValue = value;
                        if (typeof PersianNumerals !== 'undefined') {
                            westernValue = PersianNumerals.toWestern(value);
                        }
                        
                        var year = parseInt(westernValue, 10);
                        
                        // Convert Gregorian year to Jalali
                        if (year >= 2000 && year <= 2100) {
                            var container = input.closest('.components-datetime, .components-popover, form, .edit-post-sidebar');
                            var monthSelect = container ? container.querySelector('select') : null;
                            var dayInput = container ? container.querySelector('input[aria-label*="ay"], input[id*="day"]') : null;
                            
                            var month = 6;
                            var day = 15;
                            
                            if (monthSelect && monthSelect.selectedIndex >= 0) {
                                month = monthSelect.selectedIndex + 1;
                            }
                            
                            if (dayInput && dayInput.value) {
                                var dayValue = dayInput.value;
                                if (typeof PersianNumerals !== 'undefined') {
                                    dayValue = PersianNumerals.toWestern(dayValue);
                                }
                                day = parseInt(dayValue, 10) || 15;
                            }
                            
                            var jalali = PersianDateConverter.gregorianToJalali(year, month, day);
                            var persianYear = String(jalali.year);
                            
                            if (typeof PersianNumerals !== 'undefined') {
                                persianYear = PersianNumerals.toPersian(persianYear);
                            }
                            
                            overlay.textContent = persianYear;
                        } else {
                            overlay.textContent = value;
                        }
                    }
                    
                    // Initial update
                    updateOverlay();
                    
                    // Watch for changes
                    var observer = new MutationObserver(updateOverlay);
                    observer.observe(input, {
                        attributes: true,
                        attributeFilter: ['value']
                    });
                    
                    // Event listeners
                    input.addEventListener('input', updateOverlay);
                    input.addEventListener('change', updateOverlay);
                    
                    // Polling as backup
                    var lastValue = input.value;
                    setInterval(function() {
                        if (!document.body.contains(input)) return;
                        if (input.value !== lastValue) {
                            lastValue = input.value;
                            updateOverlay();
                        }
                    }, 50);
                }
                
                input.setAttribute('data-persian-year-overlay', 'true');
            });
            
            // Also check for custom React dropdown components (divs/buttons that act like selects)
            // Gutenberg might use custom components instead of native selects
            var customDropdowns = document.querySelectorAll(
                '.components-select-control__input, ' +
                '[class*="Select"], ' +
                '[class*="select"], ' +
                '[class*="Dropdown"], ' +
                'button[aria-haspopup="listbox"], ' +
                '[role="combobox"]'
            );
            customDropdowns.forEach(function(dropdown) {
                var text = dropdown.textContent || dropdown.innerText || '';
                if (text.match(/november|december|january|february|march|april|may|june|july|august|september|october|ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر/i) &&
                    !text.match(/فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند/)) {
                    // Try to convert the displayed text
                    if (!convertedElements.has(dropdown)) {
                        var converted = convertDateString(text);
                        if (converted && converted !== text) {
                            dropdown.textContent = converted;
                            if (dropdown.innerText !== undefined) {
                                dropdown.innerText = converted;
                            }
                            convertedElements.add(dropdown);
                        }
                    }
                }
            });
            
            // Convert calendar headers - check everywhere
            // Don't use WeakSet - React re-renders these
            var headers = document.querySelectorAll('h2, h3, h4, h5, .components-heading, [class*="header"], [class*="month"], button[aria-label*="month"], .components-base-control__label, button[class*="month"]');
            headers.forEach(function(header) {
                // Always try to convert - React might have re-rendered
                convertCalendarHeader(header);
            });
            
            // Convert calendar days - check everywhere
            // Don't use WeakSet - React re-renders these
            var dayElements = document.querySelectorAll(
                'button[role="gridcell"], ' +
                'button[role="button"][aria-label*="day"], ' +
                'button[aria-label*="Day"], ' +
                '.components-calendar__day, ' +
                '[data-day], ' +
                'td button, ' +
                'td[role="gridcell"], ' +
                'button[class*="day"], ' +
                'button[class*="calendar"], ' +
                'table button, ' +
                'button[type="button"]'
            );
            dayElements.forEach(function(dayElement) {
                // Always try to convert - React might have re-rendered
                convertCalendarDay(dayElement);
            });
        });
        
        // Also specifically target datepicker modal if it exists - be very aggressive
        var datepickerModals = document.querySelectorAll('.components-popover, .components-popover__content, [role="dialog"], .components-modal, body > div[class*="Popover"], body > div[class*="popover"]');
        datepickerModals.forEach(function(datepickerModal) {
            // Force convert everything in the modal
            var allElements = datepickerModal.querySelectorAll('*');
            allElements.forEach(function(el) {
                if (el.tagName === 'SELECT') {
                    var options = el.querySelectorAll('option');
                    options.forEach(function(option) {
                        convertMonthOption(option);
                    });
                } else if (el.textContent && (el.textContent.match(/november|december|january|february|march|april|may|june|july|august|september|october/i) || 
                    el.textContent.match(/ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر/))) {
                    if (!convertedElements.has(el)) {
                        if (convertCalendarHeader(el)) {
                            convertedElements.add(el);
                        }
                    }
                }
                
                // Also check for day buttons
                if (el.tagName === 'BUTTON' && el.textContent) {
                    var dayNum = parseInt(el.textContent, 10);
                    if (!isNaN(dayNum) && dayNum >= 1 && dayNum <= 31) {
                        if (!convertedElements.has(el)) {
                            if (convertCalendarDay(el)) {
                                convertedElements.add(el);
                            }
                        }
                    }
                }
            });
        });
        
        conversionInProgress = false;
    }
    
    /**
     * Initialize
     */
    function init() {
        console.log('AIO WC: Initializing Gutenberg Persian Calendar');
        
        // Run conversion immediately
        convertAll();
        
        // Run conversion every 200ms - less aggressive to prevent loops
        setInterval(function() {
            convertAll();
        }, 200);
        
        // Also use MutationObserver for immediate conversion - but debounced
        var mutationTimeout;
        var observer = new MutationObserver(function(mutations) {
            // Debounce mutations - only convert if no mutations for 200ms
            clearTimeout(mutationTimeout);
            mutationTimeout = setTimeout(function() {
                convertAll();
            }, 50);
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
        
        // Add event delegation for calendar day clicks to trigger immediate conversion
        document.addEventListener('click', function(e) {
            var target = e.target;
            // Check if clicked element is a calendar day button
            if (target && target.tagName === 'BUTTON' && 
                (target.getAttribute('role') === 'gridcell' || 
                 target.className.indexOf('calendar') !== -1 ||
                 target.className.indexOf('day') !== -1)) {
                // Trigger rapid conversions to catch React updates
                setTimeout(convertAll, 10);
                setTimeout(convertAll, 50);
                setTimeout(convertAll, 100);
                setTimeout(convertAll, 150);
            }
        }, true);
    }
    
    // Wait for Gutenberg to load
    function waitForGutenberg() {
        if (document.querySelector('.block-editor-page, .edit-post-sidebar')) {
            setTimeout(init, 500);
        } else {
            setTimeout(waitForGutenberg, 500);
        }
    }
    
    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForGutenberg);
    } else {
        waitForGutenberg();
    }
    
    // Also initialize on window load
    window.addEventListener('load', function() {
        setTimeout(init, 1000);
    });
    
})();