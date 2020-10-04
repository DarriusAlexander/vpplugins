<?php
/**
 * Deposit expiring email
 *
 * @author Your Inspiration Themes
 * @package YITH WooCommerce Deposits and Down Payments
 * @version 1.0.0
 */

/*
 * This file belongs to the YIT Framework.
 *
 * This source file is subject to the GNU GENERAL PUBLIC LICENSE (GPL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 */

if ( ! defined( 'YITH_WCDP' ) ) {
	exit;
} // Exit if accessed directly

if( ! class_exists( 'YITH_WCDP_Customer_Deposit_Expiring_Email' ) ) {
	/**
	 * New deposit created email email
	 *
	 * @since 1.0.0
	 */
	class YITH_WCDP_Customer_Deposit_Expiring_Email extends YITH_WCDP_Emails {

		/**
		 * Constructor method, used to return object of the class to WC
		 *
		 * @return \YITH_WCDP_Customer_Deposit_Expiring_Email
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->id          = 'expiring_deposit';
			$this->title       = __( 'Deposit expiring', 'yith-woocommerce-deposits-and-down-payments' );
			$this->description = __( 'This email is sent to customers as notification of their deposits, before expiration', 'yith-woocommerce-deposits-and-down-payments' );

			$this->heading = __( 'You still have down payments that have to be paid', 'yith-woocommerce-deposits-and-down-payments' );
			$this->subject = __( 'You still have down payments that have to be paid', 'yith-woocommerce-deposits-and-down-payments' );

			$this->content_html = $this->get_option( 'content_html', __( "<p>Hurry up! The following down payments are expiring: you have only <strong>{days_before_expire}</strong> days left</p>
{deposit_list}", 'yith-woocommerce-deposits-and-down-payments' ) );
			$this->content_text = $this->get_option( 'content_text', __( "Hurry up! The following down payments are expiring: you have only {days_before_expire} days left\n
{deposit_list}", 'yith-woocommerce-deposits-and-down-payments' ) );

			$this->template_html  = 'emails/customer-deposit-expiring-email.php';
			$this->template_plain = 'emails/plain/customer-deposit-expiring-email.php';

			// Triggers for this email
			add_action( 'yith_wcdp_deposits_expiring_notification', array( $this, 'trigger' ), 10, 1 );

			// Call parent constructor
			parent::__construct();
		}

		/**
		 * Method triggered to send email
		 *
		 * @param $affiliate_id int New affiliate id
		 * @return void
		 */
		public function trigger( $order_id ) {
			$this->object = wc_get_order( $order_id );
			$this->recipient = yit_get_prop( $this->object, 'billing_email' );
			$this->customer = $this->object->get_user();
			$this->suborders = YITH_WCDP_Suborders()->get_suborder( yit_get_prop( $this->object, 'id' ) );

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			$this->set_replaces();

			$deposit_expiration_date = get_option( 'yith_wcdp_deposits_expiration_duration', 15 );
			$deposit_expiration_time = strtotime( sprintf( ' + %d days', $deposit_expiration_date ) );
			$days_before_expiration = floor( ( $deposit_expiration_time - time() ) / DAY_IN_SECONDS );

			$find = array(
				'expiration-date'    => '{expiration_date}',
				'days-before-expire' => '{days_before_expire}',
				'content-html'       => '{content_html}',
				'content-text'       => '{content_text}'
			);
			
			$replace = array(
				'expiration-date'    => date_i18n( wc_date_format(), $deposit_expiration_time ),
				'days-before-expire' => $days_before_expiration,
				'content-html'       => $this->content_html,
				'content-text'       => $this->content_text
			);

			if( version_compare( wc()->version, '3.2.0', '>=' ) ){
				$this->placeholders = array_merge(
					$this->placeholders,
					array_combine( array_values( $find ), array_values( $replace ) )
				);

				$this->placeholders['{content_html}'] = $this->format_string( $this->placeholders['{content_html}'] );
				$this->placeholders['{content_text}'] = $this->format_string( $this->placeholders['{content_text}'] );
			}
			else{
				$this->find = array_merge( $this->find, $find );
				$this->replace = array_merge( $this->replace, $replace );

				$this->replace['content-html'] = $this->format_string( $this->replace['content-html'] );
				$this->replace['content-text'] = $this->format_string( $this->replace['content-text'] );
			}

			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		/**
		 * Check if mail is enabled
		 *
		 * @return bool Whether email notification is enabled or not
		 * @since 1.0.0
		 */
		public function is_enabled() {
			$deposit_expire = get_option( 'yith_wcdp_deposit_expiration_enable', 'no' );
			$notify_admin = get_option( 'yith_wcdp_notify_customer_deposit_expiring', 'no' );
			$notification_days = get_option( 'yith_wcdp_notify_customer_deposit_expiring_days_limit', 15 );

			$send_available = false;

			if ( $this->object ) {
				$order = $this->object;

				// check if current order has a deposit
				if( ! yit_get_prop( $this->object, '_has_deposit' ) ){
					return  false;
				}

				// retrieve current order suborders
				$suborders = YITH_WCDP_Suborders_Premium()->get_suborder( yit_get_prop( $this->object, 'id' ) );

				// check if order have suborders
				if( ! $suborders ){
					return false;
				}

				// enable "re-send notify email" only if at least one suborder is not expired, and not completed or cancelled
				foreach( $suborders as $suborder_id ){
					$suborder = wc_get_order( $suborder_id );

					if( ! yit_get_prop( $this->object, '_has_expired' ) && ! in_array( $suborder->get_status(), array( 'completed', 'processing', 'cancelled' ) ) ){
						$send_available = true;
					}
				}
			}

			return ( $deposit_expire == 'yes' && $notify_admin == 'yes' && $notification_days && $send_available );
		}

		/**
		 * Init form fields to display in WC admin pages
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'subject' => array(
					'title' 		=> __( 'Subject', 'woocommerce' ),
					'type' 			=> 'text',
					'description' 	=> sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'yith-woocommerce-deposits-and-down-payments' ), $this->subject ),
					'placeholder' 	=> '',
					'default' 		=> ''
				),
				'heading' => array(
					'title' 		=> __( 'Email Heading', 'woocommerce' ),
					'type' 			=> 'text',
					'description' 	=> sprintf( __( 'This controls the main heading contained in the notification email. Leave it blank to use the default heading: <code>%s</code>.', 'yith-woocommerce-deposits-and-down-payments' ), $this->heading ),
					'placeholder' 	=> '',
					'default' 		=> ''
				),
				'email_type' => array(
					'title' 		=> __( 'Email type', 'woocommerce' ),
					'type' 			=> 'select',
					'description' 	=> __( 'Choose a format for the email to send.', 'yith-woocommerce-deposits-and-down-payments' ),
					'default' 		=> 'html',
					'class'			=> 'email_type wc-enhanced-select',
					'options'		=> $this->get_email_type_options()
				),
				'content_html' => array(
					'title' 		=> __( 'Email HTML Content', 'yith-woocommerce-deposits-and-down-payments' ),
					'type' 			=> 'textarea',
					'description' 	=> __( 'This field lets you modify the main content of the HTML email. You can use the following placeholders: <code>{order_id}</code> <code>{order_date}</code> <code>{order_state}</code> <code>{customer_name}</code> <code>{customer_login}</code> <code>{customer_email}</code> <code>{suborder_list}</code> <code>{suborder_table}</code> <code>{expiration_date}</code> <code>{days_before_expiration}</code>', 'yith-woocommerce-deposits-and-down-payments' ),
					'placeholder' 	=> '',
					'css'           => 'min-height: 250px;',
					'default' 		=> __( "<p>Hurry up! The following down payments are expiring: you have only <strong>{days_before_expire}</strong> days left</p>
{deposit_list}", 'yith-woocommerce-deposits-and-down-payments' )
				),

				'content_text' => array(
					'title' 		=> __( 'Email Text Content', 'yith-woocommerce-deposits-and-down-payments' ),
					'type' 			=> 'textarea',
					'description' 	=> __( 'This field lets you modify the main content of the text email. You can use the following placeholders: <code>{order_id}</code> <code>{order_date}</code> <code>{order_state}</code> <code>{customer_name}</code> <code>{customer_login}</code> <code>{customer_email}</code> <code>{suborder_list}</code> <code>{suborder_table}</code> <code>{expiration_date}</code> <code>{days_before_expiration}</code>', 'yith-woocommerce-deposits-and-down-payments' ),
					'placeholder' 	=> '',
					'css'           => 'min-height: 250px;',
					'default' 		=> __( "Hurry up! The following down payments are expiring: you have only {days_before_expire} days left\n
{deposit_list}", 'yith-woocommerce-deposits-and-down-payments' )
				)

			);
		}
	}
}

return new YITH_WCDP_Customer_Deposit_Expiring_Email();