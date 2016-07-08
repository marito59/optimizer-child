<?php
if (class_exists('Woocommerce')) { 	
	function cma_init_woo_actions() {
		function go_hooks() {
			remove_action( 'wwoocommerce_before_subcategory_title', 'woocommerce_subcategory_thumbnail', 10);

            remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);

			add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_single_excerpt', 25);
            add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_product_description_tab', 20);
            add_action( 'woocommerce_shop_loop_item_title', 'gdb_add_category_description', 5);

 			remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);  
		}
		
		go_hooks();
			
	}
	add_action( 'wp', 'cma_init_woo_actions' , 10);
    
    function cma_woo_columns( $columns ){
        return 1;
    } // wptt_three_columns
    add_action( 'loop_shop_columns', 'cma_woo_columns' );
    
    
}

function theme_enqueue_styles() {

    $parent_style = 'parent-style';

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style )
    );
}
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );

/**
 * Setup My Child Theme's textdomain.
 *
 * Declare textdomain for this child theme.
 * Translations can be filed in the /languages/ directory.
 */
function my_child_theme_setup() {
    load_child_theme_textdomain( 'optimizer', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'my_child_theme_setup' );


function gdb_add_category_description() {
    //CMA
    global $product, $prev_term;
    $categ = $product->get_categories();
    $term = get_term_by ( 'name' , strip_tags($categ), 'product_cat' );


    if ($term->term_id == $prev_term) {
        // do nothing
    } else {
        echo sprintf ('<span id="cat-%s" class="category cat-%s">', $term->slug, $term->slug);
        echo sprintf ('<h2>%s</h2>', $term->name);
        echo sprintf ('<p class="cat_desc">%s</p>', $term->description);
        echo ('</span>');
        gdb_add_recettes( $term->slug, $term->name);

        $prev_term = $term->term_id;
        
    }
    // end CMA
}


global $contact_options;
$contact_options = array(
    'none' => ' -- choissez une valeur --',
    'bouche' => 'Bouche à oreille',
    'AMAP' => 'AMAP',
    'internet' => 'internet',
    'marche' => 'Marché',
    'client' => 'Autre client',
    'prospectus' => 'Prospectus',
    'publicite' => 'Publicité',
    'autre' => 'Autrement'        
);

/**
 * Add the field to the checkout
 */
add_action( 'woocommerce_after_order_notes', 'gdb_woo_order_notes' );
function gdb_woo_order_notes ($checkout) {
    global $contact_options;
    
    //echo '<div id="livraison_field"><h2>' . __('Lieu de livraison souhaité') . '</h2>';

    woocommerce_form_field( 'livraison', array(
        'type'          => 'text',
        'class'         => array('livraison-class form-row-wide'),
        'label'         => __('Lieu de livraison souhaité'),
        'placeholder'   => __('Lieu de livraison souhaité'),
        'required'      => true,
        ), $checkout->get_value( 'livraison' ));

   woocommerce_form_field( 'contact', array(
        'type'          => 'select',
        'class'         => array('contact-class form-row-wide'),
        'label'         => __('Comment nous avez-vous connu'),
        'options'       => $contact_options,
        ), $checkout->get_value( 'contact' ));

    //echo '</div>';
}

/**
 * Process the checkout
 */
add_action('woocommerce_checkout_process', 'gdb_woo_order_livraison_process');

function cma_woo_gdb_order_livraison_process() {
    // Check if set, if its not set add an error.
    if ( ! $_POST['livraison'] )
        wc_add_notice( __( 'Merci de saisir un lieu de livraison souhaité (un de nos points de vente, à domicile).' ), 'error' );
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'gdb_woo_order_update_order_meta' );

function gdb_woo_order_update_order_meta( $order_id ) {
    if ( ! empty( $_POST['livraison'] ) ) {
        update_post_meta( $order_id, 'livraison', sanitize_text_field( $_POST['livraison'] ) );
    }

    if ( ! empty( $_POST['contact'] ) ) {
        update_post_meta( $order_id, 'contact', sanitize_text_field( $_POST['contact'] ) );
    }
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'gdb_woo_order_display_admin_order_meta', 10, 1 );

function gdb_woo_order_display_admin_order_meta($order){
    global $contact_options;
    
    echo '<p><strong>'.__('Lieu de livraison').':</strong> ' . get_post_meta( $order->id, 'livraison', true ) . '</p>';
    echo '<p><strong>'.__('Contact').':</strong> ' .$contact_options[get_post_meta( $order->id, 'contact', true )] . '</p>';
}

/*
 * display all products in page
 */
add_filter( 'loop_shop_per_page', create_function( '$cols', 'return -1;' ), 20 );

add_filter( 'woocommerce_get_catalog_ordering_args', 'gdb_woo_get_catalog_ordering_args' );
function gdb_woo_get_catalog_ordering_args( $args ) {
    	$args['orderby'] = 'menu_order';
		$args['order'] = 'asc';
		$args['meta_key'] = '';

    return $args;
}

/*
 * Affiche les certificats Bio des producteurs qui sont des sous-pages de la page du producteur
 */

function gdb_certificats($parent_id) {
    $second_query = new WP_Query(array('post_parent'=>$parent_id, 'post_type'=>'page', 'posts_per_page'=>-1, 'order'=>'ASC', 'orderby'=>'menu_order'));
    echo '<ul>';
    // The Loop
    while( $second_query->have_posts() ) : $second_query->the_post();
        echo '<li><b>';
        the_title();
        echo ("</b><br />");
        //echo (get_post_meta(get_the_ID(), 'producteur_info', true));
        $args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => get_the_ID()
 	          , 'post_mime_type'=>array('application/pdf', 'application/msword', 'image/jpeg'), 'order'=>'ASC', 'orderby'=>'ID' ); 
        $attachments = get_posts($args);
        if ($attachments) {
	       echo('<ul>');
	           foreach ( $attachments as $attachment ) {
                    echo ('<li>');
                    //echo apply_filters( 'the_title' , $attachment->post_title );
                    //the_attachment_link( $attachment->ID , false );
                    //<a rel="alternate" href="http://www.goutdubio.fr/wp-content/uploads/2013/02/CHAMPVENT-certificat-2013.jpg" target="_blank">EARL Champvent � Certificat de conformit�&nbsp; de production biologique des produits commercialis�s</a>
                    
                    echo ('<a rel="alternate" href="');
                    echo wp_get_attachment_url($attachment->ID);
                    echo ('" target="blank">');
                    echo $attachment->post_title;
                    echo ('</a>');
                    echo ('</li>');
	           }
	       echo('</ul><br />');
        }
 
        echo '</li>';
    endwhile;
    echo '</ul>';
    //wp_reset_postdata() ;
    wp_reset_query();
}

/*
 * affiche les recettes associées à un produit de la boutique
 *     - dans le produit, on prend la catégorie
 *     - dans la recette, on prend le mot clé
 */
 
function gdb_add_recettes($categorie_slug, $category_name) {
    $args = array(
        'post_type' => 'post',
        'tax_query' => array(
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => $categorie_slug,
            ),
        ),
        'posts_per_page'=>-1, 
        'order'=>'ASC', 
        'orderby'=>'menu_order'
    );
    $second_query = new WP_Query($args);

    // The Loop
    $count = 0;
    echo ('<span class="recettes">');
    while( $second_query->have_posts() ) : $second_query->the_post();
        if ($count == 0) {
            echo ( '<h3>Les recettes</h3>');
            echo '<ul>';
        }
        echo ( '<li><b>' );
        echo ( '<a href="' . get_permalink() .'" rel="bookmark" title="lien vers "' . the_title_attribute( 'echo=0' ) . '">' );
        echo get_the_title(); 
        echo ( '</a>' );
        echo ( '</b><br /> '); 
        echo '</li>';
        $count++;
    endwhile;
    if ($count > 0) echo '</ul>';
    echo ('</span>');
    //wp_reset_postdata() ;
    wp_reset_query();
}

/**
 * Add wrappers to Display Posts Shortcode plugin
 * @author Christian
 * @link http://wordpress.org/extend/plugins/display-posts-shortcode/
 *
 * @param $output string, the original markup for an individual post
 * @param $atts array, all the attributes passed to the shortcode
 * @param $image string, the image part of the output
 * @param $title string, the title part of the output
 * @param $date string, the date part of the output
 * @param $excerpt string, the excerpt part of the output
 * @param $inner_wrapper string, what html element to wrap each post in (default is li)
 * @return $output string, the modified markup for an individual post
 */
 
add_filter( 'display_posts_shortcode_output', 'gdb_display_posts_wrappers', 10, 7 );
function gdb_display_posts_wrappers( $output, $atts, $image, $title, $date, $excerpt, $inner_wrapper ) {
	
    $image = '<span class="gdb-post-image">' . $image . '</span>';    
	$excerpt = '<span class="gdb-post-excerpt">' . $excerpt . '</span>';
    
	// Now let's rebuild the output. Only the excerpt changed so we're using the original $image, $title, and $date
	$output = '<' . $inner_wrapper . ' class="listing-item">' . $image . $title . $date . $excerpt . '</' . $inner_wrapper . '>';
	
	// Finally we'll return the modified output
	return $output;
}

add_filter( 'woocommerce_cart_item_thumbnail', 'gdb_cart_remove_thumbnail', 3, 3);
function gdb_cart_remove_thumbnail($image, $item, $key) {
    return "";
} 

add_filter( 'woocommerce_cart_item_permalink','gdb_cart_remove_permalink');

function gdb_cart_remove_permalink() {
    return "";
}
/*
 * Reveal Pages IDs in admin 
 * sample from @link https://premium.wpmudev.org/blog/display-wordpress-post-page-ids/
 */
add_filter( 'manage_posts_columns', 'revealid_add_id_column', 5 );
add_action( 'manage_posts_custom_column', 'revealid_id_column_content', 5, 2 );
add_filter( 'manage_pages_columns', 'revealid_add_id_column', 5, 2 );
add_action( 'manage_pages_custom_column', 'revealid_id_column_content', 5, 2 );
//add_filter( 'manage_media_columns', 'revealid_add_id_column' );
//add_action( 'manage_media_custom_column', 'revealid_id_column_content' );

function revealid_add_id_column( $columns ) {
   $columns['revealid_id'] = 'ID';
   return $columns;
}

function revealid_id_column_content( $column, $id ) {
  if( 'revealid_id' == $column ) {
    echo $id;
  }
}

/* Managing custom posts IDs
 * commented when not used
 
$custom_post_types = get_post_types( 
   array( 
      'public'   => true, 
      '_builtin' => false 
   ), 
   'names'
); 

foreach ( $custom_post_types as $post_type ) {
	add_action( 'manage_edit-'. $post_type . '_columns', 'revealid_add_id_column' );
	add_filter( 'manage_'. $post_type . '_custom_column', 'revealid_id_column_content' );
}
 */
/* Managing taxonomies too 
 *
$taxonomies = get_taxonomies();

foreach ( $taxonomies as $taxonomy ) {
	add_action( 'manage_edit-' . $taxonomy . '_columns', 'revealid_add_id_column' );
	add_filter( 'manage_' . $taxonomy . '_custom_column', 'revealid_id_column_content' );
}
 */
 
add_action( 'woocommerce_cart_collaterals', 'gdb_return_to_shop_button' );

function gdb_return_to_shop_button () {
    wc_get_template( 'cart/cart-return.php' );
}

function gdb_get_shop_url() {
	return  wc_get_page_permalink( 'shop' );
}

/*
 * copied from https://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts
 */
 
function exclude_category( $query ) {
    if ( $query->is_home() && $query->is_main_query() ) {
        $query->set( 'cat', '-6' );
    }
}
add_action( 'pre_get_posts', 'exclude_category' );


?>