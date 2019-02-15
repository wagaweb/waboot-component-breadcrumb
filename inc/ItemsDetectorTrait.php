<?php

trait ItemsDetectorTrait{
	/**
	 * Adds items for the front page to the items array.
	 */
	public function addFrontPageItems(){
		if ( $this->args['show_title'] ){
			$label = (is_multisite() && true === $this->args['network']) ? get_bloginfo('name') : $this->args['labels']['home'];
			$item = new WabootBreadcrumbItem($label,'');
			$this->addItem($item);
		}
	}

	/**
	 * Adds the network (all sites) home page link to the items array.
	 */
	public function addNetworkHomeLink(){
		if ( $this->args['network'] && is_multisite() && !is_main_site() ){
			$item = new WabootBreadcrumbItem($this->args['labels']['home'],network_home_url());
			$this->addItem($item);
		}
	}

	/**
	 * Adds the current site's home page link to the items array.
	 */
	public function addSiteHomeLink(){
		$label = ($this->args['network'] && is_multisite() && !is_main_site()) ? get_bloginfo('name') : $this->args['labels']['home'];
		$rel = ($this->args['network'] && is_multisite() && !is_main_site()) ? '' : 'home';
		$item = new WabootBreadcrumbItem($label,home_url());
		$item->setRel($rel);
		$this->addItem($item);
	}

	/**
	 * Adds items for the posts page (i.e., is_home()) to the items array.
	 */
	public function addPostsAndPagesItems(){
		$postId = get_queried_object_id();
		if($postId === 0){
			return;
		}
		$post = get_post($postId);
		if(0 < $post->post_parent){
			//If the post has parents, add them to the trail.
			$this->addPostParentsItems($post->post_parent);
		}
		$title = get_the_title($postId);
		if(is_paged()){
			$item = new WabootBreadcrumbItem($title,get_permalink($postId));
			$this->addItem($item);
		}elseif(\is_string($title) && $this->args['show_title']){
			$item = new WabootBreadcrumbItem($title,'');
			$this->addItem($item);
		}
	}

	public function addPostParentsItems($parentId){
		$parents = [];

		while($parentId > 0){
			$post = get_post($parentId);

			$item = new WabootBreadcrumbItem(get_the_title($post->ID),get_permalink($post->ID));
			$parents[] = $item;

			if(0 >= $post->post_parent){
				//If there's no longer a post parent, brea out of the loop.
				break;
			}

			$parentId = $post->post_parent;
		}

		//Get the post hierarchy based off the final parent post.
		$this->addPostHierarchyItems($parentId);

		//Finally, add all parents to the items
		$parents = array_reverse($parents);
		foreach ($parents as $parentItem){
			$this->addItem($parentItem);
		}
	}

	/**
	 * Adds the page/paged number to the items array.
	 */
	public function addPagedItems(){
		if(!$this->args['show_title']){
			return;
		}
		if (is_singular() && 1 < get_query_var('page')){
			$label = sprintf($this->args['labels']['paged'], number_format_i18n(absint(get_query_var('page'))));
			$item = new WabootBreadcrumbItem($label,'');
			$this->addItem($item);
		}elseif(is_paged()){
			$label = sprintf($this->args['labels']['paged'], number_format_i18n(absint(get_query_var('paged'))));
			$item = new WabootBreadcrumbItem($label,'');
			$this->addItem($item);
		}
	}

	/**
	 * Adds a specific post's hierarchy to the items array.  The hierarchy is determined by post type's
	 * rewrite arguments and whether it has an archive page.
	 *
	 * @param  int $post_id The ID of the post to get the hierarchy for.
	 * @return void
	 */
	public function addPostHierarchyItems($post_id) {
		$permalink_structure = get_option( 'permalink_structure' );

		/* Get the post type. */
		$post_type = get_post_type($post_id);
		$post_type_object = get_post_type_object($post_type);

		/*
		 * WAGA MOD: Display the archive page before the categories
		 */

		/* If there's an archive page, add it to the trail. */
		if (!empty($post_type_object->has_archive)) {
			/* Add support for a non-standard label of 'archive_title' (special use case). */
			$label = !empty($post_type_object->labels->archive_title) ? $post_type_object->labels->archive_title : $post_type_object->labels->name;
			$this->items[] = '<a href="' . get_post_type_archive_link($post_type) . '">' . $label . '</a>';
		}

		/* If this is the 'post' post type, get the rewrite front items and map the rewrite tags. */
		if ('post' === $post_type) {
			/* Add $wp_rewrite->front to the trail. */
			$this->do_rewrite_front_items();
			/* Map the rewrite tags. */
			$this->map_rewrite_tags( $post_id, $permalink_structure );
		} /* If the post type has rewrite rules. */
		elseif (false !== $post_type_object->rewrite) {
			/* Map rewrite tags */
			$this->map_rewrite_tags( $post_id, $permalink_structure );
			/* If 'with_front' is true, add $wp_rewrite->front to the trail. */
			if ($post_type_object->rewrite['with_front'])
				$this->do_rewrite_front_items();
			/* If there's a path, check for parents. */
			if (!empty($post_type_object->rewrite['slug']))
				$this->do_path_parents($post_type_object->rewrite['slug']);
		}
	}

	/**
	 * Turns %tag% from permalink structures into usable links for the breadcrumb trail.  This feels kind of
	 * hackish for now because we're checking for specific %tag% examples and only doing it for the 'post'
	 * post type.  In the future, maybe it'll handle a wider variety of possibilities, especially for custom post
	 * types.
	 *
	 * @since  0.6.0
	 * @access public
	 * @param  int $post_id ID of the post whose parents we want.
	 * @param  string $path Path of a potential parent page.
	 * @param  array $args Mixed arguments for the menu.
	 * @return array
	 */
	public function map_rewrite_tags($post_id, $path) {

		/* Get the post based on the post ID. */
		$post = get_post($post_id);

		/* If no post is returned, an error is returned, or the post does not have a 'post' post type, return. */
		if (empty($post) || is_wp_error($post))
			return $trail;

		/* Trim '/' from both sides of the $path. */
		$path = trim($path, '/');

		/* Split the $path into an array of strings. */
		$matches = explode('/', $path);

		/* If matches are found for the path. */
		if (is_array($matches)) {

			/* Loop through each of the matches, adding each to the $trail array. */
			foreach ($matches as $match) {

				/* Trim any '/' from the $match. */
				$tag = trim($match, '/');

				/* If using the %year% tag, add a link to the yearly archive. */
				if ('%year%' == $tag)
					$this->items[] = '<a href="' . get_year_link(get_the_time('Y', $post_id)) . '">' . sprintf($this->args['labels']['archive_year'], get_the_time(_x('Y', 'yearly archives date format', 'breadcrumb-trail'))) . '</a>';

				/* If using the %monthnum% tag, add a link to the monthly archive. */
				elseif ('%monthnum%' == $tag)
					$this->items[] = '<a href="' . get_month_link(get_the_time('Y', $post_id), get_the_time('m', $post_id)) . '">' . sprintf($this->args['labels']['archive_month'], get_the_time(_x('F', 'monthly archives date format', 'breadcrumb-trail'))) . '</a>';

				/* If using the %day% tag, add a link to the daily archive. */
				elseif ('%day%' == $tag)
					$this->items[] = '<a href="' . get_day_link(get_the_time('Y', $post_id), get_the_time('m', $post_id), get_the_time('d', $post_id)) . '">' . sprintf($this->args['labels']['archive_day'], get_the_time(_x('j', 'daily archives date format', 'breadcrumb-trail'))) . '</a>';

				/* If using the %author% tag, add a link to the post author archive. */
				elseif ('%author%' == $tag)
					$this->items[] = '<a href="' . get_author_posts_url($post->post_author) . '" title="' . esc_attr(get_the_author_meta('display_name', $post->post_author)) . '">' . get_the_author_meta('display_name', $post->post_author) . '</a>';

				/* If using the %category% tag, add a link to the first category archive to match permalinks. */
				elseif ('%category%' == $tag) {

					/* Force override terms in this post type. */
					$this->args['post_taxonomy'][$post->post_type] = false;

					/* Get the post categories. */
					if('post' == $post->post_type){
						$terms = get_the_category($post_id);
					}else{ /* WAGA MOD */
						$post_type_object = get_post_type_object($post->post_type);
						$post_type_taxonomies = get_object_taxonomies($post->post_type);
						//Reorder the taxonomies with the hierarchical one at the top
						if(is_array($post_type_taxonomies) && !empty($post_type_taxonomies)){
							usort($post_type_taxonomies,function($a,$b){
								if($a == $b) return 0;
								$a_tax = get_taxonomy($a);
								$b_tax = get_taxonomy($b);
								if($a_tax->hierarchical && $b_tax->hierarchical) return 0;
								if(!$a_tax->hierarchical && !$b_tax->hierarchical) return 0;
								if($a_tax->hierarchical && !$b_tax->hierarchical) return -1;
								if(!$a_tax->hierarchical && $b_tax->hierarchical) return 1;
							});
							$terms = get_the_terms($post_id,$post_type_taxonomies[0]);
						}else{
							$terms = false;
						}
					}

					//Check that categories were returned.
					/*if ($terms) {
						//Sort the terms by ID and get the first category
						usort($terms, '_usort_terms_by_ID');
						if('post' == $post->post_type){
							$taxonomy_name = "category";
						}else{
							$taxonomy_name = $terms[0]->taxonomy;
						}
						$term = get_term($terms[0], $taxonomy_name);

						//If the category has a parent, add the hierarchy to the trail.
						if ($term->parent > 0){
							$this->do_term_parents($term->parent, $taxonomy_name);
						}

						//Add the category archive link to the trail.
						$this->items[] = '<a href="' . get_term_link($term, $taxonomy_name) . '" title="' . esc_attr($term->name) . '">' . $term->name . '</a>';
					}*/
					//BETA [ WAGA MOD ]:
					$added_terms = array();
					if ($terms) {
						/* Sort the terms by ID and get the first category. */
						if(function_exists('wp_list_sort')){
							$terms = wp_list_sort($terms,'term_id','ASC');
						}else{
							usort($terms, '_usort_terms_by_ID');
						}
						/* Add the category archive link to the trail. */
						foreach($terms as $t){
							if('post' == $post->post_type){
								$taxonomy_name = "category";
							}else{
								$taxonomy_name = $t->taxonomy;
							}
							/* If the category has a parent, add the hierarchy to the trail. */
							if ($t->parent > 0 && !in_array($t->parent,$added_terms)){
								$this->do_term_parents($t->parent, $taxonomy_name);
							}
							$this->items[] = '<a href="' . get_term_link($t, $taxonomy_name) . '" title="' . esc_attr($t->name) . '">' . $t->name . '</a>';
							$added_terms[] = $t->term_id;
						}
					}
				}
			}
		}
	}
}