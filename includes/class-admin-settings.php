<?php
/**
 * Class Alireza_Admin_Settings
 * Handles admin settings page and color appearance management.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alireza_Admin_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue color picker & admin stylesheet on the settings page.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'alireza-search-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Enqueue Tailwind stylesheet for the admin panel
		wp_enqueue_style(
			'alireza-search-css',
			ALIREZA_SEARCH_URL . 'assets/css/alireza-search.css',
			array(),
			ALIREZA_SEARCH_VERSION
		);

		// Initialize color pickers with RTL & Persian support
		$inline_js = "
		jQuery(document).ready(function($){
			$('.alireza-color-picker').wpColorPicker();
		});
		";
		wp_add_inline_script( 'wp-color-picker', $inline_js );
	}

	/**
	 * Add Settings Page under Admin Sidebar & Settings menu.
	 */
	public function add_settings_page() {
		// 1. Top-level menu page in the WordPress admin menu
		add_menu_page(
			__( 'جستجوی هوشمند علیرضا', 'alireza-ajax-search' ),
			__( 'جستجوی هوشمند', 'alireza-ajax-search' ),
			'manage_options',
			'alireza-search-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-search',
			30
		);

		// 2. Submenu page under Settings (options-general.php)
		add_options_page(
			__( 'تنظیمات جستجوی هوشمند علیرضا', 'alireza-ajax-search' ),
			__( 'جستجوی هوشمند علیرضا', 'alireza-ajax-search' ),
			'manage_options',
			'alireza-search-settings-sub',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'alireza_search_settings_group',
			'alireza_search_settings',
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

			// Post Types
			$sanitized['post_types'] = array();
			if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				foreach ( $input['post_types'] as $key => $val ) {
					$sanitized['post_types'][ sanitize_key( $key ) ] = (bool) $val;
				}
			}

			// Custom Labels
			$sanitized['custom_labels'] = array();
			if ( isset( $input['custom_labels'] ) && is_array( $input['custom_labels'] ) ) {
				foreach ( $input['custom_labels'] as $key => $val ) {
					$sanitized['custom_labels'][ sanitize_key( $key ) ] = sanitize_text_field( $val );
				}
			}

			// Custom Badge Colors
			$sanitized['badge_colors'] = array();
			if ( isset( $input['badge_colors'] ) && is_array( $input['badge_colors'] ) ) {
				foreach ( $input['badge_colors'] as $key => $val ) {
					$color = sanitize_hex_color( $val );
					if ( ! empty( $color ) ) {
						$sanitized['badge_colors'][ sanitize_key( $key ) ] = $color;
					}
				}
			}

			// Global Colors Palette
			$sanitized['colors'] = array();
			$color_keys = array( 'primary', 'secondary', 'input_bg', 'border', 'text', 'placeholder', 'price' );
			if ( isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
				foreach ( $color_keys as $key ) {
					if ( ! empty( $input['colors'][ $key ] ) ) {
						$sanitized['colors'][ $key ] = sanitize_hex_color( $input['colors'][ $key ] );
					}
				}
			}

			// Section Titles & Placeholder
			$sanitized['results_header_posts']      = isset( $input['results_header_posts'] ) ? sanitize_text_field( $input['results_header_posts'] ) : '';
			$sanitized['results_header_taxonomies'] = isset( $input['results_header_taxonomies'] ) ? sanitize_text_field( $input['results_header_taxonomies'] ) : '';
			$sanitized['search_placeholder']        = isset( $input['search_placeholder'] ) ? sanitize_text_field( $input['search_placeholder'] ) : '';

			// Taxonomies
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

		$options = alireza_search()->get_settings();

		$post_types = $this->get_discoverable_post_types();
		$taxonomies = $this->get_discoverable_taxonomies();

		$saved_post_types   = isset( $options['post_types'] ) ? $options['post_types'] : array();
		$saved_taxonomies   = isset( $options['taxonomies'] ) ? $options['taxonomies'] : array();
		$saved_labels       = isset( $options['custom_labels'] ) ? $options['custom_labels'] : array();
		$saved_badge_colors = isset( $options['badge_colors'] ) ? $options['badge_colors'] : array();
		$saved_colors       = isset( $options['colors'] ) ? $options['colors'] : array();

		if ( empty( $saved_post_types ) ) {
			$saved_post_types = array( 'post' => true, 'page' => true, 'product' => true, 'esanj' => true );
		}
		if ( empty( $saved_taxonomies ) ) {
			$saved_taxonomies = array( 'product_cat' => true, 'category' => true );
		}

		$default_primary     = ! empty( $saved_colors['primary'] ) ? $saved_colors['primary'] : '#FA7993';
		$default_secondary   = ! empty( $saved_colors['secondary'] ) ? $saved_colors['secondary'] : '#FFB3C1';
		$default_input_bg    = ! empty( $saved_colors['input_bg'] ) ? $saved_colors['input_bg'] : '#FFFFFF';
		$default_border      = ! empty( $saved_colors['border'] ) ? $saved_colors['border'] : '#E2E8F0';
		$default_text        = ! empty( $saved_colors['text'] ) ? $saved_colors['text'] : '#1E293B';
		$default_placeholder = ! empty( $saved_colors['placeholder'] ) ? $saved_colors['placeholder'] : '#94A3B8';
		$default_price       = ! empty( $saved_colors['price'] ) ? $saved_colors['price'] : '#E11D48';

		$default_badge_palette = array(
			'esanj'   => '#FFB3C1',
			'post'    => '#7BA4F5',
			'page'    => '#64748B',
			'product' => '#F59E0B',
		);
		?>
		<div id="alireza-admin-settings-app">
			<!-- Header Bar -->
			<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 mb-8 border-b border-slate-200">
				<div class="flex items-center gap-3.5">
					<div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-700 shadow-xs">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
					</div>
					<div>
						<div class="flex items-center gap-2.5">
							<h1 class="text-2xl font-black text-slate-800 m-0">تنظیمات جستجوی هوشمند علیرضا</h1>
							<span class="version-badge">نسخه <?php echo esc_html( ALIREZA_SEARCH_VERSION ); ?></span>
						</div>
						<p class="text-xs text-slate-500 mt-1 m-0">مدیریت پالت رنگ، پست‌تایپ‌ها، تاکسونومی‌ها و عملکرد موتور جستجو</p>
					</div>
				</div>
			</div>

			<form action="options.php" method="post" class="w-full">
				<?php settings_fields( 'alireza_search_settings_group' ); ?>

				<!-- Global Switch Card -->
				<div class="admin-card">
					<div class="admin-card-body flex flex-col sm:flex-row sm:items-center justify-between gap-4">
						<div>
							<h3 class="text-base font-bold text-slate-800 m-0">وضعیت کلی سیستم جستجو</h3>
							<p class="text-xs text-slate-500 m-0 mt-1">با فعال‌سازی این گزینه، موتور جستجوی ایجکس روی تمام کدهای کوتاه سایت فعال خواهد بود.</p>
						</div>
						<label class="inline-flex items-center gap-3 cursor-pointer select-none bg-slate-50 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-100 transition-colors">
							<input type="checkbox" name="alireza_search_settings[enable_search]" value="1" <?php checked( isset( $options['enable_search'] ) ? $options['enable_search'] : true ); ?> class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
							<span class="text-sm font-bold text-slate-700">فعال‌سازی سیستم جستجو</span>
						</label>
					</div>
				</div>

				<!-- Grid: Color Palette & Appearance -->
				<div class="admin-card">
					<div class="admin-card-header">
						<div class="flex items-center gap-2.5">
							<span class="text-lg">🎨</span>
							<h3 class="text-base font-bold text-slate-800 m-0">پالت رنگ و استایل ظاهری نوار جستجو</h3>
						</div>
						<span class="text-xs font-semibold text-slate-400">تغییر زنده متغیرهای CSS</span>
					</div>
					<div class="admin-card-body">
						<p class="text-xs text-slate-500 mb-6 m-0">تمامی رنگ‌های نوار جستجو، حاشیه‌ها، فوکوس، پس‌زمینه و قیمت‌ها را مطابق با رنگ‌بندی برند وب‌سایت خود شخصی‌سازی کنید.</p>

						<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
							<!-- Primary Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">رنگ اصلی برند (Primary)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">رنگ آیکون‌ها، هاله فوکوس، لینک‌ها و دکمه‌ها</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][primary]" value="<?php echo esc_attr( $default_primary ); ?>" class="alireza-color-picker" data-default-color="#FA7993" />
							</div>

							<!-- Secondary Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">رنگ ثانویه (Secondary)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">رنگ انیمیشن لودر و هایلایت‌های ظریف</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][secondary]" value="<?php echo esc_attr( $default_secondary ); ?>" class="alireza-color-picker" data-default-color="#FFB3C1" />
							</div>

							<!-- Background Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">پس‌زمینه نوار و پنجره (Background)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">رنگ زمینه نوار ورودی و دراپ‌داون نتایج</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][input_bg]" value="<?php echo esc_attr( $default_input_bg ); ?>" class="alireza-color-picker" data-default-color="#FFFFFF" />
							</div>

							<!-- Border Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">رنگ کادر و خطوط (Border)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">حاشیه نوار در حالت عادی و خطوط جداکننده</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][border]" value="<?php echo esc_attr( $default_border ); ?>" class="alireza-color-picker" data-default-color="#E2E8F0" />
							</div>

							<!-- Text Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">رنگ متن ورودی (Text)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">رنگ فونت متن‌های تایپ‌شده توسط کاربر</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][text]" value="<?php echo esc_attr( $default_text ); ?>" class="alireza-color-picker" data-default-color="#1E293B" />
							</div>

							<!-- Placeholder Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">رنگ متن راهنما (Placeholder)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">متن «جستجوی محصول...» و آیکون غیرفعال</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][placeholder]" value="<?php echo esc_attr( $default_placeholder ); ?>" class="alireza-color-picker" data-default-color="#94A3B8" />
							</div>

							<!-- Price Color -->
							<div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
								<div>
									<label class="block font-bold text-sm text-slate-800 mb-1">رنگ قیمت و تخفیف (Price Accent)</label>
									<p class="text-[11px] text-slate-500 mb-3 m-0">قیمت تخفیف‌خورده و برچسب ناموجود</p>
								</div>
								<input type="text" name="alireza_search_settings[colors][price]" value="<?php echo esc_attr( $default_price ); ?>" class="alireza-color-picker" data-default-color="#E11D48" />
							</div>
						</div>
					</div>
				</div>

				<!-- Grid: Post Types & Badges -->
				<div class="admin-card">
					<div class="admin-card-header">
						<div class="flex items-center gap-2.5">
							<span class="text-lg">📑</span>
							<h3 class="text-base font-bold text-slate-800 m-0">پست‌تایپ‌های فعال و رنگ بج‌ها</h3>
						</div>
						<span class="text-xs font-semibold text-slate-400">انواع محتوای قابل جستجو</span>
					</div>
					<div class="admin-card-body">
						<p class="text-xs text-slate-500 mb-6 m-0">پست‌تایپ‌های مدنظر را فعال کرده و عنوان نمایشی و رنگ بج اختصاصی هرکدام را تنظیم نمایید.</p>

						<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
							<?php foreach ( $post_types as $slug => $label ) : 
								$badge_default = isset( $default_badge_palette[ $slug ] ) ? $default_badge_palette[ $slug ] : '#64748B';
								$current_badge = isset( $saved_badge_colors[ $slug ] ) ? $saved_badge_colors[ $slug ] : $badge_default;
							?>
								<div class="p-4 rounded-xl border border-slate-200 bg-white flex flex-col gap-3 shadow-2xs">
									<label class="inline-flex items-center gap-2.5 cursor-pointer font-bold text-sm text-slate-800">
										<input type="checkbox" name="alireza_search_settings[post_types][<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( ! empty( $saved_post_types[ $slug ] ) ); ?> class="rounded border-slate-300 text-[#FA7993] focus:ring-[#FA7993]">
										<span><?php echo esc_html( $label ); ?> <code class="text-xs bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono"><?php echo esc_html( $slug ); ?></code></span>
									</label>
									
									<input type="text" name="alireza_search_settings[custom_labels][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( isset( $saved_labels[$slug] ) ? $saved_labels[$slug] : '' ); ?>" placeholder="عنوان دلخواه نمایشی (مثال: محصول)" class="admin-input text-xs" />
									
									<div>
										<span class="block text-xs font-semibold text-slate-500 mb-1.5">رنگ بج اختصاصی:</span>
										<input type="text" name="alireza_search_settings[badge_colors][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $current_badge ); ?>" class="alireza-color-picker" data-default-color="<?php echo esc_attr( $badge_default ); ?>" />
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<!-- Grid: Taxonomies -->
				<div class="admin-card">
					<div class="admin-card-header">
						<div class="flex items-center gap-2.5">
							<span class="text-lg">🏷️</span>
							<h3 class="text-base font-bold text-slate-800 m-0">دسته‌بندی‌ها و تاکسونومی‌های فعال</h3>
						</div>
						<span class="text-xs font-semibold text-slate-400">جستجوی بلادرنگ در ساختار دسته‌ها</span>
					</div>
					<div class="admin-card-body">
						<p class="text-xs text-slate-500 mb-6 m-0">دسته‌بندی‌هایی که مایلید نتایج جستجوی آن‌ها به صورت چیپ‌های دسته‌بندی در بالای نتایج نمایش داده شوند را انتخاب کنید.</p>

						<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
							<?php foreach ( $taxonomies as $slug => $label ) : ?>
								<div class="p-4 rounded-xl border border-slate-200 bg-white flex flex-col gap-3 shadow-2xs">
									<label class="inline-flex items-center gap-2.5 cursor-pointer font-bold text-sm text-slate-800">
										<input type="checkbox" name="alireza_search_settings[taxonomies][<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( ! empty( $saved_taxonomies[ $slug ] ) ); ?> class="rounded border-slate-300 text-[#FA7993] focus:ring-[#FA7993]">
										<span><?php echo esc_html( $label ); ?> <code class="text-xs bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono"><?php echo esc_html( $slug ); ?></code></span>
									</label>
									<input type="text" name="alireza_search_settings[custom_labels][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( isset( $saved_labels[$slug] ) ? $saved_labels[$slug] : '' ); ?>" placeholder="عنوان دلخواه" class="admin-input text-xs" />
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<!-- Section: Titles & Placeholder -->
				<div class="admin-card">
					<div class="admin-card-header">
						<div class="flex items-center gap-2.5">
							<span class="text-lg">📝</span>
							<h3 class="text-base font-bold text-slate-800 m-0">عناوین بخش‌ها و متن راهنما</h3>
						</div>
						<span class="text-xs font-semibold text-slate-400">متون نمایشی در ویجت</span>
					</div>
					<div class="admin-card-body">
						<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
							<div>
								<label class="block font-bold text-sm text-slate-800 mb-1.5">عنوان نتایج مطالب و محصولات</label>
								<input type="text" name="alireza_search_settings[results_header_posts]" value="<?php echo esc_attr( isset( $options['results_header_posts'] ) && ! empty( $options['results_header_posts'] ) ? $options['results_header_posts'] : 'محصولات و مطالب' ); ?>" class="admin-input" />
							</div>
							<div>
								<label class="block font-bold text-sm text-slate-800 mb-1.5">عنوان نتایج دسته‌بندی‌ها</label>
								<input type="text" name="alireza_search_settings[results_header_taxonomies]" value="<?php echo esc_attr( isset( $options['results_header_taxonomies'] ) && ! empty( $options['results_header_taxonomies'] ) ? $options['results_header_taxonomies'] : 'دسته‌بندی‌های مرتبط' ); ?>" class="admin-input" />
							</div>
							<div>
								<label class="block font-bold text-sm text-slate-800 mb-1.5">متن راهنما (Placeholder) فیلد جستجو</label>
								<input type="text" name="alireza_search_settings[search_placeholder]" value="<?php echo esc_attr( isset( $options['search_placeholder'] ) && ! empty( $options['search_placeholder'] ) ? $options['search_placeholder'] : 'جستجوی هوشمند محصول، مقاله...' ); ?>" class="admin-input" />
							</div>
						</div>
					</div>
				</div>

				<!-- Save Action Button -->
				<div class="mt-8 flex items-center justify-between">
					<button type="submit" class="admin-btn-save">
						<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
						</svg>
						<span>ذخیره تمام تنظیمات و پالت رنگ</span>
					</button>
				</div>
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
