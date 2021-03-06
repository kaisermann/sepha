<?php

namespace App;

/**
 * Template Hierarchy should search for .blade.php files
 */
collect(['index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy','date', 'home', 'frontpage', 'page', 'paged', 'search', 'single', 'singular', 'attachment'])
    ->map(function ($type) {
        add_filter("{$type}_template_hierarchy", __NAMESPACE__ . '\\filter_templates');
    });


/**
 * Render page using Blade
 */
add_filter('template_include', function ($template) {
    $data = collect(get_body_class())->reduce(
        function ($data, $class) use ($template) {
            return apply_filters(
                "sage/template/{$class}/data",
                $data,
                $template
            );
        },
        []
    );

    if ($template) {
        echo template($template, $data);
        return get_stylesheet_directory().'/index.php';
    }

    return $template;
}, PHP_INT_MAX);

/**
 * Tell WordPress how to find the compiled path of comments.blade.php
 */
add_filter('comments_template', function ($comments_template) {
    $comments_template = str_replace(
        [ get_stylesheet_directory(), get_template_directory() ],
        '',
        $comments_template
    );

    return template_path(
        locate_template([ "views/{$comments_template}", $comments_template ])
        ?: $comments_template
    );
});

/**
 * Models path
 */
add_filter('sober/models/path', function () {
    return dirname(get_template_directory()) . '/app/Models';
});


/**
 * Pretty search and redirects
 */
add_filter('template_redirect', function () {
    global $wp_rewrite;
    if (!isset($wp_rewrite) || !is_object($wp_rewrite) || !$wp_rewrite->using_permalinks()) {
        return;
    }

    $search_base = $wp_rewrite->search_base;
    if (is_search() && !is_admin() &&
        !strpos($_SERVER['REQUEST_URI'], "/{$search_base}/") &&
        !strpos($_SERVER['REQUEST_URI'], '&')
    ) {
        wp_redirect(get_search_link());
        exit;
    }
});

/**
 * Pretty search slug for Yoast SEO
 */
add_filter('wpseo_json_ld_search_url', function ($url) {
    global $wp_rewrite;
    return str_replace('/?s=', "/{$wp_rewrite->search_base}/", $url);
});

/**
 * Use the SearchForm Component as the output of get_search_form()
 */
add_filter('get_search_form', function () {
    render_component('SearchForm');
    return '';
});

/**
 * Set JPEG upload quality back to 100
 */
add_filter('jpeg_quality', function () {
    return 100;
});

/**
 * Allows the upload of SVG files
 */
add_filter('upload_mimes', function ($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});

/**
 * Produces cleaner filenames for uploads
 * Reference: wpartisan.me/tutorials/rename-clean-wordpress-media-filenames
 *
 * @param  string $filename
 * @return string
 */
add_filter('sanitize_file_name', function ($filename) {
    // Converts to ASCII
    $sanitized_filename = remove_accents($filename);
    // Removes all non-alphanumeric except .
    $sanitized_filename = preg_replace('/[^A-Za-z0-9-\. ]/', '', $sanitized_filename);
     // Removes all but last .
    $sanitized_filename = preg_replace('/\.(?=.*\.)/', '', $sanitized_filename);
    // Replaces any more than one - in a row
    $sanitized_filename = preg_replace('/-+/', '-', $sanitized_filename);
    // Removes last - if at the end
    $sanitized_filename = str_replace('-.', '.', $sanitized_filename);
    // Transforms to lowercase
    $sanitized_filename = strtolower($sanitized_filename);
    return $sanitized_filename;
}, 10, 1);

/**
 * Defer enqueued scripts which has '#defer' on their name
 */
add_filter('script_loader_tag', function ($tag, $handle) {
    if (strpos($handle, '#defer') !== false) {
        return str_replace('src', 'defer="defer" src', $tag);
    }
    return $tag;
}, 10, 2);

/**
 * Wraps oembeds with 'embed'
 */
add_filter('embed_oembed_html', function ($cache) {
    return "<div class=\"oembed-container\">{$cache}</div>";
});

/**
 * Append a cache busting number to the enqueued assets
 * when WP_DEBUG is true or when on the development environment.
 */
$filter__parse_asset_version = function ($src) {
    if ((defined('WP_DEBUG') && WP_DEBUG) || strtoupper(WP_ENV) === 'DEVELOPMENT') {
        return add_query_arg('ver', 'dev-'.rand(), remove_query_arg('ver', $src));
    }
    return $src;
};
add_filter('style_loader_src', $filter__parse_asset_version);
add_filter('script_loader_src', $filter__parse_asset_version);

/**
 * Remove the protocol (http(s)) from asset's url
 *
 * Based on 'https://github.com/ryanjbonnell/Protocol-Relative-Theme-Assets'
 * by Ryan J. Bonnell
 */
add_filter('style_loader_src', 'App\get_url_without_protocol', 10, 2);
add_filter('script_loader_src', 'App\get_url_without_protocol', 10, 2);
add_filter('template_directory_uri', 'App\get_url_without_protocol', 10, 3);
add_filter('stylesheet_directory_uri', 'App\get_url_without_protocol', 10, 3);

/**
 * Add, remove and clean <body> classes
 */
add_filter('body_class', function (array $classes) {

    /** String patterns to remove */
    $excludePatterns = [
        'page-template-views.*',        // Removes page-template-views-$template
        'page-id-.*',                   // Removes page-id-$id
        'post-template.*',              // Removes post-template-$template
        'postid.*',                     // Removes postid$id
        'single-format.*',              // Removes single-format-$format
        'category-\d*',                 // Removes category-$id
        'tag-\d*',                      // Removes tag-$id,
        'post-type-archive',            // Removes post-type-archive
    ];

    /** Regex patterns to replace class names */
    $replacePatterns = [
        '/page-template-(?:template-)?(.*?)(?:-blade)?$/' => 'template-$1', // Simplifies template classes
        '/post-type-archive-(.*)/' => 'archive-$1', // Simplifies custom-post-type-archive
    ];

    /** Add post/page slug if not present */
    if (is_single() || is_page() && !is_front_page()) {
        $page_slug = 'page-'.basename(get_permalink());
        if (!in_array($page_slug, $classes)) {
            $classes[] = $page_slug;
        }
    }

    /** Remove unnecessary classes */
    $classes = preg_grep(
        '/^(?!(' . implode('|', $excludePatterns) . ')$)/xs',
        $classes
    );

    /** Prettify some other classes */
    $classes = preg_replace(
        array_keys($replacePatterns),
        array_values($replacePatterns),
        $classes
    );

    return $classes;
});

/**
 * Make excerpt "…"
 */
add_filter('excerpt_more', function () {
    return ' &hellip; <a href="' . get_permalink() . '">...</a>';
});
