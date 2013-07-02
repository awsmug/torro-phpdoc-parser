<?php
/**
 * Plugin Name: WP Parser
 * Description: Create a function reference site powered by WordPress
 * Author: Ryan McCue and Paul Gibbs
 * Plugin URI: https://github.com/rmccue/WP-Parser
 * Version: 1.0
 */

namespace WPFuncRef;

require __DIR__ . '/importer.php';
require __DIR__ . '/template.php';

if ( defined('WP_CLI') && WP_CLI ) {
	require __DIR__ . '/cli.php';
}

add_action( 'init', __NAMESPACE__ . '\\register_post_types' );
add_action( 'init', __NAMESPACE__ . '\\register_taxonomies' );
add_filter( 'wpfuncref_get_the_arguments', __NAMESPACE__ . '\\make_args_safe' );
add_filter( 'wpfuncref_the_return_type', __NAMESPACE__ . '\\humanize_separator' );

add_filter( 'the_content', __NAMESPACE__ . '\\expand_content' );

/**
 * Register the function and class post types
 */
function register_post_types() {
	// Functions
	register_post_type( 'wpapi-function', array(
		'has_archive'  => true,
		'hierarchical' => true,
		'label'        => __( 'Functions', 'wpfuncref' ),
		'public'       => true,
		'rewrite'      => array( 'slug' => 'functions' ),
		'supports'     => array( 'comments', 'custom-fields', 'editor', 'excerpt', 'page-attributes', 'revisions', 'title' ),
		'taxonomies'   => array( 'wpapi-source-file' ),
	) );

	// Classes
	register_post_type( 'wpapi-class', array(
		'has_archive'  => true,
		'hierarchical' => false,
		'label'        => __( 'Classes', 'wpfuncref' ),
		'public'       => true,
		'rewrite'      => array( 'slug' => 'classes' ),
		'supports'     => array( 'comments', 'custom-fields', 'editor', 'excerpt', 'revisions', 'title' ),
		'taxonomies'   => array( 'wpapi-source-file' ),
	) );
}

/**
 * Register the file and @since taxonomies
 */
function register_taxonomies() {
	// Files
	register_taxonomy( 'wpapi-source-file', array( 'wpapi-class', 'wpapi-function' ), array(
		'hierarchical'          => true,
		'label'                 => __( 'Files', 'wpfuncref' ),
		'public'                => true,
		'rewrite'               => array( 'slug' => 'files' ),
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );

	// Package
	register_taxonomy( 'wpapi-package', array( 'wpapi-class', 'wpapi-function' ), array(
		'hierarchical'          => true,
		'label'                 => '@package',
		'public'                => true,
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );

	// @since
	register_taxonomy( 'wpapi-since', array( 'wpapi-class', 'wpapi-function' ), array(
		'hierarchical'          => true,
		'label'                 => __( '@since', 'wpfuncref' ),
		'public'                => true,
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );
}

/**
 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
 *
 * @param array $args Parameter arguments to make safe
 * @param array Filtered arguments
 * @return array
 */
function make_args_safe( $args ) {
	$filters = array(
		'wp_filter_kses',
		'make_clickable',
		'force_balance_tags',
		'wptexturize',
		'convert_smilies',
		'convert_chars',
		'stripslashes_deep',
	);

	foreach ( $args as &$arg ) {
		foreach ( $arg as &$value ) {

			// Loop through all elements of the $args array, and apply our set of filters to them.
			foreach ( $filters as $filter_function )
				$value = call_user_func( $filter_function, $value );
		}
	}

	return apply_filters( 'wpfuncref_make_args_safe', $args );
}

/**
 * Replace separators with a more readable version
 *
 * @param string $type Variable type
 * @return string
 */
function humanize_separator( $type ) {
	return str_replace( '|', '<span class="wpapi-item-type-or">' . _x( ' or ', 'separator', 'wpfuncref' ) . '</span>', $type );
}

/**
 * Extend the post's content with function reference pieces
 *
 * @param string $content Unfiltered content
 * @return string Content with Function reference pieces added
 */
function expand_content( $content ) {
	$post = get_post();

	if ( $post->post_type !== 'wpapi-class' && $post->post_type !== 'wpapi-function' )
		return $content;

	$before_content = wpfuncref_prototype();
	$before_content .= '<p class="wpfuncref-description">' . get_the_excerpt() . '</p>';
	$before_content .= '<div class="wpfuncref-longdesc">';

	$after_content = '</div>';
	$after_content .= '<div class="wpfuncref-arguments"><h3>Arguments</h3>';
	$args = wpfuncref_get_the_arguments();
	foreach ( $args as $arg ) {
		$after_content .= '<div class="wpfuncref-arg">';
		$after_content .= '<h4><code><span class="type">' . $arg['type'] . '</span> <span class="variable">' . $arg['name'] . '</span></code></h4>';
		$after_content .= wpautop( $arg['desc'], false );
		$after_content .= '</div>';
	}
	$after_content .= '</div>';

	$source = wpfuncref_source_link();
	if ( $source )
		$after_content .= '<a href="' . $source . '">Source</a>';

	$before_content = apply_filters( 'wpfuncref_before_content', $before_content );
	$after_content = apply_filters( 'wpfuncref_after_content', $after_content );

	return $before_content . $content . $after_content;
}