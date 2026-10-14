<?php
/**
 * VZO site customizations: fixed header styling, enquiry list (replaces the
 * shopping cart), product filters, product info, map links, redirects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VZO_ENQUIRY_EMAIL = 'sales@vzovalves.com';
const VZO_ENQUIRY_SLUG  = 'enquiry';

function vzo_map_url() {
	return get_option( 'vzo_map_url', 'https://www.google.com/maps/search/?api=1&query=Bajaj+Engineering+Works+M-21-23+Industrial+Area+Jalandhar+Punjab+144008' );
}

function vzo_enquiry_url() {
	return home_url( '/' . VZO_ENQUIRY_SLUG . '/' );
}

/** Product category groups (by slug). Anything not listed is a product type. */
function vzo_cat_groups() {
	return array(
		'material'      => array( 'bronze', 'gun-metal', 'brass', 'cast-iron', 'cast-steel', 'stainless-steel' ),
		'application'   => array( 'water', 'steam', 'oil', 'gas', 'air' ),
		'connection'    => array( 'screwed', 'flanged' ),
		'certification' => array( 'ibr', 'isi' ),
	);
}

/* -------------------------------------------------------------------------
 * Redirects: old category slug, cart/checkout -> enquiry list.
 * ---------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( 'product-category/moisture-seprator' === $path ) {
		wp_safe_redirect( home_url( '/product-category/moisture-separator/' ), 301 );
		exit;
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) && ! is_wc_endpoint_url( 'order-received' ) ) {
		wp_safe_redirect( vzo_enquiry_url(), 302 );
		exit;
	}
}, 1 );

/* -------------------------------------------------------------------------
 * WooCommerce: catalogue mode (no buying, no prices, no zoom/lightbox).
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_is_purchasable', '__return_false' );
add_filter( 'woocommerce_get_price_html', '__return_empty_string' );
add_filter( 'woocommerce_get_stock_html', '__return_empty_string' );

add_action( 'after_setup_theme', function () {
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );
}, 100 );
add_action( 'wp', function () {
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );
}, 100 );

// Product photo: show the medium-size image, not wrapped in a link to the full-size file.
add_filter( 'woocommerce_gallery_image_size', function () {
	return 'woocommerce_single';
} );
add_filter( 'woocommerce_single_product_image_thumbnail_html', function ( $html ) {
	return preg_replace( '#<a\s[^>]*>(.*?)</a>#s', '$1', $html );
} );
// Do not offer browsers the very large originals in srcset.
add_filter( 'max_srcset_image_width', function () {
	return 1024;
} );
add_filter( 'wp_editor_set_quality', function ( $q, $mime ) {
	return 'image/jpeg' === $mime ? 72 : $q;
}, 10, 2 );

// Reviews are not used for industrial products.
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	unset( $tabs['reviews'] );
	return $tabs;
}, 98 );

// Loop: replace the add-to-cart button with "Add to Enquiry" + "View Details".
add_filter( 'woocommerce_loop_add_to_cart_link', function ( $html, $product ) {
	return vzo_enquiry_button( $product, 'loop' );
}, 100, 2 );

function vzo_enquiry_button( $product, $context = 'loop' ) {
	$id    = $product->get_id();
	$img   = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
	$attrs = sprintf(
		'data-vzo-id="%d" data-vzo-title="%s" data-vzo-url="%s" data-vzo-img="%s"',
		$id,
		esc_attr( $product->get_name() ),
		esc_url( get_permalink( $id ) ),
		esc_url( $img ? $img : '' )
	);
	$out = '<div class="vzo-enq-actions vzo-enq-actions-' . esc_attr( $context ) . '">';
	if ( 'single' === $context ) {
		$out .= '<label class="vzo-enq-qty-label">Quantity <input type="number" min="1" value="1" class="vzo-enq-qty" inputmode="numeric"></label>';
	}
	$out .= '<button type="button" class="button vzo-enq-add" ' . $attrs . '><span class="vzo-enq-add-text">Add to Enquiry</span></button>';
	if ( 'loop' === $context ) {
		$out .= '<a class="button vzo-enq-view" href="' . esc_url( get_permalink( $id ) ) . '">View Details</a>';
	}
	$out .= '</div>';
	return $out;
}

// Single product: specification table + enquiry button.
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	echo vzo_product_specs( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo vzo_enquiry_button( $product, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput
}, 25 );

function vzo_product_specs( $product ) {
	$groups = vzo_cat_groups();
	$terms  = get_the_terms( $product->get_id(), 'product_cat' );
	$rows   = array(
		'type'          => array(),
		'material'      => array(),
		'connection'    => array(),
		'application'   => array(),
		'certification' => array(),
	);
	foreach ( (array) $terms as $t ) {
		if ( ! $t instanceof WP_Term || 'uncategorized' === $t->slug ) {
			continue;
		}
		$group = 'type';
		foreach ( $groups as $g => $slugs ) {
			if ( in_array( $t->slug, $slugs, true ) ) {
				$group = $g;
			}
		}
		$rows[ $group ][] = '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
	}
	$labels = array(
		'type'          => 'Product Type',
		'material'      => 'Material',
		'connection'    => 'End Connection',
		'application'   => 'Application',
		'certification' => 'Certification',
	);
	$code   = trim( wp_strip_all_tags( $product->get_short_description() ) );
	$html   = '<table class="vzo-specs"><tbody>';
	if ( $code && preg_match( '/^VZO[\w\-]*$/i', $code ) ) {
		$html .= '<tr><th>Product Code</th><td>' . esc_html( $code ) . '</td></tr>';
	}
	foreach ( $labels as $k => $label ) {
		if ( $rows[ $k ] ) {
			$html .= '<tr><th>' . esc_html( $label ) . '</th><td>' . implode( ', ', $rows[ $k ] ) . '</td></tr>';
		}
	}
	$html .= '<tr><th>Sizes &amp; Dimensions</th><td>Available on request. Add this product to your enquiry, or <a href="' . esc_url( home_url( '/wp-content/uploads/2025/02/CATELOGUE-PDF.pdf' ) ) . '" target="_blank" rel="noopener">download our catalogue</a>.</td></tr>';
	$html .= '</tbody></table>';
	return $html;
}

// Hide the plain "VZO123" short description (it is now in the spec table).
add_filter( 'woocommerce_short_description', function ( $desc ) {
	return preg_match( '/^\s*(<p>)?\s*VZO[\w\-]*\s*(<\/p>)?\s*$/i', $desc ) ? '' : $desc;
} );

/* -------------------------------------------------------------------------
 * Product archive filters: Material / Used in (All, Bronze, Cast Iron, ...).
 * ---------------------------------------------------------------------- */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! ( $q->is_post_type_archive( 'product' ) || $q->is_tax( 'product_cat' ) ) ) {
		return;
	}
	$tax = (array) $q->get( 'tax_query' );
	foreach ( array( 'material', 'usedin' ) as $param ) {
		if ( ! empty( $_GET[ $param ] ) ) {
			$tax[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => sanitize_title( wp_unslash( $_GET[ $param ] ) ),
			);
		}
	}
	if ( count( $tax ) > 0 ) {
		$q->set( 'tax_query', $tax );
	}
} );

add_action( 'woocommerce_before_shop_loop', function () {
	$groups  = vzo_cat_groups();
	$current = is_tax( 'product_cat' ) ? get_queried_object() : null;
	$base    = $current ? get_term_link( $current ) : get_permalink( wc_get_page_id( 'shop' ) );
	$filters = array(
		'material' => array( 'By Material', $groups['material'] ),
		'usedin'   => array( 'Used In', $groups['application'] ),
	);
	echo '<div class="vzo-filters">';
	foreach ( $filters as $param => $f ) {
		// Skip a row when the page itself is one of its categories (e.g. /product-category/bronze/).
		if ( $current && in_array( $current->slug, $f[1], true ) ) {
			continue;
		}
		$active = isset( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : '';
		echo '<div class="vzo-filter-row"><span class="vzo-filter-label">' . esc_html( $f[0] ) . ':</span><div class="vzo-filter-pills">';
		echo '<a class="vzo-pill' . ( '' === $active ? ' is-active' : '' ) . '" href="' . esc_url( remove_query_arg( array( $param, 'paged' ), add_query_arg( array() ) ) ) . '">All</a>';
		foreach ( $f[1] as $slug ) {
			$t = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $t || ! $t->count ) {
				continue;
			}
			$url = add_query_arg( $param, $slug, remove_query_arg( 'paged', add_query_arg( array() ) ) );
			$url = preg_replace( '#/page/\d+/#', '/', $url );
			echo '<a class="vzo-pill' . ( $active === $slug ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $t->name ) . '</a>';
		}
		echo '</div></div>';
	}
	echo '</div>';
	unset( $base );
}, 40 );

// Catalogue mode: no price/rating sort options.
add_filter( 'woocommerce_catalog_orderby', function ( $opts ) {
	unset( $opts['price'], $opts['price-desc'], $opts['rating'] );
	return $opts;
} );

/* -------------------------------------------------------------------------
 * Enquiry storage + email.
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type( 'vzo_enquiry', array(
		'label'           => 'Enquiries',
		'labels'          => array( 'name' => 'Enquiries', 'singular_name' => 'Enquiry' ),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => array( 'title', 'editor' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

add_action( 'admin_post_nopriv_vzo_enquiry', 'vzo_handle_enquiry' );
add_action( 'admin_post_vzo_enquiry', 'vzo_handle_enquiry' );

function vzo_handle_enquiry() {
	$back = vzo_enquiry_url();
	$p    = wp_unslash( $_POST );

	// Spam checks: honeypot, minimum fill time, per-IP rate limit.
	$started = isset( $p['vzo_ts'] ) ? (int) $p['vzo_ts'] : 0;
	if ( ! empty( $p['vzo_website'] ) || ( $started && time() - $started < 3 ) ) {
		wp_safe_redirect( add_query_arg( 'sent', '1', $back ) );
		exit;
	}
	$ip_key = 'vzo_enq_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		wp_safe_redirect( add_query_arg( 'error', 'limit', $back ) );
		exit;
	}

	$name    = sanitize_text_field( $p['vzo_name'] ?? '' );
	$company = sanitize_text_field( $p['vzo_company'] ?? '' );
	$email   = sanitize_email( $p['vzo_email'] ?? '' );
	$phone   = sanitize_text_field( $p['vzo_phone'] ?? '' );
	$city    = sanitize_text_field( $p['vzo_city'] ?? '' );
	$message = sanitize_textarea_field( $p['vzo_message'] ?? '' );
	$items   = json_decode( (string) ( $p['vzo_items'] ?? '[]' ), true );
	$items   = is_array( $items ) ? array_slice( $items, 0, 100 ) : array();

	if ( ! $name || ! is_email( $email ) || ! $phone ) {
		wp_safe_redirect( add_query_arg( 'error', 'fields', $back ) );
		exit;
	}

	$rows = '';
	$text = '';
	$n    = 0;
	foreach ( $items as $it ) {
		$pid = isset( $it['id'] ) ? absint( $it['id'] ) : 0;
		$prd = $pid ? wc_get_product( $pid ) : null;
		if ( ! $prd ) {
			continue;
		}
		$qty  = max( 1, min( 100000, absint( $it['qty'] ?? 1 ) ) );
		$size = sanitize_text_field( $it['size'] ?? '' );
		$n++;
		$code  = trim( wp_strip_all_tags( $prd->get_short_description() ) );
		$rows .= sprintf(
			'<tr><td style="border:1px solid #ddd;padding:6px">%d</td><td style="border:1px solid #ddd;padding:6px"><a href="%s">%s</a></td><td style="border:1px solid #ddd;padding:6px">%s</td><td style="border:1px solid #ddd;padding:6px">%s</td><td style="border:1px solid #ddd;padding:6px">%d</td></tr>',
			$n,
			esc_url( get_permalink( $pid ) ),
			esc_html( $prd->get_name() ),
			esc_html( $code ),
			esc_html( $size ),
			$qty
		);
		$text .= "$n. {$prd->get_name()} ({$code}) | Size: {$size} | Qty: {$qty}\n";
	}

	$info  = '<p><strong>Name:</strong> ' . esc_html( $name ) . '<br><strong>Company:</strong> ' . esc_html( $company ) . '<br><strong>Email:</strong> ' . esc_html( $email ) . '<br><strong>Phone:</strong> ' . esc_html( $phone ) . '<br><strong>City / Country:</strong> ' . esc_html( $city ) . '</p>';
	$info .= '<p><strong>Message:</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';
	$table = $rows ? '<table style="border-collapse:collapse"><tr><th style="border:1px solid #ddd;padding:6px">#</th><th style="border:1px solid #ddd;padding:6px">Product</th><th style="border:1px solid #ddd;padding:6px">Code</th><th style="border:1px solid #ddd;padding:6px">Size</th><th style="border:1px solid #ddd;padding:6px">Qty</th></tr>' . $rows . '</table>' : '<p>(No products selected)</p>';
	$body  = '<h2>New product enquiry from vzovalves.com</h2>' . $info . '<h3>Products (' . $n . ')</h3>' . $table;

	wp_insert_post( array(
		'post_type'    => 'vzo_enquiry',
		'post_status'  => 'private',
		'post_title'   => sprintf( '%s%s - %d product(s)', $name, $company ? " ($company)" : '', $n ),
		'post_content' => $info . "\n<pre>" . esc_html( $text ) . '</pre>',
	) );

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: VZO Website <wordpress@vzovalves.com>',
		'Reply-To: ' . $name . ' <' . $email . '>',
	);
	$ok = wp_mail( VZO_ENQUIRY_EMAIL, 'New product enquiry: ' . $name . ( $company ? " ($company)" : '' ), $body, $headers );

	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
	wp_safe_redirect( add_query_arg( $ok ? 'sent' : 'error', $ok ? '1' : 'mail', $back ) );
	exit;
}

add_shortcode( 'vzo_enquiry', function () {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	$msg = '';
	if ( isset( $_GET['sent'] ) ) {
		$msg = '<div class="vzo-enq-notice is-ok" data-vzo-clear="1">Thank you! Your enquiry has been sent. Our team will get back to you shortly.</div>';
	} elseif ( isset( $_GET['error'] ) ) {
		$msg = '<div class="vzo-enq-notice is-err">Sorry, we could not send your enquiry. Please fill in your name, email and phone, or email us at <a href="mailto:' . VZO_ENQUIRY_EMAIL . '">' . VZO_ENQUIRY_EMAIL . '</a>.</div>';
	}
	ob_start();
	?>
	<div class="vzo-enquiry">
		<?php echo $msg; // phpcs:ignore ?>
		<div class="vzo-enq-list" id="vzo-enq-list">
			<p class="vzo-enq-empty">Your enquiry list is empty. Browse <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Our Products</a> and click "Add to Enquiry" on the items you need, or just send us a message below.</p>
		</div>
		<form class="vzo-enq-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="vzo_enquiry">
			<input type="hidden" name="vzo_items" id="vzo-enq-items" value="[]">
			<input type="hidden" name="vzo_ts" id="vzo-enq-ts" value="">
			<div class="vzo-hp" aria-hidden="true"><label>Website <input type="text" name="vzo_website" tabindex="-1" autocomplete="off"></label></div>
			<h3>Your Details</h3>
			<div class="vzo-enq-grid">
				<label>Name *<input type="text" name="vzo_name" required autocomplete="name"></label>
				<label>Company<input type="text" name="vzo_company" autocomplete="organization"></label>
				<label>Email *<input type="email" name="vzo_email" required autocomplete="email"></label>
				<label>Phone *<input type="tel" name="vzo_phone" required autocomplete="tel"></label>
				<label class="vzo-full">City / Country<input type="text" name="vzo_city"></label>
				<label class="vzo-full">Message / Requirements<textarea name="vzo_message" rows="4" placeholder="Sizes, pressure rating, quantity, delivery location..."></textarea></label>
			</div>
			<button type="submit" class="button vzo-enq-submit">Send Enquiry</button>
		</form>
	</div>
	<?php
	return ob_get_clean();
} );

/* -------------------------------------------------------------------------
 * Front-end CSS.
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	?>
	<style id="vzo-custom-css">
		/* Fixed nav bar: keep the scheme background once pinned. */
		.scheme_dark .sc_layouts_row_fixed_on, .scheme_dark.sc_layouts_row_fixed_on { background-color: var(--theme-color-alter_bg_color, #070E26) !important; }

		/* Footer credit: smaller. */
		.vzo-credit { font-size: 0.75em; opacity: .8; }

		/* No likes / views counters. */
		.post_meta_likes, .post_meta_views, .post_counters_likes, .post_counters_views { display: none !important; }

		/* Cart, wishlist, prices hidden (enquiry list replaces the cart). */
		.sc_layouts_cart, .post_featured .icons .shop_cart, .tinv-wraper, .tinvwl_add_to_wishlist_button,
		.woocommerce .product .price, .single_add_to_cart_button { display: none !important; }

		/* Map overlay link. */
		.sc_googlemap_wrap { position: relative; }
		.vzo-map-link { position: absolute; inset: 0; z-index: 5; display: block; }

		/* Product cards: boxed, theme colours. */
		ul.products li.product:not(.product-category) .post_item { border: 1px solid var(--theme-color-bd_color); background: var(--theme-color-bg_color); height: 100%; display: flex; flex-direction: column; }
		ul.products li.product:not(.product-category) .post_featured { background: var(--theme-color-alter_bg_color); margin-bottom: 0 !important; }
		ul.products li.product:not(.product-category) .post_data { padding: 1.2em; flex: 1; display: flex; }
		ul.products li.product:not(.product-category) .post_data_inner { display: flex; flex-direction: column; width: 100%; }
		ul.products li.product:not(.product-category) .vzo-enq-actions-loop { margin-top: auto; padding-top: 0.8em; display: flex; flex-direction: column; align-items: flex-start; gap: 0.6em; }
		ul.products li.product:not(.product-category) .vzo-enq-actions-loop .vzo-enq-view { background: none !important; border: 0 !important; padding: 0 !important; color: var(--theme-color-text_link) !important; text-transform: none; letter-spacing: 0; }

		/* Single product. */
		.vzo-enq-actions-single { display: flex; gap: 1em; align-items: flex-end; flex-wrap: wrap; margin: 1.5em 0; }
		.vzo-enq-qty-label { display: flex; flex-direction: column; gap: 0.3em; }
		.vzo-enq-qty { width: 6em; }
		.vzo-specs { margin: 1.5em 0; }
		.vzo-specs th { width: 38%; }

		/* Floating enquiry badge + toast (theme colours, square corners). */
		.vzo-enq-badge { position: fixed; left: 16px; bottom: 16px; z-index: 99990; background: var(--theme-color-text_dark); color: var(--theme-color-bg_color) !important; padding: 0.7em 1.1em; display: none; }
		.vzo-enq-badge.is-visible { display: inline-flex; gap: 0.5em; align-items: center; }
		.vzo-enq-badge b { background: var(--theme-color-text_link); min-width: 1.6em; text-align: center; padding: 0 0.3em; }
		.vzo-enq-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: var(--theme-color-text_dark); color: var(--theme-color-bg_color); padding: 0.8em 1.3em; z-index: 99999; opacity: 0; pointer-events: none; transition: opacity .25s; max-width: calc(100% - 32px); text-align: center; }
		.vzo-enq-toast.is-on { opacity: 1; pointer-events: auto; }
		.vzo-enq-toast a { color: inherit; text-decoration: underline; margin-left: 0.4em; }

		/* Archive filters: theme-style tag buttons. */
		.vzo-filters { margin: 0 0 2em; display: flex; flex-direction: column; gap: 0.8em; clear: both; }
		.vzo-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5em; }
		.vzo-filter-label { color: var(--theme-color-text_dark); min-width: 7em; }
		.vzo-filter-pills { display: flex; flex-wrap: wrap; gap: 0.4em; }
		.vzo-pill { padding: 0.45em 1em; border: 1px solid var(--theme-color-bd_color); color: var(--theme-color-text); }
		.vzo-pill:hover { border-color: var(--theme-color-text_dark); color: var(--theme-color-text_dark); }
		.vzo-pill.is-active { background: var(--theme-color-text_dark); border-color: var(--theme-color-text_dark); color: var(--theme-color-bg_color); }

		/* Enquiry page. */
		.vzo-enquiry { max-width: 900px; margin: 0 auto; }
		.vzo-enq-notice { padding: 1em 1.2em; margin-bottom: 1.5em; border-left: 3px solid var(--theme-color-text_link); background: var(--theme-color-alter_bg_color); color: var(--theme-color-text_dark); }
		.vzo-enq-table { width: 100%; margin-bottom: 2em; }
		.vzo-enq-table td { vertical-align: middle; }
		.vzo-enq-table img { width: 56px; height: 56px; object-fit: contain; background: var(--theme-color-alter_bg_color); }
		.vzo-enq-table input { max-width: 120px; }
		.vzo-enq-table .vzo-enq-size { max-width: 170px; }
		.vzo-enq-remove { background: none !important; border: 0 !important; color: var(--theme-color-text_link) !important; cursor: pointer; font-size: 1.4em; line-height: 1; padding: 0.2em 0.5em !important; }
		.vzo-enq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2em 1.5em; margin-bottom: 1.5em; }
		.vzo-enq-grid label { display: flex; flex-direction: column; gap: 0.4em; }
		.vzo-enq-grid .vzo-full { grid-column: 1 / -1; }
		.vzo-hp { position: absolute !important; left: -9999px !important; height: 1px; overflow: hidden; }
		@media (max-width: 767px) {
			.vzo-enq-grid { grid-template-columns: 1fr; }
			.vzo-enq-table thead { display: none; }
			.vzo-enq-table tr { display: grid; grid-template-columns: 64px 1fr auto; gap: 6px 10px; padding: 10px 0; }
			.vzo-enq-table tr { background: transparent !important; border-bottom: 1px solid var(--theme-color-bd_color); }
			.vzo-enq-table td { border: 0; padding: 0; background: transparent !important; }
			.vzo-enq-table td.vzo-c-img { grid-row: span 3; }
			.vzo-enq-table td.vzo-c-rm { grid-column: 3; grid-row: 1; }
			.vzo-enq-table td.vzo-c-size, .vzo-enq-table td.vzo-c-qty { grid-column: 2 / span 2; }
			.vzo-filter-label { min-width: 0; width: 100%; }
		}
	</style>
	<?php
}, 100 );

/* -------------------------------------------------------------------------
 * Front-end JS: enquiry list (localStorage) + map links.
 * ---------------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	?>
	<a class="vzo-enq-badge" href="<?php echo esc_url( vzo_enquiry_url() ); ?>">Enquiry List <b>0</b></a>
	<div class="vzo-enq-toast" role="status" aria-live="polite"></div>
	<script id="vzo-custom-js">
	(function () {
		var KEY = 'vzo_enquiry', ENQ_URL = <?php echo wp_json_encode( vzo_enquiry_url() ); ?>, MAP_URL = <?php echo wp_json_encode( vzo_map_url() ); ?>;
		function load() { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; } }
		function save(l) { try { localStorage.setItem(KEY, JSON.stringify(l)); } catch (e) {} refresh(); }
		function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
		var toastTimer;
		function toast(html) { var t = document.querySelector('.vzo-enq-toast'); if (!t) return; t.innerHTML = html; t.classList.add('is-on'); clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.classList.remove('is-on'); }, 3500); }
		function refresh() {
			var l = load(), b = document.querySelector('.vzo-enq-badge');
			if (b) { b.querySelector('b').textContent = l.length; b.classList.toggle('is-visible', l.length > 0 && !document.getElementById('vzo-enq-list')); }
			document.querySelectorAll('.vzo-enq-add').forEach(function (btn) {
				var inList = l.some(function (i) { return String(i.id) === btn.dataset.vzoId; });
				btn.classList.toggle('is-added', inList);
				btn.querySelector('.vzo-enq-add-text').textContent = inList ? 'Added ✓ View List' : 'Add to Enquiry';
			});
			renderList();
		}
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.vzo-enq-add');
			if (!btn) return;
			e.preventDefault();
			var l = load(), id = btn.dataset.vzoId;
			if (l.some(function (i) { return String(i.id) === id; })) { window.location.href = ENQ_URL; return; }
			var wrap = btn.closest('.vzo-enq-actions'), q = wrap && wrap.querySelector('.vzo-enq-qty');
			l.push({ id: id, title: btn.dataset.vzoTitle, url: btn.dataset.vzoUrl, img: btn.dataset.vzoImg, qty: Math.max(1, parseInt(q ? q.value : 1, 10) || 1), size: '' });
			save(l);
			toast('Added to your enquiry list.<a href="' + ENQ_URL + '">View list (' + l.length + ')</a>');
		});
		function renderList() {
			var box = document.getElementById('vzo-enq-list'); if (!box) return;
			var l = load(), field = document.getElementById('vzo-enq-items');
			if (field) field.value = JSON.stringify(l.map(function (i) { return { id: i.id, qty: i.qty, size: i.size }; }));
			if (!l.length) { box.querySelector('.vzo-enq-empty') || (box.innerHTML = box.dataset.empty || ''); return; }
			if (!box.dataset.empty) box.dataset.empty = box.innerHTML;
			var rows = l.map(function (i, n) {
				return '<tr data-n="' + n + '"><td class="vzo-c-img">' + (i.img ? '<img src="' + esc(i.img) + '" alt="">' : '') + '</td>' +
					'<td class="vzo-c-name"><a href="' + esc(i.url) + '">' + esc(i.title) + '</a></td>' +
					'<td class="vzo-c-size"><input class="vzo-enq-size" type="text" placeholder="Size (e.g. 25mm)" value="' + esc(i.size) + '" aria-label="Size"></td>' +
					'<td class="vzo-c-qty"><input class="vzo-enq-q" type="number" min="1" value="' + esc(i.qty) + '" aria-label="Quantity"></td>' +
					'<td class="vzo-c-rm"><button type="button" class="vzo-enq-remove" aria-label="Remove">&times;</button></td></tr>';
			}).join('');
			box.innerHTML = '<h3>Products in your enquiry (' + l.length + ')</h3><table class="vzo-enq-table"><thead><tr><th></th><th>Product</th><th>Size</th><th>Qty</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
		}
		document.addEventListener('input', function (e) {
			var tr = e.target.closest('#vzo-enq-list tr[data-n]'); if (!tr) return;
			var l = load(), i = l[+tr.dataset.n]; if (!i) return;
			if (e.target.classList.contains('vzo-enq-q')) i.qty = Math.max(1, parseInt(e.target.value, 10) || 1);
			if (e.target.classList.contains('vzo-enq-size')) i.size = e.target.value.slice(0, 60);
			try { localStorage.setItem(KEY, JSON.stringify(l)); } catch (er) {}
			var field = document.getElementById('vzo-enq-items');
			if (field) field.value = JSON.stringify(l.map(function (x) { return { id: x.id, qty: x.qty, size: x.size }; }));
		});
		document.addEventListener('click', function (e) {
			var rm = e.target.closest('.vzo-enq-remove'); if (!rm) return;
			var l = load(); l.splice(+rm.closest('tr').dataset.n, 1); save(l);
		});
		function init() {
			var ts = document.getElementById('vzo-enq-ts'); if (ts) ts.value = Math.floor(Date.now() / 1000);
			if (document.querySelector('.vzo-enq-notice[data-vzo-clear]')) { try { localStorage.removeItem(KEY); } catch (e) {} }
			refresh();
			// Maps: clicking opens Bajaj Engineering Works on Google Maps.
			document.querySelectorAll('.sc_googlemap_wrap').forEach(function (m) {
				if (m.querySelector('.vzo-map-link')) return;
				var a = document.createElement('a');
				a.className = 'vzo-map-link'; a.href = MAP_URL; a.target = '_blank'; a.rel = 'noopener';
				a.setAttribute('aria-label', 'Open Bajaj Engineering Works location in Google Maps');
				
				m.appendChild(a);
			});
		}
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
	})();
	</script>
	<?php
}, 100 );
