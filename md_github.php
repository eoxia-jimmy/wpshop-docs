<?php
   /*
   Plugin Name: Github Docs in WP
   Description: Make Docs with Github and Markdown
   Plugin URI: https://jimmylatour.fr
   Version: 666.666
   Author: Laygen
   Author URI: https://jimmylatour.fr
   License: GPLv2
   */

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'md_github', plugins_url( 'css/md-github.css', __FILE__ ));
} );

add_action( 'init', function() {
    add_rewrite_endpoint( 'documentation/(.*)', EP_ALL );

} );

add_shortcode("md_github_doc", function( $atts ) {
    $url = $atts['url'];
    $token = $atts['token'];

    $query_var = get_query_var( 'path' );


    if ( ! empty( $query_var ) ) {
        $url .= '/' . $query_var;
    }

    $res_navigation = laygen_get_github_response($url, $token, 'all');

    ?>

    <?php
    if ( ! empty( $res_navigation['navigation'] ) && empty( $query_var ) ) {
        laygen_display_the_diable( $res_navigation );
    } else {
        $res_content = laygen_get_github_response($url . '.md', $token, 'file');
        laygen_display_the_sheep( $res_navigation, $res_content, $query_var );
    }
});

add_filter( 'generate_rewrite_rules', function ( $wp_rewrite ){
    $wp_rewrite->rules = array_merge(
        ['documentation/(.*)/?$' => 'index.php?page_id=48&path=$matches[1]'],
        $wp_rewrite->rules
    );
} );

add_filter( 'query_vars', function( $query_vars ){
    $query_vars[] = 'path';
    return $query_vars;
} );




function laygen_get_github_response($url, $token, $method) {
    $url_list = explode('/', $url);
    $owner = $url_list[3];
    $repo = $url_list[4];
    $branch = $url_list[6];
    $path = implode("/", array_slice($url_list, 7));

    $context_params = array(
        'http' => array(
            'method' => 'GET',
            'user_agent' => 'jimmylatour.fr',
            'timeout' => 1,
            'header' => "Accept: application/vnd.github.VERSION.html+json\r\n".
                "Authorization: token ".$token."\r\n"
        )
    );

    if ($method == 'dfile') {
        //if we want to get the markdown file via md_github shortcode
        $request_url = $url;
        return file_get_contents($request_url, FALSE, stream_context_create($context_params));
    }
    if ($method == 'file') {
        //if we want to get the markdown file via md_github shortcode
        $request_url = 'https://api.github.com/repos/'.$owner.'/'.$repo.'/contents/'.$path.'?ref='.$branch;
        return file_get_contents($request_url, FALSE, stream_context_create($context_params));
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
                                    $element['real_files'] = array();

                                }

                                $name = str_replace( '.md', '', $json_file['name'] );

                                $element['files'][ $name ] = array(
                                    'name' => ucfirst($name),
                                    'path' => str_replace( '.md', '', $json_file['path'] ),
                                    'info' => $json_file,
                                );
                            } else {
                                if ( $json_file['name'] == 'thumbnail.png' ) {
                                    $element['thumbnail'] = $json_file['download_url'];
                                }
                                else {
                                    $element['order'] = json_decode(laygen_get_github_response( $json_file['download_url'], $token, 'dfile' ) );
                                }
                            }
                        }
                    }

                    $pages[ $element['name'] ] = $element;
                }
            }
        }


        $request_url = 'https://raw.githubusercontent.com/'.$owner.'/'.$repo.'/master/pages/order.json';
        $res = file_get_contents($request_url, FALSE, stream_context_create($context_params));
        $big_order = json_decode($res, true);

        $real_pages_order = array();

        // Reorder.
        foreach ( $pages as &$page ) {
            if ( ! empty( $page['order'] ) ) {
                foreach( $page['order'] as $order) {
                    $page['real_files'][] = $page['files'][ $order ];
                    unset( $page['files'][ $order ] );
                }
            }

            $page['files'] = array_merge( $page['real_files'], $page['files'] );
        }

        foreach( $big_order as $order ) {
            $real_pages_order[ $order ] = $pages[ $order ];
        }

        return array(
            'navigation' => $real_pages_order,
        );
    }

    return;
}


function laygen_display_the_diable( $res ) {
    ?>
    <div class="wpshop-documentation">
        <?php
        foreach ( $res['navigation'] as $page ) :
            ?>
            <div class="navigation-category">

                <div class="navigation-category-header">
                    <?php
                    if ( ! empty( $page['thumbnail'] ) ) :
                        ?>
                        <img src="<?php echo $page['thumbnail']; ?>" />
                        <?php
                    endif;
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
        endforeach;
        ?>
    </div>
    <?php
}

function laygen_display_the_sheep( $res, $res_content, $query_var ) {
    $current_page = null;

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
                            if ($file['path'] == $query_var) :
                                $current_page = $file;
                            endif;
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

            <a href="<?php echo str_replace( 'blob', 'edit', $current_page['info']['html_url'] ); ?>">Modifier cette page sur GitHub</a>
        </div>

    </div>
    <?php
}