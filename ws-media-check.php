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
);

class wsMediaCheck{

    public function __construct(){
/*
      $meta['attachment'] = get_post_meta($post_id, "_wp_attachment_metadata");
      $meta['aws'] = get_post_meta($post_id, "amazonS3_info");
*/

      $this->add_actions();

    }

    private function add_actions(){
        add_filter( 'manage_upload_columns',			array($this, 'add_media_library_columns') );
        add_filter( 'manage_media_custom_column',		array($this, 'add_media_library_content'), 10, 2);
        add_filter( 'manage_upload_sortable_columns',	array($this, 'add_media_library_columns_sortable') );
        add_filter( 'wp_generate_attachment_metadata',	array($this, 'generate_media_check_metadata'), 10, 2);
        add_action( 'pre_get_posts',					array($this, 'media_check_column_size_do_sort') );
        add_action( 'pre_get_posts',					array($this, 'media_check_column_mime_do_sort') );
        add_action( 'admin_print_styles-upload.php', array($this, 'media_check_column_size') );
        if( ! is_network_admin() ) add_action( 'admin_menu', array($this, 'media_check_tools_menu') );
    }



//////////////////////////////////////
//////////////////////////////////////


	function add_media_library_columns( $columns ) {
    	$columns['filesize']  = __( 'File Size', 'media-file-tools' );
    	$columns['mimetype']  = __( 'MiME Type', 'media-file-tools' );
		return $columns;
	}
  /**
   * Adjust File Size column on Media Library page in WP admin
   */
  function media_check_column_size() {
    echo
    '<style>
      .wp-list-table .column-mimetype,
      .wp-list-table .column-filesize {
        width: 10%;
      }
    </style>';
  }

  /**
   * Core Content:
   *
   */
	function add_media_library_content( $column_name, $post_id ){
		$filesize = ( get_post_meta( $post_id, '_filesize', true ) ); //size_format
		$filemime = get_post_meta( $post_id, '_filesmimetype', true );

			if( $column_name == 'filesize'){
         if ( !$filesize ){
           $image_data=array();
           $image_data = generate_media_check_metadata( $image_data, $post_id );
           $filesize = $image_data['_filesize'];
/*
           ?>
      			<a href="<?php { echo esc_url( admin_url( add_query_arg( array( 'page' => 'mediacheck_filesize', 'image_id'=>$post_id, 'action'=>'single' ), 'upload.php' ) ) ); }  ?>"><?php _e( 'Generate Size', 'media-file-tools' ); ?></a>
      		<?php
*/
        }
        echo "".$filesize."";

      }//filesize-column

		if ( $filemime ){
			if( 'mimetype' == $column_name ) echo $filemime;
		}
	}

  function generate_media_check_metadata( $image_data, $att_id ){

      $key='_filesize';
      $file  = get_attached_file( $att_id );
      $file_size = max( 0 , @filesize( $file ));
      if ( ! empty( $file_mime_type ) ) $value= $file_size;
      else $value='File Not Found';
      update_post_meta( $att_id, $key, $value );
      $image_data[$key]=$value;

      $key='_filesmimetype';
      $file_mime_type = get_post_mime_type( $att_id );
      if ( ! empty( $file_mime_type ) ) $value= $file_mime_type;
      else $value='Unknown';
      update_post_meta( $att_id, $key, $value );
      $image_data[$key]=$value;

		return $image_data;
	}

  /**
   * Add media-library page functions:
   *
   */

	function add_media_library_columns_sortable( $columns ){
    	$columns['filesize'] = '_filesize';
    	$columns['mimetype'] = '_filesmimetype';
		return $columns;
	}

	function media_check_column_size_do_sort(&$query){
    	global $current_screen;

		if( 'upload' != $current_screen->id ) return;
		$is_filesize = (isset( $_GET['orderby'] ) && '_filesize' == $_GET['orderby']);
		if( !$is_filesize ) return;
		if ( '_filesize' == $_GET['orderby'] ){
        	$query->set('meta_key',	'_filesize');
			$query->set('orderby',	'meta_value_num');
    	}
	}
	function media_check_column_mime_do_sort(&$query){
    	global $current_screen;

		if( 'upload' != $current_screen->id ) return;
		$is_mimetype = (isset( $_GET['orderby'] ) && '_filesmimetype' == $_GET['orderby']);
		if( !$is_mimetype ) return;
		if ( '_filesmimetype' == $_GET['orderby'] ){
        	$query->set('meta_key',	'_filesmimetype');
			$query->set('orderby',	'meta_value');
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
								<th><?php _e( 'File',		'media-file-tools' ) ?></th>
								<th><?php _e( 'Size',		'media-file-tools' ) ?></th>
								<th><?php _e( 'MIME Type',	'media-file-tools' ) ?></th>
								<th><?php _e( 'State',		'media-file-tools' ) ?></th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<th><?php _e( 'File',		'media-file-tools' ) ?></th>
								<th><?php _e( 'Size',		'media-file-tools' ) ?></th>
								<th><?php _e( 'MIME Type',	'media-file-tools' ) ?></th>
								<th><?php _e( 'State',		'media-file-tools' ) ?></th>
							</tr>
						</tfoot>
						<tbody><?php
							foreach( $attachments as $att ){
								$att_id				= $att->ID;
								$file 				= get_attached_file( $att_id );
								$filename_only		= basename( get_attached_file( $att_id ) );
								$mimetype			= get_post_mime_type( $att_id );
								$file_size			= false;
								$file_size			= filesize( $file );
								$file_size_format	= size_format( $file_size );

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
        $attachment_reset_result= $wpdb->get_results( "DELETE $wpdb->postmeta.* FROM $wpdb->postmeta WHERE meta_key = '_filesize' or meta_key = '_filesmimetype' " );
//        print_R($attachment_reset_result);
//        $attachment_reset_result = ( $attachment_reset_result );
//        break;

        default:
          $attachment_size_count = $wpdb->get_results( "SELECT count(*) as `total` FROM $wpdb->postmeta WHERE meta_key = '_filesize' " );
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
