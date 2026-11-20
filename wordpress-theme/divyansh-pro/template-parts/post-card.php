<?php
/**
 * One blog post card (used on the Blog page, archives, search and the
 * [kadvia_latest_posts] shortcode).
 *
 * @package divyansh-pro
 */

$dp_cats = get_the_category();
?>
<article <?php post_class( 'dp-post-card dp-reveal' ); ?>>
	<a class="dp-post-card__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span class="dp-post-card__ph"><?php echo esc_html( mb_substr( get_the_title(), 0, 1 ) ); ?></span>
		<?php endif; ?>
	</a>
	<div class="dp-post-card__body">
		<div class="dp-post-meta">
			<?php if ( $dp_cats ) : ?>
				<a class="dp-chip" href="<?php echo esc_url( get_category_link( $dp_cats[0] ) ); ?>"><?php echo esc_html( $dp_cats[0]->name ); ?></a>
			<?php endif; ?>
			<span><?php echo esc_html( dp_reading_time() ); ?></span>
		</div>
		<h3 class="dp-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="dp-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
		<a class="dp-readmore" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read article', 'divyansh-pro' ); ?> <span aria-hidden="true">&rarr;</span></a>
	</div>
</article>
