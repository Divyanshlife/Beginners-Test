<?php
/**
 * Main template: blog list, archives, search and anything else.
 *
 * @package divyansh-pro
 */

get_header();
?>
<div class="dp-container dp-plain">
	<?php if ( have_posts() ) : ?>
		<?php if ( ! is_singular() ) : ?>
			<h1 class="entry-title"><?php echo is_home() ? esc_html__( 'Notes', 'divyansh-pro' ) : wp_kses_post( get_the_archive_title() ); ?></h1>
		<?php endif; ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<?php if ( is_singular() ) : ?>
					<h1 class="entry-title"><?php the_title(); ?></h1>
					<div class="entry-content"><?php the_content(); ?></div>
				<?php else : ?>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry-content"><?php the_excerpt(); ?></div>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<h1 class="entry-title"><?php esc_html_e( 'Nothing here yet', 'divyansh-pro' ); ?></h1>
	<?php endif; ?>
</div>
<?php
get_footer();
