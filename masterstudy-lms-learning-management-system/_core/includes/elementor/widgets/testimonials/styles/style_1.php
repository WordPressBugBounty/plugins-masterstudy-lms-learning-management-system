<div class="stm-testimonials-carousel-wrapper swiper-container stm-testimonials-carousel-wrapper-style_1
	<?php
		$classes = array_filter(
			array(
				empty( $arrows ) ? 'hide-carousel-arrows' : null,
				empty( $arrows_tablet ) ? 'hide-carousel-arrows-tablet' : null,
				empty( $arrows_mobile ) ? 'hide-carousel-arrows-mobile' : null,
			)
		);

		echo esc_attr( implode( ' ', $classes ) );
		?>
	" id="<?php echo esc_attr( $unique_id ); ?>">
	<div class="ms-lms-testimonials-header">
		<i class="ms-lms-testimonials-icon"></i>
		<p><?php echo esc_html( $testimonials_title ); ?></p>
	</div>
	<div class="elementor-testimonials-carousel swiper-wrapper">
		<?php
		foreach ( $testimonials as $testimonial ) {
			$thumbnail_img = '';
			if ( ! empty( $testimonial['image'] ) && ! empty( $testimonial['image']['id'] ) ) {
				$thumbnail_img = wp_get_attachment_image_src( $testimonial['image']['id'], 'thumbnail' );
			}
			?>
			<div class="ms-lms-testimonial-data swiper-slide"
				data-thumbnail="<?php echo isset( $thumbnail_img[0] ) ? esc_attr( $thumbnail_img[0] ) : ''; ?>">
				<?php if ( $testimonial['review_rating'] > 0 ) : ?>
				<div class="ms-lms-testimonial-review-rating">
					<?php echo wp_kses_post( str_repeat( '<i class="fa fa-star"></i>', intval( $testimonial['review_rating'] ) ) ); ?>
				</div>
				<?php endif; ?>
				<div class="author-name"><?php echo esc_html( $testimonial['author_name'] ); ?></div>
				<div class="content">
					<?php echo wp_kses_post( $testimonial['content'] ); ?>
				</div>
			</div>
		<?php } ?>
	</div>
	<div class="ms-lms-elementor-testimonials-swiper-pagination"></div>
	<div class="swiper-button-prev"></div>
	<div class="swiper-button-next"></div>
</div>
