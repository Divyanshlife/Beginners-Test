<?php
/**
 * Fallback template: category and tag archives, search results and the
 * blog list when no Posts page is set.
 *
 * @package divyansh-pro
 */

get_header();

if ( is_search() ) {
	/* translators: %s: search term */
	$dp_title = sprintf( __( 'Search: %s', 'divyansh-pro' ), get_search_query() );
} elseif ( is_home() ) {
	$dp_title = wp_strip_all_tags( dp_opt( 'dp_blog_title' ) );
} else {
	$dp_title = wp_strip_all_tags( get_the_archive_title() );
}
?>
<section class="dp-blog-hero dp-blog-hero--sm">
	<div class="dp-container">
		<p class="dp-kicker"><a href="<?php echo esc_url( get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Kadvia Blog', 'divyansh-pro' ); ?></a></p>
		<h1 class="dp-blog-hero__title"><?php echo esc_html( $dp_title ); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="dp-blog-hero__lead"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
		<?php endif; ?>
	</div>
</section>

<div class="dp-container dp-blog-wrap">
	<?php if ( have_posts() ) : ?>
		<div class="dp-post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/post-card' );
			endwhile;
			?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '&larr;', 'next_text' => '&rarr;' ) ); ?>
	<?php else : ?>
		<p class="dp-empty"><?php esc_html_e( 'Nothing found. Try another topic from the blog.', 'divyansh-pro' ); ?></p>
	<?php endif; ?>
	<?php dp_blog_cta(); ?>
</div>
<?php
get_footer();
