<?php

namespace App;

//add_filter('locate_template', 'App\\template_path');
// Sage: filter to display or hide the sidebar
//add_filter( 'sage/display_sidebar', __NAMESPACE__ . '\filter__display_sidebar' );
add_filter( 'body_class', 'App\\filter__body_class' );
add_filter( 'template_redirect', 'App\\filter__template_redirect' );
add_filter( 'template_include', 'App\\filter__template_include', 10000 );
add_filter( 'get_search_form', 'App\\filter__get_search_form' );
add_filter( 'comments_template', 'App\\template_path' );
// Default jpg quality
add_filter( 'jpeg_quality', 'App\\filter__jpeg_quality' );
add_filter( 'upload_mimes', 'App\\filter__upload_mimes' );
// Removes WP version from feeds
add_filter( 'the_generator', 'App\\filter__the_generator' );
// Removes the protocol (http(s)) from asset's url
// Based on 'https://github.com/ryanjbonnell/Protocol-Relative-Theme-Assets by Ryan J. Bonnell'
add_filter( 'style_loader_src', 'App\\filter__style_loader_src', 10, 2 );
add_filter( 'script_loader_src', 'App\\filter__script_loader_src', 10, 2 );
add_filter( 'template_directory_uri', 'App\\filter__template_directory_uri', 10, 3 );
add_filter( 'stylesheet_directory_uri', 'App\\filter__stylesheet_directory_uri', 10, 3 );

/**
 * Template Hierarchy should search for .blade.php files
 */
array_map(function ( $type ) {
	add_filter("{$type}_template_hierarchy", function ( $templates ) {
		return call_user_func_array('array_merge', array_map(function ( $template ) {
			$normalizedTemplate = str_replace( '.', '/', sage( 'blade' )->normalizeViewPath( $template ) );
			return [ "{$normalizedTemplate}.blade.php", "{$normalizedTemplate}.php" ];
		}, $templates));
	});
}, [
	'index',
	'404',
	'archive',
	'author',
	'category',
	'tag',
	'taxonomy',
	'date',
	'home',
	'front_page',
	'page',
	'paged',
	'search',
	'single',
	'singular',
	'attachment',
]);

/**
 * Render page using Blade
 */
function filter__template_include( $template ) {
	echo template($template, apply_filters('sage/template_data', []));
	// Return a blank file to make WordPress happy
	return get_template_directory() . '/index.php';
}

function filter__body_class( $classes ) {
	if ( is_single() || is_page() && ! is_front_page() ) {
		$baseName = basename( get_permalink() );
		if ( ! in_array( $baseName, $classes ) ) {
			$classes[] = $baseName;
		}
	}

	if ( display_sidebar() ) {
		$classes[] = 'has-sidebar';
	}

	return $classes;
}

function filter__template_redirect() {
	global $wp_rewrite;
	if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) || ! $wp_rewrite->using_permalinks() ) {
		return;
	}

	$search_base = $wp_rewrite->search_base;
	if ( is_search() && ! is_admin() && strpos( $_SERVER['REQUEST_URI'], '/' . $search_base . '/' ) === false ) {
		wp_redirect( home_url( '/' . $search_base . '/' . urlencode( get_query_var( 's' ) ) ) );
		exit();
	}

	if ( WP_ENV === 'development' && isset( $_GET['show_sitemap'] ) ) {
		$homeUrl = get_home_url();
		$blogUrl = get_permalink( get_option( 'page_for_posts' ) );
		$the_query = new \WP_Query( [ 'post_type' => 'any', 'posts_per_page' => '-1', 'post_status' => 'publish' ] );

		$urls = [];
		$urls[] = $homeUrl;
		if ( strcmp( $blogUrl, $homeUrl ) !== 0 ) {
			$urls[] = $blogUrl;
		}

		$urls[] = $homeUrl . '/404';

		while ( $the_query->have_posts() ) {
			  $the_query->the_post();
			  $urls[] = get_permalink();
		}
		die( json_encode( $urls ) );
	}
}

function filter__get_search_form() {
	$form = '';
	locate_template( '/templates/partials/searchform.blade.php', true, false );

	return $form;
}

function filter__jpeg_quality() {
	return 100;
}

function filter__upload_mimes( $mimes ) {
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}

function filter__the_generator() {
	return '';
}

function filter__style_loader_src( $src ) {
	return getUrlWithRelativeProtocol( $src );
}

function filter__script_loader_src( $src ) {
	return getUrlWithRelativeProtocol( $src );
}

function filter__template_directory_uri( $template_dir_uri ) {
	return getUrlWithRelativeProtocol( $template_dir_uri );
}

function filter__stylesheet_directory_uri( $stylesheet_dir_uri ) {
	return getUrlWithRelativeProtocol( $stylesheet_dir_uri );
}

// Helpers
function getUrlWithRelativeProtocol( $url ) {
	return preg_replace( '(https?://)', '//', $url );
}
