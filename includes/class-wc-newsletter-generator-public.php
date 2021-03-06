<?php
/**
 * Handling public facing mechanism
 */
class WC_Newsletter_Generator_Public{
	var $wc_newsletter_generator;

	function __construct(){
		$this->wc_newsletter_generator = new WC_Newsletter_Generator;

		add_filter( 'template_include', array( $this, 'route_template' ) );

		add_action( 'wp', array( $this, 'unhook_head_footer') );

		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_enqueue_styles_scripts'), 1000 );
	}

	/**
	 * Unhooking hooks from wp_head
	 * 
	 * @return void
	 */
	function unhook_head_footer(){
		if( is_singular( 'newsletter' ) && wcng_current_user_can_edit_newsletter() ){
			global $wp_filter;

			// Allowable actions
			if( wcng_is_previewing() ){
				$allowable_wp_head_actions 		= array( 'wp_enqueue_scripts', 'wp_print_styles', 'wp_print_head_scripts' );
				$allowable_wp_footer_actions 	= array( 'wp_print_footer_scripts', 'wc_print_js', 'wp_print_media_templates' );
			} else {
				$allowable_wp_head_actions 		= array();
				$allowable_wp_footer_actions 	= array();
			}

			// Unhook actions from wp_head
			foreach ( $wp_filter['wp_head'] as $priority => $wp_head_hooks ) {
				if( is_array( $wp_head_hooks ) ){
					foreach ( $wp_head_hooks as $wp_head_hook_register => $wp_head_hook ) {

						if( !is_array( $wp_head_hook['function'] ) && !in_array( $wp_head_hook['function'], $allowable_wp_head_actions ) ){
							remove_action( 'wp_head', $wp_head_hook['function'], $priority );							
						}
					}
				}
			}

			// Unhook actions from wp_footer
			foreach ($wp_filter['wp_footer'] as $priority => $wp_footer_hooks ) {
				if( is_array( $wp_footer_hooks ) ){
					foreach ( $wp_footer_hooks as $wp_footer_hook_register => $wp_footer_hook ) {

						if( !is_array( $wp_footer_hook['function'] ) && !in_array( $wp_footer_hook['function'], $allowable_wp_footer_actions ) ){
							remove_action( 'wp_footer', $wp_footer_hook['function'], $priority );							
						}

						if( is_array( $wp_footer_hook['function'] ) && isset( $wp_footer_hook['function'][1] ) && !in_array( $wp_footer_hook['function'][1], $allowable_wp_footer_actions ) ){
							remove_action( 'wp_footer', $wp_footer_hook_register, $priority );
						}

					}
				}
			}

			// Make sure that admin bar isn't loaded on preview page
			add_action( 'show_admin_bar', array( $this, 'hide_admin_bar') );					
		}
	}

	/**
	 * Hiding admin bar
	 */
	public function hide_admin_bar(){
		return false;
	}

	/**
	 * Removing enqueued styles and scripts
	 * 
	 * @return void
	 */
	function dequeue_enqueue_styles_scripts(){
		// Removing other scripts and styles on edit page
		if( is_singular( 'newsletter' ) && wcng_current_user_can_edit_newsletter() && wcng_is_previewing() ){
			global $wp_styles, $wp_scripts, $post;

			// Dequeued styles
			if( is_array( $wp_styles->queue ) ){
				foreach ( $wp_styles->queue as $style ) {
					wp_dequeue_style( $style );
				}				
			}

			// Dequeue scripts
			if( is_array( $wp_scripts->queue) ){
				foreach ( $wp_scripts->queue as $script ) {
					wp_dequeue_script( $script );
				}				
			}

			// Enqueue style
			wp_enqueue_style( 'wcng-front-end-editor', WC_NEWSLETTER_GENERATOR_URL . 'css/wc-newsletter-generator-front-end-editor.css', array(), 20140828, 'all' );
	
			// Enqueue scripts
			wp_enqueue_media();
			wp_register_script( 'jquery-velocity', WC_NEWSLETTER_GENERATOR_URL . 'js/jquery.velocity.js', array( 'jquery' ), '0.11.9', false );
			wp_enqueue_script( 'wcng-front-end-editor', WC_NEWSLETTER_GENERATOR_URL . 'js/wc-newsletter-generator-front-end-editor.js', array( 'jquery', 'jquery-velocity' ), 20140828, false );

			// Attaching variables for scripts
			$wcng_params = array(
				'post_id' 					=> $post->ID,
				'_n_update'							=> wp_create_nonce( 'update_' . $post->ID ),
				'_n_get_products'					=> wp_create_nonce( 'get_products_' . $post->ID ),
				'endpoint'							=> site_url( '/wp-admin/admin-ajax.php?action=wcng_endpoint' ),
				'loading_message_update'			=> __( 'Saving your update on: ', 'woocommerce-newsletter-generator' ),
				'loading_message_update_end' 		=> __( 'Update Saved!', 'woocommerce-newsletter-generator' ),
				'label_select_image'				=> __( 'Select Image', 'woocommerce-newsletter-generator' ),
				'label_products_have_been_displayed' => __( 'All products have been displayed!', 'woocommerce-newsletter-generator' ),
				'label_error_getting_data'			=> __( 'Error getting data. Please try again.', 'woocommerce-newsletter-generator')
			);
			wp_localize_script( 'wcng-front-end-editor', 'wcng_params', $wcng_params );
		}
	}

	/**
	 * Routing single page to custom template
	 * 
	 * @return string of path
	 */
	function route_template( $single_template ){
		global $wp_query, $post;

		if( is_singular( 'newsletter' ) ){
			// Get template path
			$template_path = $this->wc_newsletter_generator->get_template_path( $post->ID );

			if( $template_path ){
				return $template_path;
			} else {
				// If there's no template assigned, warn user to set one first
				wp_die( __( 'Please set a template for this newsletter first.', 'woocommerce-newsletter-generator' ) );
			}
		} else {
			return $single_template;
		}
	}
}
new WC_Newsletter_Generator_Public;