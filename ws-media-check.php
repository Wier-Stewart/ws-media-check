<?php
/*
* Plugin Name: Media Library Check
* Plugin URI: http://www.wierstewart.com/
* Description:  Checks to see each Media Library item is locally available.
* Version: 1.0.0
* Author: W/S
* License: GPLv2
* */

$extensions = array(
  'gif',
  'jpg',
  'jpeg',
  'png'
  //pdf?
);

//include_once('parallel-curl.php');

class wsMediaCheck{

    private $aws_options;
    private $image_meta;

    public function __construct(){
      $this->add_actions();
      $this->aws_options = get_option('tantan_wordpress_s3');
    }

    private function add_actions(){
        add_filter( 'manage_upload_columns',            array($this, 'add_media_library_columns') );
        add_filter( 'manage_media_custom_column',        array($this, 'add_media_library_content'), 10, 2);
        add_filter( 'manage_upload_sortable_columns',    array($this, 'add_media_library_columns_sortable') );
        add_filter( 'wp_generate_attachment_metadata',    array($this, 'generate_media_check_metadata'), 10, 2);
        add_action( 'pre_get_posts',                    array($this, 'media_check_column_localsize_do_sort') );
        add_action( 'pre_get_posts',                    array($this, 'media_check_column_awssize_do_sort') );
        add_action( 'admin_print_styles-upload.php', array($this, 'media_check_column_size') );
        if( ! is_network_admin() ) add_action( 'admin_menu', array($this, 'media_check_tools_menu') );

        add_action('current_screen', function($current_screen){
          //$current_screen->base == 'upload';
          //$current_screen->post_type == 'attachment';
        });
    }



//////////////////////////////////////
//////////////////////////////////////


    function add_media_library_columns( $columns ) {
        $columns['filesize_local']  = __( 'Local File Exists', 'media-file-tools' );
        $columns['filesize_aws']  = __( 'File on AWS', 'media-file-tools' );
        return $columns;
    }
  /**
   * Adjust File Size column on Media Library page in WP admin
   */
  function media_check_column_size() {
    echo
    '<style>
    .manage-column.column-title,
    .wp-list-table.widefat td.sortable{
      width:30%;
    }

    .wp-list-table .manage-column.column-smushit{
      width:15%;
    }

    .wp-list-table .column-filesize_local,
    .wp-list-table .column-filesize_aws {
      width: 10%;
      text-align: center;
    }

    .wp-list-table td.column-filesize_local a span,
    .wp-list-table td.column-filesize_aws a span{
      font-size: 30px;
    }
    .wp-list-table .column-filesize_local a span.dashicons-yes,
    .wp-list-table .column-filesize_aws a span.dashicons-yes{
      color: green;
    }
    .wp-list-table .column-filesize_local a span.dashicons-no,
    .wp-list-table .column-filesize_aws a span.dashicons-no{
      color: red;
    }


    </style>';
  }

  /**
   * Core Content:
   *
   */
    function add_media_library_content( $column_name, $post_id ){
      $image_data = null;

            if( $column_name == 'filesize_local'){

          $image_data = json_decode( get_post_meta( $post_id, '_filesize_local', true ), true); //size_format
          if ( !$image_data ){
             $image_data = $this->generate_media_check_metadata( $image_data, $post_id );
          }

          if( $image_data['file_size'] > 0 ) echo "<a target='_blank' href='".$image_data['url']."'><span class='dashicons dashicons-yes'></span></a>";
          else  echo "<a target='_blank' href='".$image_data['url']."'><span class='dashicons dashicons-no'></span></a>";

        //TODO: add a URL if not found?
        unset($image_data);

      }//filesize-column

            if( $column_name == 'filesize_aws'){
        $aws_url = get_attached_file( $post_id );    //aws url will be returned if file not found
        if(stripos($aws_url, '//')===false) $aws_url = $this->build_aws_url($post_id);

        if( stripos($aws_url, '//')!==false ) echo "<a target='_blank' href='$aws_url'><span class='dashicons dashicons-yes'></span></a>";
        else  echo "<a target='_blank' href='$aws_url'><span class='dashicons dashicons-no'></span></a>";

      }//filesize-column

    }

  function generate_media_check_metadata( $image_data, $att_id ){
    //      $file  = get_attached_file( $att_id );    //aws url will be returned if file not found

    // Local data:
      $image_data['attachment'] = get_post_meta($att_id, "_wp_attachment_metadata");  //local filename
      $file = '';
      $url = '';
      if( array_key_exists('file',  $image_data['attachment'][0]) ){
          if(stripos($image_data['attachment'][0]['file'], 'http') === false ){
            $file =  wp_upload_dir()['basedir'] .'/'.  $image_data['attachment'][0]['file'];    //this should be the true local file
            $url =  wp_upload_dir()['baseurl'] .'/'.  $image_data['attachment'][0]['file'];    //this should be the true local file
          }
      }
      $image_data['file']=$file;
      $image_data['url']=$url;
      $image_data['file_size'] = 0;
      if(file_exists($file)) $image_data['file_size'] =  @filesize( $file );    //missing files = 4096 file size!

      $key='_filesize_local';
      if ( $image_data['file_size'] > 0 ){
        $image_data['status'] = '';
      }
      if( $file == '' ){
         $image_data['status']  = 'Could not determine local url';
      }else{
         $image_data['status'] = 'File Not Found';
      }
      unset($image_data['attachment']);
      update_post_meta( $att_id, $key, json_encode($image_data) );

/*
      // AWS data:
      $aws_urlbase = $this->aws_options['domain'];
      if( $aws_urlbase == 'cloudfront' ) $aws_urlbase = $this->aws_options['cloudfront'];
//      $aws_urlbase .= $this->aws_options['object-prefix'];

      $image_data['aws'] = get_post_meta($att_id, "amazonS3_info"); // See: ../amazon-s3-and-cloudfront/classes/as3cf-filter.php:8:    const CACHE_KEY = 'amazonS3_cache';
      $file = '';
      if( array_key_exists(0,  $image_data['aws'])   ){
      if( array_key_exists('key',  $image_data['aws'][0]) ){
          $file =  'https://'. $aws_urlbase .'/'. $image_data['aws'][0]['key'];    //this should be the true aws url
      }
      }

      $image_data['file_aws']=$file;
      $file_size = 0;


      $response = wp_remote_get( $file );
      print_r($response);
      if(!is_wp_error($response)) $file_size = wp_remote_retrieve_header( $response, 'content-length' );
      unset($response);

      if( $response ){
         $file_size =  filesize( $file );    //missing files = 4096 file size!
      }else{
        // file_exists($file);
      }
      $key='_filesize_aws';
      $value='';

      if ( $file !== '' ) $value = $file;
      if( count($image_data['aws']) == 0 ) $value = 'Could not determine AWS url! ';
//      else $value='File Not Found: '.$file;

      update_post_meta( $att_id, $key, $value );
      $image_data[$key]=$value;
      */

        return $image_data;
    }

  function build_aws_url($att_id){
    $aws_url='';
    $aws_urlbase = $this->aws_options['domain'];
    if( $aws_urlbase == 'cloudfront' ) $aws_urlbase = $this->aws_options['cloudfront'];

    $image_data['aws'] = get_post_meta($att_id, "amazonS3_info"); // See: ../amazon-s3-and-cloudfront/classes/as3cf-filter.php:8:    const CACHE_KEY = 'amazonS3_cache';

    if( array_key_exists(0,  $image_data['aws'])   ){
    if( array_key_exists('key',  $image_data['aws'][0]) ){
        $aws_url =  'https://'. $aws_urlbase .'/'. $image_data['aws'][0]['key'];    //this should be the true aws url
    }
    }
    return $aws_url;
  }

  function get_remote_sizes($urls){
/*
    $max_requests = 10;

    $curl_options = array(
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => FALSE
    );

    $parallel_curl = new ParallelCurl($max_requests, $curl_options);

    // Start 3 parallel requests. All three will be started simultaneously.
    foreach($urls as $i=>$url){
      $parallel_curl->startRequest($url, 'on_request_done');
    }

    $parallel_curl->finishAllRequests();
*/
  }

  /**
   * Add media-library page functions:
   *
   */

    function add_media_library_columns_sortable( $columns ){
        $columns['filesize_local'] = '_filesize_local';
        $columns['filesize_aws'] = '_filesize_aws';
        return $columns;
    }

    function media_check_column_localsize_do_sort(&$query){
        global $current_screen;
        if( is_object($current_screen) ){
            if( 'upload' != $current_screen->id ) return;
            $is_filesize = (isset( $_GET['orderby'] ) && '_filesize_local' == $_GET['orderby']);
            if( !$is_filesize ) return;
            if ( '_filesize_local' == $_GET['orderby'] ){
                $query->set('meta_key',    '_filesize_local');
                $query->set('orderby',    'meta_value_num');
            }
        }
    }
    function media_check_column_awssize_do_sort(&$query){
        global $current_screen;
        if( is_object($current_screen) ){
            if( 'upload' !== $current_screen->id ) return;
            $is_mimetype = (isset( $_GET['orderby'] ) && '_filesize_aws' == $_GET['orderby']);
            if( !$is_mimetype ) return;
            if ( '_filesize_aws' == $_GET['orderby'] ){
                $query->set('meta_key',    '_filesize_aws');
                $query->set('orderby',    'meta_value');
            }
        }
    }

  /**
   * Add an options-page:
   *
   */

    function media_check_tools_menu() {
        $size_media = add_media_page( 'File Sizes', 'File Sizes', 'activate_plugins', 'mediacheck_filesize', array($this,'media_check_options_page'));
    }


    function media_check_options_page(){
        global $wpdb;
        if ( !current_user_can('level_10') )
        die(__('Cheatin&#8217; uh?', 'media-file-tools' ));
        echo '<div class="wrap">';
        echo '<h2>' . __( 'File Size Options', 'media-file-tools' ) . '</h2>';

        $action = isset($_GET['action']) ? $_GET['action'] : 'default';
            switch ( $action ) {
        case "size":
        /*
                    $attachments = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment'" ); ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e( 'File',        'media-file-tools' ) ?></th>
                                <th><?php _e( 'Size',        'media-file-tools' ) ?></th>
                                <th><?php _e( 'MIME Type',    'media-file-tools' ) ?></th>
                                <th><?php _e( 'State',        'media-file-tools' ) ?></th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th><?php _e( 'File',        'media-file-tools' ) ?></th>
                                <th><?php _e( 'Size',        'media-file-tools' ) ?></th>
                                <th><?php _e( 'MIME Type',    'media-file-tools' ) ?></th>
                                <th><?php _e( 'State',        'media-file-tools' ) ?></th>
                            </tr>
                        </tfoot>
                        <tbody><?php
                            foreach( $attachments as $att ){
                                $att_id                = $att->ID;
                                $file                 = get_attached_file( $att_id );
                                $filename_only        = basename( get_attached_file( $att_id ) );
                                $mimetype            = get_post_mime_type( $att_id );
                                $file_size            = false;
                                $file_size            = filesize( $file );
                                $file_size_format    = size_format( $file_size );

                                if ( ! empty( $file_size ) ) {
                                    update_post_meta( $att_id, '_filesize', $file_size );
                                    update_post_meta( $att_id, '_filesmimetype', $mimetype ); ?>
                                    <tr>
                                        <td><?php echo $filename_only; ?></td>
                                        <td><?php echo $file_size_format; ?></td>
                                        <td><?php echo $mimetype; ?></td>
                                        <td>Done!</td>
                                    </tr><?php
                                } else {
                                    update_post_meta( $att_id, '_filesize', 'N/D' );
                                    update_post_meta( $att_id, '_filesmimetype', $mimetype ); ?>
                                    <tr>
                                        <td><?php echo $filename_only; ?></td>
                                        <td><?php echo 'Error'; ?></td>
                                        <td><?php echo $mimetype; ?></td>
                                        <td>Done!</td>
                                    </tr><?php
                                    }
                            } ?>
                        </tbody>
                    </table><?php
*/
                break;

                case 'single':
        $image_data=array();
        $image_id = isset($_GET['image_id']) ? $_GET['image_id'] : '-1';
        if($image_id){ $image_data = media_files_tools_metadata_generate( $image_data, $image_id );
          print_r($image_data);
        }
        break;

        case 'reset':
        $attachment_reset_result= $wpdb->get_results( "DELETE $wpdb->postmeta.* FROM $wpdb->postmeta WHERE meta_key = '_filesize' or meta_key = '_filesmimetype' or meta_key = '_filesize_local' or meta_key = '_filesize_aws' " );
//        print_R($attachment_reset_result);
//        $attachment_reset_result = ( $attachment_reset_result );
//        break;

        default:
          $attachment_size_count = $wpdb->get_results( "SELECT count(*) as `total` FROM $wpdb->postmeta WHERE meta_key = '_filesize_local' " );
          $sizecount = intval( $attachment_size_count[0]->total );
          ?>
          <p><?php echo $sizecount; ?> Media Library items have sizes calculated.</p>
          <p><?php _e( 'Reset all file sizes?', 'media-file-tools' ); ?></p>
          <p><a class="button" href="admin.php?page=mediacheck_filesize&action=reset"><?php _e( 'Reset All Files Sizes', 'media-file-tools' ); ?></a></p>
          <?php

                break;
            }
      /*
      ?>
        <p><?php _e( 'Update all files size can take a while.', 'media-file-tools' ); ?></p>
        <p><a class="button" href="admin.php?page=mediacheck_filesize&action=size"><?php _e( 'Get Files Size', 'media-file-tools' ); ?></a></p><?php
      */
        }


    //////////////////////////////////////
    //////////////////////////////////////


  }//class

  if( is_admin() ) $wsmc=new wsMediaCheck();
