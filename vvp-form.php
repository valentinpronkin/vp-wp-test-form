<?php
/*
	Plugin Name: WordPress contact form
	Plugin URI: 
	Description: Spec project: basic contact form
	Plugin description: 
	Tags: form, contact, email, mail
	Author: Valentin Pronkin
	Author URI: https://github.com/valentinpronkin/vp-wp-test-form
	Text Domain: vvp-form
	Contributors:
	Requires at least:
	Tested up to:
	Version:
	Requires PHP:
*/
class VVP_Form {
	const TBL_LOGS = "vvp_form_logs";
	const PLUGIN_NAME = "vvp-form";
	private $option_name = 'vvp_form_setting';
	
	public function __construct() {
		add_shortcode('vvp-form', array( $this, 'vvp_form' ));

		add_action( 'wp_ajax_nopriv_send_form', array( $this, 'send_form' ) );
		add_action( 'wp_ajax_send_form', array( $this, 'send_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );		
		
		load_plugin_textdomain( self::PLUGIN_NAME, false, self::PLUGIN_NAME . '/languages' );
		
		// admin area
		register_activation_hook(__FILE__, array( $this, 'activate'));

		add_action( 'admin_menu', array( $this, 'vvp_form_register_logs_page' ));
		add_action( 'admin_menu', array( $this, 'vvp_form_setting_menu_page'), 25 );
		add_action( 'admin_init', array( $this, 'vvp_form_fields') );
	}

    public function activate() {
		
		// creting DB table for sent messages logging
		global $wpdb;
		$table = $wpdb->prefix . self::TBL_LOGS;
		$charset = $wpdb->get_charset_collate();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE  IF NOT EXISTS $table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			fname tinytext NOT NULL,
			lname tinytext NOT NULL,
			subject tinytext NOT NULL,
			email tinytext NOT NULL,
			message text NOT NULL,
			PRIMARY KEY  (id)
			) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
    }  

	function scripts() {

		wp_enqueue_style( 'vvp-form', plugin_dir_url( __FILE__ ) . 'css/style.css' );
		wp_enqueue_script( self::PLUGIN_NAME , plugin_dir_url( __FILE__ ) . 'js/script.js', array( 'jquery' ), null, true );
		
		wp_localize_script( self::PLUGIN_NAME, 'settings', array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			)
		);

	}

	public function vvp_form( $content ) {

		$content .= '<div  class="vvp_form" id="vvp_form_1" method="POST" action="">
						<label for="vvp_fname">' . __('First name', self::PLUGIN_NAME) . ':</label>
						<input type="text" id="vvp_fname" placeholder="Enter your first name" required>

						<label for="vvp_lname">' . __('Last name', self::PLUGIN_NAME) . ':</label>
						<input type="text" id="vvp_lname" placeholder="Enter your last name" required>

						<label for="vvp_subject">' . __('Subject', self::PLUGIN_NAME) . ':</label>
						<input type="text" id="vvp_subject" placeholder="Enter subject" required>

						<label for="vvp_message">' . __('Message', self::PLUGIN_NAME) . ':</label>
						<textarea id="vvp_message" placeholder="Your message"></textarea>

						<label for="vvp_email">' . __('Email', self::PLUGIN_NAME) . ':</label>
						<input type="email" id="vvp_email" placeholder="Enter your email" required>
						<p id="vvp_email_validation"></p>

						<button id="vvp_btn_send" class="vvp_btn_send" disabled>Send</button>
						<p id="vvp_form_response"></p>
					</div>';

		return $content;

	}

	function send_form() {

		$data = $_POST;
		
		// pre-process values
		$fname = sanitize_text_field($data['vvp_fname']);
		$lname = sanitize_text_field($data['vvp_lname']);
		$email = sanitize_text_field($data['vvp_email']);
		$subject = sanitize_text_field($data['vvp_subject']);
		$message = stripcslashes(htmlspecialchars($data['vvp_message']));
		

		// backend validations
		if (!preg_match("/^[a-zA-Z-' ]*$/", $fname)) {
			wp_send_json_error(__('Only letters and white space allowed in first name', self::PLUGIN_NAME));
		}
		if (!preg_match("/^[a-zA-Z-' ]*$/", $lname)) {
			wp_send_json_error(__('Only letters and white space allowed in last name', self::PLUGIN_NAME));
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			wp_send_json_error(__('Invalid email format!', self::PLUGIN_NAME));
		}

		$add_response = array();
		if ( wp_mail( get_option( 'vvp_form_settings_email' ) , get_option( 'vvp_form_settings_subject' ), '<h2>'.$subject."</h2>".htmlspecialchars_decode($message), 'Content-type: text/html' ) ) {
			
			$add_response['email'] = "Email was sent successfully.";
			
			
			// Add just sent message to log (db table)
			global $wpdb;  
			if ($wpdb->insert($wpdb->prefix . self::TBL_LOGS, array('fname' => $fname, 'lname' => $lname,  'subject' => $subject, 'email' => $email, 'message' => $message)) > 0) {
				$add_response['db'] = __('Email log added to database successfully.', self::PLUGIN_NAME);
				
			} else {
				$add_response['db'] = __('Error while adding email log to database.', self::PLUGIN_NAME);
			}

			// Create contact on HubSpot
			if ($this->hubspot_create_contact(array(
					'fname' => $fname,
					'lname' => $lname,
					'email' => $email
				))) {
					
				$add_response['hubspot'] = __('User added to HubSpot successfully.', self::PLUGIN_NAME);
			} else {
				$add_response['hubspot'] = __('Error while adding user to HubSpot (already exists?).', self::PLUGIN_NAME);
			}
				
			
			wp_send_json_success('<p>' . implode('</p><p>', $add_response) . '</p>');
			
		} else {
			$add_response['email'] = __('Email was not sent successfully.', self::PLUGIN_NAME);
			$add_response['db'] = __('Skipping adding email log to database.', self::PLUGIN_NAME);
			$add_response['hubspot'] = __('Skipping adding user to HubSpot.', self::PLUGIN_NAME);
			
			wp_send_json_error(__('Something went wrong!', self::PLUGIN_NAME) . '<p>' . implode('</p><p>', $add_response) . '</p>');
		}
	}
	
	function hubspot_create_contact($args) {
		$arr = array(
			'properties' => array(
				array(
					'property' => 'email',
					'value' => $args['email']
				),
				array(
					'property' => 'firstname',
					'value' => $args['fname']
				),
				array(
					'property' => 'lastname',
					'value' => $args['lname']
				)
			)
		);
		$post_json = json_encode($arr);
		$hapikey = get_option( 'vvp_form_settings_hapikey' );
		$endpoint = 'https://api.hubapi.com/contacts/v1/contact';
		$ch = @curl_init();
		@curl_setopt($ch, CURLOPT_POST, true);
		@curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
		@curl_setopt($ch, CURLOPT_URL, $endpoint);
		@curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$hapikey));
		@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = @curl_exec($ch);
		$status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_errors = curl_error($ch);
		@curl_close($ch);
		
		return $status_code == 200;
	}
	
	

	// Admin area code
	//======================================================
	function vvp_form_register_logs_page() {

		add_options_page('VVP_Form Logs', 'VVP_Form Logs', 'manage_options', self::PLUGIN_NAME . '_log', array( $this, 'vvp_form_logs_page' ));
	}
	
	function vvp_form_logs_page() {

		global $wpdb;

		$result = $wpdb->get_results ( "
			SELECT * 
			FROM  " . $wpdb->prefix . self::TBL_LOGS . "
			WHERE 1
			" );


		echo '<h2>'. __('Sent email messages log', self::PLUGIN_NAME) . '</h2>';
		
		if ($wpdb->num_rows > 0) {

			$result_html = "";
			foreach ( $result as $item )
			{
				$result_html .= "<tr/><td>$item->time</td><td>$item->fname</td><td>$item->lname</td><td>$item->email</td><td>$item->subject</td><td style=\"width: 400px;\">".mb_strimwidth((htmlspecialchars_decode($item->message)), 0, 500, " ...")."</td><tr/>";
			}
			echo '<table>';
			echo '<tr><td>'. __('Timestamp', self::PLUGIN_NAME) . '</td><td>'. __('First name', self::PLUGIN_NAME) . '</td><td>'. __('Last name', self::PLUGIN_NAME) . '</td><td>'. __('Email', self::PLUGIN_NAME) . '</td><td>'. __('Subject', self::PLUGIN_NAME) . '</td><td>'. __('Message', self::PLUGIN_NAME) . '</td></tr>';
			echo $result_html;
			echo '</table>';

		} else {

			echo '<p>'. __('No log records yet.', self::PLUGIN_NAME) . '</p>';
		}

	}
	
	function vvp_form_setting_menu_page(){
	 
		add_submenu_page(
			'options-general.php',
			__('VVP_Form Settings', self::PLUGIN_NAME),
			'VVP_Form Settings',
			'manage_options',
			self::PLUGIN_NAME,
			array( $this, 'vvp_form_page_callback')
		);
	}

	function vvp_form_page_callback(){

		echo '<div class="wrap">
			<h1>' . get_admin_page_title() . '</h1>
			<form method="post" action="options.php">';

		settings_fields( 'vvp_form_settings' );
		do_settings_sections( self::PLUGIN_NAME );
		submit_button();

		echo '</form></div>';

	}
		
	function vvp_form_fields(){
	 
		// register custom plugin's settings
		register_setting(
			'vvp_form_settings', // settings group id
			'vvp_form_settings_email', // setting id
			''
		);
		register_setting(
			'vvp_form_settings',
			'vvp_form_settings_subject',
			''
		);
		register_setting(
			'vvp_form_settings',
			'vvp_form_settings_hapikey',
			''
		);
	 
		// adding settings section
		add_settings_section(
			'slider_settings_section_id', // settings section id
			'Settings',
			'',
			self::PLUGIN_NAME // slug
		);
	 
		// adding fields
		add_settings_field(
			'vvp_form_settings_email',
			'Email',
			 array( $this, 'vvp_form_settings_field'), // field callback function
			self::PLUGIN_NAME, // slug
			'slider_settings_section_id', // settings section id
			array(		// optional params
				'label_for' => 'vvp_form_settings_email',
				'class' => 'tr-class',
				'name' => 'vvp_form_settings_email', // 
			)
		);
		add_settings_field(
			'vvp_form_settings_subject',
			'Subject',
			 array( $this, 'vvp_form_settings_field'),
			self::PLUGIN_NAME,
			'slider_settings_section_id',
			array(
				'label_for' => 'vvp_form_settings_subject',
				'class' => 'tr-class',
				'name' => 'vvp_form_settings_subject',
			)
		);
		add_settings_field(
			'vvp_form_settings_hapikey',
			'HubSpot API key',
			 array( $this, 'vvp_form_settings_field'),
			self::PLUGIN_NAME,
			'slider_settings_section_id',
			array(
				'label_for' => 'vvp_form_settings_hapikey',
				'class' => 'tr-class',
				'name' => 'vvp_form_settings_hapikey',
			)
		);
	}

	function vvp_form_settings_field( $args ){

		// get saved setting value from DB
		$value = get_option( $args[ 'name' ] );
	 
		printf(
			'<input type="text" name="%s" value="%s" />',
			esc_attr( $args[ 'name' ] ),
			( $value )
		);
	 
	}
}

new VVP_Form();