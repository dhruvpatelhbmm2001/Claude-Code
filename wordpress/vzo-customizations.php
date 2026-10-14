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
	$out .= '<button type="button" class="vzo-enq-add" ' . $attrs . '><span class="vzo-enq-add-text">Add to Enquiry</span></button>';
	if ( 'loop' === $context ) {
		$out .= '<a class="vzo-enq-view" href="' . esc_url( get_permalink( $id ) ) . '">View Details</a>';
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
}, 5 );

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
			<button type="submit" class="vzo-enq-submit">Send Enquiry</button>
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
		/* Fixed nav bar: solid background once pinned. */
		.scheme_dark .sc_layouts_row_fixed_on,
		.scheme_dark.sc_layouts_row_fixed_on { background-color: #070E26 !important; box-shadow: 0 2px 8px rgba(0,0,0,.25); }
		.sc_layouts_row_fixed_on { box-shadow: 0 2px 8px rgba(0,0,0,.08); }

		/* Footer credit: smaller. */
		.vzo-credit { display: inline-block; font-size: 11px; opacity: .75; margin-left: 4px; }
		.vzo-credit a { font-size: inherit; }

		/* No likes / views counters. */
		.post_meta_likes, .post_meta_views, .post_counters_likes, .post_counters_views { display: none !important; }

		/* Cart and wishlist UI removed (enquiry list replaces it). */
		.sc_layouts_cart, .post_featured .icons .shop_cart, .tinv-wraper, .tinvwl_add_to_wishlist_button,
		.woocommerce .product .price, .single_add_to_cart_button { display: none !important; }

		/* Map: whole map is a link to Bajaj Engineering Works on Google Maps. */
		.sc_googlemap_wrap { position: relative; }
		.vzo-map-link { position: absolute; inset: 0; z-index: 5; display: block; }
		.vzo-map-link span { position: absolute; left: 50%; bottom: 20px; transform: translateX(-50%); background: #DF3C2A; color: #fff; padding: 10px 18px; border-radius: 4px; font-size: 14px; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,.2); }

		/* Product cards: boxed display. */
		ul.products li.product .post_item { background: #fff; border: 1px solid #E4E7EF; border-radius: 8px; overflow: hidden; height: 100%; display: flex; flex-direction: column; transition: box-shadow .25s, transform .25s; }
		ul.products li.product .post_item:hover { box-shadow: 0 10px 28px rgba(7,14,38,.12); transform: translateY(-3px); }
		ul.products li.product .post_featured { background: #F6F7FA; aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; margin: 0 !important; }
		ul.products li.product .post_featured > a { display: block; width: 100%; height: 100%; }
		ul.products li.product .post_featured img { width: 100%; height: 100%; object-fit: contain; padding: 14px; box-sizing: border-box; }
		ul.products li.product .post_data { padding: 16px 18px 18px; flex: 1; display: flex; }
		ul.products li.product .post_data_inner { display: flex; flex-direction: column; width: 100%; }
		ul.products li.product .woocommerce-loop-product__title { font-size: 17px !important; line-height: 1.35 !important; margin: 0 0 14px !important; }
		ul.products li.product .vzo-enq-actions-loop { margin-top: auto; display: flex; gap: 8px; flex-wrap: wrap; }

		/* Enquiry buttons. */
		.vzo-enq-add, .vzo-enq-view, .vzo-enq-submit { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; font-size: 14px; line-height: 1.2; border-radius: 4px; cursor: pointer; text-decoration: none; border: 1px solid #DF3C2A; transition: background .2s, color .2s; }
		.vzo-enq-add, .vzo-enq-submit { background: #DF3C2A; color: #fff !important; }
		.vzo-enq-add:hover, .vzo-enq-submit:hover { background: #b92f20; border-color: #b92f20; }
		.vzo-enq-add.is-added { background: #17204E; border-color: #17204E; }
		.vzo-enq-view { background: transparent; color: #17204E !important; border-color: #C9CEDC; }
		.vzo-enq-view:hover { border-color: #17204E; }
		.vzo-enq-actions-loop .vzo-enq-add, .vzo-enq-actions-loop .vzo-enq-view { flex: 1 1 120px; }
		.vzo-enq-actions-single { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 20px 0; }
		.vzo-enq-qty-label { display: flex; flex-direction: column; font-size: 13px; gap: 4px; }
		.vzo-enq-qty { width: 90px; padding: 8px 10px; }

		/* Floating enquiry badge. */
		.vzo-enq-badge { position: fixed; left: 16px; bottom: 16px; z-index: 99990; background: #17204E; color: #fff !important; padding: 10px 16px; border-radius: 30px; font-size: 14px; box-shadow: 0 6px 18px rgba(0,0,0,.25); display: none; text-decoration: none; }
		.vzo-enq-badge.is-visible { display: inline-flex; gap: 8px; align-items: center; }
		.vzo-enq-badge b { background: #DF3C2A; border-radius: 20px; min-width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
		.vzo-enq-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #17204E; color: #fff; padding: 12px 20px; border-radius: 6px; z-index: 99999; font-size: 14px; opacity: 0; pointer-events: none; transition: opacity .25s; max-width: calc(100% - 32px); text-align: center; }
		.vzo-enq-toast.is-on { opacity: 1; pointer-events: auto; }
		.vzo-enq-toast a { color: #fff; text-decoration: underline; margin-left: 6px; }

		/* Specs table. */
		.vzo-specs { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 15px; }
		.vzo-specs th, .vzo-specs td { border: 1px solid #E4E7EF; padding: 10px 12px; text-align: left; vertical-align: top; }
		.vzo-specs th { background: #F6F7FA; width: 38%; font-weight: 600; }

		/* Archive filters. */
		.vzo-filters { margin: 0 0 24px; display: flex; flex-direction: column; gap: 10px; clear: both; }
		.vzo-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
		.vzo-filter-label { font-weight: 600; min-width: 95px; }
		.vzo-filter-pills { display: flex; flex-wrap: wrap; gap: 6px; }
		.vzo-pill { padding: 6px 14px; border: 1px solid #C9CEDC; border-radius: 30px; font-size: 14px; line-height: 1.3; color: #17204E; text-decoration: none; background: #fff; }
		.vzo-pill:hover { border-color: #17204E; }
		.vzo-pill.is-active { background: #17204E; border-color: #17204E; color: #fff; }

		/* Enquiry page. */
		.vzo-enquiry { max-width: 900px; margin: 0 auto; }
		.vzo-enq-notice { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; }
		.vzo-enq-notice.is-ok { background: #E8F6EC; color: #1E6B34; }
		.vzo-enq-notice.is-err { background: #FDECEA; color: #8A1C12; }
		.vzo-enq-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
		.vzo-enq-table th, .vzo-enq-table td { border-bottom: 1px solid #E4E7EF; padding: 10px 8px; text-align: left; vertical-align: middle; }
		.vzo-enq-table img { width: 56px; height: 56px; object-fit: contain; background: #F6F7FA; border-radius: 4px; }
		.vzo-enq-table input { width: 100%; max-width: 110px; padding: 6px 8px; }
		.vzo-enq-table .vzo-enq-size { max-width: 160px; }
		.vzo-enq-remove { background: none; border: 0; color: #DF3C2A; cursor: pointer; font-size: 20px; line-height: 1; padding: 4px 8px; }
		.vzo-enq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; margin-bottom: 18px; }
		.vzo-enq-grid label { display: flex; flex-direction: column; gap: 6px; font-size: 14px; }
		.vzo-enq-grid .vzo-full { grid-column: 1 / -1; }
		.vzo-hp { position: absolute !important; left: -9999px !important; height: 1px; overflow: hidden; }
		@media (max-width: 767px) {
			.vzo-enq-grid { grid-template-columns: 1fr; }
			.vzo-enq-table thead { display: none; }
			.vzo-enq-table tr { display: grid; grid-template-columns: 64px 1fr auto; gap: 6px 10px; padding: 10px 0; border-bottom: 1px solid #E4E7EF; }
			.vzo-enq-table td { border: 0; padding: 0; }
			.vzo-enq-table td.vzo-c-img { grid-row: span 3; }
			.vzo-enq-table td.vzo-c-rm { grid-column: 3; grid-row: 1; }
			.vzo-enq-table td.vzo-c-size, .vzo-enq-table td.vzo-c-qty { grid-column: 2 / span 2; }
			.vzo-filter-label { min-width: 0; width: 100%; }
			.vzo-map-link span { font-size: 13px; padding: 8px 14px; }
			ul.products li.product .woocommerce-loop-product__title { font-size: 15px !important; }
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
				a.innerHTML = '<span>Open in Google Maps</span>';
				m.appendChild(a);
			});
		}
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
	})();
	</script>
	<?php
}, 100 );
