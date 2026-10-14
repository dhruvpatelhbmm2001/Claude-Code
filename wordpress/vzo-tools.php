<?php
/**
 * Admin helper functions used while editing the site (no front-end effect).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vzo_el_get( $id ) {
	return json_decode( get_post_meta( $id, '_elementor_data', true ), true );
}

function vzo_el_save( $id, $data ) {
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	delete_post_meta( $id, '_elementor_css' );
	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	do_action( 'litespeed_purge_all' );
}

/** Walk elements; callback may return false to remove the element. */
function vzo_el_walk( &$els, $cb, $parent = null ) {
	foreach ( $els as $i => &$e ) {
		if ( $cb( $e, $parent ) === false ) {
			unset( $els[ $i ] );
			continue;
		}
		if ( ! empty( $e['elements'] ) ) {
			vzo_el_walk( $e['elements'], $cb, $e['id'] );
		}
	}
	unset( $e );
	$els = array_values( $els );
}

function vzo_el_dump( $els, $d = 0, &$o = array() ) {
	foreach ( $els as $e ) {
		$s   = $e['settings'] ?? array();
		$txt = array();
		foreach ( $s as $k => $v ) {
			if ( is_string( $v ) && strlen( $v ) > 0 && ! preg_match( '/^(_|background|border|padding|margin|typography|css|size|width|height|align|hover|animation|motion|z_index|position|box_shadow|flex|content_width|gap|structure|layout|column|audio)/', $k ) && ! preg_match( '/_size|_width|typography|_tablet|_mobile/', $k ) ) {
				$txt[] = "$k=" . substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $v ) ) ), 0, 160 );
			} elseif ( is_array( $v ) && isset( $v['url'] ) && $v['url'] ) {
				$txt[] = "$k.url=" . $v['url'];
			} elseif ( is_array( $v ) && $v && isset( $v[0] ) && is_array( $v[0] ) ) {
				$txt[] = "$k=[" . count( $v ) . ' items: ' . substr( wp_strip_all_tags( wp_json_encode( $v ) ), 0, 300 ) . ']';
			}
		}
		$o[] = str_repeat( '  ', $d ) . ( $e['widgetType'] ?? $e['elType'] ) . '#' . $e['id'] . ' ' . implode( ' | ', $txt );
		if ( ! empty( $e['elements'] ) ) {
			vzo_el_dump( $e['elements'], $d + 1, $o );
		}
	}
	return $o;
}

function vzo_fetch( $path ) {
	return wp_remote_retrieve_body( wp_remote_get( home_url( $path ) . ( strpos( $path, '?' ) ? '&' : '?' ) . 'nc=' . time(), array( 'timeout' => 40, 'sslverify' => false ) ) );
}
