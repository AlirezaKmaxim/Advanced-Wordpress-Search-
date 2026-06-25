<?php
/**
 * Class HamSeda_Admin_Settings
 * Handles admin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HamSeda_Admin_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add Settings Submenu Page under Settings (options-general.php).
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'تنظیمات جستجوی هوشمند همصدا', 'hamseda-ajax-search' ),
			__( 'جستجوی هوشمند همصدا', 'hamseda-ajax-search' ),
			'manage_options',
			'hamseda-search-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'hamseda_search_settings_group',
			'hamseda_search_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize setting inputs.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized input.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		if ( is_array( $input ) ) {
			$sanitized['enable_search'] = isset( $input['enable_search'] ) ? (bool) $input['enable_search'] : false;

			$sanitized['post_types'] = array();
			if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				foreach ( $input['post_types'] as $key => $val ) {
					$sanitized['post_types'][ sanitize_key( $key ) ] = (bool) $val;
				}
			}

			$sanitized['custom_labels'] = array();
			if ( isset( $input['custom_labels'] ) && is_array( $input['custom_labels'] ) ) {
				foreach ( $input['custom_labels'] as $key => $val ) {
					$sanitized['custom_labels'][ sanitize_key( $key ) ] = sanitize_text_field( $val );
				}
			}

			$sanitized['results_header_posts'] = isset( $input['results_header_posts'] ) ? sanitize_text_field( $input['results_header_posts'] ) : '';
			$sanitized['results_header_taxonomies'] = isset( $input['results_header_taxonomies'] ) ? sanitize_text_field( $input['results_header_taxonomies'] ) : '';
			$sanitized['search_placeholder'] = isset( $input['search_placeholder'] ) ? sanitize_text_field( $input['search_placeholder'] ) : '';

			$sanitized['taxonomies'] = array();
			if ( isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
				foreach ( $input['taxonomies'] as $key => $val ) {
					$sanitized['taxonomies'][ sanitize_key( $key ) ] = (bool) $val;
				}
			}
		}
		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = get_option( 'hamseda_search_settings', array() );
		$post_types = $this->get_discoverable_post_types();
		$taxonomies = $this->get_discoverable_taxonomies();

		$saved_post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();
		$saved_taxonomies = isset( $options['taxonomies'] ) ? $options['taxonomies'] : array();

		// Default setup if no option saved yet
		if ( empty( $options ) ) {
			$saved_post_types = array( 'post' => true, 'page' => true, 'product' => true, 'esanj' => true );
			$saved_taxonomies = array( 'product_cat' => true );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'hamseda_search_settings_group' );
				?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'وضعیت کلی جستجو', 'hamseda-ajax-search' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="hamseda_search_settings[enable_search]" value="1" <?php checked( isset( $options['enable_search'] ) ? $options['enable_search'] : true ); ?>>
									<?php esc_html_e( 'فعال‌سازی سیستم جستجوی هوشمند AJAX همصدا', 'hamseda-ajax-search' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<hr style="margin: 20px 0;" />

				<h2><?php esc_html_e( 'تنظیمات پیشرفته هوشمند', 'hamseda-ajax-search' ); ?></h2>

				<div class="metabox-holder">
					<!-- Card 1: Active Post Types -->
					<div class="postbox" style="margin-bottom: 20px;">
						<div class="postbox-header" style="border-bottom: 1px solid #c3c4c7;">
							<h2 class="hndle ui-sortable-handle" style="margin: 0; padding: 12px; font-size: 14px;"><?php esc_html_e( 'پست‌تایپ‌های فعال در جستجو', 'hamseda-ajax-search' ); ?></h2>
						</div>
						<div class="inside" style="padding: 15px;">
							<p class="description" style="margin-bottom: 15px; font-style: italic;"><?php esc_html_e( 'پست‌تایپ‌هایی را که مایلید نتایج جستجوی آن‌ها نمایش داده شوند انتخاب کنید.', 'hamseda-ajax-search' ); ?></p>
							<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
								<?php foreach ( $post_types as $slug => $label ) : ?>
									<div style="display: flex; flex-direction: column; gap: 5px; border: 1px solid #eee; padding: 10px; border-radius: 5px;">
										<label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
											<input type="checkbox" name="hamseda_search_settings[post_types][<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( ! empty( $saved_post_types[ $slug ] ) ); ?>>
											<span><?php echo esc_html( $label ); ?> (<code><?php echo esc_html( $slug ); ?></code>)</span>
										</label>
										<input type="text" name="hamseda_search_settings[custom_labels][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( isset( $options['custom_labels'][$slug] ) ? $options['custom_labels'][$slug] : '' ); ?>" placeholder="<?php esc_attr_e( 'عنوان دلخواه (مثال: مطالب)', 'hamseda-ajax-search' ); ?>" style="width: 100%; font-size: 12px;" />
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- Card 2: Active Taxonomies -->
					<div class="postbox">
						<div class="postbox-header" style="border-bottom: 1px solid #c3c4c7;">
							<h2 class="hndle ui-sortable-handle" style="margin: 0; padding: 12px; font-size: 14px;"><?php esc_html_e( 'دسته‌بندی‌های فعال در جستجو', 'hamseda-ajax-search' ); ?></h2>
						</div>
						<div class="inside" style="padding: 15px;">
							<p class="description" style="margin-bottom: 15px; font-style: italic;"><?php esc_html_e( 'دسته‌بندی‌هایی (تاکسونومی) را که مایلید نتایج جستجوی آن‌ها نمایش داده شوند انتخاب کنید.', 'hamseda-ajax-search' ); ?></p>
							<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
								<?php foreach ( $taxonomies as $slug => $label ) : ?>
									<div style="display: flex; flex-direction: column; gap: 5px; border: 1px solid #eee; padding: 10px; border-radius: 5px;">
										<label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
											<input type="checkbox" name="hamseda_search_settings[taxonomies][<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( ! empty( $saved_taxonomies[ $slug ] ) ); ?>>
											<span><?php echo esc_html( $label ); ?> (<code><?php echo esc_html( $slug ); ?></code>)</span>
										</label>
										<input type="text" name="hamseda_search_settings[custom_labels][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( isset( $options['custom_labels'][$slug] ) ? $options['custom_labels'][$slug] : '' ); ?>" placeholder="<?php esc_attr_e( 'عنوان دلخواه', 'hamseda-ajax-search' ); ?>" style="width: 100%; font-size: 12px;" />
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- Card 3: Results Headers -->
					<div class="postbox">
						<div class="postbox-header" style="border-bottom: 1px solid #c3c4c7;">
							<h2 class="hndle ui-sortable-handle" style="margin: 0; padding: 12px; font-size: 14px;"><?php esc_html_e( 'عناوین بخش‌های جستجو', 'hamseda-ajax-search' ); ?></h2>
						</div>
						<div class="inside" style="padding: 15px;">
							<p class="description" style="margin-bottom: 15px; font-style: italic;"><?php esc_html_e( 'عناوین دلخواه برای بخش‌های مختلف نتایج وارد کنید.', 'hamseda-ajax-search' ); ?></p>
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row"><label><?php esc_html_e( 'عنوان نتایج مطالب و محصولات', 'hamseda-ajax-search' ); ?></label></th>
										<td>
											<input type="text" name="hamseda_search_settings[results_header_posts]" value="<?php echo esc_attr( isset( $options['results_header_posts'] ) && ! empty( $options['results_header_posts'] ) ? $options['results_header_posts'] : 'محصولات و مطالب' ); ?>" class="regular-text" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label><?php esc_html_e( 'عنوان نتایج دسته‌بندی‌ها', 'hamseda-ajax-search' ); ?></label></th>
										<td>
											<input type="text" name="hamseda_search_settings[results_header_taxonomies]" value="<?php echo esc_attr( isset( $options['results_header_taxonomies'] ) && ! empty( $options['results_header_taxonomies'] ) ? $options['results_header_taxonomies'] : 'دسته‌بندی‌های مرتبط' ); ?>" class="regular-text" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label><?php esc_html_e( 'متن نگهدارنده (Placeholder) فیلد جستجو', 'hamseda-ajax-search' ); ?></label></th>
										<td>
											<input type="text" name="hamseda_search_settings[search_placeholder]" value="<?php echo esc_attr( isset( $options['search_placeholder'] ) && ! empty( $options['search_placeholder'] ) ? $options['search_placeholder'] : 'جستجوی هوشمند...' ); ?>" class="regular-text" />
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Automatically discover and retrieve all public searchable post types.
	 *
	 * @return array List of discovered post types (slug => label).
	 */
	public function get_discoverable_post_types() {
		$args = array(
			'public'              => true,
			'exclude_from_search' => false,
		);

		$post_types = get_post_types( $args, 'objects' );
		$discovered = array();

		// Internal WordPress post types to filter out
		$exclude_types = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( in_array( $post_type->name, $exclude_types, true ) ) {
					continue;
				}
				$discovered[ $post_type->name ] = $post_type->label;
			}
		}

		return $discovered;
	}

	/**
	 * Automatically discover and retrieve all public taxonomies.
	 *
	 * @return array List of discovered taxonomies (slug => label).
	 */
	public function get_discoverable_taxonomies() {
		$args = array(
			'public' => true,
		);

		$taxonomies = get_taxonomies( $args, 'objects' );
		$discovered = array();

		// Internal WordPress taxonomies to filter out
		$exclude_taxonomies = array( 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area' );

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( in_array( $taxonomy->name, $exclude_taxonomies, true ) ) {
					continue;
				}
				$discovered[ $taxonomy->name ] = $taxonomy->label;
			}
		}

		return $discovered;
	}
}
