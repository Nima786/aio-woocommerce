/**
 * Media Library Date Conversion
 * Converts dates in WordPress media library and media edit pages
 */

(function($) {
    'use strict';
    
    // Check if PersianDateConverter is available
    if (typeof PersianDateConverter === 'undefined') {
        return;
    }
    
    var MediaDateConverter = {
        conversionStarted: false,
        initialized: false,
        
        /**
         * Initialize media date conversion
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
                self.convertMediaLibraryDates();
                self.convertMediaEditDate();
            }, 100);
            
            // Run once more after a longer delay ONLY if page is still loading
            setTimeout(function() {
                // Only run if we haven't converted everything yet
                var hasUnconverted = $('.wp-list-table tbody tr td').filter(function() {
                    var $cell = $(this);
                    var text = $cell.text().trim();
                    return !$cell.data('aio-wc-converted') && 
                           !$cell.data('aio-wc-skipped') &&
                           text.match(/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/) &&
                           !text.match(/[۰-۹]/u);
                }).length > 0;
                
                if (hasUnconverted) {
                    self.convertMediaLibraryDates();
                    self.convertMediaEditDate();
                }
            }, 1000);
        },
        
        /**
         * Convert dates in media library table
         */
        convertMediaLibraryDates: function() {
            var self = this;
            
            // Find all date cells in media library table
            $('.wp-list-table tbody tr').each(function() {
                var $row = $(this);
                
                // Find the date column (usually the last column or has class 'date')
                var $dateCell = $row.find('td.column-date, td:last-child');
                
                if ($dateCell.length === 0) {
                    // Try to find by looking for date-like content
                    $dateCell = $row.find('td').filter(function() {
                        var text = $(this).text().trim();
                        // Check if it looks like a date (contains slashes or dashes with numbers)
                        return /^\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4}/.test(text) || 
                               /[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}/.test(text);
                    });
                }
                
                if ($dateCell.length > 0) {
                    $dateCell.each(function() {
                        var $cell = $(this);
                        
                        // Skip if already processed (check multiple ways to be sure)
                        if ($cell.data('aio-wc-converted') || 
                            $cell.attr('data-aio-wc-converted') === 'true' || 
                            $cell.hasClass('aio-wc-date-converted')) {
                            return;
                        }
                        
                        // Get original text and store it if not already stored
                        var originalText = $cell.text().trim();
                        if (!$cell.data('aio-wc-original-text')) {
                            $cell.data('aio-wc-original-text', originalText);
                        } else {
                            // If we have stored original and current text is different, check if it's corrupted
                            var storedOriginal = $cell.data('aio-wc-original-text');
                            // If current text looks corrupted but stored original was good, restore it
                            if (/\d{5,}/.test(originalText) || /[۰-۹]{5,}/u.test(originalText) || 
                                /\d{3,}[-\/]\d{3,}/.test(originalText)) {
                                if (storedOriginal && !/\d{5,}/.test(storedOriginal)) {
                                    // Restore original and mark as converted to prevent further attempts
                                    $cell.text(storedOriginal);
                                    $cell.data('aio-wc-converted', true);
                                    $cell.data('aio-wc-skipped', true);
                                    return;
                                }
                            }
                        }
                        
                        originalText = $cell.text().trim();
                        
                        // Skip if empty
                        if (!originalText || originalText.length < 5) {
                            return;
                        }
                        
                        // Skip if already has weird format (malformed dates like "۹۶۳-/۷۴۹/۰۱")
                        // Check for patterns like: 3+ digits, slash, 3+ digits, slash, 1-2 digits
                        if (/\d{3,}[-\/]\d{3,}[-\/]\d{1,2}/.test(originalText) || 
                            /[۰-۹]{3,}[-\/][۰-۹]{3,}[-\/][۰-۹]{1,2}/u.test(originalText) ||
                            /\d{5,}/.test(originalText) ||
                            /[۰-۹]{5,}/u.test(originalText)) {
                            // This is already malformed, skip it and don't try to fix it
                            $cell.data('aio-wc-converted', true);
                            $cell.data('aio-wc-skipped', true);
                            return;
                        }
                        
                        // Skip if it contains a dash in the middle of numbers (like "۹۶۳-/۷۴۹")
                        if (/[۰-۹\d]{3,}[-\/][۰-۹\d]{3,}/u.test(originalText) && 
                            !/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/.test(originalText) &&
                            !/^[۰-۹]{4}[-\/][۰-۹]{1,2}[-\/][۰-۹]{1,2}/u.test(originalText)) {
                            // Malformed pattern detected
                            $cell.data('aio-wc-converted', true);
                            $cell.data('aio-wc-skipped', true);
                            return;
                        }
                        
                        // Skip if already properly converted to Persian calendar (contains Persian month names)
                        var persianCalendarMonths = /(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/.test(originalText);
                        if (persianCalendarMonths) {
                            $cell.data('aio-wc-converted', true);
                            return;
                        }
                        
                        // Skip if it's a valid Persian date format (YYYY/MM/DD where YYYY is 1300-1500)
                        var persianDateMatch = originalText.match(/^([\d۰-۹]{4})[\/\-]([\d۰-۹]{1,2})[\/\-]([\d۰-۹]{1,2})/);
                        if (persianDateMatch) {
                            var year = parseInt(persianDateMatch[1].replace(/[۰-۹]/g, function(char) {
                                return String.fromCharCode(char.charCodeAt(0) - 1728);
                            }), 10);
                            if (year >= 1300 && year < 1500) {
                                // Already a valid Persian date, skip
                                $cell.data('aio-wc-converted', true);
                                return;
                            }
                        }
                        
                        // Only try to convert if it looks like a valid Gregorian date
                        // Must be in format like: YYYY-MM-DD, YYYY/MM/DD, or Month DD, YYYY
                        var looksLikeGregorian = /^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/.test(originalText) ||
                                                 /^[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}/.test(originalText);
                        
                        if (!looksLikeGregorian) {
                            // Doesn't look like a date we can convert, skip it
                            $cell.data('aio-wc-converted', true);
                            return;
                        }
                        
                        // Try to convert the date
                        var converted = self.convertDateString(originalText);
                        if (converted && converted !== originalText && !/\d{5,}/.test(converted) && !/[۰-۹]{5,}/u.test(converted)) {
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
                                    // Update the cell content, preserving any HTML structure
                                    var $time = $cell.find('time, abbr');
                                    if ($time.length > 0) {
                                        $time.text(converted);
                                        // Also mark the time element
                                        $time.data('aio-wc-converted', true);
                                    } else {
                                        $cell.text(converted);
                                    }
                                    // Mark as converted to prevent double conversion
                                    $cell.data('aio-wc-converted', true);
                                    $cell.data('aio-wc-original', originalText); // Store original for reference
                                    $cell.attr('data-aio-wc-converted', 'true'); // Also use attribute for more persistence
                                    
                                    // Add a class to mark as converted (for CSS selectors if needed)
                                    $cell.addClass('aio-wc-date-converted');
                                } else {
                                    // Invalid conversion result, skip it
                                    $cell.data('aio-wc-converted', true);
                                    $cell.data('aio-wc-skipped', true);
                                }
                            } else {
                                // Conversion didn't produce valid format, skip it
                                $cell.data('aio-wc-converted', true);
                                $cell.data('aio-wc-skipped', true);
                            }
                        } else {
                            // Conversion failed or produced invalid result
                            $cell.data('aio-wc-converted', true);
                        }
                    });
                }
            });
        },
        
        /**
         * Convert date on media edit page
         */
        convertMediaEditDate: function() {
            var self = this;
            
            // Find dates in media edit page - look for "Uploaded on", "Updated on", or any date-like text
            var selectors = [
                '#attachment-details .attachment-info p',
                '.attachment-info p',
                '.misc-pub-section',
                '.misc-pub-curtime',
                '#minor-publishing-actions p',
                '.postbox p',
                '#post-status-info p',
                '.misc-pub',
                'p.misc-pub-section',
                '.submitbox .misc-pub-section'
            ];
            
            var $dateElements = $(selectors.join(', ')).filter(function() {
                var text = $(this).text();
                // Check for date-related keywords in English and Persian
                var hasDateKeyword = text.indexOf('Uploaded on') !== -1 || 
                       text.indexOf('آپلود شده در') !== -1 ||
                       text.indexOf('Updated on') !== -1 ||
                       text.indexOf('به روزرسانی شده') !== -1 ||
                       text.indexOf('به‌روزرسانی شده') !== -1;
                
                // Check for date patterns
                var hasEnglishMonth = /(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[uarychilestmber]*\s+[\d۰-۹]{1,2},?\s+[\d۰-۹]{4}/i.test(text);
                var hasPersianMonth = /(ژانویه|فوریه|مارس|آوریل|مه|ژوئن|جولای|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+[\d۰-۹]+,?\s+[\d۰-۹]{4}/.test(text);
                var hasDatePattern = /\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/.test(text) || /\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}/.test(text);
                
                return hasDateKeyword || hasEnglishMonth || hasPersianMonth || hasDatePattern;
            });
            
            if ($dateElements.length > 0) {
                $dateElements.each(function() {
                    var $elem = $(this);
                    
                    // Skip if already processed
                    if ($elem.data('aio-wc-converted')) {
                        return;
                    }
                    
                    var originalText = $elem.text();
                    
                    // Skip if already converted to Persian calendar (contains Persian month names from Jalali calendar)
                    var persianCalendarMonths = /(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/.test(originalText);
                    if (persianCalendarMonths) {
                        $elem.data('aio-wc-converted', true);
                        return;
                    }
                    
                    // Skip if has weird year (already incorrectly converted)
                    if (/\d{5,}/.test(originalText)) {
                        $elem.data('aio-wc-converted', true);
                        return;
                    }
                    
                    // Map Persian language month names to English for conversion
                    var persianToEnglishMonths = {
                        'ژانویه': 'January', 'فوریه': 'February', 'مارس': 'March', 'آوریل': 'April',
                        'مه': 'May', 'ژوئن': 'June', 'جولای': 'July', 'آگوست': 'August',
                        'سپتامبر': 'September', 'اکتبر': 'October', 'نوامبر': 'November', 'دسامبر': 'December'
                    };
                    
                    // Try to extract and convert date
                    // Pattern 1: English month name (e.g., "Oct 22, 2025" or "October 22, 2025")
                    var dateMatch = originalText.match(/([A-Za-z]{3,9})\s+([\d۰-۹]{1,2}),?\s+([\d۰-۹]{4})/);
                    
                    // Pattern 2: Persian month name (e.g., "اکتبر ۲۲, ۲۰۲۵")
                    if (!dateMatch) {
                        for (var persianMonth in persianToEnglishMonths) {
                            var persianRegex = new RegExp('(' + persianMonth + ')\\s+([\\d۰-۹]{1,2}),?\\s+([\\d۰-۹]{4})');
                            dateMatch = originalText.match(persianRegex);
                            if (dateMatch) {
                                // Replace Persian month with English for conversion
                                dateMatch[1] = persianToEnglishMonths[persianMonth];
                                break;
                            }
                        }
                    }
                    
                    if (dateMatch) {
                        // Extract day and year, converting Persian numerals to English if needed
                        var monthName = dateMatch[1];
                        var day = dateMatch[2].replace(/[۰-۹]/g, function(char) {
                            return String.fromCharCode(char.charCodeAt(0) - 1728);
                        });
                        var year = dateMatch[3].replace(/[۰-۹]/g, function(char) {
                            return String.fromCharCode(char.charCodeAt(0) - 1728);
                        });
                        
                        var dateStr = monthName + ' ' + day + ', ' + year;
                        var converted = self.convertDateString(dateStr);
                        
                        if (converted && converted !== dateStr && !/\d{5,}/.test(converted)) {
                            // Get Persian month name for display
                            var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            var monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            
                            // Parse converted date (format: YYYY/MM/DD)
                            var convertedParts = converted.split('/');
                            if (convertedParts.length === 3) {
                                var persianYear = convertedParts[0];
                                var persianMonth = parseInt(convertedParts[1], 10);
                                var persianDay = convertedParts[2];
                                
                                if (persianMonth >= 1 && persianMonth <= 12) {
                                    var persianMonthName = persianMonths[persianMonth - 1];
                                    // Format as "Day Month Year" in Persian
                                    var formattedDate = persianDay + ' ' + persianMonthName + ' ' + persianYear;
                                    
                                    // Replace the original date in the text
                                    var originalDatePattern = dateMatch[0];
                                    var newText = originalText.replace(originalDatePattern, formattedDate);
                                    $elem.text(newText);
                                    $elem.data('aio-wc-converted', true);
                                }
                            }
                        }
                    } else {
                        // Try other date formats
                        var converted = self.convertDateString(originalText);
                        if (converted && converted !== originalText && !/\d{5,}/.test(converted)) {
                            $elem.text(converted);
                            $elem.data('aio-wc-converted', true);
                        }
                    }
                });
            }
        },
        
        /**
         * Convert a date string to Persian
         */
        convertDateString: function(dateStr) {
            if (!dateStr || typeof dateStr !== 'string') {
                return dateStr;
            }
            
            // Try to parse common date formats
            var formats = [
                /(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/, // YYYY-MM-DD or YYYY/MM/DD
                /([A-Za-z]{3,9})\s+(\d{1,2}),?\s+(\d{4})/, // Month DD, YYYY
                /(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/, // DD-MM-YYYY or DD/MM/YYYY
            ];
            
            for (var i = 0; i < formats.length; i++) {
                var match = dateStr.match(formats[i]);
                if (match) {
                    try {
                        var gregorianDate;
                        if (match[0].indexOf('-') !== -1 || match[0].indexOf('/') !== -1) {
                            // Numeric date format
                            var year = parseInt(match[1], 10);
                            var month = parseInt(match[2], 10);
                            var day = parseInt(match[3], 10);
                            
                            // Helper to pad numbers
                            var pad = function(num) {
                                return (num < 10 ? '0' : '') + num;
                            };
                            
                            // Determine if it's YYYY-MM-DD or DD-MM-YYYY
                            if (year > 31) {
                                // YYYY-MM-DD
                                gregorianDate = year + '-' + pad(month) + '-' + pad(day);
                            } else {
                                // DD-MM-YYYY (match[3] is year)
                                gregorianDate = match[3] + '-' + pad(month) + '-' + pad(year);
                            }
                        } else {
                            // Month name format (e.g., "Oct 22, 2025")
                            var monthNames = {
                                'January': '01', 'February': '02', 'March': '03', 'April': '04',
                                'May': '05', 'June': '06', 'July': '07', 'August': '08',
                                'September': '09', 'October': '10', 'November': '11', 'December': '12',
                                'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
                                'May': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
                                'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
                            };
                            
                            var monthName = match[1];
                            var day = parseInt(match[2].replace(',', ''), 10);
                            var year = parseInt(match[3], 10);
                            
                            if (monthNames[monthName]) {
                                var pad = function(num) {
                                    return (num < 10 ? '0' : '') + num;
                                };
                                gregorianDate = year + '-' + monthNames[monthName] + '-' + pad(day);
                            } else {
                                continue; // Try next format
                            }
                        }
                        
                        // Parse the gregorian date string to get year, month, day
                        var dateParts = gregorianDate.split('-');
                        if (dateParts.length === 3) {
                            var gYear = parseInt(dateParts[0], 10);
                            var gMonth = parseInt(dateParts[1], 10);
                            var gDay = parseInt(dateParts[2], 10);
                            
                            // Convert to Persian using gregorianToJalali
                            var persianDate = PersianDateConverter.gregorianToJalali(gYear, gMonth, gDay);
                            if (persianDate && persianDate.year && persianDate.month && persianDate.day) {
                                // Format as Y/m/d (padStart polyfill for older browsers)
                                var pad = function(num) {
                                    return (num < 10 ? '0' : '') + num;
                                };
                                return persianDate.year + '/' + 
                                       pad(persianDate.month) + '/' + 
                                       pad(persianDate.day);
                            }
                        }
                    } catch (e) {
                        console.error('Error converting date:', e);
                    }
                }
            }
            
            return dateStr;
        }
    };
    
    // Initialize on page load
    MediaDateConverter.init();
    
})(jQuery);

