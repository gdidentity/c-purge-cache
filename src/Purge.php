<?php

namespace CPurgeCache;

use CPurgeCache\Admin\Settings;
use CPurgeCache\Preload;
use CPurgeCache\Helpers;
use WP_Error;

class Purge {

	public static function init() {
		if ( ! class_exists( 'OnDemandRevalidation' ) ) {
			add_action('wp_insert_post', function ( $post_ID, $post, $update ) {
				if ( wp_is_post_revision( $post_ID ) ) {
					return;
				}

				wp_schedule_single_event( time(), 'c_purge_cache_on_post_update', [ $post_ID ] );
			}, 100, 3);

			add_action('transition_post_status', function ( $new_status, $old_status, $post ) {
				wp_schedule_single_event( time(), 'c_purge_cache_on_post_update', [ $post->ID ] );
			}, 100, 3);

			add_action('c_purge_cache_on_post_update', function ( $post_id ) {
				$post = get_post( $post_id );
				self::purge( $post );
			}, 10, 1);
		}
	}

	public static function purge( $post = null ) {
		$zone_id      = Settings::get( 'zone_id' );
		$api_token    = Settings::get( 'api_token' );
		$frontend_url = Settings::get( 'frontend_url' );

		if ( ! ( $zone_id || $api_token ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Fill Cloudflare credentials first.', 'c-purge-cache' ), [ 'status' => 401 ] );
		}

		$post = ! ( Settings::get( 'purge_everything', 'on', 'c_purge_cache_post_update_settings' ) === 'on' ) ? $post : null;

		$data = [ 'purge_everything' => true ];
		$urls = [];

		if ( $post ) {
			$page_url = str_replace( get_site_url(), trim( $frontend_url ), get_permalink( $post->ID ) );

			if ( filter_var( $page_url, FILTER_VALIDATE_URL ) ) {
				$urls[] = substr( $page_url, -1 ) === '/' ? substr( $page_url, 0, -1 ) : $page_url;
			}

			if ( isset( $settings['purge_home_url'] ) ) {
				$urls[] = trim( $frontend_url );
			}

			$purge_urls = trim( Settings::get( 'purge_urls', '', 'c_purge_cache_post_update_settings' ) );
			$purge_urls = preg_split( '/\r\n|\n|\r/', $purge_urls );
			$purge_urls = Helpers::rewriteUrls( $purge_urls, $post );

			if ( $purge_urls ) {
				foreach ( $purge_urls as $url ) {
					if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
						$urls[] = $url;
					}
				}
			}

			$urls = apply_filters( 'c_purge_cache_urls', $urls, $post );

			$data = [ 'files' => $urls ];
		}

		$response = wp_remote_post( "https://api.cloudflare.com/client/v4/zones/$zone_id/purge_cache", [
			'body'    => json_encode( $data ),
			'headers' => [
				'Authorization' => "Bearer $api_token",
				'Content-Type'  => 'application/json',
			],
		]);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$response_data = ( ! is_wp_error( $response ) ) ? $body : null;

		if ( ! $response_data['success'] ) {
			$error = $response_data['errors'][0];
			return new WP_Error( 'cloudflare_error', $error['message'], [ 'status' => $error['code'] ] );
		}

		// if ( $urls ) {
		// 	Preload::preload( $urls );
		// }

		$purged = $urls ? implode( ', ', $urls ) : 'everything';

		return (object) [
			'success' => $response_data['success'],
			'message' => "Cloudflare Cache purged $purged successfully. Please allow up to 30 seconds for changes to take effect.",
		];
	}

	public static function purgeEverythingButton() {
		if ( Settings::get( 'admin_button', 'off', 'c_purge_cache_purge_settings' ) === 'on' ) {
			add_action('admin_bar_menu', function ( $admin_bar ) {
				global $pagenow;
				$admin_bar->add_menu([
					'id'    => 'c-purge-cache-everything',
					'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Purge All Cache</span>',
				]);
			}, 100);
		}

		add_action('admin_footer', function () { ?>
			<style>
				#wpadminbar #wp-admin-bar-c-purge-cache-everything .ab-icon:before {
					content: "\f176";
					top: 2px;
					color: #EE821E;
				}
			</style>
			<script type="text/javascript" >
				jQuery('li#wp-admin-bar-c-purge-cache-everything .ab-item').on('click', cPurgeEverything);
				jQuery('#c-purge-cache-everything-settings').on('click', cPurgeEverything);

				function cPurgeEverything () {
					jQuery.post(ajaxurl, { action: 'c-purge_everything' }, function(response) {
						purgeAlert(response)
					});
				}

				jQuery('#c-purge-cache-post-update-test').on('click', function () {
					jQuery.post(ajaxurl, { action: 'c-purge_post_update_test' }, function(response) {
						purgeAlert(response)
					});
				});

				function purgeAlert (response) {
					alert(
						response?.message ||
						(response?.errors?.rest_forbidden && response?.errors?.rest_forbidden[0]) ||
						(response?.errors?.cloudflare_error && response.errors.cloudflare_error[0]) ||
						JSON.stringify(response.errors)
					);
				}

			</script>
			<?php
		});

		add_action('wp_ajax_c-purge_everything', function () {
			if ( ! current_user_can( 'edit_posts' ) ) {
				$response = new WP_Error( 'rest_forbidden', __( 'You cannot edit posts.', 'c-purge-cache' ), [ 'status' => 401 ] );
			}

			$response = self::purge();

			wp_send_json( $response );
			wp_die();
		});

		add_action('wp_ajax_c-purge_post_update_test', function () {
			if ( ! current_user_can( 'edit_posts' ) ) {
				$response = new WP_Error( 'rest_forbidden', __( 'You cannot edit posts.', 'c-purge-cache' ), [ 'status' => 401 ] );
			}

			$latest_post = get_posts([
				'numberposts' => 1,
				'post_status' => 'publish',
			])[0];
			$response    = self::purge( $latest_post );

			wp_send_json( $response );
			wp_die();
		});
	}
}
