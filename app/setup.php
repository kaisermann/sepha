<?php

namespace App;

use Roots\Sage\Container;
use Roots\Sage\Assets\JsonManifest;
use Roots\Sage\Template\Blade;
use Roots\Sage\Template\BladeProvider;
use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Theme assets
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('selene/main.css', asset_path('styles/main.css'), false, null);
    wp_enqueue_script('selene/main.js#defer', asset_path('scripts/main.js'), [], null, true);

    /** Enqueue accessibility test script on development env */
    if (defined('WP_ENV') && strtolower(WP_ENV) === 'development') {
        wp_enqueue_script(
            'selene/accessibility-test.js#defer',
            asset_path('scripts/accessibility-test.js'),
            [],
            null,
            true
        );
    }

    /*
    * Creates the 'selene' javascript object with useful URLs.
    */
    wp_localize_script(
        'selene/main.js#defer',
        'selene',
        [
            'homeUrl' => get_bloginfo('url'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'assetsUrl' => config('assets.uri')
        ]
    );
}, 100);

/**
 * Setup image sizes, post types and taxonomies
 */
add_action('init', function () {
    global $wp_rewrite;
    $wp_rewrite->search_base = 'search';

    /** Register custom image sizes here */
    // add_image_size('name', width, height, crop)
}, 0, 2);

/**
 * Theme setup
 */
add_action('after_setup_theme', function () {
    load_theme_textdomain('selene', get_template_directory() . '/languages');

    add_theme_support('html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ]);
    add_theme_support('menus');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    // add_theme_support('woocommerce');
    // add_post_type_support('page', 'excerpt');

    add_editor_style(asset_path('styles/editor.css'));

    register_nav_menus([
        'primary_navigation' => __('Primary Navigation', 'selene'),
    ]);
}, 100);

/**
 * Register sidebars
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];

    register_sidebar([
        'name' => __('Main', 'selene'),
        'id' => 'sidebar-main',
    ] + $config);

    register_sidebar([
        'name' => __('Footer', 'selene'),
        'id' => 'sidebar-footer',
    ] + $config);
});

/**
 * Update the `$post` variable on each iteration of the loop.
 * Note: updated value is only available for subsequently loaded views, such as partials
 */
add_action('the_post', function ($post) {
    sage('blade')->share('post', $post);
});

/**
 * Setup Sage options
 */
add_action('after_setup_theme', function () {
    /**
    * Add JsonManifest to Sage container
    */
    sage()->singleton(
        'sage.assets',
        function () {
            return new JsonManifest(
                config('assets.manifest'),
                config('assets.uri')
            );
        }
    );

    /**
    * Add Blade to Sage container
    */
    sage()->singleton(
        'sage.blade',
        function (Container $app) {
            $cachePath = config('view.compiled');
            if (!file_exists($cachePath)) {
                wp_mkdir_p($cachePath);
            }(new BladeProvider($app))->register();
            return new Blade($app['view']);
        }
    );

    $sageCompiler = sage('blade')->compiler();
    foreach (config('directives') as $directive => $fn) {
        $sageCompiler->directive($directive, $fn);
    }
});

/**
 * ACF Builder initialization and fields loading
 */
define('ACF_FIELDS_DIR', __DIR__ . '/Fields');
if (is_dir(ACF_FIELDS_DIR) && function_exists('acf_add_local_field_group')) {
    add_action('init', function () {
        collect(glob(ACF_FIELDS_DIR . '/*.php'))
            ->map(function ($field) {
                return require_once($field);
            })
            ->flatten()
            ->map(function ($field) {
                if ($field instanceof FieldsBuilder) {
                    acf_add_local_field_group($field->build());
                }
            });
    });
}
