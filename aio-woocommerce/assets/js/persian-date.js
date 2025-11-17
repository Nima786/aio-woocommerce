/**
 * Persian Date Conversion Library (JavaScript)
 * Converts between Gregorian and Jalali (Persian) calendars
 */

(function(window) {
    'use strict';
    
    /**
     * Persian Date Converter
     */
    window.PersianDateConverter = {
        /**
         * Convert Gregorian date to Jalali (Persian) date
         */
        gregorianToJalali: function(g_y, g_m, g_d) {
            var g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            var j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
            
            var gy = g_y - 1600;
            var gm = g_m - 1;
            var gd = g_d - 1;
            
            var g_day_no = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400);
            
            for (var i = 0; i < gm; ++i) {
                g_day_no += g_days_in_month[i];
            }
            
            if (gm > 1 && ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0))) {
                g_day_no++;
            }
            
            g_day_no += gd;
            
            var j_day_no = g_day_no - 79;
            
            var j_np = Math.floor(j_day_no / 12053);
            j_day_no = j_day_no % 12053;
            
            var jy = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
            j_day_no %= 1461;
            
            if (j_day_no >= 366) {
                jy += Math.floor((j_day_no - 1) / 365);
                j_day_no = (j_day_no - 1) % 365;
            }
            
            var j;
            for (j = 0; j < 11 && j_day_no >= j_days_in_month[j]; ++j) {
                j_day_no -= j_days_in_month[j];
            }
            
            var jm = j + 1;
            var jd = j_day_no + 1;
            
            return {
                year: jy,
                month: jm,
                day: jd
            };
        },
        
        /**
         * Convert Jalali (Persian) date to Gregorian date
         */
        jalaliToGregorian: function(j_y, j_m, j_d) {
            var g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            var j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
            
            var jy = j_y - 979;
            var jm = j_m - 1;
            var jd = j_d - 1;
            
            var j_day_no = 365 * jy + Math.floor(jy / 33) * 8 + Math.floor((jy % 33 + 3) / 4);
            
            for (var i = 0; i < jm; ++i) {
                j_day_no += j_days_in_month[i];
            }
            
            j_day_no += jd;
            
            var g_day_no = j_day_no + 79;
            
            var gy = 1600 + 400 * Math.floor(g_day_no / 146097);
            g_day_no = g_day_no % 146097;
            
            var leap = 1;
            if (g_day_no >= 36525) {
                g_day_no--;
                gy += 100 * Math.floor(g_day_no / 36524);
                g_day_no = g_day_no % 36524;
                
                if (g_day_no >= 365) {
                    g_day_no++;
                } else {
                    leap = 0;
                }
            }
            
            gy += 4 * Math.floor(g_day_no / 1461);
            g_day_no %= 1461;
            
            if (g_day_no >= 366) {
                leap = 0;
                g_day_no--;
                gy += Math.floor(g_day_no / 365);
                g_day_no = g_day_no % 365;
            }
            
            var i;
            for (i = 0; g_day_no >= g_days_in_month[i] + (i == 1 && leap); i++) {
                g_day_no -= g_days_in_month[i] + (i == 1 && leap);
            }
            
            var gm = i + 1;
            var gd = g_day_no + 1;
            
            return {
                year: gy,
                month: gm,
                day: gd
            };
        },
        
        /**
         * Format date string to Persian format
         */
        formatDate: function(dateString, format) {
            if (!dateString) return '';
            
            var date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            var g_y = date.getFullYear();
            var g_m = date.getMonth() + 1;
            var g_d = date.getDate();
            
            var jalali = this.gregorianToJalali(g_y, g_m, g_d);
            
            format = format || 'Y/m/d';
            var persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            var persianDays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
            
            var dayOfWeek = date.getDay();
            var hour = date.getHours();
            var minute = date.getMinutes();
            var second = date.getSeconds();
            
            var replacements = {
                'Y': String(jalali.year).padStart(4, '0'),
                'y': String(jalali.year).slice(-2),
                'm': String(jalali.month).padStart(2, '0'),
                'n': String(jalali.month),
                'M': persianMonths[jalali.month - 1],
                'F': persianMonths[jalali.month - 1],
                'd': String(jalali.day).padStart(2, '0'),
                'j': String(jalali.day),
                'D': persianDays[dayOfWeek],
                'l': persianDays[dayOfWeek],
                'H': String(hour).padStart(2, '0'),
                'i': String(minute).padStart(2, '0'),
                's': String(second).padStart(2, '0')
            };
            
            var formatted = format;
            for (var key in replacements) {
                formatted = formatted.replace(new RegExp(key, 'g'), replacements[key]);
            }
            
            return formatted;
        },
        
        /**
         * Parse Persian date string to Gregorian
         */
        parsePersianDate: function(persianDateString) {
            if (!persianDateString) return null;
            
            var parts = persianDateString.split('/');
            if (parts.length !== 3) return null;
            
            var j_y = parseInt(parts[0], 10);
            var j_m = parseInt(parts[1], 10);
            var j_d = parseInt(parts[2], 10);
            
            if (isNaN(j_y) || isNaN(j_m) || isNaN(j_d)) return null;
            
            var gregorian = this.jalaliToGregorian(j_y, j_m, j_d);
            
            return new Date(gregorian.year, gregorian.month - 1, gregorian.day);
        }
    };
})(window);


