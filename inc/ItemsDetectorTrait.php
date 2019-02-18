<?php

trait ItemsDetectorTrait{
	/**
	 * Adds items for the front page to the items array.
	 */
	public function addFrontPageItems(){
		if ( $this->canShowTitles() ){
			$label = (is_multisite() && true === $this->args['network']) ? get_bloginfo('name') : $this->getLabel('home');
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
		$label = ($this->args['network'] && is_multisite() && !is_main_site()) ? get_bloginfo('name') : $this->getLabel('home');
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
		}elseif(\is_string($title) && $this->canShowTitles()){
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
				//If there's no longer a post parent, break out of the loop.
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
		if(!$this->canShowTitles()){
			return;
		}
		if (is_singular() && 1 < get_query_var('page')){
			$label = sprintf($this->getLabel('paged'), number_format_i18n(absint(get_query_var('page'))));
			$item = new WabootBreadcrumbItem($label,'');
			$this->addItem($item);
		}elseif(is_paged()){
			$label = sprintf($this->getLabel('paged'), number_format_i18n(absint(get_query_var('paged'))));
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
		$permalinkStructure = get_option( 'permalink_structure' );
		$postType = get_post_type($post_id);
		$postTypeObject = get_post_type_object($postType);

		// If there's an archive page, add it to the trail.
		if ($postTypeObject instanceof \WP_Post_Type && !empty($postTypeObject->has_archive)) {
			// Add support for a non-standard label of 'archive_title' (special use case).
			$label = !empty($postTypeObject->labels->archive_title) ? $postTypeObject->labels->archive_title : $postTypeObject->labels->name;
			$item = new WabootBreadcrumbItem($label,get_post_type_archive_link($postType));
			$this->addItem($item);
		}

		// If this is the 'post' post type, get the rewrite front items and map the rewrite tags.
		if ($postType === 'post') {
			// Add $wp_rewrite->front to the trail. */
			$this->addFrontItems();
			// Map the rewrite tags. */
			$this->mapRewriteTags( $post_id, $permalinkStructure );
		} elseif ($postTypeObject instanceof \WP_Post_Type && $postTypeObject->rewrite !== false) {
			// If the post type has rewrite rules.
			// Map rewrite tags
			$this->mapRewriteTags( $post_id, $permalinkStructure );
			// If 'with_front' is true, add $wp_rewrite->front to the trail.
			if ($postTypeObject->rewrite['with_front']){
				$this->addFrontItems();
			}
			// If there's a path, check for parents.
			if (!empty($postTypeObject->rewrite['slug'])){
				$this->addPostParentsByPath($postTypeObject->rewrite['slug']);
			}
		}
	}

	/**
	 * Add front items based on $wp_rewrite->front.
	 */
	public function addFrontItems(){
		global $wp_rewrite;

		if($wp_rewrite->front){
			$this->addPostParentsByPath($wp_rewrite->front);
		}
	}

	/**
	 * Add parent posts by path.  Currently, this method only supports getting parents of the 'page'
	 * post type.  The goal of this function is to create a clear path back to home given what would
	 * normally be a "ghost" directory.  If any page matches the given path, it'll be added.
	 *
	 * @param string $path The path (slug) to search for posts by.
	 */
	public function addPostParentsByPath($path){
		$path = trim($path, '/');

		if ( empty($path) ){
			return;
		}

		$post = get_page_by_path($path);

		if ($post instanceof \WP_Post) {
			$this->addPostParentsItems($post->ID);
		}elseif ($post === null){
			// Separate post names into separate paths by '/'
			$path = trim($path, '/');
			preg_match_all("/\/.*?\z/", $path, $matches);
			if (!isset($matches)) {
				return;
			}
			// Reverse the array of matches to search for posts in the proper order.
			$matches = array_reverse($matches);
			// Loop through each of the path matches.
			foreach ($matches as $match) {
				if(!isset($match[0])){
					continue;
				}
				// Get the parent post by the given path.
				$path = str_replace($match[0], '', $path);
				$post = get_page_by_path(trim($path, '/'));
				if(!$post instanceof \WP_Post){
					continue;
				}
				if($post->ID <= 0){
					continue;
				}
				// If a parent post is found, set the $post_id and break out of the loop.
				$this->addPostParentsItems($post->ID);
				break;
			}
		}
	}

	/**
	 * Turns %tag% from permalink structures into usable links for the breadcrumb trail.  This feels kind of
	 * hackish for now because we're checking for specific %tag% examples and only doing it for the 'post'
	 * post type.  In the future, maybe it'll handle a wider variety of possibilities, especially for custom post
	 * types.
	 *
	 * @param  int $postId ID of the post whose parents we want.
	 * @param  string $path Path of a potential parent page.
	 */
	public function mapRewriteTags($postId, $path) {

		// Get the post based on the post ID.
		$post = get_post($postId);

		// If no post is returned, an error is returned, or the post does not have a 'post' post type, return.
		if (empty($post) || !$post instanceof \WP_Post || is_wp_error($post)){
			return;
		}

		// Trim '/' from both sides of the $path.
		$path = trim($path, '/');

		// Split the $path into an array of strings.
		$matches = explode('/', $path);

		if(!is_array($matches) || count($matches) <= 0){
			return;
		}

		// Loop through each of the matches, adding each to the items
		foreach ($matches as $match) {

			// Trim any '/' from the $match.
			$tag = trim($match, '/');

			switch($tag){
				case '%year%':
					// If using the %year% tag, add a link to the yearly archive.
					$label = sprintf($this->getLabel('archive_year'), get_the_time(_x('Y', 'yearly archives date format', 'breadcrumb-trail')));
					$url = get_year_link(get_the_time('Y', $postId));
					$item = new WabootBreadcrumbItem($label,$url);
					$this->addItem($item);
					break;
				case '%monthnum%':
					// If using the %monthnum% tag, add a link to the monthly archive.
					$label = sprintf($this->getLabel('archive_month'), get_the_time(_x('F', 'monthly archives date format', 'breadcrumb-trail')));
					$url = get_month_link(get_the_time('Y', $postId), get_the_time('m', $postId));
					$item = new WabootBreadcrumbItem($label,$url);
					$this->addItem($item);
					break;
				case '%day%':
					// If using the %day% tag, add a link to the daily archive.
					$label = sprintf($this->getLabel('archive_day'), get_the_time(_x('j', 'daily archives date format', 'breadcrumb-trail')));
					$url = get_day_link(get_the_time('Y', $postId), get_the_time('m', $postId), get_the_time('d', $postId));
					$item = new WabootBreadcrumbItem($label,$url);
					$this->addItem($item);
					break;
				case '%author%':
					// If using the %author% tag, add a link to the post author archive.
					$label = get_the_author_meta('display_name', $post->post_author);
					$url = get_author_posts_url($post->post_author);
					$item = new WabootBreadcrumbItem($label,$url);
					$title = get_the_author_meta('display_name', $post->post_author);
					$item->setTitle($title);
					$this->addItem($item);
					break;
				case '%category%':
					// If using the %category% tag, add a link to the first category archive to match permalinks.
					$this->args['post_taxonomy'][$post->post_type] = false; //Force override terms in this post type.
					if('post' == $post->post_type){
						$terms = get_the_category($postId);
					}else{
						$postTypeObject = get_post_type_object($post->post_type);
						$postTypeTaxonomies = get_object_taxonomies($post->post_type);
						// Reorder the taxonomies with the hierarchical one at the top.
						if(is_array($postTypeTaxonomies) && !empty($postTypeTaxonomies)){
							usort($postTypeTaxonomies,function($a,$b){
								if($a == $b) return 0;
								$aTax = get_taxonomy($a);
								$bTax = get_taxonomy($b);
								if($aTax->hierarchical && $bTax->hierarchical){
									return 0;
								}
								if(!$aTax->hierarchical && !$bTax->hierarchical){
									return 0;
								}
								if($aTax->hierarchical && !$bTax->hierarchical){
									return -1;
								}
								if(!$aTax->hierarchical && $bTax->hierarchical){
									return 1;
								}
							});
							$terms = get_the_terms($postId,$postTypeTaxonomies[0]);
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

					if(!$terms){
						break;
					}

					$addedTerms = array();

					// Sort the terms by ID and get the first category.
					if(function_exists('wp_list_sort')){
						$terms = wp_list_sort($terms,'term_id','ASC');
					}else{
						usort($terms, '_usort_terms_by_ID');
					}
					// Add the category archive link to the trail.
					foreach($terms as $t){
						if('post' == $post->post_type){
							$taxonomyName = "category";
						}else{
							$taxonomyName = $t->taxonomy;
						}
						// If the category has a parent, add the hierarchy to the trail.
						if ($t->parent > 0 && !in_array($t->parent,$addedTerms)){
							$this->addTermParents($t->parent, $taxonomyName);
						}
						$newItemUrl = get_term_link($t, $taxonomyName);
						$newItemTitle = $t->name;
						$newItemLabel = $t->name;
						$newItem = new WabootBreadcrumbItem($newItemLabel,$newItemUrl);
						$newItem->setTitle($newItemTitle);
						$this->addItem($newItem);
						$addedTerms[] = $t->term_id;
					}

					break;
			}
		}
	}

	/**
	 * Searches for term parents of hierarchical taxonomies.  This function is similar to the WordPress
	 * function get_category_parents() but handles any type of taxonomy.
	 *
	 * @param  int    $termId  ID of the term to get the parents of.
	 * @param  string $taxonomy Name of the taxonomy for the given term.
	 */
	public function addTermParents($termId, $taxonomy) {

		// Set up some default arrays.
		$parents = array();

		// While there is a parent ID, add the parent term link to the $parents array.
		while ($termId) {
			// Get the parent term.
			$term = get_term($termId, $taxonomy);

			// Add the formatted term link to the array of parent terms.
			$newItem = new WabootBreadcrumbItem($term->name,get_term_link($term, $taxonomy));
			$parents[] = $newItem;

			// Set the parent term's parent as the parent ID.
			$termId = $term->parent;
		}

		// If we have parent terms, reverse the array to put them in the proper order for the trail.
		if (!empty($parents)){
			foreach ($parents as $newItem){
				$this->addItem($newItem);
			}
		}
	}

	/**
	 * Adds singular post items
	 */
	public function addSingularItems(){
		// Get the queried post.
		$post = get_queried_object();
		$postId = get_queried_object_id();

		// If the post has a parent, follow the parent trail.
		if (0 < $post->post_parent){
			$this->addPostParentsItems($post->post_parent);
		}
		// If the post doesn't have a parent, get its hierarchy based off the post type.
		else{
			$this->addPostHierarchyItems($postId);
		}

		// Display terms for specific post type taxonomy if requested.
		$this->addPostTerms($postId);

		// End with the post title.
		if ($postTitle = single_post_title('', false)) {
			if (1 < get_query_var('page')){
				$newItem = new WabootBreadcrumbItem($postTitle,get_permalink($postId));
				$this->addItem($newItem);
			} elseif ($this->canShowTitles()){
				$newItem = new WabootBreadcrumbItem($postTitle);
				$this->addItem($newItem);
			}
		}
	}

	/**
	 * Adds a post's terms from a specific taxonomy to the items array.
	 *
	 * @param int $post_id The ID of the post to get the terms for.
	 */
	public function addPostTerms($post_id){
		// Get the post type.
		$post_type = get_post_type($post_id);

		if(empty($this->args['post_taxonomy'][$post_type])){
			return;
		}

		// Add the terms of the taxonomy for this post.
		$taxonomy = $this->args['post_taxonomy'][$post_type];
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) ){
			return;
		}

		if ( empty( $terms ) ){
			return;
		}

		foreach ( $terms as $term ) {
			$link = get_term_link( $term, $taxonomy );
			if ( is_wp_error( $link ) ) {
				$link = '#';
			}
			$newItem = new WabootBreadcrumbItem($term->name,$link);
			$newItem->setRel('tag');
			$this->addItem($newItem);
		}
	}

	/**
	 * Adds the items to the trail items array for post type archives
	 */
	public function addPostTypeArchiveItems(){
		// Get the post type object.
		$post_type_object = get_post_type_object(get_query_var('post_type'));

		if ($post_type_object->rewrite !== false) {
			// If 'with_front' is true, add $wp_rewrite->front to the trail.
			if ($post_type_object->rewrite['with_front']){
				$this->addFrontItems();
			}
			// If there's a rewrite slug, check for parents.
			if (!empty($post_type_object->rewrite['slug'])){
				$this->addPostParentsByPath($post_type_object->rewrite['slug']);
			}
		}

		// Add the post type [plural] name to the trail end
		if (is_paged()){
			$newItemLabel = post_type_archive_title( '', false );
			$newItemUrl = get_post_type_archive_link( $post_type_object->name );
			$this->addItem(new WabootBreadcrumbItem($newItemLabel,$newItemUrl));
		} elseif ($this->canShowTitles()){
			$this->addItem(new WabootBreadcrumbItem(post_type_archive_title('', false)));
		}
	}

	/**
	 * Adds the items to the trail items array for taxonomy term archives
	 */
	public function addTermArchiveItems(){
		global $wp_rewrite;

		// Get some taxonomy and term variables.
		$term = get_queried_object();
		$taxonomy = get_taxonomy($term->taxonomy);

		// If there are rewrite rules for the taxonomy.
		if($taxonomy->rewrite === false){
			return;
		}

		// If 'with_front' is true, dd $wp_rewrite->front to the trail.
		if ($taxonomy->rewrite['with_front'] && $wp_rewrite->front){
			$this->addFrontItems();
		}

		// Get parent pages by path if they exist.
		$this->addPostParentsByPath($taxonomy->rewrite['slug']);

		// Add post type archive if its 'has_archive' matches the taxonomy rewrite 'slug'.
		if ($taxonomy->rewrite['slug']) {
			$slug = trim($taxonomy->rewrite['slug'], '/');

			/*
			 * Deals with the situation if the slug has a '/' between multiple strings. For
			 * example, "movies/genres" where "movies" is the post type archive.
			 */
			$matches = explode('/', $slug);

			// If matches are found for the path.
			if (isset($matches)) {
				// Reverse the array of matches to search for posts in the proper order.
				$matches = array_reverse($matches);

				// Loop through each of the path matches.
				foreach ($matches as $match) {
					// If a match is found.
					$slug = $match;

					// Get public post types that match the rewrite slug.
					$post_types = $this->getPostTypesBySlug($match);

					if ( !empty( $post_types ) ) {
						$post_type_object = $post_types[0];

						// Add support for a non-standard label of 'archive_title' (special use case).
						$label = !empty($post_type_object->labels->archive_title) ? $post_type_object->labels->archive_title : $post_type_object->labels->name;

						// Add the post type archive link to the trail.
						$newItem = new WabootBreadcrumbItem($label,get_post_type_archive_link($post_type_object->name));
						$this->addItem($newItem);

						// Break out of the loop.
						break;
					}
				}
			}
		}

		// If the taxonomy is hierarchical, list its parent terms.
		if (is_taxonomy_hierarchical($term->taxonomy) && $term->parent){
			$this->addTermParents($term->parent, $term->taxonomy);
		}

		// Add the term name to the trail end.
		if (is_paged()){
			$newItem = new WabootBreadcrumbItem(single_term_title('', false),get_term_link($term, $term->taxonomy));
			$this->addItem($newItem);
		} elseif ($this->canShowTitles()){
			$newItem = new WabootBreadcrumbItem(single_term_title('', false));
			$this->addItem($newItem);
		}
	}

	/**
	 * Gets post types by slug.  This is needed because the get_post_types() function doesn't exactly
	 * match the 'has_archive' argument when it's set as a string instead of a boolean.
	 *
	 * @param int $slug The post type archive slug to search for.
	 * @return array
	 */
	private function getPostTypesBySlug($slug) {
		$return = [];

		$post_types = get_post_types([], 'objects');

		foreach ($post_types as $type) {
			if ($slug === $type->has_archive || ($type->has_archive === true && $type->rewrite['slug'] === $slug)){
				$return[] = $type;
			}
		}

		return $return;
	}

	/**
	 * Adds the items to the trail items array for user (author) archives
	 */
	public function addUserArchiveItems(){
		global $wp_rewrite;

		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();

		// Get the user ID.
		$user_id = get_query_var('author');

		// If $author_base exists, check for parent pages.
		if (!empty($wp_rewrite->author_base)){
			$this->addPostParentsByPath($wp_rewrite->author_base);
		}

		// Add the author's display name to the trail end.
		if (is_paged()){
			$newItemLabel = get_the_author_meta('display_name', $user_id);
			$newItemUrl = get_author_posts_url($user_id);
			$this->addItem(new WabootBreadcrumbItem($newItemLabel,$newItemUrl));
		} elseif ($this->canShowTitles()){
			$this->addItem(new WabootBreadcrumbItem(get_the_author_meta('display_name', $user_id)));
		}
	}

	/**
	 * Adds the items to the trail items array for minute + hour archives
	 */
	public function addMinuteHourArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();

		// Add the minute + hour item.
		if ($this->canShowTitles()){
			$newItemLabel = sprintf($this->args['labels']['archive_minute_hour'], get_the_time(_x('g:i a', 'minute and hour archives time format', 'breadcrumb-trail')));
			$this->addItem(new WabootBreadcrumbItem($newItemLabel));
		}
	}

	/**
	 * Adds the items to the trail items array for minute archives.
	 */
	public function addMinuteArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();

		// Add the minute item.
		if ($this->canShowTitles()){
			$newItemLabel = sprintf( $this->args['labels']['archive_minute'], get_the_time( _x( 'i', 'minute archives time format', 'breadcrumb-trail' ) ) );
			$this->addItem(new WabootBreadcrumbItem($newItemLabel));
		}
	}

	/**
	 * Adds the items to the trail items array for hour archives.
	 */
	public function addHourArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();

		// Add the hour item.
		if ($this->canShowTitles()){
			$newItemLabel = sprintf( $this->args['labels']['archive_hour'], get_the_time( _x( 'g a', 'hour archives time format', 'breadcrumb-trail' ) ) );
			$this->addItem(new WabootBreadcrumbItem($newItemLabel));
		}
	}

	/**
	 * Adds the items to the trail items array for day archives.
	 */
	public function addDayArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();

		// Get year, month, and day.
		$year = sprintf($this->getLabel('archive_year'), get_the_time(_x('Y', 'yearly archives date format', 'breadcrumb-trail')));
		$month = sprintf( $this->getLabel('archive_month'), get_the_time( _x( 'F', 'monthly archives date format', 'breadcrumb-trail' ) ) );
		$day = sprintf($this->getLabel('archive_day'), get_the_time(_x('j', 'daily archives date format', 'breadcrumb-trail')));

		// Add the year and month items.
		$yearItem = new WabootBreadcrumbItem($year,get_year_link(get_the_time('Y')));
		$monthItem = new WabootBreadcrumbItem($month,get_month_link(get_the_time('Y'), get_the_time('m')));
		$this->addItem($yearItem);
		$this->addItem($monthItem);

		// Add the day item.
		if (is_paged()){
			$newItemUrl = get_day_link( get_the_time( 'Y' ), get_the_time( 'm' ), get_the_time( 'd' ) );
			$this->addItem(new WabootBreadcrumbItem($day, $newItemUrl));
		} elseif ($this->canShowTitles()){
			$this->addItem(new WabootBreadcrumbItem($day));
		}
	}

	/**
	 *
	 */
	public function addWeekArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();
	}

	/**
	 *
	 */
	public function addMonthArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();
	}

	/**
	 *
	 */
	public function addYearArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();
	}

	/**
	 *
	 */
	public function addDefaultArchiveItems(){
		// Add $wp_rewrite->front to the trail.
		$this->addFrontItems();
	}

	/**
	 * Adds the items to the trail items array for search results
	 */
	public function addSearchItems(){
		if (is_paged()){
			$newItemLabel = sprintf($this->getLabel('search'), get_search_query());
			$newItemUrl = get_search_link();
			$this->addItem(new WabootBreadcrumbItem($newItemLabel,$newItemUrl));
		} elseif ($this->canShowTitles()){
			$newItemLabel = sprintf($this->getLabel('search'), get_search_query());
			$this->addItem(new WabootBreadcrumbItem($newItemLabel));
		}
	}

	/**
	 * Adds the items to the trail items array for 404 pages
	 */
	public function add404Items(){
		if ($this->canShowTitles()){
			$this->addItem(new WabootBreadcrumbItem($this->getLabel('error_404')));
		}
	}
}