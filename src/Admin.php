<?php

namespace CPurgeCache;

use CPurgeCache\Purge;
use CPurgeCache\Settings;

class Admin
{
    protected static $option = 'c-purge-cache';

    public static function init()
    {
        if (is_admin()) {
            self::settings();
            self::adminRender();

            Purge::purgeEverythingButton();
        }
    }

    private static function adminRender()
    {
        add_action('admin_menu', function () {
            add_options_page(
                'Cloudflare Purge Cache',
                'Cloudflare Purge Cache',
                'manage_options',
                'c-purge-cache',
                function () {
                    if (!current_user_can('manage_options')) {
                        wp_die(__('You do not have sufficient permissions to access this page.'));
                    } ?>
					<div class="wrap">
						<h1>Cloudflare Purge Cache</h1>
						<form method="post" action="options.php">
						<?php
                            settings_fields(self::$option);
                    do_settings_sections(self::$option);
                    submit_button(); ?>
						</form>
					</div>
					<?php
                }
            );
        });
    }

    private static function settings()
    {
        add_action('admin_init', function () {
            register_setting(self::$option, self::$option);
            add_settings_section(
                'general',
                'General',
                function () {
                },
                self::$option
            );
            add_settings_field(
                'zone_id',
                'Zone ID',
                function () {
                    $field = 'zone_id';
                    $option = get_option(self::$option);
                    $value = $option[$field] ? esc_attr($option[$field]) : '';
                    echo('<input type="text" name="'.self::$option.'['.$field.']" value="'.$value.'" />');
                },
                self::$option,
                'general'
            );
            add_settings_field(
                'api_token',
                'API Token',
                function () {
                    $field = 'api_token';
                    $option = get_option(self::$option);
                    $value = $option[$field] ? esc_attr($option[$field]) : '';
                    echo('<input type="password" name="'.self::$option.'['.$field.']" value="'.$value.'" />');
                    echo '<p class="description"><a href="https://developers.cloudflare.com/api/tokens/create" target="_blank">Create API Token</a> with <b>Zone.Cache Purge</b> permissions.</p>';
                },
                self::$option,
                'general'
            );
            add_settings_field(
                'frontend_url',
                'Homepage URL',
                function () {
                    $field = 'frontend_url';
                    $option = get_option(self::$option);
                    $value = $option[$field] ? esc_attr($option[$field]) : get_site_url();
                    echo('<input type="text" name="'.self::$option.'['.$field.']" value="'.$value.'" />');
                },
                self::$option,
                'general'
            );
            add_settings_section(
                'purge_everything',
                'Purge Everything:',
                function () {
                },
                self::$option
            );
            add_settings_field(
                'purge_everything',
                'Purge Everything Now',
                function () {
                    $field = 'purge_everything';
                    $option = get_option(self::$option);
                    echo '<p><a id="c-purge-cache-everything-settings" class="button button-primary" style="margin-bottom: 15px;">Purge All Cache</a></p>';
                    echo '<input type="checkbox" id="'.$field.'" name="'.self::$option.'['.$field.']" value="1"' . checked('1', isset($option[$field]), false) . '/>';
                    echo '<label for="'.$field.'">Show <b>Purge All Cache</b> button in admin bar</label>';
                },
                self::$option,
                'purge_everything'
            );
            add_settings_field(
                'purge_everything_endpoint',
                'API Endpoint',
                function () {
                    $field = 'purge_everything_endpoint';
                    $option = get_option(self::$option);
                    echo '<input type="checkbox" id="'.$field.'" name="'.self::$option.'['.$field.']" value="1"' . checked('1', isset($option[$field]), false) . '/>';
                    echo '<label for="'.$field.'">Allow WP REST API endpoint for Purge Everything</label>';
                    $apiUrl = rest_url('cpc/v1/purge');
                    $secret = $option['purge_everything_secret'];
                    echo "<p class='description'><code>curl -X PUT '$apiUrl' -H 'Authorization: Bearer $secret' -H 'Content-Type:application/json'</code></p>";
                },
                self::$option,
                'purge_everything'
            );
            add_settings_field(
                'purge_everything_secret',
                'Endpoint Secret',
                function () {
                    $field = 'purge_everything_secret';
                    $option = get_option(self::$option);
                    $value = $option[$field] ? esc_attr($option[$field]) : wp_generate_uuid4();
                    echo('<input type="text" name="'.self::$option.'['.$field.']" value="'.$value.'" />');
                },
                self::$option,
                'purge_everything'
            );

            add_settings_section(
                'on_post_update',
                'On post update:',
                function () {
                    echo '<p class="description">On post update is current page purged automatically.</p>';
                },
                self::$option
            );
            add_settings_field(
                'purge_home_url',
                'Purge Homepage',
                function () {
                    $field = 'purge_home_url';
                    $option = get_option(self::$option);
                    echo '<input type="checkbox" id="'.$field.'" name="'.self::$option.'['.$field.']" value="1"' . checked('1', isset($option[$field]), false) . '/>';
                    echo '<label for="'.$field.'">Purge Homepage URL on post update</label>';
                },
                self::$option,
                'on_post_update'
            );
            add_settings_field(
                'purge_everything_on_update',
                'Purge Everything',
                function () {
                    $field = 'purge_everything_on_update';
                    $option = get_option(self::$option);
                    echo '<input type="checkbox" id="'.$field.'" name="'.self::$option.'['.$field.']" value="1"' . checked('1', isset($option[$field]), false) . '/>';
                    echo '<label for="'.$field.'">Purge Everything on post update</label>';
                },
                self::$option,
                'on_post_update'
            );
            add_settings_field(
                'purge_urls',
                'Additional URLs to purge on Post update',
                function () {
                    $field = 'purge_urls';
                    $option = get_option(self::$option);
                    $value = $option[$field] ? esc_attr($option[$field]) : '';
                    $placeholder = ($option['frontend_url'] ? esc_attr($option['frontend_url']) : get_site_url()) . '/category/%categories%';
                    echo '<textarea type="textarea" name="'.self::$option.'['.$field.']" placeholder="'. $placeholder .'" rows="8" cols="40">'.$value.'</textarea>';
                    echo '<p class="description">One URL per row.</p>';
                    echo '<p class="description"><i>Available current Post placeholders:</i></p>';
                    echo '<p class="description"><code>%slug%</code> <code>%author_nicename%</code> <code>%categories%</code> <code>%tags%</code></p>';
                    echo '<p class="description"><br/>Test your config:</p>';
                    echo '<p><a id="c-purge-cache-post-update-test" class="button button-primary" style="margin-bottom: 15px;">Purge Latest Post Cache</a></p>';
                },
                self::$option,
                'on_post_update'
            );
        });
    }
}
