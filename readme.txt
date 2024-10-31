=== SecurePay For Fluent Forms ===
Contributors: SecurePay
Tags: payment gateway, payment platform, Malaysia, online banking, fpx
Requires at least: 5.4
Tested up to: 6.3
Requires PHP: 7.2
Stable tag: 1.0.5
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SecurePay payment platform plugin for Fluent Forms.

== Description ==

Install this plugin to extends the [Fluent Forms](https://www.fluentforms.com/) plugin to accept payments with the [SecurePay Payment Platform](https://www.securepay.my/?utm_source=wp-plugins-fluentforms&utm_campaign=author-uri&utm_medium=wp-dash) for Malaysians.

If you have any questions or suggestions about this plugin, please contact us directly through email at **hello@securepay.my** . Our friendly team will gladly reply as soon as possible.

Other Integrations:

- [SecurePay For WooCommerce](https://wordpress.org/plugins/securepay/)
- [SecurePay For WPJobster](https://wordpress.org/plugins/securepay-for-wpjobster/)
- [SecurePay For WPForms](https://wordpress.org/plugins/securepay-for-wpforms/)
- [SecurePay For Restrict Content Pro](https://wordpress.org/plugins/securepay-for-restrictcontentpro)
- [SecurePay For Paid Memberships Pro](https://wordpress.org/plugins/securepay-for-paidmembershipspro)
- [SecurePay For GiveWP](https://wordpress.org/plugins/securepay-for-givewp)

== Installation ==

Make sure that you already have Fluent Froms plugin installed and activated.

**Step 1:**

- Login to your *WordPress Dashboard*
- Go to **Plugins > Add New**
- Search **SecurePay for FluentForms**

**Step 2:**

- **Activate** the plugin through the 'Plugins' screen in WordPress.

**Step 3:**

- Create a new form **Forms > New Frorm**
- Click **Forms -> your-new-form**
- Click **Settings -> SecurePay**
- Click **Add New**

**Step 4:**

- Fill in your **Token, Checksum Token, UID Token**. You can retrieve your credentials from your SecurePay account.
- Click **Save** to save changes.

Contact us through email hello@securepay.my if you have any questions or comments about this plugin.


== Changelog ==
= 1.0.5 (15-11-2021) =
- Fixed: remove debug code.

= 1.0.4 (15-11-2021) =
- Fixed: js securepayffm_bank_select() -> invalid selector.
- Fixed: Declaration of SecurePayProcessor::handlePaymentAction() -> invalid fluenformpro version.
- Added: compability check for fluentforms pro version 4.2.0 and later.

= 1.0.3 (13-11-2021) =
- Fixed: banklist not shown when default single payment menthod.

= 1.0.2 (13-11-2021) =
- Fixed: Declaration of SecurePayProcessor::handlePaymentAction.

= 1.0.1 (29-10-2021) =
- Fixed: variable sanitize.
- Fixed: capture required field name value.
- Fixed: banklist selection.
- Fixed: fluentforms pro compability check.

= 1.0.0 (22-10-2021) =
- Initial release.
