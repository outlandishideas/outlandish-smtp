<?php
/**
 * Plugin Name: Outlandish SMTP Plugin
 * Plugin URI: https://outlandish.com/
 * Description: Provides a number of different ways to set how WordPress sends emails
 * Version: 1.1.2
 * Author: Outlandish
 * Author URI: https://outlandish.com/
 * License: MIT License
 */

function getEnvValue($name)
{
    return $_ENV[$name] ?? getenv($name);
}

if (getEnvValue('SES_SMTP_USER') && getEnvValue('SES_SMTP_PASS')) {
    // Send using SES

    $user = getEnvValue('SES_SMTP_USER');
    $pass = getEnvValue('SES_SMTP_PASS');
    $port = getEnvValue('SES_SMTP_PORT') ?: '587';
    $host = getEnvValue('SES_SMTP_HOST') ?: 'email-smtp.eu-west-1.amazonaws.com';

    add_action('phpmailer_init', function ($phpmailer) use ($user, $pass, $host, $port) {
        $phpmailer->isSMTP();
        $phpmailer->Host     = $host;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port     = $port;
        $phpmailer->Username = $user;
        $phpmailer->Password = $pass;
    });
} elseif (getEnvValue('MAILTRAP_USER') && getEnvValue('MAILTRAP_PASS')) {
    // Use mailtrap

    $user = getEnvValue('MAILTRAP_USER');
    $pass = getEnvValue('MAILTRAP_PASS');

    add_action('phpmailer_init', function ($phpmailer) use ($user, $pass) {
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.mailtrap.io';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 2525;
        $phpmailer->Username = $user;
        $phpmailer->Password = $pass;
    });
} elseif (getEnvValue('SMTP_HOST')) {
    // Use SMTP_* env variables

    $host = getEnvValue('SMTP_HOST');
    $port = getEnvValue('SMTP_PORT') ?: '25';
    $secure = getEnvValue('SMTP_SECURE') ?: '';
    $user = getEnvValue('SMTP_USER');
    $pass = getEnvValue('SMTP_PASS');

    add_action('phpmailer_init', function ($phpmailer) use ($user, $pass, $host, $port, $secure) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = $port;
        $phpmailer->SMTPSecure = $secure;
        if ($user && $pass) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $user;
            $phpmailer->Password = $pass;
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
add_filter("wp_mail_from", function ($original_email_address) {
    $from_email = getEnvValue('FROM_EMAIL_ADDRESS');
    return $from_email ?: $original_email_address;
});

// changes what the wp_mail from name is if the constant is set
add_filter("wp_mail_from_name", function ($original_from_name) {
    $from_name = getEnvValue('FROM_EMAIL_NAME');
    return $from_name ?: $original_from_name;
});

add_action('wp_mail_failed', function ($wp_error) {
    /** @var WP_Error $wp_error */
    error_log('Mail Failed: ' . $wp_error->get_error_message());
});
