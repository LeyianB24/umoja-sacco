<?php
declare(strict_types=1);

namespace USMS;

/**
 * Validator provides comprehensive input validation and sanitization.
 * Ensures all user inputs are safe and meet business requirements.
 */
class Validator
{
    private array $errors = [];
    
    /**
     * Validate required field
     */
    public static function required($value, string $field = ''): bool
    {
        if (empty($value) && $value !== '0') {
            return false;
        }
        return true;
    }
    
    /**
     * Validate email format
     */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate numeric value within range
     */
    public static function numeric($value, float $min = null, float $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $num = (float)$value;
        if ($min !== null && $num < $min) {
            return false;
        }
        if ($max !== null && $num > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate phone number (East African formats)
     */
    public static function phone(string $phone): bool
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return preg_match('/^(\+?254|0)[0-9]{9}$/', $phone) === 1;
    }
    
    /**
     * Validate date format
     */
    public static function date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate national ID (Kenyan format)
     */
    public static function nationalId(string $id): bool
    {
        return preg_match('/^\d{1,8}$/', trim($id)) === 1;
    }
    
    /**
     * Validate string length
     */
    public static function stringLength(string $value, int $min = 0, int $max = null): bool
    {
        $len = strlen($value);
        if ($len < $min) {
            return false;
        }
        if ($max !== null && $len > $max) {
            return false;
        }
        return true;
    }
    
    /**
     * Validate array contains only specified keys
     */
    public static function arrayKeys(array $data, array $allowedKeys): bool
    {
        return empty(array_diff_key($data, array_flip($allowedKeys)));
    }
    
    /**
     * Validate enum value
     */
    public static function enum($value, array $allowedValues): bool
    {
        return in_array($value, $allowedValues, true);
    }
    
    /**
     * Sanitize string input - remove dangerous characters
     */
    public static function sanitizeString(string $input): string
    {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }
    
    /**
     * Sanitize numeric input
     */
    public static function sanitizeNumeric($input): float
    {
        return (float)preg_replace('/[^0-9\.\-]/', '', $input);
    }
    
    /**
     * Sanitize email
     */
    public static function sanitizeEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Escape for HTML output
     */
    public static function escape(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Validate password strength
     */
    public static function passwordStrength(string $password): array
    {
        $strength = 0;
        $feedback = [];
        
        if (strlen($password) >= 8) {
            $strength++;
        } else {
            $feedback[] = 'Password must be at least 8 characters';
        }
        
        if (preg_match('/[a-z]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password must contain lowercase letters';
        }
        
        if (preg_match('/[A-Z]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password must contain uppercase letters';
        }
        
        if (preg_match('/[0-9]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password must contain numbers';
        }
        
        if (preg_match('/[!@#$%^&*]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password should contain special characters (!@#$%^&*)';
        }
        
        return [
            'score' => $strength,
            'max' => 5,
            'percent' => ($strength / 5) * 100,
            'feedback' => $feedback
        ];
    }
}
