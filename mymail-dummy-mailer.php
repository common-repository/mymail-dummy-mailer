<?php
/*
Plugin Name: MyMail Dummy Mailer
Plugin URI: https://evp.to/mymail?utm_campaign=wporg&utm_source=MyMail+Dummy+Mailer
Description: A Dummy Mailer for MyMail 2
Version: 0.5.1
Author: EverPress
Author URI: https://everpress.co

License: GPLv2 or later
*/

define( 'MYMAIL_DUMMYMAILER_VERSION', '0.5.1' );
define( 'MYMAIL_DUMMYMAILER_REQUIRED_VERSION', '2.0' );
define( 'MYMAIL_DUMMYMAILER_ID', 'dummymailer' );

class MyMailDummyMailer {

	private $plugin_path;
	private $plugin_url;

	/**
	 *
	 */
	public function __construct() {

		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		add_action( 'init', array( &$this, 'init' ) );
	}


	/**
	 *
	 *
	 * @param unknown $network_wide
	 */
	public function activate( $network_wide ) {

		if ( function_exists( 'mymail' ) ) {

			$defaults = array(
				'dummymailer_admin_notice'      => true,
				'dummymailer_simulate'          => true,
				'dummymailer_openrate'          => 50,
				'dummymailer_clickrate'         => 20,
				'dummymailer_unsubscriberate'   => 2,
				'dummymailer_bouncerate'        => 0.4,
				'dummymailer_successrate'       => 100,
				'dummymailer_campaignerrorrate' => 0,
			);

			$mymail_options = mymail_options();

			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $mymail_options[ $key ] ) ) {
					mymail_update_option( $key, $value );
				}
			}
		}

		if ( function_exists( 'mailster' ) ) {

			add_action(
				'admin_notices',
				function() {

					$name = 'MyMail Dummy Mailer';
					$slug = 'mailster-dummy-mailer/mailster-dummy-mailer.php';

					$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . dirname( $slug ) ), 'install-plugin_' . dirname( $slug ) );

					$search_url = add_query_arg(
						array(
							's'    => $slug,
							'tab'  => 'search',
							'type' => 'term',
						),
						admin_url( 'plugin-install.php' )
					);

					?>
			<div class="error">
				<p>
				<strong><?php echo esc_html( $name ); ?></strong> is deprecated in Mailster and no longer maintained! Please switch to the <a href="<?php echo esc_url( $search_url ); ?>">new version</a> as soon as possible or <a href="<?php echo esc_url( $install_url ); ?>">install it now!</a>
				</p>
			</div>
					<?php

				}
			);
		}
	}


	/**
	 *
	 *
	 * @param unknown $network_wide
	 */
	public function deactivate( $network_wide ) {

		if ( function_exists( 'mymail' ) ) {
			if ( mymail_option( 'deliverymethod' ) == MYMAIL_DUMMYMAILER_ID ) {
				mymail_update_option( 'deliverymethod', 'simple' );
			}
		}

	}


	/**
	 * init function.
	 *
	 * init the plugin
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		if ( ! function_exists( 'mymail' ) ) {

		} else {

			add_filter( 'mymail_delivery_methods', array( &$this, 'delivery_method' ) );
			add_action( 'mymail_deliverymethod_tab_dummymailer', array( &$this, 'deliverytab' ) );
			add_filter( 'mymail_verify_options', array( &$this, 'verify_options' ) );

			if ( mymail_option( 'deliverymethod' ) == MYMAIL_DUMMYMAILER_ID ) {

				add_filter( 'mymail_get_ip', array( &$this, 'random_ip' ) );
				add_filter( 'mymail_get_user_client', array( &$this, 'get_user_client' ) );
				add_filter( 'gettext', array( &$this, 'gettext' ), 20, 3 );

				add_filter( 'mymail_subscriber_errors', array( &$this, 'subscriber_errors' ) );

				add_action( 'mymail_cron_worker', array( &$this, 'simulate' ), 1 );
				add_action( 'admin_notices', array( &$this, 'admin_notice' ) );

				add_action( 'mymail_initsend', array( &$this, 'initsend' ) );
				add_action( 'mymail_presend', array( &$this, 'presend' ) );
				add_action( 'mymail_dosend', array( &$this, 'dosend' ) );

			}
		}

	}


	/**
	 * simulate function.
	 *
	 * simulates opens, clicks, unsubscribes and bounces
	 *
	 * @access public
	 * @param unknown $translated_text
	 * @param unknown $untranslated_text
	 * @param unknown $domain
	 * @return void
	 */
	public function gettext( $translated_text, $untranslated_text, $domain ) {

		if ( $domain != 'mymail' ) {
			return $translated_text;
		}

		switch ( $untranslated_text ) {
			case 'Message sent. Check your inbox!':
				return __( 'You are using the Dummy Mailer, no email has been sent!', 'mymail-dummymailer' );
		}

		return $translated_text;
	}


	/**
	 *
	 */
	public function admin_notice() {

		$screen = get_current_screen();

		switch ( $screen->parent_file ) {
			case 'edit.php?post_type=newsletter':
				if ( mymail_option( 'dummymailer_admin_notice' ) ) :
					?>
					<div class="error"><p><?php _e( 'All outgoing mails and statistics are simulated so do not expect anything in your inbox!', 'mymail-dummymailer' ); ?></p></div>
					<?php
					endif;
				break;
		}

	}


	/**
	 * simulates opens, clicks, unsubscribes and bounces
	 *
	 * @access public
	 * @param mixed $mailobject
	 * @return void
	 */
	public function simulate() {

		if ( ! mymail_option( 'dummymailer_simulate' ) ) {
			return false;
		}

		$campaigns = mymail( 'campaigns' )->get_campaigns( array( 'post_status' => array( 'finished', 'active', 'paused' ) ) );

		if ( empty( $campaigns ) ) {
			return;
		}

		define( 'MYMAIL_DUMMYMAILER_SIMULATE', true );
		$now        = time();
		$timeoffset = get_option( 'gmt_offset' ) * 3600;

		$openrate        = mymail_option( 'dummymailer_openrate' );
		$clickrate       = mymail_option( 'dummymailer_clickrate' );
		$unsubscriberate = mymail_option( 'dummymailer_unsubscriberate' );
		$bouncerate      = mymail_option( 'dummymailer_bouncerate' );

		foreach ( $campaigns as $i => $campaign ) {

			$open = mymail( 'campaigns' )->get_open_rate( $campaign->ID );
			if ( $open * 100 >= $openrate ) {
				continue;
			}

			$click       = mymail( 'campaigns' )->get_click_rate( $campaign->ID );
			$bounces     = mymail( 'campaigns' )->get_bounce_rate( $campaign->ID );
			$unsubscribe = mymail( 'campaigns' )->get_unsubscribe_rate( $campaign->ID );

			$links = mymail( 'campaigns' )->get_links( $campaign->ID );

			$links = array_values( array_diff( $links, array( '#' ) ) );

			$explicitopen = $this->rand( 50 );

			$subscribers = mymail( 'campaigns' )->get_sent_subscribers( $campaign->ID );

			if ( empty( $subscribers ) ) {
				continue;
			}

			$meta = mymail( 'campaigns' )->meta( $campaign->ID );

			foreach ( $subscribers as $j => $subscriber ) {

				if ( $explicitopen ) {

					if ( $this->rand( $openrate ) && $open * 100 < $openrate ) {
						do_action( 'mymail_open', $subscriber, $campaign->ID, true );
					}
				} else {
					if ( $this->rand( $openrate ) && $open * 100 < $openrate ) {
						do_action( 'mymail_open', $subscriber, $campaign->ID, false );

						if ( $this->rand( $clickrate ) && $click * 100 < $clickrate ) {
							do_action( 'mymail_click', $subscriber, $campaign->ID, $links[ array_rand( $links ) ], false );
						}

						if ( $this->rand( $unsubscriberate ) && $unsubscribe * 100 < $unsubscriberate ) {
							$unsublink = mymail()->get_unsubscribe_link( $campaign->ID );
							do_action( 'mymail_click', $subscriber, $campaign->ID, $unsublink, false );
							mymail( 'subscribers' )->unsubscribe( $subscriber, $campaign->ID );
						}
					}
					if ( $this->rand( $bouncerate ) && $bounces * 100 < $bouncerate ) {
						mymail( 'subscribers' )->bounce( $subscriber, $campaign->ID, true );
					}
				}

				$max_memory_usage = memory_get_peak_usage( true );

				if ( $j > ( $now - $meta['timestamp'] ) / 5 ) {
					break;
				}
			}
		}

	}


	/**
	 *
	 *
	 * @return unknown
	 */
	public function random_ip() {

		return defined( 'MYMAIL_DUMMYMAILER_SIMULATE' ) ? rand( 1, 200 ) . '.' . rand( 1, 255 ) . '.' . rand( 1, 255 ) . '.' . rand( 1, 255 ) : null;
	}


	/**
	 *
	 *
	 * @return unknown
	 */
	public function get_user_client() {

		$clients = array(

			array(
				'client'  => 'Thunderbird',
				'version' => rand( 23, 26 ),
				'type'    => 'desktop',
			),
			array(
				'client'  => 'Gmail App (Android)',
				'version' => '',
				'type'    => 'mobile',
			),
			array(
				'client'  => 'Gmail',
				'version' => '',
				'type'    => 'webmail',
			),
			array(
				'client'  => 'WebClient (unknown)',
				'version' => '',
				'type'    => 'webmail',
			),
			array(
				'client'  => 'iPad',
				'version' => 'iOS ' . rand( 6, 8 ),
				'type'    => 'mobile',
			),
			array(
				'client'  => 'iPhone',
				'version' => 'iOS ' . rand( 6, 8 ),
				'type'    => 'mobile',
			),
			array(
				'client'  => 'Microsoft Outlook',
				'version' => rand( 2010, 2015 ),
				'type'    => 'desktop',
			),
			array(
				'client'  => 'Microsoft Outlook',
				'version' => '2003-2007',
				'type'    => 'desktop',
			),
			array(
				'client'  => 'Windows Live Mail',
				'version' => '',
				'type'    => 'desktop',
			),
		);

		return (object) $clients[ array_rand( $clients ) ];

	}


	/**
	 * initsend function.
	 *
	 * uses mymail_initsend hook to set initial settings
	 *
	 * @access public
	 * @return void
	 * @param mixed $mailobject
	 */
	public function initsend( $mailobject ) {}




	/**
	 * presend function.
	 *
	 * uses the mymail_presend hook to apply setttings before each mail
	 *
	 * @access public
	 * @return void
	 * @param mixed $mailobject
	 */
	public function presend( $mailobject ) {

		// use pre_send from the main class
		$mailobject->pre_send();

	}


	/**
	 * dosend function.
	 *
	 * uses the ymail_dosend hook and triggers the send
	 *
	 * @access public
	 * @return void
	 * @param mixed $mailobject
	 */
	public function dosend( $mailobject ) {

		$successrate       = mymail_option( 'dummymailer_successrate' );
		$campaignerrorrate = mymail_option( 'dummymailer_campaignerrorrate' );
		$mailobject->sent  = $this->rand( $successrate );
		if ( ! $mailobject->sent ) {
			if ( $this->rand( $campaignerrorrate ) ) {
				$mailobject->last_error = new Exception( 'DummyMailer Campaign Error' );
			} else {
				$mailobject->last_error = new Exception( 'DummyMailer Subscriber Error' );
			}
		} else {
			// $this->log('sent to '.$mailobject->to[0], $mailobject->campaignID);
		}

	}




	/**
	 * rand function.
	 *
	 * add the delivery method to the options
	 *
	 * @access public
	 * @param mixed $p
	 * @return void
	 */
	public function rand( $p ) {
		return mt_rand( 0, 10000 ) <= ( $p * 100 );
	}


	/**
	 * subscriber_errors function.
	 *
	 * adds a subscriber error
	 *
	 * @access public
	 * @param unknown $errors
	 * @return $errors
	 */
	public function subscriber_errors( $errors ) {
		$errors[] = 'DummyMailer Subscriber Error';
		return $errors;
	}


	/**
	 * delivery_method function.
	 *
	 * add the delivery method to the options
	 *
	 * @access public
	 * @param mixed $delivery_methods
	 * @return void
	 */
	public function delivery_method( $delivery_methods ) {
		$delivery_methods[ MYMAIL_DUMMYMAILER_ID ] = 'DummyMailer';
		return $delivery_methods;
	}


	/**
	 * deliverytab function.
	 *
	 * the content of the tab for the options
	 *
	 * @access public
	 * @return void
	 */
	public function deliverytab() {

		$verified = mymail_option( MYMAIL_DUMMYMAILER_ID . '_verified' );

		?>

		<p class="description"><?php _e( 'The Dummy Mailer doesn\'t send any real mail rather it simulates a real environment. The rates you can define will be used to simulate the campaigns', 'mymail-dummymailer' ); ?></p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Admin Notice', 'mymail-dummymailer' ); ?>
				</th>
				<td><label><input type="hidden" name="mymail_options[dummymailer_admin_notice]" value="0"><input type="checkbox" name="mymail_options[dummymailer_admin_notice]" value="1" <?php checked( mymail_option( 'dummymailer_admin_notice' ) ); ?>> <?php _e( 'Display Admin Notice on the Newsletter page', 'mymail-dummymailer' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Simulate Rates', 'mymail-dummymailer' ); ?>
				</th>
				<td><label><input type="hidden" name="mymail_options[dummymailer_simulate]" value="0"><input type="checkbox" name="mymail_options[dummymailer_simulate]" value="1" <?php checked( mymail_option( 'dummymailer_simulate' ) ); ?>> <?php _e( 'Simulate Rates based on the settings below', 'mymail-dummymailer' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Rates', 'mymail-dummymailer' ); ?>
				<p class="description"><?php _e( 'Define the rates which should get simulated', 'mymail-dummymailer' ); ?></p>
				</th>
				<td>
				<div class="mymail_text"><label><?php _e( 'Open Rate', 'mymail-dummymailer' ); ?>:</label> <input type="number" min="0" max="100" step="0.1" name="mymail_options[dummymailer_openrate]" class="postform textright" value="<?php echo mymail_option( 'dummymailer_openrate' ); ?>">% </div>
				<div class="mymail_text"><label><?php _e( 'Click Rate', 'mymail-dummymailer' ); ?>:</label> <input type="number" min="0" max="100" step="0.1" name="mymail_options[dummymailer_clickrate]" class="postform textright" value="<?php echo mymail_option( 'dummymailer_clickrate' ); ?>">% </div>
				<div class="mymail_text"><label><?php _e( 'Unsubscribe Rate', 'mymail-dummymailer' ); ?>:</label> <input type="number" min="0" max="100" step="0.1" name="mymail_options[dummymailer_unsubscriberate]" class="postform textright" value="<?php echo mymail_option( 'dummymailer_unsubscriberate' ); ?>">% </div>
				<div class="mymail_text"><label><?php _e( 'Bounce Rate', 'mymail-dummymailer' ); ?>:</label> <input type="number" min="0" max="100" step="0.1" name="mymail_options[dummymailer_bouncerate]" class="postform textright" value="<?php echo mymail_option( 'dummymailer_bouncerate' ); ?>">% </div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Error Rates', 'mymail-dummymailer' ); ?>
				</th>
				<td>
				<div class="mymail_text"><label><?php _e( 'Success Rate', 'mymail-dummymailer' ); ?>:</label> <input type="number" min="0" max="100" step="0.1" name="mymail_options[dummymailer_successrate]" class="postform textright" value="<?php echo mymail_option( 'dummymailer_successrate' ); ?>">% </div>
				<div class="mymail_text"><label><?php _e( 'Campaign Error Rate', 'mymail-dummymailer' ); ?>:</label> <input type="number" min="0" max="100" step="0.1" name="mymail_options[dummymailer_campaignerrorrate]" class="postform textright" value="<?php echo mymail_option( 'dummymailer_campaignerrorrate' ); ?>">% </div>
				</td>
			</tr>
		</table>

		<?php

	}



	/**
	 * verify_options function.
	 *
	 * some verification if options are saved
	 *
	 * @access public
	 * @param mixed $options
	 * @return void
	 */
	public function verify_options( $options ) {

		// only if delivery method is dummymailer
		if ( $options['deliverymethod'] == MYMAIL_DUMMYMAILER_ID ) {

		}

		return $options;
	}


	/**
	 * verify_options function.
	 *
	 * some verification if options are saved
	 *
	 * @access public
	 * @return void
	 * @param unknown $str
	 * @param unknown $campaing_id (optional)
	 */
	public function log( $str, $campaing_id = null ) {

		$time = microtime( true );

		$str = '[' . date( 'H:i:s' ) . ':' . round( ( $time - floor( $time ) ) * 10000 ) . '] ' . $str;

		file_put_contents( MYMAIL_UPLOAD_DIR . '/dummymail' . ( $campaing_id ? '_' . $campaing_id : '' ) . '.log', $str . "\n", FILE_APPEND );

	}


}


new MyMailDummyMailer();
