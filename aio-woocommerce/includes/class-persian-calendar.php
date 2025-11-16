<?php
/**
 * Persian Calendar Conversion Library
 * Converts between Gregorian and Jalali (Persian) calendars
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIO_WC_Persian_Calendar {
    
    /**
     * Convert Gregorian date to Jalali (Persian) date
     * 
     * @param int $g_y Gregorian year
     * @param int $g_m Gregorian month
     * @param int $g_d Gregorian day
     * @return array Array with 'year', 'month', 'day'
     */
    public static function gregorian_to_jalali($g_y, $g_m, $g_d) {
        $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
        
        $gy = $g_y - 1600;
        $gm = $g_m - 1;
        $gd = $g_d - 1;
        
        $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
        
        for ($i = 0; $i < $gm; ++$i) {
            $g_day_no += $g_days_in_month[$i];
        }
        
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
            $g_day_no++;
        }
        
        $g_day_no += $gd;
        
        $j_day_no = $g_day_no - 79;
        
        $j_np = floor($j_day_no / 12053);
        $j_day_no = $j_day_no % 12053;
        
        $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
        $j_day_no %= 1461;
        
        if ($j_day_no >= 366) {
            $jy += floor(($j_day_no - 1) / 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }
        
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
            $j_day_no -= $j_days_in_month[$i];
        }
        
        $jm = $i + 1;
        $jd = $j_day_no + 1;
        
        return array(
            'year' => (int)$jy,
            'month' => (int)$jm,
            'day' => (int)$jd
        );
    }
    
    /**
     * Convert Jalali (Persian) date to Gregorian date
     * 
     * @param int $j_y Jalali year
     * @param int $j_m Jalali month
     * @param int $j_d Jalali day
     * @return array Array with 'year', 'month', 'day'
     */
    public static function jalali_to_gregorian($j_y, $j_m, $j_d) {
        $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
        
        $jy = $j_y - 979;
        $jm = $j_m - 1;
        $jd = $j_d - 1;
        
        $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor(($jy % 33 + 3) / 4);
        
        for ($i = 0; $i < $jm; ++$i) {
            $j_day_no += $j_days_in_month[$i];
        }
        
        $j_day_no += $jd;
        
        $g_day_no = $j_day_no + 79;
        
        $gy = 1600 + 400 * floor($g_day_no / 146097);
        $g_day_no = $g_day_no % 146097;
        
        $leap = 1;
        if ($g_day_no >= 36525) {
            $g_day_no--;
            $gy += 100 * floor($g_day_no / 36524);
            $g_day_no = $g_day_no % 36524;
            
            if ($g_day_no >= 365) {
                $g_day_no++;
            } else {
                $leap = 0;
            }
        }
        
        $gy += 4 * floor($g_day_no / 1461);
        $g_day_no %= 1461;
        
        if ($g_day_no >= 366) {
            $leap = 0;
            $g_day_no--;
            $gy += floor($g_day_no / 365);
            $g_day_no = $g_day_no % 365;
        }
        
        for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) {
            $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
        }
        
        $gm = $i + 1;
        $gd = $g_day_no + 1;
        
        return array(
            'year' => (int)$gy,
            'month' => (int)$gm,
            'day' => (int)$gd
        );
    }
    
    /**
     * Convert WordPress date format to Persian date
     * 
     * @param string $date_string Date string in any format
     * @param string $format Date format (default: WordPress date format)
     * @return string Formatted Persian date
     */
    public static function convert_to_persian($date_string, $format = null) {
        if (empty($date_string)) {
            return '';
        }
        
        if ($format === null) {
            $format = get_option('date_format', 'Y/m/d');
        }
        
        // Normalize the date string - handle Persian month names from WordPress
        // When user language is Persian, WordPress might display dates with Persian month names
        // We need to convert these to English month names for strtotime() to work
        $normalized_date = self::normalize_date_string($date_string);
        
        // Parse the date - try multiple methods
        $timestamp = false;
        
        // Method 1: Try strtotime with normalized date
        if ($normalized_date !== $date_string) {
            $timestamp = strtotime($normalized_date);
        }
        
        // Method 2: Try strtotime with original date
        if ($timestamp === false) {
            $timestamp = strtotime($date_string);
        }
        
        // Method 3: Try parsing common date formats manually
        if ($timestamp === false) {
            $timestamp = self::parse_date_manual($date_string);
        }
        
        // If all parsing methods failed, return original string
        if ($timestamp === false) {
            return $date_string;
        }
        
        $g_y = (int)date('Y', $timestamp);
        $g_m = (int)date('m', $timestamp);
        $g_d = (int)date('d', $timestamp);
        
        $jalali = self::gregorian_to_jalali($g_y, $g_m, $g_d);
        
        // Format the date
        $formatted = self::format_jalali_date($jalali, $format, $timestamp);
        
        return $formatted;
    }
    
    /**
     * Normalize date string - convert Persian month names to English for parsing
     * 
     * @param string $date_string Date string that might contain Persian month names
     * @return string Normalized date string with English month names
     */
    private static function normalize_date_string($date_string) {
        // Map Persian language month names (WordPress translations) to English
        $persian_to_english_months = array(
            'ژانویه' => 'January', 'ژانویهٔ' => 'January',
            'فوریه' => 'February', 'فوریهٔ' => 'February',
            'مارس' => 'March',
            'آوریل' => 'April',
            'مه' => 'May', 'مهٔ' => 'May',
            'ژوئن' => 'June',
            'جولای' => 'July',
            'آگوست' => 'August',
            'سپتامبر' => 'September',
            'اکتبر' => 'October',
            'نوامبر' => 'November',
            'دسامبر' => 'December',
        );
        
        $normalized = $date_string;
        foreach ($persian_to_english_months as $persian => $english) {
            $normalized = str_replace($persian, $english, $normalized);
        }
        
        return $normalized;
    }
    
    /**
     * Manually parse date string when strtotime fails
     * Handles common WordPress date formats
     * 
     * @param string $date_string Date string to parse
     * @return int|false Unix timestamp or false on failure
     */
    private static function parse_date_manual($date_string) {
        // Try YYYY-MM-DD format
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $date_string, $matches)) {
            return mktime(0, 0, 0, (int)$matches[2], (int)$matches[3], (int)$matches[1]);
        }
        
        // Try DD/MM/YYYY or MM/DD/YYYY format
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/', $date_string, $matches)) {
            // Try MM/DD/YYYY first (US format)
            $timestamp = mktime(0, 0, 0, (int)$matches[1], (int)$matches[2], (int)$matches[3]);
            if ($timestamp !== false && checkdate((int)$matches[1], (int)$matches[2], (int)$matches[3])) {
                return $timestamp;
            }
            // Try DD/MM/YYYY (European format)
            $timestamp = mktime(0, 0, 0, (int)$matches[2], (int)$matches[1], (int)$matches[3]);
            if ($timestamp !== false && checkdate((int)$matches[2], (int)$matches[1], (int)$matches[3])) {
                return $timestamp;
            }
        }
        
        return false;
    }
    
    /**
     * Format Jalali date according to format string
     * 
     * @param array $jalali Jalali date array
     * @param string $format Format string
     * @param int $timestamp Unix timestamp for time
     * @return string Formatted date
     */
    public static function format_jalali_date($jalali, $format, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $persian_months = array(
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        );
        
        $persian_days = array(
            'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'
        );
        
        $day_of_week = date('w', $timestamp);
        $hour = date('H', $timestamp);
        $minute = date('i', $timestamp);
        $second = date('s', $timestamp);
        
        $replacements = array(
            'Y' => str_pad($jalali['year'], 4, '0', STR_PAD_LEFT),
            'y' => substr($jalali['year'], -2),
            'm' => str_pad($jalali['month'], 2, '0', STR_PAD_LEFT),
            'n' => $jalali['month'],
            'M' => $persian_months[$jalali['month'] - 1],
            'F' => $persian_months[$jalali['month'] - 1],
            'd' => str_pad($jalali['day'], 2, '0', STR_PAD_LEFT),
            'j' => $jalali['day'],
            'D' => $persian_days[$day_of_week],
            'l' => $persian_days[$day_of_week],
            'H' => str_pad($hour, 2, '0', STR_PAD_LEFT),
            'i' => str_pad($minute, 2, '0', STR_PAD_LEFT),
            's' => str_pad($second, 2, '0', STR_PAD_LEFT),
        );
        
        $formatted = $format;
        foreach ($replacements as $key => $value) {
            $formatted = str_replace($key, $value, $formatted);
        }
        
        return $formatted;
    }
    
    /**
     * Get current Persian date
     * 
     * @param string $format Date format
     * @return string Formatted Persian date
     */
    public static function get_current_persian_date($format = null) {
        return self::convert_to_persian(current_time('mysql'), $format);
    }
    
    /**
     * Convert Persian date string to Gregorian timestamp
     * 
     * @param string $persian_date Persian date string (Y/m/d format)
     * @return int|false Unix timestamp or false on failure
     */
    public static function persian_to_timestamp($persian_date) {
        $parts = explode('/', $persian_date);
        if (count($parts) !== 3) {
            return false;
        }
        
        $j_y = (int)$parts[0];
        $j_m = (int)$parts[1];
        $j_d = (int)$parts[2];
        
        $gregorian = self::jalali_to_gregorian($j_y, $j_m, $j_d);
        
        return mktime(0, 0, 0, $gregorian['month'], $gregorian['day'], $gregorian['year']);
    }
}


