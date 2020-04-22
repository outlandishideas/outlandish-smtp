<?php
/**
 * Plugin Name: Outlandish SMTP Plugin
 * Plugin URI: https://outlandish.com/
 * Description: Provides a number of different ways to set how WordPress sends emails
 * Version: 1.0.1
 * Author: Outlandish
 * Author URI: https://outlandish.com/
 * License: MIT License
 */

if (getenv('SES_SMTP_USER') && getenv('SES_SMTP_PASS')) {
    // Send using SES
    add_action('phpmailer_init', function($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host     = getenv('SES_SMTP_HOST') ?: 'email-smtp.eu-west-1.amazonaws.com';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port     = getenv('SES_SMTP_PORT') ?: '587';
        $phpmailer->Username = getenv('SES_SMTP_USER');
        $phpmailer->Password = getenv('SES_SMTP_PASS');
    });
} else if (getenv('MAILTRAP_USER') && getenv('MAILTRAP_PASS')) {
    // Use mailtrap
    add_action('phpmailer_init', function($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.mailtrap.io';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 2525;
        $phpmailer->Username = getenv('MAILTRAP_USER');
        $phpmailer->Password = getenv('MAILTRAP_PASS');
    });
} else if (getenv('SMTP_HOST')) {
    // Use SMTP_* env variables
    add_action('phpmailer_init', function($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = getenv('SMTP_HOST');
        $phpmailer->Port       = getenv('SMTP_PORT') ?: '25';
        $phpmailer->SMTPSecure = getenv('SMTP_SECURE') ?: '';
        if (getenv('SMTP_USER') && getenv('SMTP_PASS')) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = getenv('SMTP_USER');
            $phpmailer->Password = getenv('SMTP_PASS');
        }
    });
}

/**
 * Filters out the MIME-Version header. It is already added by PHPMailer, and mails with
 * duplicate headers are rejected by AWS SES.
 **/
add_filter('wp_mail', function ($args) {
    $headers = $args['headers'];
    if (empty($headers) || is_array($headers)) {
        return $args;
    }
    $headers = preg_split('#\r?\n#', $headers);
    $headers = array_filter($headers, function ($header) {
        $header = trim($header);
        if (empty($header)) {
            return false;
        }
        $key = strtolower(explode(':', $header)[0]);
        // PHPMailer adds this header; duplicate headers are rejected by AWS SES
        if ($key === 'mime-version') {
            return false;
        }
        return true;
    });
    $args['headers'] = $headers;
    return $args;
});

// changes what the wp_mail from address is if the constant is set
add_filter("wp_mail_from", function ( $original_email_address ) {
    $from_email = getenv('FROM_EMAIL_ADDRESS');
    return $from_email ?: $original_email_address;
});

// changes what the wp_mail from name is if the constant is set
add_filter("wp_mail_from_name", function ( $original_from_name ) {
    $from_name = getenv('FROM_EMAIL_NAME');
    return $from_name ?: $original_from_name;
});

add_action('wp_mail_failed', function ($wp_error) {
  /** @var WP_Error $wp_error */
  error_log('Mail Failed: ' . $wp_error->get_error_message());
});

