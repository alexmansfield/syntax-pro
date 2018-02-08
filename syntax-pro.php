<?php
/*
Plugin Name: Syntax Pro
Plugin URI: https://themefoundation.com/
Description: Preserve and highlight syntax of code examples in the WordPress editor.
Version: 0.1.3
Author: ModularWP
Author URI: https://themefoundation.com/
License: GPLv2 or later
Text Domain: syntax_textdomain
*/


/**
 * Syntax plugin class
 *
 * @see https://github.com/dtbaker/wordpress-mce-view-and-shortcode-editor
 */
class MDLR_Syntax_Pro {

	/**
	 * Constructor function
	 *
	 * Runs when class is instantiated.
	 */
	function __construct() {
		add_action( 'admin_init',                array( $this, 'register_settings' ) );
		add_action( 'admin_head',                array( $this, 'enqueue_admin' ) );
		add_action( 'admin_menu',                array( $this, 'register_settings_page' ) );
		add_action( 'add_meta_boxes',            array( $this, 'add_meta' ) );
		add_action( 'save_post',                 array( $this, 'save_meta' ) );
		add_action( 'wp_enqueue_scripts',        array( $this, 'enqueue' ) );
		add_filter( 'mce_external_plugins',      array( $this, 'tinymce_plugin') );
		add_action( 'default_hidden_meta_boxes', array( $this, 'hide_meta_by_default' ), 10, 2 );
		add_action( 'admin_head',                array( $this, 'syntax_settings_styles' ) );

		add_filter( 'mce_buttons',               array( $this, 'tinymce_button' ) );
		add_filter( 'mce_css',                   array( $this, 'editor_style' ) );
	}

	/**
	 * Registers TinyMCE plugin
	 *
	 * @param array $plugin_array An array of regsitered TinyMCE plugins.
	 */
	public function tinymce_plugin( $plugin_array ){
		$plugin_array['mdlr_syntax_plugin'] = plugins_url( 'js/mce-plugin.js', __FILE__ );

		return $plugin_array;
	}

	/**
	 * Loads back end scripts
	 */
	public function enqueue_admin() {
		$current_screen = get_current_screen();

		// Is this a screen that requires any Syntax features?
		if ( isset( $current_screen->post_type ) && post_type_supports( $current_screen->post_type, 'editor' ) ) {

			$localize_prism = array(
				'languages'    =>  $this->format_languages(),
				'languageText' => __( 'Languages', 'syntax_textdomain' ),
				'codeText'     => __( 'Code', 'syntax_textdomain' ),
				'addCodeText'  => __( 'Add Code', 'syntax_textdomain' ),
			);

			wp_register_script( 'syntax-editor-js', plugins_url( 'js/editor.js', __FILE__ ), array( 'wp-util', 'jquery' ), false, true );
			wp_localize_script( 'syntax-editor-js', 'syntax', $localize_prism );
			wp_enqueue_script( 'syntax-editor-js' );
		}
	}

	/**
	 * Format languages
	 *
	 * Format languages in a useful way for adding a language
	 * dropdown list in TinyMCE.
	 */
	public function format_languages() {
		$all_languages = $this->language_list();
		$enabled_languages = get_option( 'syntax_languages' );

		// Are any languages enabled?
		if ( !empty( $enabled_languages ) ) {

			$formatted_languages = array();
			$formatted_languages[] = array( 'text' => '', 'value' => '' );

			foreach ( $all_languages as $label => $value ) {
				if ( in_array( $value, $enabled_languages ) ) {
					$formatted_languages[] = array( 'text' => $label, 'value' => $value );
				}
			}

			return json_encode( $formatted_languages );
		}
	}

	/**
	 * Adds stylesheet to editor
	 *
	 * Format languages in a useful way for adding a language
	 * dropdown list in TinyMCE.
	 *
	 * @param string $mce_css Comma-delimited list of stylesheets to load in TinyMCE.
	 */
	public function editor_style( $mce_css ) {
		$mce_css .= ', ' . plugins_url( 'css/prism.css', __FILE__ );
		return $mce_css;
	}

	/**
	 * Adds syntax button to array
	 *
	 * Adds syntax button to array of TinyMCE buttons.
	 *
	 * @param array $buttons Array of TinyMCE buttons.
	 */
	public function tinymce_button( $buttons ){
		$kitchen_sink = array_pop ( $buttons );
		array_push( $buttons, 'mdlr_syntax' );
		array_push( $buttons, $kitchen_sink );

		return $buttons;
	}

	/**
	 * Hides meta box by default
	 *
	 * The user can toggle the visibility of each meta box. This function
	 * ensures that the meta box is hidden by default.
	 *
	 * @param array $hidden An array of meta boxes that are hidden by default.
	 * @param $screen The current screen of the WP admin.
	 */
	public function hide_meta_by_default( $hidden, $screen ) {
		array_push( $hidden, 'mdlr_syntax_meta' );

		return $hidden;
	}

	/**
	 * Adds a meta box to all post types that support the WordPress editor
	 */
	public function add_meta() {
		$suported_post_types = get_post_types_by_support( 'editor' );
		add_meta_box(
			'mdlr_syntax_meta',
			__( 'Syntax', 'syntax_textdomain' ),
			array( $this, 'meta_callback' ),
			$suported_post_types
		);
	}


	/**
	 * Outputs the content of the meta box
	 *
	 * @param object $post The current post object.
	 */
	function meta_callback( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'syntax_nonce' );
		$saved_meta = get_post_meta( $post->ID );
		?>
			<p>
				<label for="syntax-data"><?php _e( 'JSON representation of all the code in the post. This protects the code from formatting imposed by the editor.', 'syntax_textdomain' )?></label>
				<textarea name="syntax-data" id="syntax-storage" style="width: 100%; height: 200px;"><?php if ( isset ( $saved_meta['_syntax_data'] ) ) echo $saved_meta['_syntax_data'][0]; ?></textarea>
			</p>
		<?php
	}

	/**
	 * Saves the custom meta input
	 *
	 * @param int $post_id The current post ID.
	 */
	function save_meta( $post_id ) {

		// Checks save status
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST[ 'syntax_nonce' ] ) && wp_verify_nonce( $_POST[ 'syntax_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';

		// Exits script depending on save status
		if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
			return;
		}

		// Checks for input and sanitizes/saves if needed
		if( isset( $_POST[ 'syntax-data' ] ) ) {
			update_post_meta( $post_id, '_syntax_data', esc_html( $_POST[ 'syntax-data' ] ) );
		}

	}

	/**
	 * Loads front end scripts
	 */
	public function enqueue() {

		// Enqueue prism.js scripts.
		wp_register_script( 'mdlr-syntax-prism-js', plugins_url( 'js/prism.js', __FILE__ ) );
		$localize_prism = array(
			'components' =>  plugins_url( 'js/prism-languages/', __FILE__ ),

		);
		wp_localize_script( 'mdlr-syntax-prism-js', 'syntax', $localize_prism );
		wp_enqueue_script( 'mdlr-syntax-prism-js' );

		// Enqueue prism.js styles.
		wp_enqueue_style( 'mdlr-syntax-prism-css', plugins_url( 'css/prism.css', __FILE__ ) );
	}

	/**
	 * Registers the plugin settings page
	 */
	public function register_settings_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Syntax Pro Settings', 'syntax_textdomain' ),
			__( 'Syntax Pro', 'syntax_textdomain' ),
			'manage_options',
			'syntax',
			array( $this, 'settings_page_content' )
		);
	}

	/**
	 * Registers the individual plugin settings
	 */
	public function register_settings() {
		add_settings_section(
			'syntax_fields',     // ID used to identify this section and with which to register options
			'',                  // Title to be displayed on the administration page
			'__return_false',    // Callback used to render the description of the section
			'syntax'             // Page on which to add this section of options
		);

		add_settings_field(
			'syntax_languages',
			__( 'Choose which languages to enable', 'syntax_textdomain' ),
			array( $this, 'display_language_fields' ),
			'syntax',
			'syntax_fields',
			array( __( 'Activate this setting to display the header.', 'syntax_textdomain' ) )
		);

		register_setting(
			'syntax_fields',
			'syntax_languages',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Prepares the plugin settings to be saved to the database
	 */
	public function sanitize_settings( $input ) {
		$output = array();
		$languages = $this->language_list();

		// Loops through each of the incoming settings
		foreach( $input as $key => $value ) {
			if( isset( $input[$key] ) ) {
				if ( in_array( $input[$key], $languages ) ) {
					$output[$key] = $input[$key];
				}
			}
		}

		return $output;
	}

	/**
	 * Outputs the contents of the plugin settings page
	 */
	public function settings_page_content() {

		// Does the current user have permission to manage options?
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
			<div class="wrap">
				<h1><?= esc_html(get_admin_page_title()); ?></h1>
				<form action="options.php" method="post">
					<?php
						$syntax_fields = get_option( 'syntax_languages' );

						settings_fields('syntax_fields');
						do_settings_sections('syntax');
						submit_button( __( 'Save Settings', 'syntax_textdomain' ) ) ;
					?>
				</form>
			</div>
		<?php
	}

	/**
	 * Outputs the language settings checkboxes
	 */
	public function display_language_fields( $args ) {
		$enabled_languages = get_option( 'syntax_languages' );
		$languages = $this->language_list();
		$html = '<ul>';

		foreach ( $languages as $label => $value) {
			$checked = '';

			if ( !empty( $enabled_languages ) && in_array( $value, $enabled_languages ) ) {
				$checked = 'checked="checked"';
			}

			$html .= '<li><input type="checkbox" id="' . $value . '" name="syntax_languages[]" value="' . $value . '" ' . $checked . '/>';
			$html .= '<label for="' . $value . '">'  . $label . '</label></li>';
		}
		$html .= '</ul>';

		echo $html;
	}

	/**
	 * Adds styles for the plugin settings page
	 */
	public function syntax_settings_styles() {
		$screen = get_current_screen();
		if ( $screen->id == 'settings_page_syntax' ) {
			echo '<style>@media (min-width: 450px) { .form-table td ul { max-width: 1140px; column-count: 2;} } @media (min-width: 600px) { .form-table td ul { column-count: 3;} } @media (min-width: 1300px) { .form-table td ul { column-count: 4;} } @media (min-width: 1450px) { .form-table td ul { column-count: 5;} }</style>';
		}
	}

	/**
	 * Returns a list of all supported languages
	 */
	public function language_list() {
		$languages = array(
			__( 'Markup', 'syntax_textdomain' ) => 'markup',
			__( 'CSS', 'syntax_textdomain' ) => 'css',
			__( 'C-like', 'syntax_textdomain' ) => 'clike',
			__( 'JavaScript', 'syntax_textdomain' ) => 'javascript',

			__( 'ABAP', 'syntax_textdomain' ) => 'abap',
			__( 'ActionScript', 'syntax_textdomain' ) => 'actionscript',
			__( 'Ada', 'syntax_textdomain' ) => 'ada',
			__( 'Apache Configuration', 'syntax_textdomain' ) => 'apacheconf',
			__( 'APL', 'syntax_textdomain' ) => 'apl',
			__( 'AppleScript', 'syntax_textdomain' ) => 'applescript',
			__( 'AsciiDoc', 'syntax_textdomain' ) => 'asciidoc',
			__( 'ASP.NET (C#)', 'syntax_textdomain' ) => 'aspnet',
			__( 'AutoIt', 'syntax_textdomain' ) => 'autoit',
			__( 'AutoHotkey', 'syntax_textdomain' ) => 'autohotkey',
			__( 'Bash', 'syntax_textdomain' ) => 'bash',
			__( 'BASIC', 'syntax_textdomain' ) => 'basic',
			__( 'Batch', 'syntax_textdomain' ) => 'batch',
			__( 'Bison', 'syntax_textdomain' ) => 'bison',
			__( 'Brainfuck', 'syntax_textdomain' ) => 'brainfuck',
			__( 'Bro', 'syntax_textdomain' ) => 'bro',
			__( 'C', 'syntax_textdomain' ) => 'c',
			__( 'C#', 'syntax_textdomain' ) => 'csharp',
			__( 'C++', 'syntax_textdomain' ) => 'cpp',
			__( 'CoffeeScript', 'syntax_textdomain' ) => 'coffeescript',
			__( 'Crystal', 'syntax_textdomain' ) => 'crystal',
			__( 'CSS Extras', 'syntax_textdomain' ) => 'css-extras',
			__( 'D', 'syntax_textdomain' ) => 'd',
			__( 'Dart', 'syntax_textdomain' ) => 'dart',
			__( 'Django/Jinja2', 'syntax_textdomain' ) => 'django',
			__( 'Diff', 'syntax_textdomain' ) => 'diff',
			__( 'Docker', 'syntax_textdomain' ) => 'docker',
			__( 'Eiffel', 'syntax_textdomain' ) => 'eiffel',
			__( 'Elixir', 'syntax_textdomain' ) => 'elixir',
			__( 'Erlang', 'syntax_textdomain' ) => 'erlang',
			__( 'F#', 'syntax_textdomain' ) => 'fsharp',
			__( 'Fortran', 'syntax_textdomain' ) => 'fortran',
			__( 'Gherkin', 'syntax_textdomain' ) => 'gherkin',
			__( 'Git', 'syntax_textdomain' ) => 'git',
			__( 'GLSL', 'syntax_textdomain' ) => 'glsl',
			__( 'Go', 'syntax_textdomain' ) => 'go',
			__( 'GraphQL', 'syntax_textdomain' ) => 'graphql',
			__( 'Groovy', 'syntax_textdomain' ) => 'groovy',
			__( 'Haml', 'syntax_textdomain' ) => 'haml',
			__( 'Handlebars', 'syntax_textdomain' ) => 'handlebars',
			__( 'Haskell', 'syntax_textdomain' ) => 'haskell',
			__( 'Haxe', 'syntax_textdomain' ) => 'haxe',
			__( 'HTTP', 'syntax_textdomain' ) => 'http',
			__( 'Icon', 'syntax_textdomain' ) => 'icon',
			__( 'Inform 7', 'syntax_textdomain' ) => 'inform7',
			__( 'Ini', 'syntax_textdomain' ) => 'ini',
			__( 'J', 'syntax_textdomain' ) => 'j',
			__( 'Jade', 'syntax_textdomain' ) => 'jade',
			__( 'Java', 'syntax_textdomain' ) => 'java',
			__( 'Jolie', 'syntax_textdomain' ) => 'jolie',
			__( 'JSON', 'syntax_textdomain' ) => 'json',
			__( 'Julia', 'syntax_textdomain' ) => 'julia',
			__( 'Keyman', 'syntax_textdomain' ) => 'keyman',
			__( 'Kotlin', 'syntax_textdomain' ) => 'kotlin',
			__( 'LaTeX', 'syntax_textdomain' ) => 'latex',
			__( 'Less', 'syntax_textdomain' ) => 'less',
			__( 'LiveScript', 'syntax_textdomain' ) => 'livescript',
			__( 'LOLCODE', 'syntax_textdomain' ) => 'lolcode',
			__( 'Lua', 'syntax_textdomain' ) => 'lua',
			__( 'Makefile', 'syntax_textdomain' ) => 'makefile',
			__( 'Markdown', 'syntax_textdomain' ) => 'markdown',
			__( 'MATLAB', 'syntax_textdomain' ) => 'matlab',
			__( 'MEL', 'syntax_textdomain' ) => 'mel',
			__( 'Mizar', 'syntax_textdomain' ) => 'mizar',
			__( 'Monkey', 'syntax_textdomain' ) => 'monkey',
			__( 'NASM', 'syntax_textdomain' ) => 'nasm',
			__( 'nginx', 'syntax_textdomain' ) => 'nginx',
			__( 'Nim', 'syntax_textdomain' ) => 'nim',
			__( 'Nix', 'syntax_textdomain' ) => 'nix',
			__( 'NSIS', 'syntax_textdomain' ) => 'nsis',
			__( 'Objective-C', 'syntax_textdomain' ) => 'objectivec',
			__( 'OCaml', 'syntax_textdomain' ) => 'ocaml',
			__( 'Oz', 'syntax_textdomain' ) => 'oz',
			__( 'PARI/GP', 'syntax_textdomain' ) => 'parigp',
			__( 'Parser', 'syntax_textdomain' ) => 'parser',
			__( 'Pascal', 'syntax_textdomain' ) => 'pascal',
			__( 'Perl', 'syntax_textdomain' ) => 'perl',
			__( 'PHP', 'syntax_textdomain' ) => 'php',
			__( 'PHP Extras', 'syntax_textdomain' ) => 'php-extras',
			__( 'PowerShell', 'syntax_textdomain' ) => 'powershell',
			__( 'Processing', 'syntax_textdomain' ) => 'processing',
			__( 'Prolog', 'syntax_textdomain' ) => 'prolog',
			__( '.properties', 'syntax_textdomain' ) => 'properties',
			__( 'Protocol Buffers', 'syntax_textdomain' ) => 'protobuf',
			__( 'Puppet', 'syntax_textdomain' ) => 'puppet',
			__( 'Pure', 'syntax_textdomain' ) => 'pure',
			__( 'Python', 'syntax_textdomain' ) => 'python',
			__( 'Q', 'syntax_textdomain' ) => 'q',
			__( 'Qore', 'syntax_textdomain' ) => 'qore',
			__( 'R', 'syntax_textdomain' ) => 'r',
			__( 'React JSX', 'syntax_textdomain' ) => 'jsx',
			__( 'Reason', 'syntax_textdomain' ) => 'reason',
			__( 'reST (reStructuredText)', 'syntax_textdomain' ) => 'rest',
			__( 'Rip', 'syntax_textdomain' ) => 'rip',
			__( 'Roboconf', 'syntax_textdomain' ) => 'roboconf',
			__( 'Ruby', 'syntax_textdomain' ) => 'ruby',
			__( 'Rust', 'syntax_textdomain' ) => 'rust',
			__( 'SAS', 'syntax_textdomain' ) => 'sas',
			__( 'Sass (Sass)', 'syntax_textdomain' ) => 'sass',
			__( 'Sass (Scss)', 'syntax_textdomain' ) => 'scss',
			__( 'Scala', 'syntax_textdomain' ) => 'scala',
			__( 'Scheme', 'syntax_textdomain' ) => 'scheme',
			__( 'Smalltalk', 'syntax_textdomain' ) => 'smalltalk',
			__( 'Smarty', 'syntax_textdomain' ) => 'smarty',
			__( 'SQL', 'syntax_textdomain' ) => 'sql',
			__( 'Stylus', 'syntax_textdomain' ) => 'stylus',
			__( 'Swift', 'syntax_textdomain' ) => 'swift',
			__( 'Tcl', 'syntax_textdomain' ) => 'tcl',
			__( 'Textile', 'syntax_textdomain' ) => 'textile',
			__( 'Twig', 'syntax_textdomain' ) => 'twig',
			__( 'TypeScript', 'syntax_textdomain' ) => 'typescript',
			__( 'Verilog', 'syntax_textdomain' ) => 'verilog',
			__( 'VHDL', 'syntax_textdomain' ) => 'vhdl',
			__( 'vim', 'syntax_textdomain' ) => 'vim',
			__( 'Wiki markup', 'syntax_textdomain' ) => 'wiki',
			__( 'Xojo (REALbasic)', 'syntax_textdomain' ) => 'xojo',
			__( 'YAML', 'syntax_textdomain' ) => 'yaml'
		);

		return $languages;
	}
}

new MDLR_Syntax_Pro;
