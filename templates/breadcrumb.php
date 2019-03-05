<?php
if($is_woocommerce && function_exists("woocommerce_breadcrumb")){
	woocommerce_breadcrumb([
		'wrap_before'   => '<div class="breadcrumbs__wrapper"><div class="breadcrumb-trail breadcrumbs" itemprop="breadcrumb">',
		'wrap_after'   => '</div></div>',
		'delimiter'  => '<span class="sep">&nbsp;&#47;&nbsp;</span>'
	]);
}else{
	Breadcrumb::render_breadcrumb(null, 'before_inner', [
		'wrapper_start' => '<div class="breadcrumbs__wrapper">',
		'wrapper_end' => '</div>'
	]);
}