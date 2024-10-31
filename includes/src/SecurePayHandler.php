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

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;

class SecurePayHandler extends BasePaymentMethod
{
    public function __construct()
    {
        parent::__construct('securepay');
    }

    public function init()
    {
        add_filter('fluentform_payment_method_settings_validation_'.$this->key, [$this, 'validateSettings'], 10, 2);

        if (!$this->isEnabled()) {
            return;
        }

        add_filter('fluentform_transaction_data_'.$this->key, [$this, 'modifyTransaction'], 10, 1);

        add_filter('fluentformpro_available_payment_methods', [$this, 'pushPaymentMethodToForm']);

        ( new SecurePayProcessor() )->init();
    }

    public function pushPaymentMethodToForm($methods)
    {
        $methods[$this->key] = [
            'title' => esc_html__('SecurePay', 'securepayffm'),
            'enabled' => 'yes',
            'method_value' => $this->key,
            'settings' => [
                'option_label' => [
                    'type' => 'text',
                    'template' => 'inputText',
                    'value' => 'Pay with SecurePay',
                    'label' => 'Method Label',
                ],
            ],
        ];

        return $methods;
    }

    public function validateSettings($errors, $settings)
    {
        delete_transient(SECUREPAYFFM_SLUG.'_banklist');

        if ('no' == ArrayHelper::get($settings, 'is_active')) {
            return [];
        }

        $mode = ArrayHelper::get($settings, 'payment_mode');
        switch ($mode) {
            case 'sandboxmode':
                if (!ArrayHelper::get($settings, 'sandbox_token')) {
                    $errors['sandbox_token'] = esc_html__('Please provide Sandbox Token', 'securepayffm');
                }

                if (!ArrayHelper::get($settings, 'sandbox_checksum')) {
                    $errors['sandbox_checksum'] = esc_html__('Please provide Sandbox Checksum Token', 'securepayffm');
                }

                if (!ArrayHelper::get($settings, 'sandbox_uid')) {
                    $errors['sandbox_uid'] = esc_html__('Please provide Sandbox UID', 'securepayffm');
                }

                break;
            case 'livemode':
                if (!ArrayHelper::get($settings, 'live_token')) {
                    $errors['live_token'] = esc_html__('Please provide Live Token', 'securepayffm');
                }

                if (!ArrayHelper::get($settings, 'live_checksum')) {
                    $errors['live_checksum'] = esc_html__('Please provide Live Checksum Token', 'securepayffm');
                }

                if (!ArrayHelper::get($settings, 'live_uid')) {
                    $errors['live_uid'] = esc_html__('Please provide Live UID', 'securepayffm');
                }

                break;
            case 'testmode':
                break;
            default:
                $errors['payment_mode'] = esc_html__('Please select Payment Mode', 'securepayffm');
        }

        return $errors;
    }

    public function modifyTransaction($transaction)
    {
        return $transaction;
    }

    public function isEnabled()
    {
        $settings = $this->getGlobalSettings();

        return 'yes' === $settings['is_active'];
    }

    public function getGlobalFields()
    {
        return [
             'label' => 'SecurePay',
             'fields' => [
                 [
                     'settings_key' => 'is_active',
                     'type' => 'yes-no-checkbox',
                     'label' => 'Status',
                     'checkbox_label' => esc_html__('Enable SecurePay Payment Method', 'securepayffm'),
                 ],
                 [
                     'settings_key' => 'payment_mode',
                     'type' => 'input-radio',
                     'label' => 'Payment Mode',
                     'options' => [
                         'testmode' => 'Test Mode',
                         'sandboxmode' => 'Sandbox Mode',
                         'livemode' => 'Live Mode',
                     ],
                     'info_help' => esc_html__('Select the payment mode. For testing purposes you should select Test Mode to test without credentials, or Sandbox Mode, otherwise select Live mode.', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'banklist',
                     'type' => 'yes-no-checkbox',
                     'label' => esc_html__('Show Bank List', 'securepayffm'),
                     'checkbox_label' => esc_html__('Enable', 'securepayffm'),
                 ],
                 [
                     'settings_key' => 'banklogo',
                     'type' => 'yes-no-checkbox',
                     'label' => esc_html__('Use Supported Bank Logo', 'securepayffm'),
                     'checkbox_label' => esc_html__('Enable', 'securepayffm'),
                 ],
                 [
                     'type' => 'html',
                     'html' => '<h2>'.esc_html__('Your Live API Credentials', 'securepayffm').'</h2>',
                 ],
                 [
                     'settings_key' => 'live_token',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Live Token', 'securepayffm'),
                     'label' => esc_html__('Live Token', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Live Token', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'live_checksum',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Live Checksum Token', 'securepayffm'),
                     'label' => esc_html__('Live Checksum Token', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Live Checksum Token', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'live_uid',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Live UID', 'securepayffm'),
                     'label' => esc_html__('Live UID', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Live UID', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'live_partner_uid',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Live Partner UID', 'securepayffm'),
                     'label' => esc_html__('Live Partner UID (Optional)', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Live Partner UID', 'securepayffm'),
                     'check_status' => 'no',
                 ],
                 [
                     'type' => 'html',
                     'html' => '<h2>'.esc_html__('Your Sandbox API Credentials', 'securepayffm').'</h2>',
                 ],
                 [
                     'settings_key' => 'sandbox_token',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Sandbox Token', 'securepayffm'),
                     'label' => esc_html__('Sandbox Token', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Sandbox Token', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'sandbox_checksum',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Sandbox Checksum Token', 'securepayffm'),
                     'label' => esc_html__('Sandbox Checksum Token', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Sandbox Checksum Token', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'sandbox_uid',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Sandbox UID', 'securepayffm'),
                     'label' => esc_html__('Sandbox UID', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Sandbox UID', 'securepayffm'),
                     'check_status' => 'yes',
                 ],
                 [
                     'settings_key' => 'sandbox_partner_uid',
                     'type' => 'input-text',
                     'data_type' => 'text',
                     'placeholder' => esc_html__('Sandbox Partner UID', 'securepayffm'),
                     'label' => esc_html__('Sandbox Partner UID (Optional)', 'securepayffm'),
                     'inline_help' => esc_html__('Provide your SecurePay Sandbox Partner UID', 'securepayffm'),
                     'check_status' => 'no',
                 ],
                 [
                     'type' => 'html',
                     'html' => '<p><a target="_blank" rel="noopener" href="https://docs.securepay.my/api">Please read the documentation</a> to learn how to setup <b>SecurePay Payment </b> Gateway. </p>',
                 ],
             ],
         ];
    }

    public function getGlobalSettings()
    {
        return SecurePaySettings::getSettings();
    }
}
