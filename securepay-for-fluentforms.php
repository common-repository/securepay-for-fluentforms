<?php
/**
 * SecurePay for FluentForms.
 *
 * @author  SecurePay Sdn Bhd
 * @license GPL-2.0+
 *
 * @see    https://securepay.my
 */

/*
 * @wordpress-plugin
 * Plugin Name:         SecurePay for FluentForms
 * Plugin URI:          https://securepay.my/?utm_source=wp-plugins-fluentforms&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Version:             1.0.5
 * Description:         Accept payment by using SecurePay. A Secure Marketplace Platform for Malaysian.
 * Author:              SecurePay Sdn Bhd
 * Author URI:          https://securepay.my/?utm_source=wp-plugins-fluentforms&utm_campaign=author-uri&utm_medium=wp-dash
 * Requires at least:   5.4
 * Requires PHP:        7.2
 * License:             GPL-2.0+
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:         securepayffm
 * Domain Path:         /languages
 */
if (!\defined('ABSPATH') || \defined('SECUREPAYFFM_FILE')) {
    exit;
}

\define('SECUREPAYFFM_VERSION', '1.0.5');
\define('SECUREPAYFFM_SLUG', 'securepay-for-fluentforms');
\define('SECUREPAYFFM_ENDPOINT_LIVE', 'https://securepay.my/api/v1/');
\define('SECUREPAYFFM_ENDPOINT_SANDBOX', 'https://sandbox.securepay.my/api/v1/');
\define('SECUREPAYFFM_ENDPOINT_PUBLIC_LIVE', 'https://securepay.my/api/public/v1/');
\define('SECUREPAYFFM_ENDPOINT_PUBLIC_SANDBOX', 'https://sandbox.securepay.my/api/public/v1/');

\define('SECUREPAYFFM_FILE', __FILE__);
\define('SECUREPAYFFM_HOOK', plugin_basename(SECUREPAYFFM_FILE));
\define('SECUREPAYFFM_PATH', realpath(plugin_dir_path(SECUREPAYFFM_FILE)).'/');
\define('SECUREPAYFFM_URL', trailingslashit(plugin_dir_url(SECUREPAYFFM_FILE)));

require __DIR__.'/includes/load.php';
SecurePayFluentForms::attach();
