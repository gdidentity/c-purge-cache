<?php

namespace CPurgeCache\Admin;

use CPurgeCache\Admin\SettingsRegistry;
use CPurgeCache\Purge;

class Settings {

	/**
	 * @var SettingsRegistry
	 */
	public $settings_api;


	/**
	 * Initialize the Settings Pages
	 *
	 * @return void
	 */
	public function init() {
		$this->settings_api = new SettingsRegistry();
		add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		add_action( 'init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'initialize_settings_page' ] );
	}


	/**
	 * Add the options page to the WP Admin
	 *
	 * @return void
	 */
	public function add_options_page() {

		add_options_page(
			__( 'Cloudflare Purge Cache', 'c-purge-cache' ),
			__( 'Cloudflare Purge Cache', 'c-purge-cache' ),
			'manage_options',
			'c-purge-cache',
			[ $this, 'render_settings_page' ]
		);

	}

	/**
	 * Registers the settings fields
	 *
	 * @return void
	 */
	public function register_settings() {

		$this->settings_api->register_section( 'c_purge_cache_default_settings', [
			'title' => __( 'General', 'c-purge-cache' ),
		] );

		$this->settings_api->register_fields( 'c_purge_cache_default_settings', [
			[
				'name'  => 'zone_id',
				'label' => __( 'Zone ID', 'c-purge-cache' ),
				'type'  => 'text',
			],
			[
				'name'  => 'api_token',
				'label' => __( 'API Token', 'c-purge-cache' ),
				'desc'  => '<a href="https://developers.cloudflare.com/api/tokens/create" target="_blank">Create API Token</a> with <b>Zone.Cache Purge</b> permissions.',
				'type'  => 'password',
			],
			[
				'name'    => 'frontend_url',
				'label'   => __( 'Homepage URL', 'c-purge-cache' ),
				'type'    => 'text',
				'default' => get_site_url(),
			],
		] );

		$this->settings_api->register_section( 'c_purge_cache_purge_settings', [
			'title' => __( 'Purge Everything', 'c-purge-cache' ),
		] );

		$api_url = rest_url( 'cpc/v1/purge' );
		$secret  = self::get( 'secret', 'YOUR-SECRET', 'c_purge_cache_purge_settings' );

		$this->settings_api->register_fields( 'c_purge_cache_purge_settings', [
			[
				'name' => 'purge-button',
				'desc' => '<a id="c-purge-cache-everything-settings" class="button button-primary" style="margin-bottom: 15px;">Purge All Cache</a>',
				'type' => 'html',
			],
			[
				'name' => 'admin_button',
				'desc' => 'Show <b>Purge All Cache</b> button in admin bar</label>',
				'type' => 'checkbox',
			],
			[
				'name'  => 'endpoint',
				'label' => 'API Endpoint',
				'desc'  => 'Allow WP REST API endpoint for Purge Everything',
				'type'  => 'checkbox',
			],
			[
				'name'    => 'secret',
				'label'   => __( 'Endpoint Secret', 'c-purge-cache' ),
				'type'    => 'text',
				'default' => wp_generate_uuid4(),
			],
			[
				'name' => 'curl-example',
				'desc' => "<p class='description'><code>curl -X PUT '$api_url' -H 'Authorization: Bearer $secret' -H 'Content-Type:application/json'</code></p>",
				'type' => 'html',
			],
		] );

		$this->settings_api->register_section( 'c_purge_cache_post_update_settings', [
			'title' => __( 'On post update', 'c-purge-cache' ),
			'desc'  => __( 'On post update is current page purged automatically.', 'c-purge-cache' ),
		] );

		$frontend_url = self::get( 'frontend_url', get_site_url() );

		$this->settings_api->register_fields( 'c_purge_cache_post_update_settings', [
			[
				'name'    => 'purge_home_url',
				'desc'    => 'Purge Homepage URL on post update',
				'type'    => 'checkbox',
				'default' => 'on',
			],
			[
				'name' => 'purge_everything',
				'desc' => 'Purge Everything on post update',
				'type' => 'checkbox',
			],
			[
				'name'        => 'purge_urls',
				'label'       => __( 'Additional URLs to purge on Post update', 'c-purge-cache' ),
				'desc'        => 'One URL per row.<br/><br/><i>Available current Post placeholders:</i><br/><code>%slug%</code> <code>%author_nicename%</code> <code>%categories%</code> <code>%tags%</code>',
				'placeholder' => "$frontend_url/category/%categories%",
				'type'        => 'textarea',
			],
			[
				'name'  => 'test-config',
				'label' => __( 'Test your config:', 'c-purge-cache' ),
				'desc'  => '<a id="c-purge-cache-post-update-test" class="button button-primary" style="margin-bottom: 15px;">Purge Latest Post Cache</a>',
				'type'  => 'html',
			],
		] );

	}

	/**
	 * Initialize the settings admin page
	 *
	 * @return void
	 */
	public function initialize_settings_page() {
		$this->settings_api->admin_init();

		if ( is_admin() && current_user_can( 'edit_posts' ) ) {
			Purge::purgeEverythingButton();
		}
	}

	/**
	 * Render the settings page in the admin
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		?>
		<div class="wrap">
			<?php
			echo '<h1>Cloudflare Purge Cache</h1>';
			$this->settings_api->show_navigation();
			$this->settings_api->show_forms();
			?>
		</div>
		<?php
	}

	/**
	 * Get field value
	 */
	public static function get( string $option_name, $default = '', $section_name = 'c_purge_cache_default_settings' ) {

		$section_fields = get_option( $section_name );

		return isset( $section_fields[ $option_name ] ) ? $section_fields[ $option_name ] : $default;
	}

}
