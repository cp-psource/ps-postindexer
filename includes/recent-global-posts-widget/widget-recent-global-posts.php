<?php

// define default constan value
if ( !defined( 'RECENT_GLOBAL_POSTS_WIDGET_MAIN_BLOG_ONLY' ) ) {
	define( 'RECENT_GLOBAL_POSTS_WIDGET_MAIN_BLOG_ONLY', true );
}


// Integration als Erweiterung für den Beitragsindexer
add_action('plugins_loaded', function() {
	if ( !class_exists('Postindexer_Extensions_Admin') ) return;
	global $postindexer_extensions_admin;
	if ( !isset($postindexer_extensions_admin) ) {
		if ( isset($GLOBALS['postindexeradmin']) && isset($GLOBALS['postindexeradmin']->extensions_admin) ) {
			$postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
		}
	}
	if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('recent_global_posts_widget') ) {
		add_action( 'widgets_init', 'rgpwidget_register_widget', 11 );
	}
});

if ( !function_exists( 'rgpwidget_register_widget' ) ) :
	/**
	 * Registers widget in the system.
	 *
	 * @global wpdb $wpdb Current database connection instance.
	 */
	function rgpwidget_register_widget() {
		global $wpdb;

		// don't register the widget if Network_Query class is not loaded
		if ( !class_exists( 'Network_Query', false ) ) {
			return;
		}
		register_widget( Recent_Global_Posts_Widget::NAME );
	}
endif;

add_filter( 'network_posts_where', 'rgpwidget_exclude_blogs', 10, 2 );
if ( !function_exists( 'rgpwidget_exclude_blogs' ) ) :
	/**
	 * Excludes blogs from network query.
	 *
	 * @since 3.0.4
	 *
	 * @param string $where Initial WHERE clause of the network query.
	 * @param Network_Query $query The network query object.
	 * @return string Updated WHERE clause.
	 */
	function rgpwidget_exclude_blogs( $where, Network_Query $query ) {
		return !empty( $query->query_vars['blogs_not_in'] )
			? $where . sprintf( ' AND %s.BLOG_ID NOT IN (%s) ', $query->network_posts, implode( ', ', (array)$query->query_vars['blogs_not_in'] ) )
			: $where;
	}
endif;

/**
 * Neueste Netzwerk Beiträge class.
 */
class Recent_Global_Posts_Widget extends WP_Widget {

	const NAME = __CLASS__;

	const DISPLAY_TITLE_CONTENT      = 'title_content';
	const DISPLAY_TITLE_BLOG_CONTENT = 'title_blog_content';
	const DISPLAY_TITLE              = 'title';
	const DISPLAY_TITLE_BLOG         = 'title_blog';
	const DISPLAY_CONTENT            = 'content';
	const DISPLAY_BLOG_CONTENT       = 'blog_content';

	/**
	 * Constructor.
	 *
	 * @since 3.0.2
	 *
	 * @access public
	 * @param array $widget_options Array of widget options.
	 * @param array $control_options Array of control options.
	 */
	public function __construct( $widget_options = array(), $control_options = array() ) {
		$widget_options = array_merge( array(
			'classname'   => 'rgpwidget',
			'description' => __( 'Recent Global Posts', 'rgpwidget' ),
			), $widget_options );

		$control_options = array_merge( array(
			'id_base' => 'rgpwidget',
			), $control_options );

		parent::__construct( 'rgpwidget', __( 'Recent Global Posts', 'rgpwidget' ), $widget_options, $control_options );
	}

	/**
	 * Renders widget content.
	 *
	 * @access public
	 * @global Network_Query $network_query
	 * @param array $args The array of widget arguments.
	 * @param array $instance The array of widget instance settings.
	 */
	public function widget( $args, $instance ) {
		global $network_query;

		$substr = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		extract( array_merge( array(
			'recentglobalpostsdisplay'           => '',
			'recentglobalpostsnumber'            => '',
			'recentglobalpoststitlecharacters'   => '',
			'recentglobalpostscontentcharacters' => '',
			'recentglobalpostsavatars'           => '',
			'recentglobalpostsavatarsize'        => '',
			'exclude_blogs'                      => '',
		), $instance ) );

		$title = !empty( $instance['recentglobalpoststitle'] ) ? $instance['recentglobalpoststitle'] : __( 'Recent Global Posts' );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$recentglobalpoststype = !empty( $instance['recentglobalpoststype'] ) ? $instance['recentglobalpoststype'] : 'post';
		$recentglobalpostsnumber = !empty( $instance['recentglobalpostsnumber'] ) ? absint( $instance['recentglobalpostsnumber'] ) : 10;
		if ( !$recentglobalpostsnumber ) {
 			$recentglobalpostsnumber = 10;
		}

		$exclude_blogs = array_filter( array_map( 'intval', explode( ',', $exclude_blogs ) ) );

		$network_query = network_query_posts( array(
			'post_type'      => $recentglobalpoststype,
			'posts_per_page' => $recentglobalpostsnumber,
			'blogs_not_in'   => $exclude_blogs,
		) );

		echo $args['before_widget'];
			echo $args['before_title'], $title, $args['after_title'];
			if ( network_have_posts() ) :
				echo '<ul>';
				while ( network_have_posts() ) :
					network_the_post();
					echo '<li>';
						$post = network_get_post();
						$the_permalink = network_get_permalink();
						$the_title = network_get_the_title();
						$the_content = network_get_the_content();

						if ( $recentglobalpostsavatars == 'show' ) :
							echo '<a href="', $the_permalink, '">', get_avatar( network_get_the_author_id(), $recentglobalpostsavatarsize, '' ), '</a> ';
						endif;

						$blog = get_blog_details( $post->BLOG_ID );
						$blog_title = $blog ? $blog->blogname : '';
						$title = $substr( $the_title, 0, $recentglobalpoststitlecharacters );
						$content = $substr( strip_tags( $the_content ), 0, $recentglobalpostscontentcharacters );
						switch ( $recentglobalpostsdisplay ) {
							case self::DISPLAY_BLOG_CONTENT:
								echo '<a href="', $the_permalink, '">', '[', $blog_title, ']</a>';
								echo '<br>';
								echo $content, $recentglobalpostscontentcharacters < strlen( $the_content ) ? '&hellip;' : '';
								echo '<br><a href="', $the_permalink, '">', __( 'Read More', 'rgpwidget' ), ' &raquo;</a>';
								break;
							case self::DISPLAY_CONTENT:
								echo $content, $recentglobalpostscontentcharacters < strlen( $the_content ) ? '&hellip;' : '';
								echo '<br><a href="', $the_permalink, '">', __( 'Read More', 'rgpwidget' ), ' &raquo;</a>';
								break;
							case self::DISPLAY_TITLE:
								echo '<a href="', $the_permalink, '">', $title, '</a>';
								break;
							case self::DISPLAY_TITLE_BLOG:
								echo '<a href="', $the_permalink, '">', $title, ' [', $blog_title, ']</a>';
								break;
							case self::DISPLAY_TITLE_BLOG_CONTENT:
								echo '<a href="', $the_permalink, '">', $title, ' [', $blog_title, ']</a>';
								echo '<br>';
								echo $content;
								break;
							case self::DISPLAY_TITLE_CONTENT:
							default:
								echo '<a href="', $the_permalink, '">', $title, '</a>';
								echo '<br>';
								echo $content;
								break;
						}
					echo '</li>';
				endwhile;
				echo '</ul>';
			endif;
		echo $args['after_widget'];
	}

	/**
	 * Renders widget settings form.
	 *
	 * @access public
	 * @param array $instance The array of current widget instance settings.
	 */
	public function form( $instance ) {
		$post_types = $this->get_post_types();
		$instance = wp_parse_args( $instance, array(
			'recentglobalpoststitle'             => '',
			'recentglobalpostsdisplay'           => '',
			'recentglobalpostsnumber'            => '',
			'recentglobalpoststitlecharacters'   => '',
			'recentglobalpostscontentcharacters' => '',
			'recentglobalpostsavatars'           => '',
			'recentglobalpostsavatarsize'        => '',
			'recentglobalpoststype'              => 'post',
			'post_type'                          => 'post',
			'exclude_blogs'                      => '',
		) );

		$displays = array(
			self::DISPLAY_TITLE_CONTENT      => __( 'Title and content', 'rgpwidget' ),
			self::DISPLAY_TITLE_BLOG_CONTENT => __( 'Title, blog name and content', 'rgpwidget' ),
			self::DISPLAY_TITLE              => __( 'Title only', 'rgpwidget' ),
			self::DISPLAY_TITLE_BLOG         => __( 'Title and blog name', 'rgpwidget' ),
			self::DISPLAY_CONTENT            => __( 'Content only', 'rgpwidget' ),
			self::DISPLAY_BLOG_CONTENT       => __( 'Blog name and content', 'rgpwidget' ),
		);

		if ( !absint( $instance['recentglobalpostsnumber'] ) ) {
			$instance['recentglobalpostsnumber'] = 5;
		}

		if ( !absint( $instance['recentglobalpoststitlecharacters'] ) ) {
			$instance['recentglobalpoststitlecharacters'] = 30;
		}

		if ( !absint( $instance['recentglobalpostscontentcharacters'] ) ) {
			$instance['recentglobalpostscontentcharacters'] = 100;
		}

		?><p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpoststitle' ) ?>"><?php _e( 'Title', 'rgpwidget' ) ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'recentglobalpoststitle' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpoststitle' ); ?>" value="<?php echo esc_attr( stripslashes( $instance['recentglobalpoststitle'] ) ) ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpostsdisplay' ) ?>"><?php _e( 'Display', 'rgpwidget' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpostsdisplay' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpostsdisplay' ) ?>">
				<?php foreach ( $displays as $key => $label ) : ?>
				<option value="<?php echo $key ?>"<?php selected( $key, $instance['recentglobalpostsdisplay'] ) ?>><?php echo $label ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpostsnumber' ) ?>"><?php _e( 'Number', 'rgpwidget' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpostsnumber' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpostsnumber' ) ?>">
				<?php for ( $counter = 1; $counter <= 25; $counter++ ) : ?>
					<option value="<?php echo $counter ?>"<?php selected( $counter, $instance['recentglobalpostsnumber'] ) ?>><?php echo $counter ?></option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpoststitlecharacters' ) ?>"><?php _e( 'Title Characters', 'rgpwidget' ); ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpoststitlecharacters' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpoststitlecharacters' ) ?>">
				<?php for ( $counter = 1; $counter <= 200; $counter++ ) : ?>
					<option value="<?php echo $counter ?>"<?php selected( $counter, $instance['recentglobalpoststitlecharacters'] ) ?>><?php echo $counter ?></option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpostscontentcharacters' ) ?>"><?php _e( 'Content Characters', 'rgpwidget' ); ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpostscontentcharacters' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpostscontentcharacters' ) ?>">
				<?php for ( $counter = 1; $counter <= 500; $counter++ ) : ?>
					<option value="<?php echo $counter ?>"<?php selected( $counter, $instance['recentglobalpostscontentcharacters'] ) ?>><?php echo $counter ?></option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpostsavatars' ) ?>"><?php _e( 'Avatars', 'rgpwidget' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpostsavatars' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpostsavatars' ) ?>">
				<option value="show"<?php selected( $instance['recentglobalpostsavatars'], 'show' ) ?> ><?php _e( 'Show', 'rgpwidget' ) ?></option>
				<option value="hide"<?php selected( $instance['recentglobalpostsavatars'], 'hide' ) ?> ><?php _e( 'Hide', 'rgpwidget' ) ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpostsavatarsize' ) ?>"><?php _e( 'Avatar Size', 'rgpwidget' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpostsavatarsize' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpostsavatarsize' ) ?>">
				<option value="16"<?php selected( $instance['recentglobalpostsavatarsize'], '16' ) ?>>16px</option>
				<option value="32"<?php selected( $instance['recentglobalpostsavatarsize'], '32' ) ?>>32px</option>
				<option value="48"<?php selected( $instance['recentglobalpostsavatarsize'], '48' ) ?>>48px</option>
				<option value="96"<?php selected( $instance['recentglobalpostsavatarsize'], '96' ) ?>>96px</option>
				<option value="128"<?php selected( $instance['recentglobalpostsavatarsize'], '128' ) ?>>128px</option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'recentglobalpoststype' ) ?>"><?php _e( 'Post Type', 'rgpwidget' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'recentglobalpoststype' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'recentglobalpoststype' ) ?>">
				<?php if ( !empty( $post_types ) ) : ?>
					<?php foreach ( $post_types as $r ) : ?>
						<option value="<?php echo $r ?>"<?php selected( $instance['recentglobalpoststype'], $r ) ?>><?php echo esc_html( $r ) ?></option>
					<?php endforeach; ?>
				<?php else : ?>
					<option value="post"<?php selected( $instance['recentglobalpoststype'], 'post' ) ?>><?php _e( 'post' ) ?></option>
				<?php endif; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'exclude_blogs' ) ?>"><?php _e( 'Exclude Blogs', 'rgpwidget' ) ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'exclude_blogs' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'exclude_blogs' ); ?>" value="<?php echo esc_attr( $instance['exclude_blogs'] ) ?>"><br>
			<small><?php esc_html_e( 'Blog IDs, separated by commas.', 'rgpwidget' ) ?></small>
		</p>

		<input type="hidden" name="<?php echo $this->get_field_name( 'recentglobalpostssubmit' ) ?>" value="1"><?php
	}

	/**
	 * Returns array of available post types.
	 *
	 * @access public
	 * @global wpdb $wpdb The current database connection instance.
	 * @return array The array of available post types.
	 */
	public function get_post_types() {
		global $wpdb;

		$prefix = isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix;

		return $wpdb->get_col( "SELECT DISTINCT post_type FROM {$prefix}network_posts" );
	}

}