<?php

require_once  __DIR__.'/WabootBreadcrumbItem.php';
require_once  __DIR__.'/ItemsDetectorTrait.php';

class WabootBreadcrumbTrail extends WBF\components\breadcrumb\Breadcrumb {
    use ItemsDetectorTrait;

    private $args;

    public function __construct($args) {
        $defaults = [
            'container' => 'div',
            'separator' => '&#47;',
            'before' => '',
            'after' => '',
            'show_on_front' => true,
            'network' => false,
            //'show_edit_link'  => false,
            'show_title' => true,
            'show_browse' => true,
            //Parse the path in search of parents
            'parse_path' => true,
            /*
            Post taxonomy (examples follow).
            You can prepend the term name to a specific singular post type
            by specify the taxonomy name here, for example:
            'book' => 'genre' will prepend the term assigned to a book in the
            'genre' taxonomy before the book title
            */
            'post_taxonomy' => [
                // 'post'  => 'post_tag',
                // 'book'  => 'genre',
            ],
            // Labels for text used (see Breadcrumb_Trail::default_labels).
            'labels' => [
                'browse' => __( 'Browse:', 'breadcrumb-trail' ),
                'home' => __( 'Home', 'breadcrumb-trail' ),
                'error_404' => __( '404 Not Found', 'breadcrumb-trail' ),
                'archives' => __( 'Archives', 'breadcrumb-trail' ),
                // Translators: %s is the search query. The HTML entities are opening and closing curly quotes.
                'search' => __( 'Search results for &#8220;%s&#8221;', 'breadcrumb-trail' ),
                // Translators: %s is the page number. */
                'paged' => __( 'Page %s', 'breadcrumb-trail' ),
                // Translators: Minute archive title. %s is the minute time format.
                'archive_minute' => __( 'Minute %s', 'breadcrumb-trail' ),
                // Translators: Weekly archive title. %s is the week date format.
                'archive_week' => __( 'Week %s', 'breadcrumb-trail' ),
                // "%s" is replaced with the translated date/time format.
                'archive_minute_hour' => '%s',
                'archive_hour' => '%s',
                'archive_day' => '%s',
                'archive_month' => '%s',
                'archive_year' => '%s',
            ]
        ];

        if(!isset($args['labels'])){
            $args['labels'] = [];
        }

        $args['labels'] = wp_parse_args($args['labels'], $defaults['labels']);
        $args['labels'] = apply_filters('waboot/component/breadcrumb/breadcrumb_args/labels',$args['labels']);

        $this->args = apply_filters('waboot/component/breadcrumb/breadcrumb_args', wp_parse_args($args, $defaults));

        $this->populateItems();
    }

    /**
     * Populate the items array
     */
    private function populateItems(){
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
                $this->addSingularItems();
            }elseif(is_post_type_archive()){
                $this->addPostTypeArchiveItems();
            }elseif(is_category() || is_tag() || is_tax()){
                $this->addTermArchiveItems();
            }elseif(is_author()){
                $this->addUserArchiveItems();
            }elseif(get_query_var( 'minute' ) && get_query_var( 'hour' )){
                $this->addMinuteHourArchiveItems();
            }elseif(get_query_var( 'minute' )){
                $this->addMinuteArchiveItems();
            }elseif(get_query_var( 'hour' )){
                $this->addHourArchiveItems();
            }elseif(is_day()){
                $this->addDayArchiveItems();
            }elseif(get_query_var( 'w' )){
                $this->addWeekArchiveItems();
            }elseif(is_month()){
                $this->addMonthArchiveItems();
            }elseif(is_year()){
                $this->addYearArchiveItems();
            }elseif(is_archive()){
                $this->addDefaultArchiveItems();
            }elseif(is_search()){
                $this->addSearchItems();
            }elseif(is_404()){
                $this->add404Items();
            }
        }

        $this->addPagedItems();

        //$this->items = apply_filters( 'waboot/component/breadcrumb/items', $this->items, $this->args );
    }

    /**
     * @return bool
     */
    private function canShowTitles(){
        return $this->args['show_title'] === true;
    }

    /**
     * @param $labelIndex
     * @return string
     */
    private function getLabel($labelIndex){
        if(\array_key_exists($labelIndex,$this->args['labels'])){
            return $this->args['labels'][$labelIndex];
        }
        return $labelIndex;
    }

    /**
     * Render the breadcrumb HTML
     */
    public function renderHtml(){
        $bc = $this->getHtml();
        echo $bc;
    }

    /**
     * @return string
     */
    public function getHtml(){
        $bc = $this->trail();
        return $bc;
    }

    /**
     * Formats and outputs the breadcrumb trail.
     *
     * @since  1.0
     * @access public
     * @return string
     */
    private function trail() {
        $breadcrumb = '';

        $items = apply_filters( 'waboot/component/breadcrumb/items', $this->getItems(), $this->args );

        $itemCount = count($items);

        /* Connect the breadcrumb trail if there are items in the trail. */
        if ( !empty( $items ) && is_array( $items ) ) {

            /* Open the breadcrumb trail containers. */
            $breadcrumb = $this->trailStart();

            /* Add 'browse' label if it should be shown. */
            if ( $this->args['show_browse'] === true ){
                $breadcrumb .= "\n\t\t\t" . '<span class="trail-browse">' . $this->args['labels']['browse'] . '</span> ';
            }

            // Adds the 'trail-begin' class around first item if there's more than one item.
            if ( $itemCount > 1 ){
                $mustPrependTrailBegin = true;
            }else{
                $mustPrependTrailBegin = false;
            }

            // Format the separator.
            $separator = ( !empty( $this->args['separator'] ) ? '<span class="sep">' . $this->args['separator'] . '</span>' : '<span class="sep">/</span>' );

            foreach ($items as $k => $item){
                if(!$item instanceof WabootBreadcrumbItem){
                    continue;
                }
                if($k === 0 && $mustPrependTrailBegin){
                    $breadcrumb .= '<span class="trail-begin">';
                }
                if($k === ($itemCount - 1)){
                    $breadcrumb .= '<span class="trail-end">';
                }
                $breadcrumb .= $item->getHtml();
                if( ($k === 0 && $mustPrependTrailBegin) || ($k === ($itemCount - 1)) ){
                    $breadcrumb .= '</span>';
                }
                if($k !== ($itemCount - 1)){
                    $breadcrumb .= $separator;
                }
            }

            /* Close the breadcrumb trail containers. */
            $breadcrumb .= $this->trailEnd();
        }

        /* Allow developers to filter the breadcrumb trail HTML. */
        $breadcrumb = apply_filters( 'breadcrumb_trail', $breadcrumb, $this->args );

        return $breadcrumb;
    }

    /**
     * @return string
     */
    private function trailStart(){
        // Open Wrapper
        $str = !empty( $this->args['wrapper_start'] )? $this->args['wrapper_start'] : "";

        $str .= "\n\t\t" . '<' . tag_escape($this->args['container']) . ' class="breadcrumb-trail breadcrumbs ' . $this->args['additional_classes'] . '" itemprop="breadcrumb">';

        // If $before was set, wrap it in a container.
        $str .= ( !empty( $this->args['before'] ) ? "\n\t\t\t" . '<span class="trail-before">' . $this->args['before'] . '</span> ' . "\n\t\t\t" : '' );

        return $str;
    }

    /**
     * @return string
     */
    private function trailEnd(){
        // If $after was set, wrap it in a container.
        $str = ( !empty( $this->args['after'] ) ? "\n\t\t\t" . ' <span class="trail-after">' . $this->args['after'] . '</span>' : '' );

        $str .= "\n\t\t" . '</' . tag_escape( $this->args['container'] ) . '>';

        // Close Wrapper
        $str .= !empty( $this->args['wrapper_end'] )? $this->args['wrapper_end'] : "";

        return $str;
    }
}