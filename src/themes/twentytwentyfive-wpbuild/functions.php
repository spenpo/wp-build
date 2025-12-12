<?php
/**
 * Twenty Twenty Five WPBuild Theme Functions
 * 
 * This file contains the functions for the Twenty Twenty Five WPBuild theme.
 * 
 * @package Twenty_Twenty_Five_WPBuild
 * @subpackage Functions
 * @since 1.0.0
 * @version 1.0.0
 */

 add_action('wp_enqueue_scripts', function() {
    // Enqueue parent theme styles first
    wp_enqueue_style(
        'twentytwentyfive-style',
        get_template_directory_uri() . '/style.css'
    );
    
    // Enqueue child theme's main style.css
    wp_enqueue_style(
        'twentytwentyfive-wpbuild-style',
        get_stylesheet_uri(),
        ['twentytwentyfive-wpbuild-style']
    );
});