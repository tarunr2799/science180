<?php
// File: includes/class-security.php
if (!defined('ABSPATH')) {
    exit;
}

class AdvNews_Security
{
    /**
     * Sanitize email address
     */
    public static function sanitize_email($email)
    {
        $email = trim($email);
        $email = strtolower($email);
        return sanitize_email($email);
    }

    /**
     * Sanitize text field
     */
    public static function sanitize_text($text)
    {
        return sanitize_text_field($text);
    }

    /**
     * Sanitize HTML content (allow safe HTML for emails)
     */
    public static function sanitize_html($html)
    {
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
                'rel' => array()
            ),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array(),
            'div' => array(
                'class' => array(),
                'style' => array()
            ),
            'span' => array(
                'class' => array(),
                'style' => array()
            ),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'table' => array(
                'border' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'style' => array()
            ),
            'tr' => array(),
            'td' => array(
                'colspan' => array(),
                'rowspan' => array(),
                'style' => array()
            ),
            'th' => array(),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array()
            ),
            'b' => array(),
            'i' => array(),
            'u' => array(),
            'strike' => array(),
            'hr' => array(),
            'blockquote' => array(),
            'code' => array(),
            'pre' => array(),
        );
        return wp_kses($html, $allowed_html);
    }

    /**
     * Sanitize array of data
     */
    public static function sanitize_array($data)
    {
        if (!is_array($data)) {
            return self::sanitize_text($data);
        }
        $sanitized = array();
        foreach ($data as $key => $value) {
            $key = self::sanitize_text($key);
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_array($value);
            } elseif (is_string($value)) {
                // Check if it's HTML content
                if (strpos($key, 'content') !== false || strpos($key, 'html') !== false) {
                    $sanitized[$key] = self::sanitize_html($value);
                } else {
                    $sanitized[$key] = self::sanitize_text($value);
                }
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Validate email address
     */
    public static function validate_email($email)
    {
        if (!is_email($email)) {
            return false;
        }
        // Additional validation
        $email = self::sanitize_email($email);
        // Check for disposable email domains (optional)
        $disposable_domains = array(
            'tempmail.com',
            'throwawaymail.com',
            // Add more as needed
        );
        $domain = substr(strrchr($email, "@"), 1);
        if (in_array($domain, $disposable_domains)) {
            return false;
        }
        return $email;
    }

    /**
     * Generate secure hash
     */
    public static function generate_hash($data, $length = 32)
    {
        $salt = wp_salt('secure_auth');
        return substr(hash_hmac('sha256', $data, $salt), 0, $length);
    }

    /**
     * Verify nonce for form submissions
     */
    public static function verify_nonce($action, $nonce_field = '_wpnonce')
    {
        if (!isset($_REQUEST[$nonce_field])) {
            return false;
        }
        return wp_verify_nonce($_REQUEST[$nonce_field], $action);
    }

    /**
     * Create nonce field
     */
    public static function create_nonce_field($action)
    {
        return wp_nonce_field($action, '_wpnonce', true, true);
    }

    /**
     * Check user capability
     */
    public static function check_capability($capability = 'manage_options')
    {
        if (!current_user_can($capability)) {
            wp_die(
                __('You do not have sufficient permissions to access this page.', 'advnews-manager'),
                __('Permission Denied', 'advnews-manager'),
                array('response' => 403)
            );
        }
    }

    /**
     * Log security event
     */
    public static function log_event($event, $user_id = null, $ip_address = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }
        if (is_null($ip_address)) {
            $ip_address = self::get_client_ip();
        }
        // Log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AdvNews Security] Event: %s | User ID: %d | IP: %s',
                $event,
                $user_id,
                $ip_address
            ));
        }
    }

    /**
     * Get client IP address
     */
    public static function get_client_ip()
    {
        $candidates = array();
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $value = (string) $_SERVER[$header];
            if ($header === 'HTTP_FORWARDED') {
                preg_match_all('/for="?([^;,\"]+)/i', $value, $matches);
                $parts = $matches[1] ?? array();
            } else {
                $parts = explode(',', $value);
            }

            foreach ($parts as $part) {
                $ip = self::normalize_ip_candidate($part);
                if ($ip !== '') {
                    $candidates[] = $ip;
                }
            }
        }

        foreach ($candidates as $ip_address) {
            if (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip_address;
            }
        }

        foreach ($candidates as $ip_address) {
            if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
                return $ip_address;
            }
        }

        return '0.0.0.0';
    }

    private static function normalize_ip_candidate($value)
    {
        $value = trim((string) $value);
        $value = trim($value, "\"'[] ");
        $value = preg_replace('/^for=/i', '', $value);

        if (strpos($value, ']') !== false) {
            $value = trim(strstr($value, ']', true), '[]');
        } elseif (substr_count($value, ':') === 1 && strpos($value, '.') !== false) {
            $value = preg_replace('/:\d+$/', '', $value);
        }

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : '';
    }

    /**
     * Sanitize CSV data
     */
    public static function sanitize_csv_data($data)
    {
        $sanitized = array();
        foreach ($data as $row) {
            $sanitized_row = array();
            foreach ($row as $key => $value) {
                $key = self::sanitize_text($key);
                $value = self::sanitize_text($value);
                $sanitized_row[$key] = $value;
            }
            $sanitized[] = $sanitized_row;
        }
        return $sanitized;
    }

    /**
     * Validate CSV or Excel file upload
     */
    public static function validate_csv_upload($file)
    {
        $allowed_extensions = array('csv', 'xlsx');
        $allowed_types = array(
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip',
            'application/octet-stream'
        );
        $max_size = 10 * 1024 * 1024; // 10MB
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', __('Invalid file upload.', 'advnews-manager'));
        }
        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large',
                sprintf(__('File is too large. Maximum size is %s.', 'advnews-manager'), size_format($max_size)));
        }
        // Check file type and extension. Some hosts report .xlsx as a generic zip,
        // so the extension is the stable signal while MIME remains a useful guard.
        $file_info = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $type = isset($file_info['type']) ? $file_info['type'] : '';
        if (!in_array($extension, $allowed_extensions, true) || ($type && !in_array($type, $allowed_types, true))) {
            return new WP_Error('invalid_file_type',
                __('Invalid file type. Please upload a CSV or Excel (.xlsx) file.', 'advnews-manager'));
        }
        // Check for malware (basic check)
        if (self::contains_malware($file['tmp_name'])) {
            return new WP_Error('malware_detected',
                __('Malware detected in uploaded file.', 'advnews-manager'));
        }
        return true;
    }

    /**
     * Basic malware detection
     */
    private static function contains_malware($file_path)
    {
        $content = file_get_contents($file_path);
        // Check for suspicious patterns
        $suspicious_patterns = array(
            '<?php',
            'eval(',
            'base64_decode(',
            'gzinflate(',
            'exec(',
            'system(',
            'shell_exec(',
            'passthru(',
            'proc_open(',
            'popen(',
        );
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get persistent encryption key
     * FIXED: Ensures key is properly stored and retrieved with autoload
     */
    private static function get_encryption_key()
    {
        // Try to get from options with autoload
        $key = get_option('advnews_encryption_key', false);

        // If key doesn't exist or is empty, generate a new one
        if (empty($key)) {
            $key = wp_generate_password(64, true, true);
            // Store with autoload = yes to ensure it's always available
            update_option('advnews_encryption_key', $key, 'yes');
            // Log key generation for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] New encryption key generated and stored');
            }
        }
        return $key;
    }

    /**
     * Encrypt sensitive data
     * FIXED: Uses random IV stored with encrypted data
     */
    public static function encrypt($data)
    {
        if (empty($data)) {
            return $data;
        }

        // Check if already encrypted (prevent double encryption)
        if (self::is_encrypted($data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Data already encrypted, skipping');
            }
            return $data;
        }

        $method = 'AES-256-CBC';
        $key = hash('sha256', self::get_encryption_key(), true);

        // Generate a random IV for each encryption (16 bytes for AES-256-CBC)
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));

        if ($iv === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Failed to generate random IV');
            }
            return $data;
        }

        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Encryption failed: ' . openssl_error_string());
            }
            return $data; // Return original if encryption fails
        }

        // Combine IV and encrypted data, then base64 encode
        // Format: iv:encrypted_data
        $combined = $iv . $encrypted;
        return base64_encode($combined);
    }

    /**
     * Decrypt data
     * FIXED: Extracts IV from encrypted data and properly decrypts
     */
    public static function decrypt($data)
    {
        if (empty($data)) {
            return $data;
        }

        // Check if data is actually encrypted
        if (!self::is_encrypted($data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Data is not encrypted, returning as-is');
            }
            return $data;
        }

        $method = 'AES-256-CBC';
        $key = hash('sha256', self::get_encryption_key(), true);

        // Decode base64
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Base64 decode failed');
            }
            return $data;
        }

        // Extract IV (first 16 bytes) and encrypted data (rest)
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);

        if (strlen($iv) !== $iv_length) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Invalid IV length: ' . strlen($iv) . ' (expected: ' . $iv_length . ')');
            }
            return $data;
        }

        if (empty($encrypted)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] No encrypted data found');
            }
            return $data;
        }

        $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

        // If decryption fails, return original data (might be unencrypted or corrupted)
        if ($decrypted === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Security] Decryption failed: ' . openssl_error_string());
                error_log('[AdvNews Security] Data length: ' . strlen($data));
                error_log('[AdvNews Security] Encrypted data length: ' . strlen($encrypted));
                error_log('[AdvNews Security] This usually means the encryption key changed (fresh install)');
                error_log('[AdvNews Security] SOLUTION: Re-enter your SMTP password in settings');
            }
            return $data;
        }

        return $decrypted;
    }

    /**
     * Hash email for storage (for privacy)
     */
    public static function hash_email($email)
    {
        $email = self::sanitize_email($email);
        return hash_hmac('sha256', $email, wp_salt('secure_auth'));
    }

    /**
     * Check if data is encrypted
     * Helper method to detect if data needs re-encryption
     */
    public static function is_encrypted($data)
    {
        if (empty($data)) {
            return false;
        }

        // Encrypted data is base64 encoded and typically longer than original
        // Minimum length check (IV 16 bytes + at least some encrypted data)
        if (strlen($data) < 44) {
            return false;
        }

        // Check if it's valid base64
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }

        // Check if decoded data looks like encrypted data (binary with IV)
        // IV is 16 bytes, so total should be at least 17 bytes
        if (strlen($decoded) < 17) {
            return false;
        }

        // Check for binary content (encrypted data contains non-printable characters)
        $printable_count = 0;
        $total_count = strlen($decoded);
        for ($i = 0; $i < $total_count; $i++) {
            $char = ord($decoded[$i]);
            // Count printable ASCII characters (32-126)
            if ($char >= 32 && $char <= 126) {
                $printable_count++;
            }
        }

        // Encrypted data should have less than 50% printable characters
        $printable_ratio = $printable_count / $total_count;
        return $printable_ratio < 0.5;
    }

    /**
     * Reset encryption key
     * Use this when migrating to a new server or after fresh install
     */
    public static function reset_encryption_key()
    {
        delete_option('advnews_encryption_key');
        $new_key = wp_generate_password(64, true, true);
        update_option('advnews_encryption_key', $new_key, 'yes');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Security] Encryption key reset. All encrypted data must be re-entered.');
        }
        return $new_key;
    }

    /**
     * Validate encrypted data
     * Check if encrypted data can be decrypted with current key
     */
    public static function validate_encrypted_data($encrypted_data)
    {
        $decrypted = self::decrypt($encrypted_data);
        // If decrypted data looks reasonable (not garbage), it's valid
        if ($decrypted && strlen($decrypted) > 0 && strlen($decrypted) < 500) {
            return true;
        }
        return false;
    }
}
