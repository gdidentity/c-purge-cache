<?php

namespace CPurgeCache;

use CPurgeCache\Settings;
use CPurgeCache\Preload;
use CPurgeCache\Helpers;
use WP_Error;

class Purge
{
    public static function init()
    {
        add_action('wp_insert_post', function ($post_ID, $post, $update) {
            if (wp_is_post_revision($post_ID)) {
                return;
            }

            wp_schedule_single_event(time(), 'c_purge_cache_on_post_update', [$post_ID]);
        }, 100, 3);

        add_action('transition_post_status', function ($new_status, $old_status, $post) {
            wp_schedule_single_event(time(), 'c_purge_cache_on_post_update', [$post->ID]);
        }, 100, 3);

        add_action('c_purge_cache_on_post_update', function ($postId) {
            self::purge(!isset(Settings::get()['purge_everything_on_update']) ? $postId : '');
        }, 10, 1);
    }

    public static function purge($postId= '')
    {
        $settings = Settings::get();

        if (!($settings['zone_id'] || $settings['api_token'])) {
            return new WP_Error('rest_forbidden', __('Fill Cloudflare credentials first.', 'c-purge-cache'), [ 'status' => 401 ]);
        }

        $data = ['purge_everything' => true];

        if ($postId) {
            $files = [];
            $pageUrl = str_replace(get_site_url(), trim($settings['frontend_url']), get_permalink($postId));

            if (filter_var($pageUrl, FILTER_VALIDATE_URL)) {
                $files[] = substr($pageUrl, -1) == '/' ? substr($pageUrl, 0, -1) : $pageUrl;
            }

            if (isset($settings['purge_home_url'])) {
                $files[] = trim($settings['frontend_url']);
            }

            $purgeUrls = trim($settings['purge_urls']);
            $purgeUrls = preg_split('/\r\n|\n|\r/', $purgeUrls);
            $purgeUrls = Helpers::rewriteUrls($purgeUrls, $postId);

            if ($purgeUrls) {
                foreach ($purgeUrls as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $files[] = $url;
                    }
                }
            }

            $data = ['files' => $files];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.cloudflare.com/client/v4/zones/'. $settings['zone_id'] .'/purge_cache');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer '. $settings['api_token']
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($ch));
        curl_close($ch);

        if (!$response->success) {
            $error = $response->errors[0];
            return new WP_Error('cloudflare_error', $error->message, [ 'status' => $error->code ]);
        }

        if ($files) {
            Preload::preload($files);
        }

        $purged = $files? implode(', ', $files) : 'everything';

        return (object) [
            'success' => $response->success,
            'message' => "Cloudflare Cache purged $purged successfully. Please allow up to 30 seconds for changes to take effect."
        ];
    }

    public static function purgeEverythingButton()
    {
        $settings = Settings::get();

        if (isset($settings['purge_everything'])) {
            add_action('admin_bar_menu', function ($admin_bar) {
                global $pagenow;
                $admin_bar->add_menu([
                    'id'	=> 'c-purge-cache-everything',
                    'title'	=> '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Purge All Cache</span>',
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
						alert(response?.message || response?.errors?.rest_forbidden[0] || response?.errors?.cloudflare_error[0] || JSON.stringify(response.errors));
					});
				}

				jQuery('#c-purge-cache-post-update-test').on('click', function () {
					jQuery.post(ajaxurl, { action: 'c-purge_post_update_test' }, function(response) {
						alert(response?.message || response?.errors?.rest_forbidden[0] || response?.errors?.cloudflare_error[0] || JSON.stringify(response.errors));
					});
				});

			</script> <?php
        });



        add_action('wp_ajax_c-purge_everything', function () {
            if (!current_user_can('edit_posts')) {
                $response = new WP_Error('rest_forbidden', __('You cannot edit posts.', 'c-purge-cache'), [ 'status' => 401 ]);
            }

            $response = self::purge();

            wp_send_json($response);
            wp_die();
        });

        add_action('wp_ajax_c-purge_post_update_test', function () {
            if (!current_user_can('edit_posts')) {
                $response = new WP_Error('rest_forbidden', __('You cannot edit posts.', 'c-purge-cache'), [ 'status' => 401 ]);
            }

            $latestPostId = get_posts([
                'numberposts' => 1,
                'post_status' => 'publish'
            ])[0]->ID;
            $response = self::purge($latestPostId);

            wp_send_json($response);
            wp_die();
        });
    }
}
