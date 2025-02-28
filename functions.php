<?php
/*
Plugin Name: WP My Product Webspark
Description: Плагін для створення і редагування продуктів через сторінку My Account.
Version: 1.0
Author: Bohdan
Text Domain: wp-my-product-webspark
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( class_exists( 'WooCommerce' ) ) {
	// Register new pages in My Account
	add_filter( 'woocommerce_account_menu_items', 'wp_my_product_menu_pages' );
	function wp_my_product_menu_pages( $items ) {
		$items['add-product'] = __( 'Add Product', 'wp-my-product-webspark' );
		$items['my-products'] = __( 'My Products', 'wp-my-product-webspark' );

		return $items;
	}

	add_action( 'init', 'wp_my_product_add_endpoints' );
	function wp_my_product_add_endpoints() {
		add_rewrite_endpoint( 'add-product', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'my-products', EP_ROOT | EP_PAGES );
	}

	// Add page "Add Product"
	add_action( 'woocommerce_account_add-product_endpoint', 'wp_my_product_add_page_add' );
	function wp_my_product_add_page_add() {
		?>
		<h2><?php esc_html_e( 'Add Product', 'wp-my-product-webspark' ); ?></h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'add_product_nonce', 'add_product_nonce_field' ); ?>
			<input type="text" name="product_name"
				   placeholder="<?php esc_html_e( 'Product Name', 'wp-my-product-webspark' ); ?>" required>
			<input type="number" name="product_price"
				   placeholder="<?php esc_html_e( 'Price', 'wp-my-product-webspark' ); ?>" required>
			<input type="number" name="product_quantity"
				   placeholder="<?php esc_html_e( 'Quantity', 'wp-my-product-webspark' ); ?>" required>
			<?php wp_editor( '', 'product_description', array( 'textarea_name' => 'product_description' ) ); ?>
			<input type="file" name="product_image" accept="image/*" required>
			<button type="submit"><?php esc_html_e( 'Save Product', 'wp-my-product-webspark' ); ?></button>
		</form>
		<?php
	}

	// Додавання продукту після сабміту форми
	add_action( 'template_redirect', 'wp_my_product_save_product' );
	function wp_my_product_save_product() {
		if ( isset( $_POST['product_name'] ) && isset( $_POST['add_product_nonce_field'] ) && wp_verify_nonce( $_POST['add_product_nonce_field'], 'add_product_nonce' ) ) {
			// add status 'pending review'
			$product_data = array(
				'post_title'   => sanitize_text_field( $_POST['product_name'] ),
				'post_content' => sanitize_textarea_field( $_POST['product_description'] ),
				'post_status'  => 'pending',
				'post_type'    => 'product',
			);
			$product_id   = wp_insert_post( $product_data );

			if ( ! is_wp_error( $product_id ) ) {
				update_post_meta( $product_id, '_regular_price', sanitize_text_field( $_POST['product_price'] ) );
				update_post_meta( $product_id, '_price', sanitize_text_field( $_POST['product_price'] ) );
				update_post_meta( $product_id, '_stock', sanitize_text_field( $_POST['product_quantity'] ) );
				// Завантаження зображення
				if ( ! empty( $_FILES['product_image'] ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';

					$image_id = media_handle_upload( 'product_image', 0 );

					if ( is_wp_error( $image_id ) ) {
						echo 'Image loading error: ' . $image_id->get_error_message();
					} else {
						update_post_meta( $product_id, '_thumbnail_id', $image_id );
					}
				}
				wp_redirect( wc_get_account_endpoint_url( 'my-products' ) . '?success=1' );
				exit;
			}
		}
	}

	add_action( 'woocommerce_account_my-products_endpoint', 'wp_my_product_add_page_products' );
	function wp_my_product_add_page_products() {
		$current_user_id = get_current_user_id();
		$args            = array(
			'post_type'      => 'product',
			'author'         => $current_user_id,
			'post_status'    => 'any',
			'posts_per_page' => - 1,
		);
		$my_products     = new WP_Query( $args );
		if ( $my_products->have_posts() ) :
			?>
			<?php if ( isset( $_GET['success'] ) && $_GET['success'] == 1 ) : ?>
			<div class="woocommerce-message">
				<?php esc_html_e( 'Product was successfully added', 'wp-my-product-webspark' ); ?>
			</div>
		<?php endif; ?>
			<h2><?php esc_html_e( 'My Products', 'wp-my-product-webspark' ); ?></h2>
			<table>
				<tr>
					<th><?php echo esc_html( 'Product Name' ); ?></th>
					<th><?php echo esc_html( 'Quantity' ); ?></th>
					<th><?php echo esc_html( 'Price' ); ?></th>
					<th><?php echo esc_html( 'Status' ); ?></th>
					<th><?php echo esc_html( 'Edit' ); ?></th>
					<th><?php echo esc_html( 'Delete' ); ?></th>
				</tr>
				<?php
				while ( $my_products->have_posts() ) :
					$my_products->the_post();
					$product_id = wc_get_product( get_the_ID() );
					?>
					<tr>
						<td><?php the_title(); ?></td>
						<td><?php echo esc_html( $product_id->get_stock_quantity() ); ?></td>
						<td><?php echo esc_html( $product_id->get_price() ); ?></td>
						<td><?php echo esc_html( $product_id->get_status() ); ?></td>
						<td>
							<a href="#">
								<?php echo esc_html( 'Edit' ); ?>
							</a>
						</td>
						<td>
							<a href="#">
								<?php echo esc_html( 'Delete' ); ?>
							</a>
						</td>
					</tr>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</table>
		<?php else : ?>
			<h2><?php esc_html( 'Products Not Found' ); ?></h2>
			<?php
		endif;
	}
}
