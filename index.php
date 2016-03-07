<?php
/*
 * Plugin Name: WordPress Download Manager Search
 * Version: 1.0.0
 * Description: This plugin extends WordPress Download Manager to allow searches to include attachments' text. As of now, only available for PDF attachments
 * Author: Nicholas Bruns (Brunzino)
 * Text Domain: wordpress-download-manager-search
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0
*/

// exec("pdftotext $filepath -", $output);


function wpdm_helper__populate_search_text($args) {
	$id = $args['id'];
	// pr($args);
	wpdm_populate_search_text($id);
	// wpdm_populate_all_search_text();
	return;
}

function wpdm_helper__populate_search_text_all($args) {
	if($args['bypass']) return;
	else wpdm_populate_all_search_text();
	return;
}


function wpdm_populate_all_search_text() {
	// error_reporting(E_ALL & ~E_NOTICE);

	global $wpdb;
	// Can't do where
	// $packages = get_posts(["post_type" => "wpdmpro", "numberposts" => 5, "meta_key" => "__wpdm_search_text", "meta_value" => ""]);
	// $packages = $wpdb->get_results("SELECT ID FROM wp_posts p WHERE post_type='wpdmpro' AND p.ID NOT IN (SELECT DISTINCT post_id FROM wp_postmeta WHERE meta_key = '__wpdm_search_text' AND meta_value != '')", OBJECT);
	$packages = $wpdb->get_results("SELECT ID FROM wp_posts p WHERE post_type='wpdmpro' ", OBJECT);
	
	foreach ( $packages as $package ) {
		// print_r($package);
		// var_dump($package);
		wpdm_populate_search_text($package->ID); // This should work, haven't tested yet.
		// break;
	}
	// error_log("Fullpath: $fullpath");
	// exec("pdftotext $fullpath -", $output);
	// print_r($output);
	// error_log("Text: ".serialize($output));
	// print_r("Writing info: ".serialize($info));

	// wpdm_populate_search_text($id, $info);
	return;
}



function wpdm_populate_search_text($id) {
	$info = wpdm_get_package($id);
	// print_r($info);
	
	echo "Title: {$info['post_title']}<br>ID: {$info['ID']}<br> Extension: {$info['file_ext']}<br>";

	$ext = $info['file_ext'];

	$filename = str_replace(" ", "\ ", current(get_package_data($id, "files")));
	$fullpath = UPLOAD_DIR.$filename;

	$output = "";
	switch(strtolower($ext)) {
		case "doc":
			$exec = "catdoc $fullpath";
			// exec("catdoc $fullpath", $output, $error);
			break; 		
		case "xls":
			$exec = "xls2csv $fullpath";
			break;
		case "ppt":
			$exec = "catppt $fullpath";
			break;
		default: 
		case "docx": 
			$exec = "docx2txt $fullpath -";
			// exec("docx2txt $fullpath -", $output, $error);
			break;
		case "pdf": 
			$exec = "pdftotext $fullpath -";
			// exec("pdftotext $fullpath -", $output, $error);
			break;
		case "rtf":
			$exec = "unrtf --text $fullpath";
			// exec("unrtf --text $fullpath", $output, $error);
			break;
			$exec = "";
			break;	
	}
	
	$error = 1;

	if($exec != "") {
		echo "$exec<br>";
		exec($exec, $output, $error);
	} else {
		echo "File type is not recognized as index-able";
		echo "<br>".$info['page_link']."<br>";
		return;
	}

	if($error != 0) {
		echo "ERROR:<br>";
		print_r($output);
		echo "Command: > $exec <br>";
		echo "<br>end of error";
		return;
	}	else {
		echo "Success!<br>";
		$text = wpdm_search_sanitize(implode($output));
		update_post_meta($id, "__wpdm_search_text", $text);
		echo "Search Text: $text<br>";
	}
	echo "----------------";
	return;
}


add_shortcode('wpdm_helper__populate_search_text', 'wpdm_helper__populate_search_text');
add_shortcode('wpdm_helper__populate_search_text_all', 'wpdm_helper__populate_search_text_all');


add_action("after_add_package", "wpdm_populate_search_text", 10, 3);

// add_post_meta($post_id, $meta_key, $meta_value);

function wpdm_search_sanitize($str) {
	// replace non-alpha-numerics, replace with space
	$str = preg_replace("/[^A-Za-z0-9]/", ' ', $str);
	// try to separate thingsLikeThis
	$str = preg_replace("/(?<=[^A-Z])([A-Z])/", ' $0', $str);
	return $str;
}


function search_meta_data_join($join) {
	global $wpdb;

    // Only join the post meta table if we are performing a search
    if ( empty ( get_query_var( 's' ) ) ) {
        return $join;
    }
                  
    // Join the post meta table
    $join .= " LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
         
    return $join;
}


function search_meta_data_where($where) {
    global $wpdb;
 
    // Only join the post meta table if we are performing a search
    if ( empty ( get_query_var( 's' ) ) ) {
            return $where;
        }
     
        // Only join the post meta table if we are on the Contacts Custom Post Type
    // if ( 'contact' != get_query_var( 'post_type' ) ) {
    //     return $where;
    // }
     
     error_log("\nINITIAL where: ".$where."\n\n");
    // Get the start of the query, which is ' AND ((', and the rest of the query
    $startOfQuery = substr( $where, 0, 6 );
    error_log("Start of query: $startOfQuery\n");
    $restOfQuery = substr( $where ,7 );
     
    // Inject our WHERE clause in between the start of the query and the rest of the query
    $where = $startOfQuery . 
            "(". $wpdb->postmeta . ".meta_key='__wpdm_search_text' AND " . $wpdb->postmeta . ".meta_value LIKE '%" . wpdm_search_sanitize(get_query_var( 's' )) . "%') OR (" . $restOfQuery .
            " GROUP BY " . $wpdb->posts . ".ID";

		error_log("\nAfter Adjustments: ".$where."\n\n");
		
		// echo "WHERE: $where";
    // Return revised WHERE clause
    return $where;
}

add_filter( 'posts_join', 'search_meta_data_join' );
add_filter( 'posts_where', 'search_meta_data_where' ); 

/*


function OLDwpdm_populate_search_text($post_id, $fileinfo = NULL, $filepath = "") {

	if($fileinfo == NULL) $fileinfo = 
	$filename = current($fileinfo["files"]);
	$fullpath = UPLOAD_DIR.$filename;
	error_log("First Fullpath: $fullpath");

	$filename = current(get_package_data($post_id, "files"));
	$fullpath = UPLOAD_DIR.$filename;
	error_log("Second Fullpath: $fullpath");

	$info = wpdm_get_package($post_id);
	print_r("Info: ".serialize($info));

	exec("pdftotext $fullpath -", $output);
	print_r($output);
	// error_log("Text: ".implode($output));

	// error_log("Serialize file info, post_id: {$post_id}, filepath: {$filepath}, fileinfo: ".serialize($fileinfo));
	//update_post_meta($post_id, "fileinfo", serialize($fileinfo)); // do not use this key!! (fileinfo)
  // echo "HERE IS AM ";
  return;

	print_r($options);
	$id = 610;
		$package = wpdm_get_package($id);
		print_r($package);

		// $ext = $package['file_ext'];

		echo "File info: ".get_package_data($id, "fileinfo")."</br>"; 
		echo "Package dir: ".get_package_data($id, 'package_dir')."</br>"; 


	$title = "NEMB_SNF.pdf";
	echo "hello";

}


AND 
(
 	( (wp_posts.post_title LIKE '%welcome%') OR (wp_posts.post_content LIKE '%welcome%') ) 
	AND 
	( (wp_posts.post_title LIKE '%tocare%') OR (wp_posts.post_content LIKE '%tocare%') ) 
	AND 
	( (wp_posts.post_title LIKE '%points%') OR (wp_posts.post_content LIKE '%points%') )
)  
AND 
	( wp_posts.post_password = '') 
AND 
	wp_posts.post_type IN ('post', 'page', 'attachment', 'wpdmpro') 
AND 
	(wp_posts.post_status = 'publish')




// AFTER 

AND 
( wp_postmeta.meta_value LIKE '%welcome tocare points%') OR (wp_posts.post_title LIKE '%welcome%') OR (wp_posts.post_content LIKE '%welcome%')) AND ((wp_posts.post_title LIKE '%tocare%') OR (wp_posts.post_content LIKE '%tocare%')) AND ((wp_posts.post_title LIKE '%points%') OR (wp_posts.post_content LIKE '%points%')))  AND (wp_posts.post_password = '')  AND wp_posts.post_type IN ('post', 'page', 'attachment', 'wpdmpro') AND (wp_posts.post_status = 'publish') GROUP BY wp_posts.id

AND 
(
 	( (wp_postmeta.meta_value LIKE '%welcome tocare points%' OR (wp_posts.post_title LIKE '%welcome%') OR (wp_posts.post_content LIKE '%welcome%')) AND ((wp_posts.post_title LIKE '%tocare%') OR (wp_posts.post_content LIKE '%tocare%')) AND ((wp_posts.post_title LIKE '%points%') OR (wp_posts.post_content LIKE '%points%')))  AND (wp_posts.post_password = '')  AND wp_posts.post_type IN ('post', 'page', 'attachment', 'wpdmpro') AND (wp_posts.post_status = 'publish')) GROUP BY wp_posts.id	
*/
?>
