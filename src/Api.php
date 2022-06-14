<?php

namespace CPurgeCache;

use CPurgeCache\Admin\Settings;
use CPurgeCache\Purge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Api {

	public static function init() {
		add_action('rest_api_init', function () {
			$namespace = 'cpc/v1';

			if ( Settings::get( 'endpoint', 'off', 'c_purge_cache_purge_settings' ) === 'on' ) {
				register_rest_route($namespace, 'purge', [
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => function () {
						return true;
					},
					'callback'            => function ( WP_REST_Request $request ) {
						$authorization_header = $request->get_headers()['authorization'][0];
						$secret               = Settings::get( 'secret', '', 'c_purge_cache_purge_settings' );

						if ( $authorization_header !== $secret ) {
							return new WP_Error( 'rest_forbidden', __( 'Unauthorized', 'c-purge-cache' ), [ 'status' => 401 ] );
						}

						return new WP_REST_Response( Purge::purge() );
					},
				]);
			}
		});
	}
}
