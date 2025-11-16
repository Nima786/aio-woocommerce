/**
 * Post Edit Page Date Conversion
 * Converts dates in the Publish meta box on post edit pages (products, posts, pages)
 */

(function($) {
    'use strict';
    
    // Check if PersianDateConverter is available
    if (typeof PersianDateConverter === 'undefined') {
        return;
    }
    
    var PostEditDates = {
        initialized: false,
        
        /**
         * Initialize date conversion
         */
        init: function() {
            var self = this;
            
            if (self.initialized) {
                return;
            }
            self.initialized = true;
            
            // Run immediately if DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    self.startConversion();
                });
            } else {
                self.startConversion();
            }
        },
        
        /**
         * Start the conversion process
         */
        startConversion: function() {
            var self = this;
            
            // Wait a bit for page to fully load
            setTimeout(function() {
                self.convertPublishBoxDates();
            }, 100);
            
            // Run again after a delay
            setTimeout(function() {
                self.convertPublishBoxDates();
            }, 500);
            
            // Run again after longer delay
            setTimeout(function() {
                self.convertPublishBoxDates();
            }, 1000);
            
            // Run again after even longer delay
            setTimeout(function() {
                self.convertPublishBoxDates();
            }, 2000);
            
            // Listen for when timestamp is saved to update display
            $(document).on('click', '#timestamp .save-timestamp', function(e) {
                // Clear conversion flags so we can convert again
                $('#timestamp span, .misc-pub-section').removeData('aio-wc-converted').removeAttr('data-aio-wc-converted').removeClass('aio-wc-date-converted');
                // After saving, convert the display back to Persian
                setTimeout(function() {
                    self.convertPublishBoxDates();
                }, 100);
                setTimeout(function() {
                    self.convertPublishBoxDates();
                }, 300);
                setTimeout(function() {
                    self.convertPublishBoxDates();
                }, 500);
            });
            
            // Listen for when timestamp is cancelled to update display
            $(document).on('click', '#timestamp .cancel-timestamp', function(e) {
                // Clear conversion flags so we can convert again
                $('#timestamp span, .misc-pub-section').removeData('aio-wc-converted').removeAttr('data-aio-wc-converted').removeClass('aio-wc-date-converted');
                // After cancelling, convert the display back to Persian
                setTimeout(function() {
                    self.convertPublishBoxDates();
                }, 100);
                setTimeout(function() {
                    self.convertPublishBoxDates();
                }, 300);
            });
            
            // Also listen for when timestamp div is hidden (after OK/Cancel)
            if (typeof MutationObserver !== 'undefined') {
                var timestampObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                            var $target = $(mutation.target);
                            if ($target.is('#timestampdiv') && !$target.is(':visible')) {
                                // Clear conversion flags so we can convert again
                                $('#timestamp span, .misc-pub-section').removeData('aio-wc-converted').removeAttr('data-aio-wc-converted').removeClass('aio-wc-date-converted');
                                // Timestamp div was hidden, convert display
                                setTimeout(function() {
                                    self.convertPublishBoxDates();
                                }, 100);
                            }
                        }
                    });
                });
                
                var timestampDiv = document.getElementById('timestampdiv');
                if (timestampDiv) {
                    timestampObserver.observe(timestampDiv, {
                        attributes: true,
                        attributeFilter: ['style']
                    });
                }
            }
            
            // Use MutationObserver to catch dynamically updated dates
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    var shouldConvert = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === 1) { // Element node
                                    var $node = $(node);
                                    if ($node.closest('#minor-publishing-actions, #misc-publishing-actions, .misc-pub-section').length > 0 ||
                                        $node.text().match(/(Published on|at|Apr|Jan|Feb|Mar|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i)) {
                                        shouldConvert = true;
                                        break;
                                    }
                                }
                            }
                        } else if (mutation.type === 'characterData') {
                            // Text content changed
                            var text = mutation.target.textContent || '';
                            if (text.match(/(Published on|at|Apr|Jan|Feb|Mar|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i)) {
                                shouldConvert = true;
                            }
                        }
                    });
                    if (shouldConvert) {
                        setTimeout(function() {
                            self.convertPublishBoxDates();
                        }, 100);
                        setTimeout(function() {
                            self.convertPublishBoxDates();
                        }, 300);
                    }
                });
                
                // Observe the publish box
                var publishBox = document.getElementById('submitdiv') || document.querySelector('.postbox');
                if (publishBox) {
                    observer.observe(publishBox, {
                        childList: true,
                        subtree: true,
                        characterData: true
                    });
                }
            }
        },
        
        /**
         * Convert dates in Publish meta box
         */
        convertPublishBoxDates: function() {
            var self = this;
            
            // Find the "Published on" date element - try multiple selectors
            var selectors = [
                '#misc-publishing-actions .misc-pub-section',
                '.misc-pub-section',
                '#timestamp',
                '.timestamp-wrap',
                '#submitdiv .misc-pub-section',
                '.postbox .misc-pub-section'
            ];
            
            // Also search more broadly for elements containing "Published on"
            var $allSections = $(selectors.join(', '));
            
            // If no sections found, search for any element containing "Published on"
            if ($allSections.length === 0) {
                $allSections = $('body').find('*').filter(function() {
                    var text = $(this).text() || '';
                    return text.indexOf('Published on') !== -1 || text.indexOf('Published:') !== -1;
                });
            }
            
            $allSections.each(function() {
                var $element = $(this);
                
                // Skip if already processed
                if ($element.data('aio-wc-converted') || 
                    $element.attr('data-aio-wc-converted') === 'true' || 
                    $element.hasClass('aio-wc-date-converted')) {
                    return;
                }
                
                // Get original HTML and text
                var originalHtml = $element.html() || '';
                var originalText = $element.text() || '';
                
                // Skip if empty
                if (!originalText || originalText.length < 5) {
                    $element.data('aio-wc-converted', true);
                    return;
                }
                
                // Check if it contains dates with English month names (like "Apr ۱۴, ۲۰۲۵" or "Apr 14, 2025")
                var hasEnglishMonth = /(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+[\d۰-۹]+\s*[,،]?\s*[\d۰-۹]{4}/i.test(originalText);
                
                // Check if it's already a Persian calendar date
                var hasPersianCalendarMonth = /(فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)/.test(originalText);
                
                if (hasPersianCalendarMonth) {
                    $element.data('aio-wc-converted', true);
                    return;
                }
                
                if (!hasEnglishMonth) {
                    $element.data('aio-wc-converted', true);
                    return;
                }
                
                // Find the span element that contains the date (usually #timestamp span)
                // WordPress uses #timestamp span to display the date, and the Edit link is separate
                var $dateSpan = $element.find('#timestamp span, .timestamp span, span.timestamp');
                
                // Also try to find the span directly by ID
                if ($dateSpan.length === 0) {
                    $dateSpan = $('#timestamp span');
                }
                
                if ($dateSpan.length > 0) {
                    // Convert date in the span element only - this preserves the Edit link
                    $dateSpan.each(function() {
                        var $span = $(this);
                        
                        // Skip if already converted
                        if ($span.data('aio-wc-converted') || 
                            $span.attr('data-aio-wc-converted') === 'true' || 
                            $span.hasClass('aio-wc-date-converted')) {
                            return;
                        }
                        
                        var spanText = $span.text() || '';
                        
                        // Check for date pattern with time (e.g., "Apr 14, 2025 at 16:27")
                        var dateTimePattern = /((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+[\d۰-۹]+\s*[,،]?\s*[\d۰-۹]{4}\s+at\s+[\d۰-۹]+:[\d۰-۹]+)/i;
                        var dateOnlyPattern = /((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+[\d۰-۹]+\s*[,،]?\s*[\d۰-۹]{4})/i;
                        
                        var newSpanText = spanText;
                        
                        // Try date with time first
                        if (spanText.match(dateTimePattern)) {
                            newSpanText = spanText.replace(dateTimePattern, function(match) {
                                // Extract date and time parts
                                var dateTimeMatch = match.match(/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December))\s+([\d۰-۹]+)\s*[,،]?\s*([\d۰-۹]{4})\s+at\s+([\d۰-۹]+):([\d۰-۹]+)/i);
                                if (dateTimeMatch) {
                                    var datePart = dateTimeMatch[1] + ' ' + dateTimeMatch[2] + ', ' + dateTimeMatch[3];
                                    var converted = self.convertDateString(datePart);
                                    if (converted && converted !== datePart && !/\d{5,}/.test(converted)) {
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
                                                var formattedDate = persianMonthName + ' ' + convDay + ', ' + convYear;
                                                
                                                // Add time back
                                                var hour = dateTimeMatch[4];
                                                var minute = dateTimeMatch[5];
                                                if (typeof PersianNumerals !== 'undefined') {
                                                    formattedDate += ' در ' + PersianNumerals.toPersian(hour + ':' + minute);
                                                } else {
                                                    formattedDate += ' at ' + hour + ':' + minute;
                                                }
                                                
                                                if (typeof PersianNumerals !== 'undefined') {
                                                    formattedDate = PersianNumerals.toPersian(formattedDate);
                                                }
                                                
                                                return formattedDate;
                                            }
                                        }
                                    }
                                }
                                return match;
                            });
                        }
                        
                        // If no time pattern matched, try date only
                        if (newSpanText === spanText && spanText.match(dateOnlyPattern)) {
                            newSpanText = spanText.replace(dateOnlyPattern, function(match) {
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
                                            var formattedDate = persianMonthName + ' ' + convDay + ', ' + convYear;
                                            
                                            if (typeof PersianNumerals !== 'undefined') {
                                                formattedDate = PersianNumerals.toPersian(formattedDate);
                                            }
                                            
                                            return formattedDate;
                                        }
                                    }
                                }
                                return match;
                            });
                        }
                        
                        if (newSpanText !== spanText) {
                            // Only update the text content, not the HTML structure
                            $span.text(newSpanText);
                            $span.data('aio-wc-converted', true);
                            $span.attr('data-aio-wc-converted', 'true');
                            $span.addClass('aio-wc-date-converted');
                            $element.data('aio-wc-converted', true);
                        } else {
                            $span.data('aio-wc-converted', true);
                            $element.data('aio-wc-converted', true);
                        }
                    });
                } else {
                    // If no span found, look for text nodes directly (fallback)
                    var $textNodes = $element.contents().filter(function() {
                        return this.nodeType === 3; // Text node
                    });
                    
                    var converted = false;
                    $textNodes.each(function() {
                        var text = this.textContent || '';
                        if (text.match(/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+[\d۰-۹]+\s*[,،]?\s*[\d۰-۹]{4}/i)) {
                            var newText = text.replace(/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+[\d۰-۹]+\s*[,،]?\s*[\d۰-۹]{4})/gi, function(match) {
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
                                            var formattedDate = persianMonthName + ' ' + convDay + ', ' + convYear;
                                            
                                            if (typeof PersianNumerals !== 'undefined') {
                                                formattedDate = PersianNumerals.toPersian(formattedDate);
                                            }
                                            
                                            return formattedDate;
                                        }
                                    }
                                }
                                return match;
                            });
                            if (newText !== text) {
                                this.textContent = newText;
                                converted = true;
                            }
                        }
                    });
                    
                    if (converted) {
                        $element.data('aio-wc-converted', true);
                        $element.attr('data-aio-wc-converted', 'true');
                        $element.addClass('aio-wc-date-converted');
                    } else {
                        $element.data('aio-wc-converted', true);
                    }
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
            
            // Map English month names to numbers
            var monthMap = {
                'jan': 1, 'january': 1,
                'feb': 2, 'february': 2,
                'mar': 3, 'march': 3,
                'apr': 4, 'april': 4,
                'may': 5,
                'jun': 6, 'june': 6,
                'jul': 7, 'july': 7,
                'aug': 8, 'august': 8,
                'sep': 9, 'september': 9,
                'oct': 10, 'october': 10,
                'nov': 11, 'november': 11,
                'dec': 12, 'december': 12
            };
            
            // Extract date: "Apr ۱۴, ۲۰۲۵" or "Apr 14, 2025" (with or without comma)
            var dateMatch = dateStr.match(/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+([\d۰-۹]+)\s*[,،]?\s*([\d۰-۹]{4})/i);
            
            if (dateMatch) {
                try {
                    var monthName = dateMatch[1].toLowerCase();
                    var monthNum = monthMap[monthName];
                    
                    if (!monthNum) {
                        return dateStr;
                    }
                    
                    // Convert Persian numerals to Western if needed
                    var day = dateMatch[2].replace(/[۰-۹]/g, function(char) {
                        return String.fromCharCode(char.charCodeAt(0) - 1728);
                    });
                    var year = dateMatch[3].replace(/[۰-۹]/g, function(char) {
                        return String.fromCharCode(char.charCodeAt(0) - 1728);
                    });
                    
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
    PostEditDates.init();
    
})(jQuery);

