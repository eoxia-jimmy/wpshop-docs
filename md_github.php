<?php
   /*
   Plugin Name: WPShop docs markdown
   Description: A plugin to inject markdown files directly into a post from Github
   Plugin URI:
   Version: 666.666
   Author: -
   Author URI:
   License: GPLv2
   */

function MDGH_atts_extract($atts) {
  extract(shortcode_atts(array(
          'url' => "",
          'token' => "",
        ), $atts
      )
   );
  return array($url, $token);
}

function MDGH_get_api_response($url, $token, $method) {

  $url_list = explode('/', $url);
  $owner = $url_list[3];
  $repo = $url_list[4];
  $branch = $url_list[6];
  $path = implode("/", array_slice($url_list, 7));

  $context_params = array(
    'http' => array(
      'method' => 'GET',
      'user_agent' => 'GIS-OPS.com',
      'timeout' => 1,
      'header' => "Accept: application/vnd.github.VERSION.html+json\r\n".
                  "Authorization: token ".$token."\r\n"
    )
  );

  if ($method == 'file') {
    //if we want to get the markdown file via md_github shortcode
    $request_url = 'https://api.github.com/repos/'.$owner.'/'.$repo.'/contents/'.$path.'?ref='.$branch;
    $res = file_get_contents($request_url, FALSE, stream_context_create($context_params));

    return $res;
  } else if ($method == 'all') {
    $request_url = 'https://api.github.com/repos/'.$owner.'/'.$repo.'/contents/pages/?ref='.$branch;
    $res = file_get_contents($request_url, FALSE, stream_context_create($context_params));
    $json = json_decode($res, true);
    
    $pages = array();
    $files = array();
    
     if ( ! empty( $json ) ) {
       foreach ( $json as $element ) {
         if ( $element['type'] == 'file' ) {
           $files[] = $element;
         } else {
           $request_url = 'https://api.github.com/repos/'.$owner.'/'.$repo.'/contents/pages/' . $element['name'] . '?ref='.$branch;
           $res = file_get_contents($request_url, FALSE, stream_context_create($context_params));
           $json_files = json_decode($res, true);
           
           if ( ! empty( $json_files ) ) {
             foreach ( $json_files as $json_file ) {
               if ( ! in_array( $json_file['name'], array( 'thumbnail.png' ,'order.json' ) ) ) {
                 if ( empty( $element['files'] ) ) {
                   $element['files'] = array();
                 }
                 
                 $element['files'][] = array(
                   'name' => str_replace( '.md', '', $json_file['name'] ),
                   'path' => str_replace( '.md', '', $json_file['path'] ),
                 );
               } else {
                 $element['thumbnail'] = $json_file['download_url'];
               }
             }
           }
           
           $pages[] = $element;
         }
       }
     }

    return array(
      'navigation' => $pages,
    );
  } else {
    //if we want to get the checkout html via checkout_github shortcode
    $res = file_get_contents($request_url, FALSE, stream_context_create($context_params));

    $json = json_decode($res, true);
    return $json;
  }

  return;
}

function MDGH_get_github_checkout($json, $url) {
  $datetime = $json['commit']['committer']['date'];

  $max_datetime = strtotime($datetime);
  $max_datetime_f = date('d/m/Y H:i:s', $max_datetime);

  $checkout_label = '<div class="markdown-github">
      <div class="markdown-github-labels">
        <label class="github-link">
          <a href="'.$url.'" target="_blank">Check it out on github</a>
          <label class="github-last-update"> Last updated: '.$max_datetime_f.'</label>
        </label>
      </div>
    </div>';

  return $checkout_label;
  }

function MDGH_md_github_handler($atts) {
 list($url, $token) = MDGH_atts_extract($atts);
 //get raw markdown from file URL
 $res = MDGH_get_api_response($url, $token, 'file');
 //send back text to replace shortcode in post
 return $res;
}

function MDGH_md_github_all_handler($atts) {
 list($url, $token) = MDGH_atts_extract($atts);
 //get raw markdown from file URL
  $query_var = get_query_var( 'path' );

  
  if ( ! empty( $query_var ) ) {
      $url .= '/' . $query_var;
  }

$res = MDGH_get_api_response($url, $token, 'all');
$res_content = MDGH_get_api_response($url . '.md', $token, 'file');

?>

   <?php
   if ( ! empty( $res['navigation'] ) && empty( $query_var ) ) {
     ?>
     <div class="wpshop-documentation">
       <?php
     foreach ( $res['navigation'] as $page ) {
       ?>
        <div class="navigation-category">

        <div class="navigation-category-header">
             <?php
             if ( ! empty( $page['thumbnail'] ) ) {
               ?>
               <img src="<?php echo $page['thumbnail']; ?>" />
               <?php
             }
             ?>

               <span class="navigation-category-title">
                    <?php echo $page['name']; ?>
               </span>
            </div>
            
             <ul class="navigation-files">
              <?php
              if ( ! empty( $page['files'] ) ) {
                  foreach ( $page['files'] as $element ) {
                      ?>
                      <li class="navigation-file"><a href="<?php echo home_url( 'documentation/' . $element['path'] ); ?>"><?php echo str_replace( '-', ' ', $element['name'] ); ?></a></li>
                      <?php
                  }
              }
             ?>
              </ul>
        </div>

	     <?php
     }
     ?>
    </div>
     <?php
   }
   ?>
	    <?php
	    if ( ! empty( $res_content ) ) {
      ?>
      <div class="wpshop-documentation-single">
        <ul class="wpshop-documentation-sidebar">
        <?php
		    foreach ( $res['navigation'] as $page ) :
          ?>
          <li class="sidebar-navigation-element">
            <span class="sidebar-navigation-title"><?php echo $page['name']; ?></span>
            <ul class="sidebar-navigation-child">
              <?php
              foreach( $page['files'] as $file ) :
                ?>
                <li class="sidebar-navigation-element <?php echo $file['path'] == $query_var ? 'sidebar-navigation-active' : ''; ?>">
                    <a class="sidebar-navigation-title" href="<?php echo home_url( 'documentation/' . $file['path'] ); ?>"><?php echo str_replace( '-', ' ', $file['name'] ); ?></a>
                </li>
                <?php
              endforeach; 
              ?>
            </ul>
          </li>
          <?php
		    endforeach;
		    ?>
      </ul>

      <div class="wpshop-documentation-content">
        <?php echo $res_content; ?>
      </div>
		    <?php
	    }
	    ?>
    </div>
   <?php
}


function MDGH_md_github_checkout($atts) {
 list($url, $token) = MDGH_atts_extract($atts);
 // query commit endpoint for latest update time
 $json = MDGH_get_api_response($url, $token, 'checkout');
 $last_update_htnl = MDGH_get_github_checkout($json, $url);

 return $last_update_htnl;
}

function MDGH_md_github_enqueue_style() {
	wp_enqueue_style( 'md_github', plugins_url( 'css/md-github.css', __FILE__ ));
}

function MDGH_md_github_init_endpoint() {
  add_rewrite_endpoint( 'documentation/(.*)', EP_ALL );

}
add_action( 'wp_enqueue_scripts', 'MDGH_md_github_enqueue_style' );
add_action( 'init', 'MDGH_md_github_init_endpoint' );
add_shortcode('checkout_github', "MDGH_md_github_checkout");
add_shortcode("md_github", "MDGH_md_github_handler");
add_shortcode("md_github_all", "MDGH_md_github_all_handler");

add_filter( 'generate_rewrite_rules', function ( $wp_rewrite ){
    $wp_rewrite->rules = array_merge(
        ['documentation/(.*)/?$' => 'index.php?page_id=8&path=$matches[1]'],
        $wp_rewrite->rules
    );
} );

add_filter( 'query_vars', function( $query_vars ){
    $query_vars[] = 'path';
    return $query_vars;
} );