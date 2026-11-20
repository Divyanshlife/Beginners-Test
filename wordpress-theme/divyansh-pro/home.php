<?php
/**
 * Blog page (the page chosen in Settings > Reading > Posts page).
 *
 * @package divyansh-pro
 */

get_header();
$dp_paged = max( 1, get_query_var( 'paged' ) );
?>
<section class="dp-blog-hero">
	<div class="dp-container">
		<p class="dp-kicker"><?php esc_html_e( 'Kadvia Blog', 'divyansh-pro' ); ?></p>
		<h1 class="dp-blog-hero__title"><?php echo wp_kses( dp_opt( 'dp_blog_title' ), array( 'em' => array() ) ); ?></h1>
		<p class="dp-blog-hero__lead"><?php echo esc_html( dp_opt( 'dp_blog_intro' ) ); ?></p>
		<?php
		$dp_terms = get_categories( array( 'hide_empty' => true, 'exclude' => array( (int) get_option( 'default_category' ) ) ) );
		if ( $dp_terms ) :
			?>
			<nav class="dp-cat-pills" aria-label="<?php esc_attr_e( 'Blog topics', 'divyansh-pro' ); ?>">
				<a class="is-active" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'All', 'divyansh-pro' ); ?></a>
				<?php foreach ( $dp_terms as $dp_term ) : ?>
					<a href="<?php echo esc_url( get_category_link( $dp_term ) ); ?>"><?php echo esc_html( $dp_term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</section>

<div class="dp-container dp-blog-wrap">
	<?php if ( have_posts() ) : ?>
		<?php
		// First post on page 1 is shown large.
		if ( 1 === $dp_paged ) :
			the_post();
			$dp_cats = get_the_category();
			?>
			<article <?php post_class( 'dp-post-feature' ); ?>>
				<a class="dp-post-feature__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
					<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'alt' => '' ) ); } ?>
				</a>
				<div class="dp-post-feature__body">
					<div class="dp-post-meta">
						<span class="dp-chip dp-chip--pink"><?php esc_html_e( 'Latest', 'divyansh-pro' ); ?></span>
						<?php if ( $dp_cats ) : ?>
							<a class="dp-chip" href="<?php echo esc_url( get_category_link( $dp_cats[0] ) ); ?>"><?php echo esc_html( $dp_cats[0]->name ); ?></a>
						<?php endif; ?>
						<span><?php echo esc_html( dp_reading_time() ); ?></span>
					</div>
					<h2 class="dp-post-feature__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="dp-post-feature__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<a class="dp-pill" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read article', 'divyansh-pro' ); ?></a>
				</div>
			</article>
		<?php endif; ?>

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
		<p class="dp-empty"><?php esc_html_e( 'No articles yet. Add one from Posts > Add New in wp-admin.', 'divyansh-pro' ); ?></p>
	<?php endif; ?>

	<?php dp_ad_slot( 'list' ); ?>
	<?php dp_blog_cta(); ?>
</div>
<?php
get_footer();
