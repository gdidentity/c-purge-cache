<?php

namespace CPurgeCache;

use CPurgeCache\Settings;
use CPurgeCache\Purge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Api
{
    public static function init()
    {
        add_action('rest_api_init', function () {
            $namespace = 'cpc/v1';
            $settings = Settings::get();

            if (isset($settings['purge_everything_endpoint'])) {
                register_rest_route($namespace, 'purge', [
                'methods'   => WP_REST_Server::EDITABLE,
                'permission_callback' =>  function () {
                    return true;
                },
                'callback'  => function (WP_REST_Request $request) {
                    $authorizationHeader = $request->get_headers()['authorization'][0];
                    $secret = Settings::get()['purge_everything_secret'];

                    if ($authorizationHeader !== "Bearer $secret") {
                        return new WP_Error('rest_forbidden', __('Unauthorized', 'c-purge-cache'), [ 'status' => 401 ]);
                    }

                    return new WP_REST_Response(Purge::purge());
                }
            ]);
            }
        });
    }
}
