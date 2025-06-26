function globalsitesearch_render_block($attributes) {
    ob_start();
    ?>

    <div class="global-site-search-widget">
        <h2><?php echo esc_html($attributes['title']); ?></h2>
        <?php global_site_search_form(); ?>
    </div>

    <?php
    return ob_get_clean();
}

register_block_type('global-site-search/search', array(
    'render_callback' => 'globalsitesearch_render_block',
    'attributes'      => array(
        'title' => array(
            'type'    => 'string',
            'default' => __('Netzwerksuche', 'globalsitesearch'),
        ),
    ),
));