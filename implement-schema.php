<?php

/** The scripts in this file will automate the schema.org markup **/
/** implementation on a custom wordpress Website                 **/


/**********************************************************************************

              Define some useful variables for future use

 **********************************************************************************/
/*
  To encode array to ld-json
  The function removes first and last {} from data json
*/
 function process_data_json($data) {
 	return substr($data, 1, strlen($data) - 2);
 }

/* Get the url of the loaded page */
function get_current_url() {
global $wp;
  $current_url = home_url( add_query_arg( array(), $wp->request ) );
	print_r ($current_url);
}

/* Get the name of the articles loop page */
function get_blog_page_title() {
	$blog_title = get_the_title( get_option('page_for_posts', true) );
	print_r ($blog_title);
}

// Returns a part of the slug :: useful when necessary
// $nice_name :: the $name_slug with the first letter capitalized
function get_slug_name() {
	global $post;
	$post_slug=$post->post_name;
	$name_slug=substr_replace($post_slug,"",-3);
	$nice_name=ucfirst($name_slug);
	echo $nice_name;
}


// Targets the children of one specific page
function is_child( $page_id_or_slug ) {
	global $post;
		$page = get_page_by_path($page_id_or_slug);
		$page_id_or_slug = $page->ID;
	if(is_page() && $post->post_parent == $page_id_or_slug ) {
       		return true;
	} else {
       		return false;
	}
}

// Check if a page is in a specific category
// in order to add specific markup for posts in different categories

function has_categories( $category ){
	if ( is_single() ) {
		$cats =  get_the_category();
	} else {
		$cats = array( get_category( get_query_var( 'cat' ) ) );
	}
	
	foreach( $cats as $_cat ){
		$cat_slugs[] = $_cat->slug;
		if ( $category == $_cat->slug ) {
			return true;
		} else {
			return false;
		}
	}
};

// checks if a plugin is active or not
// you need to use the path to the plugin file
// i.e. check_plugins('plugins/wordpress-seo/wp-seo.php') for Yoast SEO Plugin
function check_plugins($plugin){
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if (is_plugin_active($plugin)) {
		return false;
	} else {
		return true;
	}
}

// check if Yoast SEO is installed
function y_s_p(){
	if (check_plugins('plugins/wordpress-seo/wp-seo.php')) {
		return true;
	}
}

// check if Woocommerce is installed
function w_c_p(){
	if (check_plugins('plugins/woocommerce/woocommerce.php')) {
		return true;
	}
}

/**********************************************************************************

					Add attributes for the Site Navigation Element

 **********************************************************************************/

/* Set the SiteNavigationElement type for the navigation */

function sd_list_attr( $items ) {
    $dom = new DOMDocument();
	  libxml_use_internal_errors(true);

    $dom->loadHTML(mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8'));

    $items = $dom->getElementsByTagName('ul');
    $navigation = $items[0];

    $navigation->setAttribute('itemscope','itemscope');
    $navigation->setAttribute('itemtype','http://schema.org/SiteNavigationElement');

    return $dom->saveHTML(html_entity_decode($navigation));

}

if (!is_front_page()){
	add_action('wp_nav_menu', 'sd_list_attr', 10, 2);
}
/* Add the "name" property on menu items */

function sd_item_attr( $items ) {
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8'));
    $items = $dom->getElementsByTagName('li');

    foreach ($items as $item ) :
        $item->setAttribute('itemprop','name');
    endforeach;

    return $dom->saveHTML(html_entity_decode($items));

}

if (!is_front_page()){
	add_filter('wp_nav_menu_items', 'sd_item_attr', 10, 2);
}
/* Add the "url" property on menu anchors */

function sd_menu_atts( $atts, $item, $args ) {
	$atts['itemprop'] = 'url';
    return $atts;
}

if (!is_front_page()){
	add_filter( 'nav_menu_link_attributes', 'sd_menu_atts', 10, 3 );
}

/**********************************************************************************

              Set schema type for the webpage, on the <html> tag

 **********************************************************************************/

function set_header_schema_markup($output) {

	if ( is_page( 'contact-us' ) ) {
		$type = "ContactPage";
	}
  	elseif ( is_front_page() ) {
	  if ( is_single() ) {
		$type = "ProfilePage";
	  }
	  else {
		$type = "CollectionPage";
	  }
	}
	elseif ( is_page( 'terms-conditions' ) || is_child( 'glossarium' ) || is_page( 'about' ) || is_page( 'about-us' ) ) {
		$type = "AboutPage";
	} 
	elseif ( !is_single() ) {
		$type = "CollectionPage";
  	}	
	elseif ( is_single() ) {
		if ( has_categories('scripturam') ) {
			$type = "ShortStory";			
		} 
		elseif ( has_categories('odysseia') ) {
			$type = "ReportageNewsArticle";
		}
		else {
			$type = "ScholarlyArticle";
		}
  	}
	else {
		$type ="WebPage";
	}

	$output = 'itemscope="itemscope" itemtype="http://schema.org/'. $type .'"';

	return $output;
}

add_filter('language_attributes', 'set_header_schema_markup');

/**********************************************************************************

					Add attributes to tags inside the page content

 **********************************************************************************/

function sd_supplementary_tags( $content ){

	/* add additionalType and name for ordered lists */

	$content = preg_replace_callback( '/(\<ol(.*?))\>(.*)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'id=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . '><link itemprop="additionalType" href="http://schema.org/CreativeWorkSeries"><meta itemprop="name" content="Description List">' . $matches[3];
		endif;
		return $matches[0];
	}, $content );

if (!is_single()){
	$content = preg_replace_callback( '/(\<article(.*?))\>(.*)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'id=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . ' itemprop="blogPost" itemscope itemtype="http://schema.org/BlogPosting">' . $matches[3];
		endif;
		return $matches[0];
	}, $content );
}
	/* add additionalType and name for unordered lists */

	$content = preg_replace_callback( '/(\<ul(.*?))\>(.*)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'id=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . '><link itemprop="additionalType" href="http://schema.org/CreativeWorkSeries"><meta itemprop="name" content="Description List">' . $matches[3];
		endif;
		return $matches[0];
	}, $content );

	/* add additionalType and name for list items */

	$content = preg_replace_callback( '/(\<li(.*?))\>(.*)(<\/li>)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'id=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . '><span itemprop="name">' . $matches[3] . '</span>' . $matches[4];
		endif;
		return $matches[0];
	}, $content );

	/* add additionalType and name for dl Tables */

	$content = preg_replace_callback( '/(\<dl(.*?))\>(.*)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'id=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . '><link itemprop="additionalType" href="http://schema.org/ItemList"><meta itemprop="name" content="Enumeration List">' . $matches[3];
		endif;
		return $matches[0];
	}, $content );

    return $content;

}

add_filter( 'the_content', 'sd_supplementary_tags' );


function sd_content_attributes( $items ) {
	libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8'));

	$par = $dom->getElementsByTagName('p');
	$em = $dom->getElementsByTagName('em');
	$strong = $dom->getElementsByTagName('strong');
	$pre = $dom->getElementsByTagName('pre');
	$img = $dom->getElementsByTagName('img');
	$ul = $dom->getElementsByTagName('ul');
	$ol = $dom->getElementsByTagName('ol');
	$li = $dom->getElementsByTagName('li');
	$dl = $dom->getElementsByTagName('dl');
	$dt = $dom->getElementsByTagName('dt');
	$dd = $dom->getElementsByTagName('dd');
	$anchor = $dom->getElementsByTagName('a');
	$table = $dom->getElementsByTagName('table');
	$th = $dom->getElementsByTagName('th');
	$tr = $dom->getElementsByTagName('tr');
	$td = $dom->getElementsByTagName('td');
	$html_object = $dom->getElementsByTagName('object');
	$figure = $dom->getElementsByTagName('figure');
	$article = $dom->getElementsByTagName('article');
//	$tooltip_key = $dom->getElementsByClassName('tooltipsall');

    foreach ($par as $item ) :
        $item->setAttribute('itemprop','text');
    endforeach;

	foreach ($em as $item ) :
        $item->setAttribute('itemprop','keywords');
    endforeach;

	foreach ($strong as $item ) :
        $item->setAttribute('itemprop','keywords');
    endforeach;

	foreach ($pre as $item ) :
        $item->setAttribute('itemprop','workExample');
    endforeach;

	foreach ($img as $item ) :
        $item->setAttribute('itemprop','image');
    endforeach;
	
	foreach ($figure as $item ) :
        $item->setAttribute('itemprop','image');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/ImageObject');
    endforeach;

	foreach ($ul as $item ) :
        $item->setAttribute('itemprop','about');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/ItemList');
    endforeach;

	foreach ($ol as $item ) :
        $item->setAttribute('itemprop','about');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/ItemList');
    endforeach;

	foreach ($li as $item ) :
        $item->setAttribute('itemprop','itemListElement');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/ListItem');
    endforeach;

	foreach ($dl as $item ) :
        $item->setAttribute('itemprop','hasPart');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/Dataset');
    endforeach;

    foreach ($dt as $item ) :
        $item->setAttribute('itemprop','headline');
    endforeach;

    foreach ($dd as $item ) :
        $item->setAttribute('itemprop','text');
    endforeach;
    
	foreach ($html_object as $item ) :
        $item->setAttribute('itemprop','associatedMedia');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/MediaObject');
    endforeach;    

    foreach ($anchor as $item ) :
        $item->setAttribute('itemprop','url');
		$item->setAttribute('rel','nofollow');
		$item->setAttribute('target','_blank');
    endforeach;

	foreach ($table as $item ) :
        $item->setAttribute('itemprop','hasPart');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/Table');
    endforeach;

	foreach ($th as $item ) :
        $item->setAttribute('itemprop','headline');
    endforeach;

	foreach ($tr as $item ) :
        $item->setAttribute('itemprop','hasPart');
		$item->setAttribute('itemscope','itemscope');
		$item->setAttribute('itemtype','http://schema.org/Dataset');
    endforeach;

	foreach ($td as $item ) :
        $item->setAttribute('itemprop','text');
    endforeach;

	foreach ($article as $item ) :
		if ( is_single() && is_page_template('single.php') ){
			$item->setAttribute('itemprop','articleBody');
		} 
		else {
			$item->setAttribute('itemprop','text');
			if ( $item->hasAttribute('itemscope') ) {
					$item->removeAttribute('itemscope');
				}
			if ( $item->hasAttribute('itemtype') ) {
					$item->removeAttribute('itemtype');
            }
		}
    endforeach;

    return $dom->saveHTML();

}

add_filter('the_content', 'sd_content_attributes');

/** Set schema type for headlines, in the content section **/

function auto_schema_headings( $content ) {
	$content = preg_replace_callback( '/(\<h[1-6](.*?))\>(.*)(<\/h[1-6]>)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'itemprop=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . ' itemprop="headline">' . $matches[3] . $matches[4];
		endif;
		return $matches[0];
	}, $content );
    return $content;
}

add_filter( 'the_content', 'auto_schema_headings' );

/** Set schema type for links, in the content section **/

function auto_schema_links( $content ) {
	$content = preg_replace_callback( '/(\<a(.*?))\>(.*)(<\/a>)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'itemprop=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . ' itemprop="isBasedOnUrl">' . $matches[3] . $matches[4];
		endif;
		return $matches[0];
	}, $content );
    return $content;
}

if (!is_single()) {
	add_filter( 'the_content', 'auto_schema_links' );
}

/** Set schema type for images, in the content section **/
if (!is_single()) {
	function auto_schema_images( $content ) {
		$content = preg_replace_callback( '/(\<img(.*?))\>(.*)(>)/i', function( $matches ) {
			if ( ! stripos( $matches[0], 'itemprop=' ) ) :
				$matches[0] = $matches[1] . $matches[2] . ' itemprop="image">' . $matches[3] . $matches[4];
			endif;
			return $matches[0];
		}, $content );
		return $content;	
	}

	add_filter( 'the_content', 'auto_schema_images' );
}

/*
  This function creates the SiteLink Searchbox schema markup
*/
function sd_sitelink_searchbox(){
  	global $post;

    $sitename = get_bloginfo('name');
    $sitelink = get_site_url();

    echo '<script type="application/ld+json">
      { "@context":"http:\/\/schema.org\/",
        "@type":"Website",
        "@id":"#website",
        "name":"'. $sitename .'",
        "url":"'. $sitelink .'",
        "potentialAction":{
          "@type":"SearchAction",
          "target":"'. $sitelink .'/?s={search_term_string}",
          "query-input":"required name=search_term_string"}
        }
        </script>';
}

/*
  If Yoast SEO Plugin is not active, this script will activate the SiteLink Searchbox
*/
if (!y_s_p()){
  if (!is_front_page()) {
    add_action('wp_head','sd_sitelink_searchbox');
  }
}

function sd_entity_schema() {

  echo '<script type="application/ld+json">
    {
      "@context":"http:\/\/schema.org\/",
      "@type":"Person",
      "@id":"#person",
      "additionalType":"https:\/\/en.wikipedia.org\/wiki\/Writer",
      "name":"Iulian Andriescu",
      "givenName":"Iulian",
      "familyName":"Andriescu",
      "additionalName":["iurianu","Lyljan Dracon"],
      "gender":"Male",
      "email":["iulianandriescu@gmail.com","iulianandriescu@yahoo.co.uk", "dragonlyljan@gmail.com"],
      "telephone":"+40.729.062.628",
      "url":"http:\/\/iurianu.rocks",
      "sameAs":[
        "https:\/\/www.upwork.com\/freelancers\/~0160030fd9a29057f0",
        "https:\/\/www.metal-archives.com\/artists\/Iulian_Andriescu\/300900",
        "http:\/\/www.poezie.ro\/index.php\/author\/0021473\/index.html",
        "https:\/\/stackoverflow.com\/users\/4937173\/iulian-andriescu",
        "https:\/\/www.versuri.ro\/artist\/iulian-andriescu-_vl3.html",
        "http:\/\/purls.site\/iulian-andriescu\/resume\/",
        "https:\/\/github.com\/iurianu",
        "https:\/\/www.lyricsofsong.com\/artist\/iulian-andriescu-lyrics-_vl3.html",
        "http:\/\/independent.academia.edu\/IulianAndriescu",
        "https:\/\/foursquare.com\/iurianu",
        "https:\/\/www.flickr.com\/photos\/134464013@N05\/",
        "https:\/\/www.quora.com\/profile\/Iulian-Andriescu"
      ],
      "image":{
        "@type":"ImageObject",
        "name":"Iulian Andriescu Photo",
        "url":"http:\/\/iurianu.rocks",
        "image":"http:\/\/iurianu.rocks\/wp-content\/uploads\/2019\/10\/ia.jpg",
        "width":"300",
        "height":"300"
      },
      "affiliation":{
        "@type":"Organization",
        "name":"kazenokodomo",
        "legalName":"S.C. Kazenokodomo S.R.L.",
        "url":"http:\/\/purls.site"
      },
      "worksFor":[
        {
          "@type":"Organization",
          "name":"upwork",
          "url":"https:\/\/upwork.com"
        },
        {
          "@type":"Organization",
          "name":"kazenokodomo",
          "legalName":"S.C. Kazenokodomo S.R.L.",
          "url":"http:\/\/purls.site"
        },
        {
          "@type":"Organization",
          "name":"Semantic SEO Solutions",
          "url":"http:\/\/semanticseosolutions.com"
        }
      ],
      "jobTitle":["Writer", "Singer", "Structured Data Expert", "Frontend Web Developer"],
      "birthDate":"1980-02-07",
      "birthPlace":{
        "@type":"Place",
        "name":"Iaşi, România"
      },
      "nationality":{
        "@type":"Country",
        "name":"România"
      },
      "homeLocation":{
        "@type":"AdministrativeArea",
        "name":"Iaşi, jud.Iaşi, România",
        "address":{
          "@type":"PostalAddress",
          "streetAddress":"93. Vasile Lupu Str.",
          "postalCode":"700319",
          "addressLocality":"Iaşi",
          "addressRegion":"Iaşi",
          "addressCountry":"România"
        }
      },
      "knowsLanguage":["Romanian", "English", "French"],
      "knowsAbout":["Reiki", "Feng Shui", "Wicca", "Solomonari", "Magick", "Divination"],
      "makesOffer":{
        "@type":"AggregateOffer",
        "name":"Semantic SEO",
        "itemOffered":[
          {
            "@type":"Service",
            "name":"Schema.org Rich Snippets"
          },{
            "@type":"Service",
            "name":"Full Schema.org Implementation"
          },{
            "@type":"Service",
            "name":"Custom RDF Ontology Creation"
          },{
            "@type":"Service",
            "name":"Structured Data Implementation"
          }
        ]
      }
    }
  </script>';

}

add_action('wp_head','sd_entity_schema');


function logo_markup() {
  global $post;

  $dom = new DOMDocument();
  libxml_use_internal_errors(true);

  $dom->loadHTML();
  $items = $dom->getElementsByTagName('img');
  $logo = $items[0];
  $logo->setAttribute('itemprop','image');

  return $dom->saveHTML();
}

add_action('header_image','logo_markup');

/* Create schema for blog posts */

function sd_post_metadata(){
  global $post;

    $sitename = get_bloginfo('name');
    $sitelink = get_site_url();
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    $sitelogo = esc_url( get_header_image() );
    $datePublished = get_the_date('Y-m-d\Th:ia');
    $dateModified = get_the_modified_time('Y-m-d\Th:ia');
    $postLink = get_permalink();
	$postImage = get_the_post_thumbnail_url(get_the_ID(),'full');
    $authorFirstName = get_the_author_meta( 'first_name' );
    $authorLastName = get_the_author_meta( 'last_name' );
    $authorNickName = get_the_author_meta(' nicename ');
    $authorLink = get_the_author_meta( 'user_url' );

  echo '<meta itemprop="datePublished" content="'. $datePublished .'">
        <meta itemprop="dateModified" content="'. $dateModified .'">
        <style>#header-logo img{display:none;}</style>
        <meta itemprop="sdDatePublished" content="'. $dateModified .'">
        <link itemprop="url" href="'. $postLink .'" />
        <link itemprop="mainEntityOfPage" href="'. $postLink .'content">
        <link itemprop="sdLicense" href="http://purls.site/terms-conditions/#structured-data"/>
		<img src="'. $postImage .'" itemprop="image" style="display: none;">
        <template>
          <section itemprop="publisher" itemscope itemtype="http://schema.org/Organization">
            <meta itemprop="name" content="'. $sitename .'"/><link itemprop="url" href="'. $sitelink .'">
            <figure itemprop="logo" itemscope itemtype="http://schema.org/ImageObject">
              <a itemprop="url" href="'. $sitelink .'">
                <img src="'. $sitelogo .'" itemprop="image"><meta itemprop="width" content="399"/><meta itemprop="height" content="90"/>
              </a>
            </figure>
          </section>
	      <section itemprop="sdPublisher" itemscope itemtype="http://schema.org/Person">
                <span itemprop="name"><em itemprop="givenName">Iulian</em> <em itemprop="familyName">Andriescu</em></span>
                <link itemprop="url" href="http://iurianu.rocks">
          </section>
          <section itemprop="author" itemscope itemtype="http://schema.org/Person">
            <span itemprop="name">
              <span itemprop="givenName">'. $authorFirstName .'</span>&nbsp;
              <span itemprop="familyName">'. $authorLastName .'</span>
            </span>
            <span itemprop="additionalName">'. $authorNickName .'</span>
            <link itemprop="url" href="'. $authorLink .'"/>
          </section>
        </template>';
}

add_action('wp_head', 'sd_post_metadata');
