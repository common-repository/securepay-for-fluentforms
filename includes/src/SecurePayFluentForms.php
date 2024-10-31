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

final class SecurePayFluentForms
{
    private static function register_locale()
    {
        add_action(
            'plugins_loaded',
            function () {
                load_plugin_textdomain(
                    'securepayffm',
                    false,
                    SECUREPAYFFM_PATH.'languages/'
                );
            },
            0
        );
    }

    public static function register_admin_hooks()
    {
        add_action(
            'plugins_loaded',
            function () {
                if (current_user_can(apply_filters('capability', 'manage_options'))) {
                    add_action('all_admin_notices', [__CLASS__, 'callback_compatibility'], \PHP_INT_MAX);
                }
            }
        );

        if (self::is_ffforms_activated()) {
            add_action('fluentform_loaded', function () {
                require_once __DIR__.'/SecurePaySettings.php';
                require_once __DIR__.'/SecurePayProcessor.php';
                require_once __DIR__.'/SecurePayHandler.php';
                (new SecurePayHandler())->init();
            });
        }
    }

    private static function is_ffforms_activated()
    {
        return \defined('FLUENTFORMPRO_VERSION') && class_exists('FluentFormPro', false);
    }

    private static function register_autoupdates()
    {
        if (!\defined('SECUREPAYFFM_AUTOUPDATE_DISABLED') || !SECUREPAYFFM_AUTOUPDATE_DISABLED) {
            add_filter(
                'auto_update_plugin',
                function ($update, $item) {
                    if (SECUREPAYFFM_SLUG === $item->slug) {
                        return true;
                    }

                    return $update;
                },
                \PHP_INT_MAX,
                2
            );
        }
    }

    public static function callback_compatibility()
    {
        if (!self::is_ffforms_activated()) {
            $html = '<div id="securepayffm-notice" class="notice notice-error is-dismissible">';
            $html .= '<p>'.esc_html__('SecurePay for FluentForms require Fluent Forms Pro plugin. Please install and activate.', 'securepayffm').'</p>';
            $html .= '</div>';
            echo wp_kses_post($html);

            return;
        }

        if (\defined('FLUENTFORMPRO_VERSION') && version_compare(FLUENTFORMPRO_VERSION, '4.2.0', '<')) {
            $html = '<div id="securepayffm-notice" class="notice notice-error is-dismissible">';
            $html .= '<p>'.esc_html__('SecurePay for FluentForms require Fluent Forms Pro plugin version 4.2.0 and later.', 'securepayffm').'</p>';
            $html .= '</div>';
            echo wp_kses_post($html);

            return;
        }
    }

    public static function activate()
    {
        return true;
    }

    public static function deactivate()
    {
        return true;
    }

    public static function uninstall()
    {
        return true;
    }

    public static function register_plugin_hooks()
    {
        register_activation_hook(SECUREPAYFFM_HOOK, [__CLASS__, 'activate']);
        register_deactivation_hook(SECUREPAYFFM_HOOK, [__CLASS__, 'deactivate']);
        register_uninstall_hook(SECUREPAYFFM_HOOK, [__CLASS__, 'uninstall']);
    }

    public static function attach()
    {
        self::register_locale();
        self::register_plugin_hooks();
        self::register_admin_hooks();
        self::register_autoupdates();
    }
}
