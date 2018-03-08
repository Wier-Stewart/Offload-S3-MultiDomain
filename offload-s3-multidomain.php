<?php
/*
Plugin Name: WP Offload S3: MultiDomain
Plugin URI: https://github.com/Wier-Stewart/Offload-S3-MultiDomain
Description: Make sure Offload S3 works for all domains ever used to upload media into content.
Author: Wier/Stewart
Version: 0.1
Author URI: https://www.wierstewart.com
*/

/**
 * Offload S3 only uses the siteurl in the database.
 *
 * Problem Definition:
  1) Save a post on dev.domain.com with an newly uploaded image: it will add dev.domain.com/image
  2) Load site on preview.domain.com: S3 Offload will only look for preview.domain.com/image,
    skipping dev.domain.com/image.

 *
 * Fix:
 1) Save a list of all domains the site has been edited on.
 2) Alert S3 Offload about those domains via it's as3cf_local_domains filter.
 *
 */

// Step 1: For all domains which have ever saved a post, add it to an option.
add_action( 'save_post', 'domain_edited_content', 10, 3 );
function domain_edited_content( $post_ID, $post, $update ) {
//    $s3_options = get_option('tantan_wordpress_s3'); // make sure the plugin is installed..

    $wp_domain = get_option('siteurl');
    $this_domain = parse_url( $wp_domain , PHP_URL_HOST);

    $option_name = 'site_url_history';  //

    $domains = json_decode(get_option($option_name));
    if(!is_array($domains )) $domains = array();

    //Append
    $domains[] = $this_domain;
    $domains = array_filter(array_unique( $domains ));  //clean blank, null and dups

    //Save
    update_option( $option_name, json_encode($domains)  );
}

// Step 2: Add those domains to what S3 Offload knows to swap.
add_filter( 'as3cf_local_domains', function( $domains ){
    $option_name = 'site_url_history';  //

    $domain_history = json_decode(get_option($option_name));
    if( is_array($domain_history) ) $domains = array_merge($domains, $domain_history);

    $domain_alternates = get_alternate_domains( $domains );
    if( is_array($domain_alternates) ) $domains = array_merge($domains, $domain_alternates);

    return $domains;
}, 9);

// Helper: make sure it works for local, preview, dev, www, etc, just in case
function get_alternate_domains( $full_domains ){    //given www.domain.com and live.domain.com..

    $handled_subdomains = array('local.', 'dev.', 'preview.' ,'live.', 'admin.', 'www.' ); //All the ones we can expect

    //Knock off any known subdomains..
    $parent_domains = array_map( function( $domain ) use ($handled_subdomains) {
        return str_replace( $handled_subdomains , '', $domain);
    }, $full_domains );

    $parent_domains = array_unique($parent_domains);  // Should just be domain.com

    //match all known subdomains to all known domains.
    $alternate_domains = array();
    foreach($parent_domains as $i=>$domain){
        //match all known subdomains to this domain
        $these_domains = array_map( function( $subdomain ) use($domain) {
            return $subdomain.$domain;
        }, $handled_subdomains );

        $alternate_domains = array_merge($alternate_domains, $these_domains);
    }

    return $alternate_domains;  //local.domain.com, dev.domain.com, etc
}
