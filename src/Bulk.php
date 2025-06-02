<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

class Bulk {

	public function __construct() {

		add_action( 'pwoosms_settings_form_bottom_sms_send', [ $this, 'bulk_form' ] );
		add_action( 'pwoosms_settings_form_admin_notices', [ $this, 'bulk_notice' ], 10 );

		if ( PWSMS()->get_option( 'enable_buyer' ) ) {
			add_action( 'admin_footer', [ $this, 'bulk_script' ], 10 );
			add_action( 'load-edit.php', [ $this, 'bulk_action' ] );
		}
	}

	public function bulk_form() { ?>
		<div class="notice notice-info below-h2">
			<p>با استفاده از قسمت ارسال پیامک ، میتوانید آزمایش کنید که آیا پنل پیامک شما به خوبی به افزونه متصل شده است
				یا خیر.
			</p>
		</div>
		<form class="initial-form" id="pwoosms-send-sms-bulk-form" method="post"
		      action="<?php echo admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=send' ) ?>">

			<?php wp_nonce_field( 'pwoosms_send_sms_nonce', '_wpnonce' ); ?>

			<p>
				<label for="pwoosms_mobile">شماره دریافت کننده</label><br>
				<input type="text" name="pwoosms_mobile" id="pwoosms_mobile"
				       value="<?php echo esc_attr( $_POST['pwoosms_mobile'] ?? null ); ?>"
				       style="direction:ltr; text-align:left; width:100%; !important"/><br>
				<span>شماره موبایل دریافت کننده پیامک را وارد کنید. شماره ها را با کاما (,) جدا نمایید.</span>
			</p>

			<p>
				<label for="pwoosms_message">متن پیامک</label><br>
				<textarea name="pwoosms_message" id="pwoosms_message" rows="10"
				          style="width:100% !important"><?php echo ! empty( $_POST['pwoosms_message'] ) ? esc_attr( $_POST['pwoosms_message'] ) : ''; ?></textarea><br>
				<span>متن دلخواهی که میخواهید به دریافت کننده ارسال کنید را وارد کنید.</span>
			</p>

			<p>
				<input type="submit" class="button button-primary" name="pwoosms_send_sms"
				       value="ارسال پیامک">
			</p>
		</form>
		<?php
	}

	public function bulk_notice() {

		if ( isset( $_POST['pwoosms_send_sms'] ) ) {

			if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? null, 'pwoosms_send_sms_nonce' ) ) {
				wp_die( 'خطایی رخ داده است.' );
			}

			$data            = [];
			$data['type']    = 1;
			$data['mobile']  = $mobiles = ! empty( $_POST['pwoosms_mobile'] ) ? explode( ',',
				sanitize_text_field( $_POST['pwoosms_mobile'] ) ) : [];
			$data['message'] = ! empty( $_POST['pwoosms_message'] ) ? sanitize_textarea_field( $_POST['pwoosms_message'] ) : '';

			// Get gateway information for debugging
			$gateway_obj = PWSMS()->get_sms_gateway();
			$gateway_class = get_class( $gateway_obj );
			$gateway_name = method_exists( $gateway_obj, 'name' ) ? $gateway_obj->name() : 'نامشخص';
			
			// Clean and prepare mobile numbers
			$cleaned_mobiles = PWSMS()->modify_mobile( $mobiles );
			$cleaned_mobiles = explode( ',', implode( ',', (array) $cleaned_mobiles ) );
			$cleaned_mobiles = array_map( 'trim', $cleaned_mobiles );
			$cleaned_mobiles = array_unique( array_filter( $cleaned_mobiles ) );

			// Get current time for debug info
			$current_time = current_time( 'Y-m-d H:i:s' );

			$response = PWSMS()->send_sms( $data );

			if ( $response === true ) { ?>
				<div class="notice notice-success below-h2">
					<p><strong>✅ پیامک با موفقیت ارسال شد!</strong></p>
					<div style="background: #f0f8ff; padding: 15px; border: 1px solid #0073aa; border-radius: 5px; margin-top: 10px;">
						<h4 style="margin-top: 0; color: #0073aa;">🔍 جزئیات ارسال:</h4>
						<ul style="margin: 5px 0;">
							<li><strong>زمان ارسال:</strong> <?php echo $current_time; ?></li>
							<li><strong>درگاه پیامک:</strong> <?php echo esc_html( $gateway_name ); ?> (<?php echo esc_html( $gateway_class ); ?>)</li>
							<li><strong>تعداد شماره‌های ورودی:</strong> <?php echo count( $mobiles ); ?></li>
							<li><strong>تعداد شماره‌های معتبر (پس از پاکسازی):</strong> <?php echo count( $cleaned_mobiles ); ?></li>
							<li><strong>شماره‌های دریافت کننده:</strong> 
								<code style="background: #fff; padding: 2px 5px; border: 1px solid #ddd; direction: ltr; display: inline-block;"><?php echo esc_html( implode( ', ', $cleaned_mobiles ) ); ?></code>
							</li>
							<li><strong>طول متن پیامک:</strong> <?php echo mb_strlen( $data['message'] ); ?> کاراکتر</li>
							<li><strong>پاسخ API:</strong> <span style="color: green; font-weight: bold;">SUCCESS (TRUE)</span></li>
						</ul>
					</div>
				</div>
				<?php
				return true;
			} ?>

			<div class="notice notice-error below-h2">
				<p><strong>❌ خطا: پیامک ارسال نشد!</strong></p>
				<div style="background: #fff2f2; padding: 15px; border: 1px solid #dc3232; border-radius: 5px; margin-top: 10px;">
					<h4 style="margin-top: 0; color: #dc3232;">🔍 جزئیات خطا:</h4>
					<ul style="margin: 5px 0;">
						<li><strong>زمان تلاش:</strong> <?php echo $current_time; ?></li>
						<li><strong>درگاه پیامک:</strong> <?php echo esc_html( $gateway_name ); ?> (<?php echo esc_html( $gateway_class ); ?>)</li>
						<li><strong>تعداد شماره‌های ورودی:</strong> <?php echo count( $mobiles ); ?></li>
						<li><strong>تعداد شماره‌های معتبر (پس از پاکسازی):</strong> <?php echo count( $cleaned_mobiles ); ?></li>
						<?php if ( ! empty( $cleaned_mobiles ) ): ?>
						<li><strong>شماره‌های مقصد:</strong> 
							<code style="background: #fff; padding: 2px 5px; border: 1px solid #ddd; direction: ltr; display: inline-block;"><?php echo esc_html( implode( ', ', $cleaned_mobiles ) ); ?></code>
						</li>
						<?php endif; ?>
						<li><strong>طول متن پیامک:</strong> <?php echo mb_strlen( $data['message'] ); ?> کاراکتر</li>
						<li><strong>نوع پاسخ API:</strong> <?php echo gettype( $response ); ?></li>
						<li><strong>پاسخ کامل وبسرویس:</strong><br>
							<div style="background: #f9f9f9; padding: 10px; border: 1px solid #ccc; border-radius: 3px; margin-top: 5px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; direction: ltr;">
								<?php 
								if ( is_array( $response ) || is_object( $response ) ) {
									echo '<pre>' . esc_html( print_r( $response, true ) ) . '</pre>';
								} else {
									echo esc_html( $response );
								}
								?>
							</div>
						</li>
					</ul>
					
					<div style="background: #fff8e1; padding: 10px; border: 1px solid #ffc107; border-radius: 3px; margin-top: 10px;">
						<h5 style="margin: 0 0 5px 0; color: #f57c00;">💡 راهنمای عیب‌یابی:</h5>
						<ul style="margin: 5px 0; font-size: 12px;">
							<li>بررسی کنید که اطلاعات وبسرویس (نام کاربری، رمز عبور، شماره فرستنده) صحیح باشد</li>
							<li>مطمئن شوید که اعتبار پنل پیامک شما کافی است</li>
							<li>شماره فرستنده باید معتبر و فعال باشد</li>
							<li>بعضی وبسرویس‌ها محدودیت زمانی برای ارسال دارند</li>
							<li>متن پیامک نباید حاوی کلمات فیلتر شده باشد</li>
						</ul>
					</div>
				</div>
			</div>
			<?php
		}

		return false;
	}

	public function bulk_script() {

		$screen = get_current_screen();

		if ( $screen->post_type !== 'shop_order' ) {
			return false;
		}

		?>
		<script type="text/javascript">
            jQuery(function () {
                jQuery('<option>').val('send_sms').text('ارسال پیامک دسته جمعی').appendTo("select[name='action']");
                jQuery('<option>').val('send_sms').text('ارسال پیامک دسته جمعی').appendTo("select[name='action2']");
            });
		</script>

		<?php

	}

	public function bulk_action() {

		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();
		if ( $action != 'send_sms' ) {
			return;
		}

		$post_ids = array_map( 'absint', (array) $_REQUEST['post'] );
		$mobiles  = [];
		foreach ( $post_ids as $order_id ) {
			$mobiles[] = PWSMS()->buyer_mobile( $order_id );
		}

		$mobiles = implode( ',', array_unique( array_filter( $mobiles ) ) );

		echo '<form method="POST" name="pwoosms_posted_form" action="' . admin_url( 'admin.php?page=persian-woocommerce-sms-pro&tab=send' ) . '">
		<input type="hidden" value="' . esc_attr( $mobiles ) . '" name="pwoosms_mobile" />
		</form>
		<script language="javascript" type="text/javascript">document.pwoosms_posted_form.submit(); </script>';
		exit();
	}
}

