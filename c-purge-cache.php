<?php
/**
 * Cloudflare Purge Cache
 *
 * Plugin Name:       	Purge Cache
 * Plugin URI: 			https://github.com/gdidentity/c-purge-cache
 * GitHub Plugin URI: 	https://github.com/gdidentity/c-purge-cache
 * Description:       	Purge Cloudflare URL cache on the post update, purge specific URLs on the post update, Purge Everything button.
 * Version:           	1.0.0
 * Author:            	GD IDENTITY
 * Author URI:        	https://gdidentity.sk
 * Text Domain:       	c-purge-cache
 * License: 			GPL-3
 * License URI: 		https://www.gnu.org/licenses/gpl-3.0.html
 *
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;


if (! class_exists('CPurgeCache')) :

    /**
     * This is the one true CPurgeCache class
     */
    final class CPurgeCache
    {

        /**
         * Stores the instance of the CPurgeCache class
         *
         * @since 0.0.1
         *
         * @var CPurgeCache The one true CPurgeCache
         */
        private static $instance;

        /**
         * The instance of the CPurgeCache object
         *
         * @since 0.0.1
         *
         * @return CPurgeCache The one true CPurgeCache
         */
        public static function instance(): self
        {
            if (! isset(self::$instance) && ! (is_a(self::$instance, __CLASS__))) {
                self::$instance = new self();
                self::$instance->setup_constants();
                if (self::$instance->includes()) {
                    self::$instance->admin();
                    self::$instance->preload();
                    self::$instance->purge();
                    self::$instance->api();
                    self::$instance->pluginLinks();

                    \CPurgeCache\Helpers::preventWrongApiUrl();
                }
            }

            /**
             * Fire off init action.
             *
             * @param CPurgeCache $instance The instance of the CPurgeCache class
             */
            do_action('c-purge-cache_init', self::$instance);

            // Return the CPurgeCache Instance.
            return self::$instance;
        }

        /**
         * Throw error on object clone.
         * The whole idea of the singleton design pattern is that there is a single object
         * therefore, we don't want the object to be cloned.
         *
         * @since 0.0.1
         */
        public function __clone()
        {

            // Cloning instances of the class is forbidden.
            _doing_it_wrong(
                __FUNCTION__,
                esc_html__(
                    'The CPurgeCache class should not be cloned.',
                    'c-purge-cache'
                ),
                '0.0.1'
            );
        }

        /**
         * Disable unserializing of the class.
         *
         * @since 0.0.1
         */
        public function __wakeup()
        {

            // De-serializing instances of the class is forbidden.
            _doing_it_wrong(
                __FUNCTION__,
                esc_html__(
                    'De-serializing instances of the CPurgeCache class is not allowed.',
                    'c-purge-cache'
                ),
                '0.0.1'
            );
        }

        /**
         * Setup plugin constants.
         *
         * @since 0.0.1
         */
        private function setup_constants(): void
        {

            // Plugin version.
            if (! defined('CPurgeCache_VERSION')) {
                define('CPurgeCache_VERSION', '1.0.0');
            }

            // Plugin Folder Path.
            if (! defined('CPurgeCache_PLUGIN_DIR')) {
                define('CPurgeCache_PLUGIN_DIR', plugin_dir_path(__FILE__));
            }

            // Plugin Folder URL.
            if (! defined('CPurgeCache_PLUGIN_URL')) {
                define('CPurgeCache_PLUGIN_URL', plugin_dir_url(__FILE__));
            }

            // Plugin Root File.
            if (! defined('CPurgeCache_PLUGIN_FILE')) {
                define('CPurgeCache_PLUGIN_FILE', __FILE__);
            }

            // Whether to autoload the files or not.
            if (! defined('CPurgeCache_AUTOLOAD')) {
                define('CPurgeCache_AUTOLOAD', true);
            }
        }

        /**
         * Uses composer's autoload to include required files.
         *
         * @since 0.0.1
         *
         * @return bool
         */
        private function includes(): bool
        {

            // Autoload Required Classes.
            if (defined('CPurgeCache_AUTOLOAD') && false !== CPurgeCache_AUTOLOAD) {
                if (file_exists(CPurgeCache_PLUGIN_DIR . 'vendor/autoload.php')) {
                    require_once CPurgeCache_PLUGIN_DIR . 'vendor/autoload.php';
                }

                // Bail if installed incorrectly.
                if (! class_exists('\CPurgeCache\Admin')) {
                    add_action('admin_notices', [ $this, 'missing_notice' ]);
                    return false;
                }
            }

            return true;
        }

        /**
         * Composer dependencies missing notice.
         *
         * @since 0.0.1
         */
        public function missing_notice(): void
        {
            if (! current_user_can('manage_options')) {
                return;
            } ?>
			<div class="notice notice-error">
				<p>
					<?php esc_html_e('Purge Cache appears to have been installed without its dependencies. It will not work properly until dependencies are installed. This likely means you have cloned Cloudflare Purge Cache from Github and need to run the command `composer install`.', 'c-purge-cache'); ?>
				</p>
			</div>
			<?php
        }

        /**
         * Set up admin.
         *
         * @since 0.0.1
         */
        private function admin(): void
        {
            \CPurgeCache\Admin::init();
        }

        /**
         * Set up Preload.
         *
         * @since 0.0.1
         */
        private function preload(): void
        {
            \CPurgeCache\Preload::init();
        }

        /**
         * Set up Purge.
         *
         * @since 0.0.1
         */
        private function purge(): void
        {
            \CPurgeCache\Purge::init();
        }

        /**
         * Set up Api.
         *
         * @since 0.0.1
         */
        private function api(): void
        {
            \CPurgeCache\Api::init();
        }

        /**
         * Set up Action Links.
         *
         * @since 0.0.1
         */
        private function pluginLinks(): void
        {

            // Setup Settings link.
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
                $links[] = '<a href="/wp-admin/admin.php?page=c-purge-cache">Settings</a>';

                return $links;
            });
        }
    }

endif;

\CPurgeCache::instance();
