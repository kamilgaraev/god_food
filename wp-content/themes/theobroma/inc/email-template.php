<?php

declare(strict_types=1);

/**
 * Shared branded email renderer for the Theobroma theme.
 *
 * WooCommerce uses its own templates for order mail, while contact forms and
 * account verification send mail directly with wp_mail(). Keeping the visual
 * shell here gives both paths the same logo, palette and spacing.
 */

function theobroma_email_logo_url(): string {
    $default = set_url_scheme(
        get_stylesheet_directory_uri() . '/assets/images/logo.png',
        'https'
    );
    $url = (string) apply_filters(
        'theobroma_email_logo_url',
        // PNG is intentionally used for mail clients with limited WebP support.
        $default
    );

    return set_url_scheme($url, 'https');
}

/** @param list<string> $paragraphs @param list<string> $details */
function theobroma_email_render_html(
    string $heading,
    array $details = array(),
    array $paragraphs = array(),
    ?string $buttonLabel = null,
    ?string $buttonUrl = null
): string {
    $detailRows = '';
    foreach ($details as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $parts = explode(':', $line, 2);
        $label = count($parts) === 2 ? trim($parts[0]) : '';
        $value = count($parts) === 2 ? trim($parts[1]) : $line;
        $detailRows .= '<tr>';
        if ($label !== '') {
            $detailRows .= '<td style="padding:13px 0;border-bottom:1px solid #dfd2c3;color:#756b63;font-size:13px;line-height:1.45;width:34%;vertical-align:top;">'
                . esc_html($label)
                . '</td>';
            $detailRows .= '<td style="padding:13px 0;border-bottom:1px solid #dfd2c3;color:#343434;font-size:14px;line-height:1.45;vertical-align:top;">'
                . nl2br(esc_html($value))
                . '</td>';
        } else {
            $detailRows .= '<td colspan="2" style="padding:13px 0;border-bottom:1px solid #dfd2c3;color:#343434;font-size:14px;line-height:1.45;vertical-align:top;">'
                . nl2br(esc_html($value))
                . '</td>';
        }
        $detailRows .= '</tr>';
    }

    $paragraphHtml = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '') {
            continue;
        }
        $paragraphHtml .= '<p style="margin:0 0 16px;color:#343434;font-size:15px;line-height:1.6;">'
            . nl2br(esc_html($paragraph))
            . '</p>';
    }

    $buttonHtml = '';
    if ($buttonLabel !== null && $buttonUrl !== null && $buttonLabel !== '' && $buttonUrl !== '') {
        $buttonHtml = '<p style="margin:28px 0 4px;text-align:center;">'
            . '<a href="' . esc_url(set_url_scheme($buttonUrl, 'https')) . '" style="display:inline-block;padding:14px 28px;border-radius:999px;background:#b0903d;color:#ffffff;font-size:14px;font-weight:600;line-height:1.2;text-decoration:none;">'
            . esc_html($buttonLabel)
            . '</a></p>';
    }

    $logoUrl = esc_url(theobroma_email_logo_url());
    $homeUrl = esc_url(set_url_scheme(home_url('/'), 'https'));

    return '<!doctype html><html lang="ru"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>'
        . esc_html($heading)
        . '</title></head><body style="margin:0;padding:0;background:#fbf8f3;color:#343434;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#fbf8f3;"><tr><td align="center" style="padding:28px 14px;">'
        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-collapse:collapse;">'
        . '<tr><td style="padding:24px 32px 18px;text-align:center;border-bottom:1px solid #dfd2c3;">'
        . '<a href="' . $homeUrl . '" style="display:inline-block;text-decoration:none;"><img src="' . $logoUrl . '" alt="Theobroma — Пища Богов" width="220" style="display:block;width:220px;max-width:100%;height:auto;border:0;"></a>'
        . '</td></tr>'
        . '<tr><td style="padding:34px 40px 38px;">'
        . '<p style="margin:0 0 12px;color:#b0903d;font-size:12px;letter-spacing:.08em;text-transform:uppercase;">Theobroma — Пища Богов</p>'
        . '<h1 style="margin:0 0 24px;color:#171511;font-family:Georgia,\'Times New Roman\',serif;font-size:32px;font-weight:400;line-height:1.15;">'
        . esc_html($heading)
        . '</h1>'
        . $paragraphHtml
        . ($detailRows !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:24px 0 0;">' . $detailRows . '</table>' : '')
        . $buttonHtml
        . '</td></tr>'
        . '<tr><td style="padding:18px 32px 22px;border-top:1px solid #dfd2c3;text-align:center;color:#756b63;font-size:12px;line-height:1.5;">Theobroma — Пища Богов<br><a href="' . $homeUrl . '" style="color:#b0903d;text-decoration:none;">theobroma.one</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** @param list<string> $details @param list<string> $paragraphs */
function theobroma_send_branded_email(
    string $recipient,
    string $subject,
    string $heading,
    array $details = array(),
    array $paragraphs = array(),
    ?string $buttonLabel = null,
    ?string $buttonUrl = null
): bool {
    $html = theobroma_email_render_html($heading, $details, $paragraphs, $buttonLabel, $buttonUrl);

    return wp_mail(
        $recipient,
        $subject,
        $html,
        array('Content-Type: text/html; charset=UTF-8')
    );
}

/** Keep WooCommerce's order emails on the same palette as the storefront. */
function theobroma_email_styles(string $styles, mixed $email = null): string {
    $styles .= <<<'CSS'

/* Theobroma email skin. Keep one separator between line items and totals. */
body, #outer_wrapper {
    background-color: #fbf8f3 !important;
}
#wrapper {
    max-width: 640px !important;
    padding: 28px 0 !important;
}
#inner_wrapper, #template_container, #body_content, #body_content_inner {
    background-color: #ffffff !important;
}
#template_container {
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}
#template_header_image {
    padding: 24px 32px 18px !important;
    text-align: center !important;
    border-bottom: 1px solid #dfd2c3 !important;
}
#template_header_image p {
    margin: 0 !important;
    text-align: center !important;
}
#template_header_image img {
    width: 220px !important;
    max-width: 100% !important;
    height: auto !important;
    margin: 0 auto !important;
}
#template_header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #dfd2c3 !important;
    border-radius: 0 !important;
}
#template_header h1, #template_header h1 a {
    color: #171511 !important;
    font-family: Georgia, 'Times New Roman', serif !important;
    font-size: 30px !important;
    font-weight: 400 !important;
    line-height: 1.2 !important;
}
#header_wrapper {
    padding: 28px 32px !important;
}
#body_content table td {
    padding: 20px 32px 32px !important;
}
#body_content_inner, #body_content .td, #body_content p {
    color: #343434 !important;
    font-family: Arial, Helvetica, sans-serif !important;
}
#body_content a, #body_content_inner a {
    color: #b0903d !important;
}
#body_content .td {
    border: 0 !important;
}
#body_content .email-order-details tbody tr td,
#body_content .email-order-details .order-totals-last td,
#body_content .email-order-details .order-totals-last th {
    border-bottom: 0 !important;
}
#body_content .email-order-details tbody tr:last-child td {
    border-bottom: 1px solid #dfd2c3 !important;
    padding-bottom: 20px !important;
}
#body_content .email-order-details tfoot tr:first-child td,
#body_content .email-order-details tfoot tr:first-child th {
    border-top: 0 !important;
    padding-top: 20px !important;
}
#body_content .email-order-details .order-totals-total td,
#body_content .email-order-details .order-totals-total th {
    color: #171511 !important;
}
#body_content .email-order-details .order-totals-total td {
    font-size: 18px !important;
}
#template_footer #credit {
    border-top: 1px solid #dfd2c3 !important;
    color: #756b63 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    padding: 20px 32px !important;
}
#template_footer #credit a,
.button, .button a, a.button {
    color: #ffffff !important;
}
.button, a.button {
    background-color: #b0903d !important;
    border-color: #b0903d !important;
    border-radius: 999px !important;
}
CSS;

    return $styles;
}

function theobroma_email_header_image(): string {
    return theobroma_email_logo_url();
}

function theobroma_email_footer_text(string $text = '', mixed $email = null): string {
    return trim($text) !== '' ? $text : 'Theobroma — Пища Богов';
}

add_filter('woocommerce_email_styles', 'theobroma_email_styles', 20, 2);
add_filter('pre_option_woocommerce_email_header_image', 'theobroma_email_header_image', 20);
add_filter('option_woocommerce_email_header_image', 'theobroma_email_header_image', 20);
add_filter('woocommerce_email_footer_text', 'theobroma_email_footer_text', 20, 2);
