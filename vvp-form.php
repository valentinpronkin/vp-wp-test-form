<?php
/*
	Plugin Name: Demo contact form
	Plugin URI: 
	Description: Demo contact form plugin description
	Tags: form, contact, email, mail
	Author: VVP
	Author URI:
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
	
		add_filter( 'the_content', array( $this, 'vvp_form' ), 10, 1 );

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		
		wp_enqueue_script( self::PLUGIN_NAME , plugin_dir_url( __FILE__ ) . 'js/script.js', array( 'jquery' ), null, true );
		
		wp_localize_script( self::PLUGIN_NAME, 'settings', array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			)
		);
		add_action( 'wp_ajax_nopriv_send_form', array( $this, 'send_form' ) );
		add_action( 'wp_ajax_send_form', array( $this, 'send_form' ) );
		
		
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
        $sql = "CREATE TABLE $table (
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

	}

	public function vvp_form( $content ) {

		$content .= '<div  class="vvp_form" id="vvp_form_1" method="POST" action="">
						<label for="vvp_fname">First name:</label>
						<input type="text" id="vvp_fname" placeholder="Enter your first name" required>

						<label for="vvp_lname">Last name:</label>
						<input type="text" id="vvp_lname" placeholder="Enter your last name" required>

						<label for="vvp_subject">Subject:</label>
						<input type="text" id="vvp_subject" placeholder="Enter subject" required>

						<label for="vvp_message">Message:</label>
						<textarea id="vvp_message" placeholder="Your message"></textarea>

						<label for="vvp_email">Email:</label>
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
		$message = htmlspecialchars($data['vvp_message']);
		

		// backend validations
		if (!preg_match("/^[a-zA-Z-' ]*$/", $fname)) {
			wp_send_json_error('Only letters and white space allowed in first name');
		}
		if (!preg_match("/^[a-zA-Z-' ]*$/", $lname)) {
			wp_send_json_error('Only letters and white space allowed in first name');
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			wp_send_json_error('Invalid email format!');
		}

		if ( wp_mail( get_option( 'vvp_form_settings_email' ) , get_option( 'vvp_form_settings_subject' ), '<h2>'.$subject."</h2>\r\n".$message ) ) {
			
			// Add sent message to log (db table)
			global $wpdb;  
			$wpdb->insert($wpdb->prefix . self::TBL_LOGS, array('fname' => $fname, 'lname' => $lname,  'subject' => $subject, 'email' => $email, 'message' => $message)); 

			wp_send_json_success('Sent successfully!');
			
		} else {
			wp_send_json_error('Something went wrong!');
		}
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

		$result_html = "";
		foreach ( $result as $item )
		{
			$result_html .= "<tr/><td>$item->time</td><td>$item->fname</td><td>$item->lname</td><td>$item->email</td><td>$item->subject</td><td style=\"width: 400px;\">".mb_strimwidth((htmlspecialchars_decode($item->message)), 0, 500, " ...")."</td><tr/>";
		}
		echo '<h2>Sent email messages log</h2>';
		echo '<table>';
		echo '<tr><td>Timestamp</td><td>First name</td><td>Last name</td><td>Email</td><td>Subject</td><td>Message</td></tr>';
		echo $result_html;
		echo '</table>';
	}
	
	function vvp_form_setting_menu_page(){
	 
		add_submenu_page(
			'options-general.php',
			'VVP_Form Settings', // тайтл страницы
			'VVP_Form Settings', // текст ссылки в меню
			'manage_options', // права пользователя, необходимые для доступа к странице
			self::PLUGIN_NAME, // ярлык страницы
			array( $this, 'vvp_form_page_callback') // функция, которая выводит содержимое страницы
		);
	}

	function vvp_form_page_callback(){

		echo '<div class="wrap">
			<h1>' . get_admin_page_title() . '</h1>
			<form method="post" action="options.php">';

		settings_fields( 'vvp_form_settings' ); // settings group id
		do_settings_sections( self::PLUGIN_NAME ); // ярлык страницы, не более
		submit_button(); // функция для вывода кнопки сохранения

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
			'vvp_form_settings', // settings group id
			'vvp_form_settings_subject', // setting id
			''
		);
	 
		// adding settings section
		add_settings_section(
			'slider_settings_section_id', // settings section id
			'Settings', // заголовок (не обязательно)
			'', // функция для вывода HTML секции (необязательно)
			self::PLUGIN_NAME // ярлык страницы
		);
	 
		// addinf fields
		add_settings_field(
			'vvp_form_settings_email',
			'Email',
			 array( $this, 'vvp_form_settings_field'), // field callback function
			self::PLUGIN_NAME, // ярлык страницы
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
			 array( $this, 'vvp_form_settings_field'), // field callback function
			self::PLUGIN_NAME, // ярлык страницы
			'slider_settings_section_id', // settings section id
			array(		// optional params
				'label_for' => 'vvp_form_settings_subject',
				'class' => 'tr-class',
				'name' => 'vvp_form_settings_subject',
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








// Send email messages with HTML formatting
function vvp_form_set_email_content_type(){
    return "text/html";
}
add_filter( 'wp_mail_content_type','vvp_form_set_email_content_type' );


new VVP_Form();