<?php

require_once  __DIR__.'/ItemsDetectorTrait.php';

class WabootBreadcrumbTrail extends WBF\components\breadcrumb\Breadcrumb {
	use ItemsDetectorTrait;

	private $args;

	public function __construct($args) {
		$defaults = array(
			'container' => 'div',
			'separator' => '&#47;',
			'before' => '',
			'after' => '',
			'show_on_front' => true,
			'network' => false,
			//'show_edit_link'  => false,
			'show_title' => true,
			'show_browse' => true,
			'echo' => true,

			/* Post taxonomy (examples follow). */
			'post_taxonomy' => array(
				// 'post'  => 'post_tag',
				// 'book'  => 'genre',
			),

			/* Labels for text used (see Breadcrumb_Trail::default_labels). */
			'labels' => array(
				'browse'              => __( 'Browse:',                             'breadcrumb-trail' ),
				'home'                => __( 'Home',                                'breadcrumb-trail' ),
				'error_404'           => __( '404 Not Found',                       'breadcrumb-trail' ),
				'archives'            => __( 'Archives',                            'breadcrumb-trail' ),
				/* Translators: %s is the search query. The HTML entities are opening and closing curly quotes. */
				'search'              => __( 'Search results for &#8220;%s&#8221;', 'breadcrumb-trail' ),
				/* Translators: %s is the page number. */
				'paged'               => __( 'Page %s',                             'breadcrumb-trail' ),
				/* Translators: Minute archive title. %s is the minute time format. */
				'archive_minute'      => __( 'Minute %s',                           'breadcrumb-trail' ),
				/* Translators: Weekly archive title. %s is the week date format. */
				'archive_week'        => __( 'Week %s',                             'breadcrumb-trail' ),

				/* "%s" is replaced with the translated date/time format. */
				'archive_minute_hour' => '%s',
				'archive_hour'        => '%s',
				'archive_day'         => '%s',
				'archive_month'       => '%s',
				'archive_year'        => '%s',
			)
		);

		$args['labels'] = apply_filters('waboot/component/breadcrumb/breadcrumb_args/labels', wp_parse_args($args['labels'], $defaults['labels']));

		$this->args = apply_filters('waboot/component/breadcrumb/breadcrumb_args', wp_parse_args($args, $defaults));
	}

	public function populateItems(){
		if(is_front_page()){
			//Only show front items if the 'show_on_front' argument is set to 'true'.
			if($this->args['show_on_front'] || (is_singular() && 1 < get_query_var('page'))){
				if(is_paged()){
					//If on a paged view, add the home link items
					$this->addNetworkHomeLink();
					$this->addSiteHomeLink();
				}else{
					//If on the main front page, add the network home link item and the home item.
					$this->addNetworkHomeLink();
					$this->addFrontPageItems();
				}
			}
		}else{
			$this->addNetworkHomeLink();
			$this->addSiteHomeLink();

			if(is_home()){
				$this->addPostsAndPagesItems();
			}elseif(is_singular()){

			}elseif(is_post_type_archive()){

			}elseif(is_category() || is_tag() || is_tax()){

			}elseif(is_author()){

			}elseif(get_query_var( 'minute' ) && get_query_var( 'hour' )){

			}elseif(get_query_var( 'minute' )){

			}elseif(get_query_var( 'hour' )){

			}elseif(is_day()){

			}elseif(get_query_var( 'w' )){

			}elseif(is_month()){

			}elseif(is_year()){

			}elseif(is_archive()){

			}elseif(is_search()){

			}elseif(is_404()){

			}
		}

		$this->addPagedItems();

		$this->items = apply_filters( 'waboot/component/breadcrumb/items', $this->items, $this->args );
	}

	/**
     * Formats and outputs the breadcrumb trail.
     *
     * @since  1.0
     * @access public
     * @return string
     */
    public function trail() {

        $breadcrumb = '';

	    /* Allow developers to edit BC items. */
	    $this->items = apply_filters("wbf/breadcrumb_trail/items",$this->items);

        /* Connect the breadcrumb trail if there are items in the trail. */
        if ( !empty( $this->items ) && is_array( $this->items ) ) {

            /* Make sure we have a unique array of items. */
            $this->items = array_unique($this->items);

            /* Open the breadcrumb trail containers. */
            $breadcrumb = "\n\t\t" . '<' . tag_escape($this->args['container']) . ' class="breadcrumb-trail breadcrumbs ' . $this->args['additional_classes'] . '" itemprop="breadcrumb">';

            /* Crea Wrapper */
            $breadcrumb .= !empty( $this->args['wrapper_start'] )? $this->args['wrapper_start'] : "";

            /* If $before was set, wrap it in a container. */
            $breadcrumb .= ( !empty( $this->args['before'] ) ? "\n\t\t\t" . '<span class="trail-before">' . $this->args['before'] . '</span> ' . "\n\t\t\t" : '' );

            /* Add 'browse' label if it should be shown. */
            if ( true === $this->args['show_browse'] )
                $breadcrumb .= "\n\t\t\t" . '<span class="trail-browse">' . $this->args['labels']['browse'] . '</span> ';

            /* Adds the 'trail-begin' class around first item if there's more than one item. */
            if ( 1 < count( $this->items ) )
                array_unshift( $this->items, '<span class="trail-begin">' . array_shift( $this->items ) . '</span>' );

            /* Adds the 'trail-end' class around last item. */
            array_push( $this->items, '<span class="trail-end">' . array_pop( $this->items ) . '</span>' );

            /* Format the separator. */
            $separator = ( !empty( $this->args['separator'] ) ? '<span class="sep">' . $this->args['separator'] . '</span>' : '<span class="sep">/</span>' );

            /* Join the individual trail items into a single string. */
            $breadcrumb .= join( "\n\t\t\t {$separator} ", $this->items );

            /* If $after was set, wrap it in a container. */
            $breadcrumb .= ( !empty( $this->args['after'] ) ? "\n\t\t\t" . ' <span class="trail-after">' . $this->args['after'] . '</span>' : '' );

            /* Chiude Wrapper */
            $breadcrumb .= !empty( $this->args['wrapper_end'] )? $this->args['wrapper_end'] : "";

            /* Close the breadcrumb trail containers. */
            $breadcrumb .= "\n\t\t" . '</' . tag_escape( $this->args['container'] ) . '>';
        }

        /* Allow developers to filter the breadcrumb trail HTML. */
        $breadcrumb = apply_filters( 'breadcrumb_trail', $breadcrumb, $this->args );

        if ( true === $this->args['echo'] )
            echo $breadcrumb;
        else
            return $breadcrumb;
    }
}