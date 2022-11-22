<?php

namespace CPurgeCache;

class Helpers {


	// Prevent wrong REST API url in Headless WP
	public static function preventWrongApiUrl() {
		if ( home_url() !== site_url() ) {
			add_filter('rest_url', function ( $url ) {
				return str_replace( home_url(), site_url(), $url );
			});
		}
	}

	// https://github.com/WordPress/WordPress/blob/ecc08a41f61940345489b8566a43cea5b5ab78ca/wp-includes/class-wp-rewrite.php#L1062
	public static function rewriteUrls( $urls, $post ) {

		$final_urls = [];

		foreach ( $urls as $url ) {
			$url = trim( $url );

			if ( strpos( $url, '%slug%' ) !== false ) {
				$final_urls[] = str_replace( '%slug%', $post->post_name, $url );
			} elseif ( strpos( $url, '%author_nicename%' ) !== false ) {
				$final_urls[] = str_replace( '%author_nicename%', get_the_author_meta( 'user_nicename', $post->post_author ), $url );
			} elseif ( strpos( $url, '%categories%' ) !== false ) {
				$categories = wp_get_post_categories( $post->ID, [ 'fields' => 'slugs' ] ) ?? [];
				foreach ( $categories as $category ) {
					$final_urls[] = str_replace( '%categories%', $category, $url );
				}
			} elseif ( strpos( $url, '%tags%' ) !== false ) {
				$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'slugs' ] ) ?? [];
				foreach ( $tags as $tag ) {
					$final_urls[] = str_replace( '%tags%', $tag, $url );
				}
			} else {
				$final_urls[] = $url;
			}
		}

		return $final_urls;
	}
}
