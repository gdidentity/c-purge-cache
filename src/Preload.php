<?php

namespace CPurgeCache;

class Preload
{
    public static function init()
    {
        // add_action('cloudflare_purge_preload', function ($urls) {
        //     foreach ($urls as $url) {
        //         $response = wp_remote_get(esc_url_raw($url), [
        //             'timeout'    => 0.01,
        //             'blocking'   => false,
        //             'user-agent' => 'Preload',
        //             'sslverify'  => apply_filters('https_local_ssl_verify', false),
        //         ]);
        //     }
        // }, 10, 1);
    }

    public static function preload($urls = [])
    {
        if (!$urls) {
            return;
        }

        // Schedule cache preload in 30 seconds
        wp_schedule_single_event(time() + 30, 'cloudflare_purge_preload', [$urls]);
    }
}
