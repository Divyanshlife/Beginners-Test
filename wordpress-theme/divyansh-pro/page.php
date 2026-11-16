<?php
/**
 * Pages. Elementor pages get the full width; normal pages get a neat text column.
 *
 * @package divyansh-pro
 */

get_header();

while ( have_posts() ) :
	the_post();
	$dp_is_elementor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->documents->get( get_the_ID() ) && \Elementor\Plugin::$instance->documents->get( get_the_ID() )->is_built_with_elementor();

	if ( $dp_is_elementor ) :
		the_content();
	else :
		?>
		<div class="dp-container dp-plain">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="entry-content"><?php the_content(); ?></div>
		</div>
		<?php
	endif;
endwhile;

get_footer();
