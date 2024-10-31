<?php
/**
 * SecurePay for FluentForms.
 *
 * @author  SecurePay Sdn Bhd
 * @license GPL-2.0+
 *
 * @see    https://securepay.my
 */
\defined('ABSPATH') || exit;

class SecurePaySettings
{
    public static function getSettings()
    {
        $defaults = [
            'is_active' => 'no',
            'payment_mode' => 'testmode',
            'live_token' => '',
            'live_checksum' => '',
            'live_uid' => '',
            'live_partner_uid' => '',
            'sandbox_token' => '',
            'sandbox_checksum' => '',
            'sandbox_uid' => '',
            'sandbox_partner_uid' => '',
            'banklist' => 'no',
            'banklogo' => 'no',
            'checkout_type' => '',
        ];

        return wp_parse_args(get_option('fluentform_payment_settings_securepay', []), $defaults);
    }

    public static function isLive()
    {
        $settings = self::getSettings();

        return 'livemode' === $settings['payment_mode'];
    }

    public static function isSandbox()
    {
        $settings = self::getSettings();

        return 'sandboxmode' === $settings['payment_mode'];
    }

    public static function isTestmode()
    {
        $settings = self::getSettings();

        return 'testmode' === $settings['payment_mode'];
    }

    public static function getCredentials()
    {
        $settings = self::getSettings();
        $mode = $settings['payment_mode'];

        if ('livemode' === $mode) {
            return [
                'token' => $settings['live_token'],
                'checksum' => $settings['live_checksum'],
                'uid' => $settings['live_uid'],
                'partner_uid' => $settings['live_partner_uid'],
                'url' => SECUREPAYFFM_ENDPOINT_LIVE,
            ];
        }

        if ('sandboxmode' === $mode) {
            return [
                'token' => $settings['sandbox_token'],
                'checksum' => $settings['sandbox_checksum'],
                'uid' => $settings['sandbox_uid'],
                'partner_uid' => $settings['sandbox_partner_uid'],
                'url' => SECUREPAYFFM_ENDPOINT_SANDBOX,
            ];
        }

        return [
            'token' => 'GFVnVXHzGEyfzzPk4kY3',
            'checksum' => '3faa7b27f17c3fb01d961c08da2b6816b667e568efb827544a52c62916d4771d',
            'uid' => '4a73a364-6548-4e17-9130-c6e9bffa3081',
            'partner_uid' => '',
            'url' => SECUREPAYFFM_ENDPOINT_SANDBOX,
        ];
    }
}
