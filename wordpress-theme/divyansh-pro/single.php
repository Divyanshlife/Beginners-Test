<?php
/**
 * Single blog post.
 *
 * @package divyansh-pro
 */

get_header();

while ( have_posts() ) :
	the_post();
	$dp_cats  = get_the_category();
	$dp_url   = rawurlencode( get_permalink() );
	$dp_title = rawurlencode( get_the_title() );
	?>
	<article <?php post_class( 'dp-single' ); ?>>
		<header class="dp-single__head">
			<div class="dp-container dp-container--narrow">
				<p class="dp-kicker"><a href="<?php echo esc_url( get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Kadvia Blog', 'divyansh-pro' ); ?></a></p>
				<div class="dp-post-meta">
					<?php if ( $dp_cats ) : ?>
						<a class="dp-chip" href="<?php echo esc_url( get_category_link( $dp_cats[0] ) ); ?>"><?php echo esc_html( $dp_cats[0]->name ); ?></a>
					<?php endif; ?>
					<span><?php echo esc_html( get_the_date() ); ?></span>
					<span aria-hidden="true">&middot;</span>
					<span><?php echo esc_html( dp_reading_time() ); ?></span>
				</div>
				<h1 class="dp-single__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="dp-single__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="dp-container dp-single__img"><?php the_post_thumbnail( 'large', array( 'alt' => '' ) ); ?></div>
		<?php endif; ?>

		<div class="dp-container dp-container--narrow">
			<div class="dp-prose entry-content">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>

			<?php dp_ad_slot( 'post' ); ?>

			<div class="dp-share">
				<span><?php esc_html_e( 'Share this article', 'divyansh-pro' ); ?></span>
				<a href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . $dp_url ); ?>" target="_blank" rel="noopener">LinkedIn</a>
				<a href="<?php echo esc_url( 'https://wa.me/?text=' . $dp_title . '%20' . $dp_url ); ?>" target="_blank" rel="noopener">WhatsApp</a>
				<a href="<?php echo esc_url( 'https://twitter.com/intent/tweet?url=' . $dp_url . '&text=' . $dp_title ); ?>" target="_blank" rel="noopener">X</a>
				<a href="<?php echo esc_url( 'mailto:?subject=' . $dp_title . '&body=' . $dp_url ); ?>">Email</a>
			</div>

			<div class="dp-author">
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/kadvia-icon-180.png' ); ?>" width="56" height="56" alt="">
				<div>
					<p class="dp-author__name"><?php echo esc_html( get_the_author() ); ?> <span>&middot; <?php bloginfo( 'name' ); ?></span></p>
					<p><?php esc_html_e( 'Kadvia books qualified sales meetings for B2B teams. We write about outbound, LinkedIn, cold email and sales strategy.', 'divyansh-pro' ); ?></p>
				</div>
			</div>
		</div>
	</article>

	<?php
	// Related posts from the same category.
	$dp_related = new WP_Query(
		array(
			'posts_per_page'      => 3,
			'post__not_in'        => array( get_the_ID() ),
			'category__in'        => $dp_cats ? wp_list_pluck( $dp_cats, 'term_id' ) : array(),
			'ignore_sticky_posts' => true,
		)
	);
	if ( $dp_related->post_count < 3 ) {
		$dp_related = new WP_Query( array( 'posts_per_page' => 3, 'post__not_in' => array( get_the_ID() ), 'ignore_sticky_posts' => true ) );
	}
	if ( $dp_related->have_posts() ) :
		?>
		<section class="dp-related">
			<div class="dp-container">
				<h2 class="dp-related__title"><?php esc_html_e( 'Keep reading', 'divyansh-pro' ); ?></h2>
				<div class="dp-post-grid">
					<?php
					while ( $dp_related->have_posts() ) :
						$dp_related->the_post();
						get_template_part( 'template-parts/post-card' );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<div class="dp-container"><?php dp_blog_cta(); ?></div>
	<?php
endwhile;

get_footer();
