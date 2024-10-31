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

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;

class SecurePayProcessor extends BaseProcessor
{
    public $method = 'securepay';

    protected $form;

    public function init()
    {
        add_action(
            'fluentform_process_payment_'.$this->method,
            [$this, 'handlePaymentAction'],
            10,
            6
        );

        add_filter(
            'fluentform_submitted_payment_items_'.$this->method,
            [$this, 'validateSubmittedItems'],
            10,
            4
        );

        add_action(
            'fluentform_scripts_registered',
            [$this, 'securepay_scripts'],
            10,
            3
        );

        add_filter(
            'fluentform_rendering_field_html_payment_method',
            function ($html, $data, $form) {
                if (!\defined('SPFFMBANKLISTRUN')) {
                    \define('SPFFMBANKLISTRUN', true);
                }

                return $this->banklist_output($html, $data, $form, true);
            },
            10,
            3
        );

        if (!\defined('SPFFMBANKLISTRUN')) {
            add_filter(
                'fluentform_rendering_field_html_button',
                function ($html, $data, $form) {
                    return $this->banklist_output($html, $data, $form, false);
                },
                10,
                3
            );
        }

        if (!empty($_GET['fluentform_payment']) && !empty($_GET['payment_method'])) {
            add_action(
                'wp',
                function () {
                    $data = $this->sanitize_request($_REQUEST);
                    $type = sanitize_text_field($_GET['fluentform_payment']);
                    $this->validateFrameLessPage($data);
                    $paymentMethod = sanitize_text_field($_GET['payment_method']);
                    $this->handleSessionRedirectBack($data);
                }
            );
        }

        // fix response from api
        add_action(
            'init',
            function () {
                if (!empty($_GET['fluentform_payment']) && false !== strpos($_SERVER['REQUEST_URI'], '&amp;spreturn=timeout')) {
                    $url = str_replace('&amp;', '&', $_SERVER['REQUEST_URI']);
                    wp_redirect(site_url($url));
                    exit;
                }
            }
        );
    }

    public function handlePaymentAction($submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable)
    {
        $this->setSubmissionId($submissionId);
        $this->form = $form;
        $submission = $this->getSubmission();

        $uniqueHash = md5($submission->id.'-'.$form->id.'-'.time().'-'.mt_rand(100, 999));

        $transactionId = $this->insertTransaction(
            [
                'transaction_type' => 'onetime',
                'transaction_hash' => $uniqueHash,
                'payment_total' => $this->getAmountTotal(),
                'status' => 'pending',
                'currency' => PaymentHelper::getFormCurrency($form->id),
                'payment_mode' => $this->getPaymentMode(),
            ]
        );

        $transaction = $this->getTransaction($transactionId);
        $this->handleRedirect($transaction, $submission, $form, $methodSettings);
    }

    // reference: fluentformpro/src/Payments/PaymentHandler.php -> PaymentHandler::validateFrameLessPage
    private function validateFrameLessPage($data)
    {
        // We should verify the transaction hash from the URL
        $transactionHash = sanitize_text_field(ArrayHelper::get($data, 'transaction_hash'));
        $submissionId = (int) ArrayHelper::get($data, 'fluentform_payment');
        if (!$submissionId) {
            exit('Validation Failed');
        }

        if ($transactionHash) {
            $transaction = wpFluent()->table('fluentform_transactions')
                ->where('submission_id', $submissionId)
                ->where('transaction_hash', $transactionHash)
                ->first();
            if ($transaction) {
                return true;
            }

            exit('Transaction hash is invalid');
        }

        $uid = sanitize_text_field(ArrayHelper::get($data, 'entry_uid'));
        if (!$uid) {
            exit('Validation Failed');
        }

        $originalUid = Helper::getSubmissionMeta($submissionId, '_entry_uid_hash');

        if ($originalUid != $uid) {
            exit('Transaction UID is invalid');
        }

        return true;
    }

    private function calculate_sign($checksum, $a, $b, $c, $d, $e, $f, $g, $h, $i)
    {
        $str = $a.'|'.$b.'|'.$c.'|'.$d.'|'.$e.'|'.$f.'|'.$g.'|'.$h.'|'.$i;

        return hash_hmac('sha256', $str, $checksum);
    }

    private function sanitize_request($request)
    {
        $ret = $request;
        if (!empty($request) && \is_array($request)) {
            foreach ($request as $k => $v) {
                $ret[$k] = sanitize_text_field($request[$k]);
            }
        }

        return $ret;
    }

    private function sanitize_response()
    {
        $params = [
             'amount',
             'bank',
             'buyer_email',
             'buyer_name',
             'buyer_phone',
             'checksum',
             'client_ip',
             'created_at',
             'created_at_unixtime',
             'currency',
             'exchange_number',
             'fpx_status',
             'fpx_status_message',
             'fpx_transaction_id',
             'fpx_transaction_time',
             'id',
             'interface_name',
             'interface_uid',
             'merchant_reference_number',
             'name',
             'order_number',
             'payment_id',
             'payment_method',
             'payment_status',
             'receipt_url',
             'retry_url',
             'source',
             'status_url',
             'transaction_amount',
             'transaction_amount_received',
             'uid',
             'spreturn',
         ];

        $response_params = [];
        if (isset($_REQUEST)) {
            foreach ($params as $k) {
                if (isset($_REQUEST[$k])) {
                    $response_params[$k] = sanitize_text_field($_REQUEST[$k]);
                }
            }
        }

        return $response_params;
    }

    private function response_status($response_params)
    {
        if ((isset($response_params['payment_status']) && 'true' === $response_params['payment_status']) || (isset($response_params['fpx_status']) && 'true' === $response_params['fpx_status'])) {
            return true;
        }

        return false;
    }

    private function is_response_callback($response_params)
    {
        if (isset($response_params['fpx_status'])) {
            return true;
        }

        return false;
    }

    private function viewback($url = null)
    {
        if (empty($url)) {
            $url = site_url('/');
            $name = get_bloginfo('name');
        } else {
            $name = $url;
        }

        return '<hr>Back to: <a href="'.$url.'">'.$name.'</a>';
    }

    public function handleRedirect($transaction, $submission, $form, $methodSettings)
    {
        $credentials = SecurePaySettings::getCredentials();
        $settings = SecurePaySettings::getSettings();

        $redirect_url = add_query_arg(
            [
                'fluentform_payment' => $submission->id,
                'payment_method' => $this->method,
                'transaction_hash' => $transaction->transaction_hash,
                'type' => 'success',
            ],
            site_url('/')
        );

        $callback_url = $redirect_url;
        $cancel_url = add_query_arg(['spreturn' => 'cancel'], $redirect_url);
        $timeout_url = add_query_arg(['spreturn' => 'timeout'], $redirect_url);

        $customer_email = PaymentHelper::getCustomerEmail($submission);

        $customer_name = '';
        if (!empty($submission->response['names'])) {
            foreach ($submission->response['names'] as $n) {
                $customer_name .= $n.' ';
            }
            $customer_name = trim($customer_name);
        }

        if (empty($customer_name)) {
            $customer_name = PaymentHelper::getCustomerName($submission);
        }

        $customer_phone = '';
        if (!empty($submission->response['phone'])) {
            $customer_phone = $submission->response['phone'];
        }

        $description = $form->title;
        $amount = PaymentHelper::floatToString((float) round($transaction->payment_total / 100, 2));
        $securepay_token = $credentials['token'];
        $securepay_uid = $credentials['uid'];
        $securepay_checksum = $credentials['checksum'];
        $securepay_partner_uid = $credentials['partner_uid'];
        $securepay_payment_url = $credentials['url'];
        $entry_id = $submission->id;
        $securepay_sign = $this->calculate_sign($securepay_checksum, $customer_email, $customer_name, $customer_phone, $redirect_url, $entry_id, $description, $redirect_url, $amount, $securepay_uid);
        $buyer_bank_code = !empty($submission->response['buyer_bank_code']) ? esc_attr($submission->response['buyer_bank_code']) : false;

        $securepay_args['order_number'] = esc_attr($entry_id);
        $securepay_args['buyer_name'] = esc_attr($customer_name);
        $securepay_args['buyer_email'] = esc_attr($customer_email);
        $securepay_args['buyer_phone'] = esc_attr($customer_phone);
        $securepay_args['product_description'] = esc_attr($description);
        $securepay_args['transaction_amount'] = esc_attr($amount);
        $securepay_args['redirect_url'] = wp_sanitize_redirect($redirect_url);
        $securepay_args['callback_url'] = wp_sanitize_redirect($callback_url);
        $securepay_args['cancel_url'] = wp_sanitize_redirect($cancel_url);
        $securepay_args['timeout_url'] = wp_sanitize_redirect($timeout_url);
        $securepay_args['token'] = esc_attr($securepay_token);
        $securepay_args['partner_uid'] = esc_attr($securepay_partner_uid);
        $securepay_args['checksum'] = esc_attr($securepay_sign);
        $securepay_args['payment_source'] = 'fluentforms';

        if ('yes' === $settings['banklist'] && !empty($buyer_bank_code)) {
            $securepay_args['buyer_bank_code'] = esc_attr($buyer_bank_code);
        }

        do_action(
            'ff_log_data',
            [
                'parent_source_id' => $submission->form_id,
                'source_type' => 'submission_item',
                'source_id' => $submission->id,
                'component' => 'Payment',
                'status' => 'info',
                'title' => 'Redirect to SecurePay',
                'description' => 'User redirect to SecurePay for completing the payment',
            ]
        );

        $output = '<!doctype html><html><head><title>SecurePay</title>';
        $output .= '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">';
        $output .= '</head><body>';
        $output .= '<form name="order" id="securepay_payment" method="post" action="'.esc_url_raw($securepay_payment_url).'payments">';
        foreach ($securepay_args as $f => $v) {
            $output .= '<input type="hidden" name="'.$f.'" value="'.$v.'">';
        }

        $output .= '</form>';
        $output .= wp_get_inline_script_tag('document.getElementById( "securepay_payment" ).submit();');
        $output .= '</body></html>';

        exit($output);
    }

    protected function getPaymentMode()
    {
        $settings = SecurePaySettings::getSettings();

        return $settings['payment_mode'];
    }

    public function handleSessionRedirectBack($data)
    {
        $type = sanitize_text_field($data['type']);
        $submissionId = (int) $data['fluentform_payment'];
        $this->setSubmissionId($submissionId);
        $submission = $this->getSubmission();

        $transactionHash = sanitize_text_field($data['transaction_hash']);
        $transaction = $this->getTransaction($transactionHash, 'transaction_hash');

        if (!$transaction || !$submission || $transaction->payment_method !== $this->method) {
            return;
        }

        $form_url = $submission->source_url;

        if ('paid' === $submission->payment_status && 'yes' === $this->getMetaData('from_callback') && 'yes' === $this->getMetaData('from_redirect')) {
            $returnData = $this->getReturnData();
            $returnData['title'] = __('Payment Completed', 'securepayffm');
            $returnData['is_new'] = false;
            $returnData['type'] = $type;
            if (isset($returnData['result']['message'])) {
                $returnData['result']['message'] .= $this->viewback($form_url);
            }
            $this->showPaymentView($returnData);

            return;
        }

        $response_params = $this->sanitize_response();
        if (!empty($response_params['spreturn'])) {
            $status = $response_params['spreturn'];

            if ('pending' === $submission->payment_status) {
                $this->changeTransactionStatus($transaction->id, $status);
                $this->changeSubmissionPaymentStatus($status);
            }

            $returnData = [
                'insert_id' => $submission->id,
                'title' => 'cancel' === $status ? esc_html__('Payment Cancelled', 'securepayffm') : esc_html__('Payment Timeout', 'securepayffm'),
                'result' => false,
                'error' => 'cancel' === $status ? esc_html__('Looks like you have cancelled the payment', 'securepayffm') : esc_html__('Looks like the payment has been timeout', 'securepayffm'),
            ];

            $returnData['type'] = $status;
            $returnData['is_new'] = false;
            $returnData['error'] .= $this->viewback($form_url);

            $this->showPaymentView($returnData);

            return;
        }

        if (!empty($response_params) && isset($response_params['order_number'])) {
            $success = $this->response_status($response_params);

            $is_callback = $this->is_response_callback($response_params);

            $callback = $is_callback ? 'Callback' : 'Redirect';
            $receipt_link = !empty($response_params['receipt_url']) ? $response_params['receipt_url'] : '';
            $status_link = !empty($response_params['status_url']) ? $response_params['status_url'] : '';
            $retry_link = !empty($response_params['retry_url']) ? $response_params['retry_url'] : '';

            $this->setMetaData(($is_callback ? 'from_callback' : 'from_redirect'), 'yes');

            if ($success) {
                do_action(
                    'ff_log_data',
                    [
                        'parent_source_id' => $submission->form_id,
                        'source_type' => 'submission_item',
                        'source_id' => $submission->id,
                        'component' => 'Payment',
                        'status' => 'info',
                        'title' => 'SecurePay Response',
                        'description' => 'Received '.$callback.' response from SecurePay',
                    ]
                );

                $note = 'SecurePay payment successful<br>';
                $note .= 'Response from: '.$callback.'<br>';
                $note .= 'Transaction ID: '.$response_params['merchant_reference_number'].'<br>';

                if (!empty($receipt_link)) {
                    $note .= 'Receipt link: <a href="'.$receipt_link.'" target=new rel="noopener">'.$receipt_link.'</a><br>';
                }

                if (!empty($status_link)) {
                    $note .= 'Status link: <a href="'.$status_link.'" target=new rel="noopener">'.$status_link.'</a><br>';
                }

                $this->setMetaData('_notes', $note);

                $updateData = [
                    'payment_note' => maybe_serialize($data),
                    'charge_id' => sanitize_text_field($response_params['merchant_reference_number']),
                ];

                $status = 'paid';
                $this->updateTransaction($transaction->id, $updateData);
                $this->changeTransactionStatus($transaction->id, $status);
                $this->changeSubmissionPaymentStatus($status);

                $returnData = $this->completePaymentSubmission(false);
                $returnData['type'] = $type;
                $returnData['is_new'] = false;

                if (isset($returnData['result']['message'])) {
                    $returnData['result']['message'] .= $this->viewback($form_url);
                }
                $this->showPaymentView($returnData);

                return;
            }

            $note = 'SecurePay payment failed<br>';
            $note .= 'Response from: '.$callback.'<br>';
            $note .= 'Transaction ID: '.$response_params['merchant_reference_number'].'<br>';

            if (!empty($retry_link)) {
                $note .= 'Retry link: <a href="'.$retry_link.'" target=new rel="noopener">'.$retry_link.'</a><br>';
            }

            if (!empty($status_link)) {
                $note .= 'Status link: <a href="'.$status_link.'" target=new rel="noopener">'.$status_link.'</a><br>';
            }

            if ('pending' === $submission->payment_status) {
                $this->setMetaData('_notes', $note);

                $updateData = [
                    'payment_note' => maybe_serialize($data),
                ];

                $status = 'failed';
                $this->updateTransaction($transaction->id, $updateData);
                $this->changeTransactionStatus($transaction->id, $status);
                $this->changeSubmissionPaymentStatus($status);
            }

            $returnData = $this->completePaymentSubmission(false);
            $returnData['type'] = 'failed';
            $returnData['is_new'] = false;
            if (isset($returnData['result']['message'])) {
                $returnData['result']['message'] .= $this->viewback($form_url);
            }
            $this->showPaymentView($returnData);

            return;
        }

        $returnData['title'] = esc_html__('Invalid response', 'securepayffm');
        $returnData['error'] = esc_html__('Invalid response', 'securepayffm').$this->viewback($form_url);
        $returnData['type'] = 'failed';
        $returnData['is_new'] = false;

        $this->showPaymentView($returnData);
        exit('Invalid response');
    }

    public function validateSubmittedItems($paymentItems, $form, $formData, $subscriptionItems)
    {
        if (\count($subscriptionItems)) {
            wp_send_json(
                [
                    'errors' => esc_html_('SecurePay Error: SecurePay does not support subscriptions right now!', 'securepayffm'),
                ],
                423
            );
        }
    }

    public function securepay_scripts()
    {
        if (!is_admin()) {
            $version = SECUREPAYFFM_VERSION.'z'.(\defined('WP_DEBUG') && WP_DEBUG ? time() : date('Ymdh'));
            $slug = SECUREPAYFFM_SLUG;
            $url = SECUREPAYFFM_URL;
            $selectid = 'securepayselect2';
            $selectdeps = [];
            if (wp_script_is('select2', 'enqueued')) {
                $selectdeps = ['jquery', 'select2'];
            } elseif (wp_script_is('selectWoo', 'enqueued')) {
                $selectdeps = ['jquery', 'selectWoo'];
            } elseif (wp_script_is($selectid, 'enqueued')) {
                $selectdeps = ['jquery', $selectid];
            }

            if (empty($selectdeps)) {
                wp_enqueue_style($selectid, $url.'includes/admin/min/select2.min.css', null, $version);
                wp_enqueue_script($selectid, $url.'includes/admin/min/select2.min.js', ['jquery'], $version);
                $selectdeps = ['jquery', $selectid];
            }

            wp_enqueue_script($slug, $url.'includes/admin/securepayffm.js', $selectdeps, $version);

            // remove jquery
            unset($selectdeps[0]);

            wp_enqueue_style($selectid.'-helper', $url.'includes/admin/securepayffm.css', $selectdeps, $version);
            wp_add_inline_script($slug, 'function securepaybankffm() { if ( "function" === typeof(securepayffm_bank_select) ) { securepayffm_bank_select(jQuery, "'.$url.'includes/admin/bnk/", '.time().', "'.$version.'"); }}');
        }
    }

    private function get_bank_list($force = false, $is_sandbox = false)
    {
        if (is_user_logged_in()) {
            $force = true;
        }

        $bank_list = $force ? false : get_transient(SECUREPAYFFM_SLUG.'_banklist');
        $endpoint_pub = $is_sandbox ? SECUREPAYFFM_ENDPOINT_PUBLIC_SANDBOX : SECUREPAYFFM_ENDPOINT_PUBLIC_LIVE;

        if (empty($bank_list)) {
            $remote = wp_remote_get(
                $endpoint_pub.'/banks/b2c?status',
                [
                    'timeout' => 10,
                    'user-agent' => SECUREPAYFFM_SLUG.'/'.SECUREPAYFFM_VERSION,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Referer' => home_url(),
                    ],
                ]
            );

            if (!is_wp_error($remote) && isset($remote['response']['code']) && 200 === $remote['response']['code'] && !empty($remote['body'])) {
                $data = json_decode($remote['body'], true);
                if (!empty($data) && \is_array($data) && !empty($data['fpx_bankList'])) {
                    $list = $data['fpx_bankList'];
                    foreach ($list as $arr) {
                        $status = 1;
                        if (empty($arr['status_format2']) || 'offline' === $arr['status_format1']) {
                            $status = 0;
                        }

                        $bank_list[$arr['code']] = [
                            'name' => $arr['name'],
                            'status' => $status,
                        ];
                    }

                    if (!empty($bank_list) && \is_array($bank_list)) {
                        set_transient(SECUREPAYFFM_SLUG.'_banklist', $bank_list, 60);
                    }
                }
            }
        }

        return !empty($bank_list) && \is_array($bank_list) ? $bank_list : false;
    }

    private function is_bank_list(&$bank_list = '')
    {
        $settings = SecurePaySettings::getSettings();
        $is_sandbox = !SecurePaySettings::isLive();

        if ('yes' === $settings['banklist']) {
            $bank_list = $this->get_bank_list(false, $is_sandbox);

            return !empty($bank_list) && \is_array($bank_list) ? true : false;
        }

        $bank_list = '';

        return false;
    }

    public function banklist_output($html, $data, $form, $pm = false)
    {
        $bank_list = '';
        $bank_html = '';
        if ($this->is_bank_list($bank_list)) {
            $bank_id = !empty($_POST['buyer_bank_code']) ? sanitize_text_field($_POST['buyer_bank_code']) : false;
            $image = false;
            $settings = SecurePaySettings::getSettings();

            if ('yes' === $settings['banklogo']) {
                $image = SECUREPAYFFM_URL.'includes/admin/securepay-bank-alt.png';
            }

            $bank_html .= '<div id="spffmbody-fpxbank" class="spffmbody" style="display:none;">';
            if (!empty($image)) {
                $bank_html .= '<img src="'.$image.'" class="spffmlogo">';
            }

            $bank_html .= '<select name="buyer_bank_code" id="buyer_bank_code">';
            $bank_html .= "<option value=''>Please Select Bank</option>";
            foreach ($bank_list as $id => $arr) {
                $name = $arr['name'];
                $status = $arr['status'];

                $disabled = empty($status) ? ' disabled' : '';
                $offline = empty($status) ? ' (Offline)' : '';
                $selected = $id === $bank_id ? ' selected' : '';
                $bank_html .= '<option value="'.$id.'"'.$selected.$disabled.'>'.$name.$offline.'</option>';
            }
            $bank_html .= '</select>';

            $bank_html .= '</div>';

            $bank_html .= wp_get_inline_script_tag('if ( "function" === typeof(securepaybankffm) ) {securepaybankffm();}', ['id' => SECUREPAYFFM_SLUG.'-bankselect']);
        }

        if ($pm) {
            return $html.$bank_html;
        }

        if (!\defined('SPFFMBANKLISTRUN')) {
            return $bank_html.$html;
        }

        return $html;
    }
}
