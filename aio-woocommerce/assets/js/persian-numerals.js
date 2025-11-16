/**
 * Persian Numeral Converter
 * Converts Persian/Arabic numerals to Western numerals and vice versa
 */

(function(window) {
    'use strict';
    
    window.PersianNumerals = {
        // Persian/Arabic to Western numeral mapping
        persianToWestern: {
            '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
            '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9'
        },
        
        westernToPersian: {
            '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴',
            '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹'
        },
        
        /**
         * Convert Persian/Arabic numerals to Western numerals
         */
        toWestern: function(str) {
            if (!str) return str;
            var result = String(str);
            for (var persian in this.persianToWestern) {
                result = result.replace(new RegExp(persian, 'g'), this.persianToWestern[persian]);
            }
            return result;
        },
        
        /**
         * Convert Western numerals to Persian/Arabic numerals
         */
        toPersian: function(str) {
            if (!str) return str;
            var result = String(str);
            for (var western in this.westernToPersian) {
                result = result.replace(new RegExp(western, 'g'), this.westernToPersian[western]);
            }
            return result;
        }
    };
})(window);


