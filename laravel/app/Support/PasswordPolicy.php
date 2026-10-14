<?php

namespace App\Support;

/**
 * Registration password rules ported from register.php. The same word list
 * is used by the client-side strength meter in auth/register.blade.php.
 */
class PasswordPolicy
{
    public const COMMON_PASSWORDS = [
        'password', 'password1', 'password123', 'password123!', 'passw0rd',
        'admin', 'admin123', 'admin123!', 'administrator',
        'welcome', 'welcome1', 'welcome123', 'welcome123!',
        'qwerty', 'qwerty1', 'qwerty123', 'qwerty123!', 'qwertyuiop',
        'asdfgh', 'asdfghjkl', 'zxcvbn', 'zxcvbnm',
        'abc123', 'abcd1234', 'abcdef', 'abcdefg',
        '12345678', '123456789', '1234567890', '11111111', '00000000',
        'iloveyou', 'letmein', 'monkey', 'dragon', 'master', 'login',
        'changeme', 'changeme1', 'test1234', 'testing1',
        'sjnhs', 'sanjosef', 'cabanatuan', 'philippines',
        'student', 'student1', 'student123', 'student123!',
        'instructor', 'teacher', 'teacher1', 'graduate',
        'p@ssw0rd', 'p@ssword', 'p@ssword1', 'pass@word1',
        'summer2024', 'summer2025', 'summer2026',
        'winter2024', 'winter2025', 'winter2026',
    ];

    private const SEQUENCES = [
        '0123456789', '9876543210',
        'abcdefghijklmnopqrstuvwxyz',
        'zyxwvutsrqponmlkjihgfedcba',
        'qwertyuiop', 'asdfghjkl', 'zxcvbnm',
    ];

    private const COMMON_WORDS = ['password', 'pass', 'admin', 'user', 'login', 'welcome', 'qwerty', 'abc', 'test', 'school', 'student'];

    /**
     * First failing rule message, or null when the password is acceptable.
     */
    public static function registrationError(string $password): ?string
    {
        return match (true) {
            strlen($password) < 8 => 'Password must be at least 8 characters.',
            ! preg_match('/[A-Z]/', $password) => 'Password must contain at least one uppercase letter.',
            ! preg_match('/[a-z]/', $password) => 'Password must contain at least one lowercase letter.',
            ! preg_match('/[0-9]/', $password) => 'Password must contain at least one number.',
            ! preg_match('/[\W_]/', $password) => 'Password must contain at least one special character (e.g. @, #, !, $).',
            self::isEasyToGuess($password) => 'That password is too easy to guess. Please choose a stronger, less common password.',
            default => null,
        };
    }

    public static function isEasyToGuess(string $password): bool
    {
        $lower = strtolower($password);
        $compact = self::compact($password);

        foreach (self::COMMON_PASSWORDS as $word) {
            $wordCompact = self::compact($word);
            if ($lower === $word || $compact === $wordCompact) {
                return true;
            }
            if (strlen($word) >= 5 && str_contains($compact, $wordCompact)
                && strlen($compact) <= strlen($wordCompact) + 4) {
                return true;
            }
        }

        if (preg_match('/^(.)\1{4,}/u', $password) || preg_match('/(.)\1{5,}/u', $password)) {
            return true;
        }

        foreach (self::SEQUENCES as $sequence) {
            for ($i = 0; $i <= strlen($sequence) - 5; $i++) {
                if (str_contains($compact, substr($sequence, $i, 5))) {
                    return true;
                }
            }
        }

        if (preg_match('/^[A-Za-z]+[0-9]{1,4}[!@#$%^&*._-]?$/', $password) && strlen($password) <= 12) {
            $letters = strtolower(preg_replace('/[^A-Za-z]/', '', $password) ?? '');
            foreach (self::COMMON_WORDS as $word) {
                if ($letters === $word || str_starts_with($letters, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function compact(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? strtolower($value);
    }
}
