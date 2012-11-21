<?php
/**
 * Plugin Name: Internal Link Manager
 * Plugin URI: http://www.kevinleary.net
 * Description: Easily manage internal links throughout your WordPress site
 * Version: 1.0
 * Author: Kevin Leary
 * Author URI: http://www.kevinleary.net
 *
 * Very loosely based on the Internal Link Builder plugin by sablab
 */

if ( !class_exists('Internal_Link_Manager') ):

class Internal_Link_Manager 
{
	/**
	 * Hooks
	 *
	 * Tap into WordPress API's with filters and actions
	 */
	function __construct( $options = array() ) {
		
		// Get metabox display option
		$metabox_option = get_option( __CLASS__ . '-metabox');
		if ( $metabox_option == 'Yes' ) {
			add_action( 'add_meta_boxes', array($this, 'meta_box') );
		}
	
		add_filter( 'the_content', array($this, 'autolink') );
		add_filter( 'comment_text', array($this, 'autolink') );
		add_action( 'admin_menu', array($this, 'admin_link') );
	}
	
	
	/**
	 * Autolink Keywords
	 */
	function autolink( $text, $is_content = false ) {
		global $post;
		
		// Get keywords list
		$keywords = get_option( __CLASS__ . '-keywords');
		if ( !is_array($keywords) ) 
	    	return $text;
		
		// Store replace $text in variable
		$html = NULL;
	    
	    // Get settings
	    $maxuse = get_option( __CLASS__ . '-maxuse');
	    $metabox_option = get_option( __CLASS__ . '-metabox');
		
		// Build REGEX array
		$anysign = '(.|\A|\Z|)';
		$permalink = get_permalink();
		foreach ( $keywords as $keyword => $url ) {
		
			// Don't link a keyword to the current page to avoid redundency
			if ( $permalink == strtolower($url) )
				return $text;
		
			// Add http:// if not entered
			if ( substr($url, 0, 7) != 'http://' )
				$url = 'http://' . $url;
			
			// Create REGEX find/replace
			$reg = '/'.$anysign.'('.$keyword.')'.$anysign.'/';
			$expr_from[] = $reg;
			$expr_to[] = '$1<a href="'.$url.'">$2</a>$3';
		}
		
		// Create links on strings not inside HTML tags
		$pieces = explode( '<', $text );
		
		// Run regex on each fragment
		$html = '';
		$count = 0;
		foreach( $pieces as $cont => $piece ) {
			
			// Replace chunks
			if ( strpos( $piece, '>' ) ) {
				$tmp = explode( '>', $piece );
				$html .= $tmp[0].'>';
				unset( $tmp[0] );
				foreach ( $tmp as $part ) {
					if ( $count >= $maxuse ) {
						$html .= $part;
					}
					else {
						$replace = preg_replace($expr_from, $expr_to, $part);
						$html .= $replace;
						
						// Keep track of the number of links generated
						if ( $replace != $part )
							$count++;
					}
				}
			} 
			else {
				if ( $count >= $maxuse ) {
					$html .= $piece;
				}
				else {
					$replace = preg_replace($expr_from, $expr_to, $piece);
					$html .= $replace;
					
					// Keep track of the number of links generated
					if ( $replace != $piece )
						$count++;
				}
			}
		
			if ( $cont+1 != count($pieces) ) {
				$html .= '<';
			}
		}
		
		return $html;
	}
	
	
	/**
	 * Admin Configuration
	 */
	function admin() {
	
		// Options
	    $selects = array(
	        'maxuse' => array(1,2,3,4,5),
	        'metabox' => array('Yes', 'No')
	    );    
	    
	    // Postdata handling
	    if ( isset($_POST['submitted']) ) {
	        $n = ceil( count( $_POST ) / 2 );
	        for ( $k = 0; $k < $n; $k++ ) {
	            if ( isset($_POST['key_'.$k]) && $_POST['key_'.$k] != '' ) {
	                $tmp[ esc_attr($_POST['key_'.$k]) ] = esc_url( $_POST['url_'.$k] );
	            }
	        }
	        update_option( __CLASS__ . '-keywords', $tmp);
	        foreach( $selects as $keyword => $url ) {
	        	if ( isset($_POST[$keyword]) )
	            	update_option( __CLASS__ . '-' . $keyword, esc_attr( $_POST[$keyword] ) );
	        }
	    }
	    
	    // Create select fields
	    foreach ( $selects as $field => $select ) {
	        $var_value = get_option( __CLASS__ . '-' . $field);
	        $sel[$field] = '<select name="'.$field.'">';
	        foreach( $select as $option ){
	            $selected = ( $option == $var_value ) ? ' selected="selected"' : '';
	            $sel[$field] .= '<option value="'.$option.'"'.$selected.'>'.ucwords($option).'</option>';
	        }
	        $sel[$field] .= '</select>';
	    }   
	    
	    // Get stored keywords
	    $keywords = get_option( __CLASS__ . '-keywords');
	    
	    $html = '
		<style type="text/css">
		.widefat tbody td {
			padding:6px 7px 7px;
			line-height:23px;
			vertical-align:center;
		}
		.widefat tbody .row-title {
			padding:7px 7px 6px;
		}
		.ilm h3 {
			margin:1.5em 0 1em;
		}
		.options {
			margin-top:-0.5em;
		}
		.options td {
			padding:5px 0;
		}
		.note {
			font-size:11px;
			color:#999;
		}
		</style>
	    <div class="wrap ilm">
	        <h2>Internal Link Manager</h2>
	        <form name="internal-link-manager" method="post">
	        
	        <h3>Options</h3>
	        <table class="options">
	            <tr>
	            	<td style="padding-right:20px;">Links Per Post</td>
	            	<td style="padding-right:20px;">'.$sel['maxuse'].'</td>
	            	<td><span class="note">Maximum number of links added to a given post</span></td>
	            </tr>
	            <tr>
	            	<td style="padding-right:20px;">Author Coaching</td>
	            	<td style="padding-right:20px;">'.$sel['metabox'].'</td>
	            	<td><span class="note">Add a metabox to the post "Edit" screen, coaching authors to use keywords in posts</span></td>
	            </tr>
	        </table>
	
	        <h3>Keywords</h3>
	        <p>In order to delete a key/url couple, you must empty the url field and then save</p>
	        <table class="wp-list-table widefat fixed posts" style="margin-bottom: 10px;">
	        <thead>
	        	<tr>
	        		<th class="column-name" style="width: 20px;">#</th>
	        		<th class="column-name" style="width: 250px;">Keyword</th>
	        		<th class="column-name">URL</th>
	            </tr>
	        </thead>
	        <tbody>';
		    $cont = 1;
		    if ( is_array($keywords) && count( $keywords ) > 0 ) {
		        foreach( $keywords as $key => $url ){
		            $html .= '<tr><td class="row-title">'.$cont.'</td>
		                <td><input type="text" name="key_'.$cont.'" value="'.$key.'" /></td>
		                <td><input type="text" name="url_'.$cont.'" value="'.$url.'" style="width:99%;" /></td></tr>';
		            $cont++;
		        }
		    }
		    $html .= '  
	            <tr>
	            	<td class="row-title">'.$cont.'</td>
	                <td><input type="text" name="key_'.$cont.'" value="" /></td>
	                <td><input type="text" name="url_'.$cont.'" value=""  style="width:99%;" /></td>
	            </tr>
	            </tbody>
	            </table>
	            <p>
	            	<input type="hidden" name="submitted" />
	            	<input type="submit" value="Save" class="button-primary action" />
	            </p>
	        <form>
	    </div>
	    ';
	    
	    echo $html;    
	}
	
	
	/**
	 * Admin Link
	 *
	 * Add a submenu link under "Tools > Internal Links"
	 */
	function admin_link() {
		add_submenu_page( 'tools.php', 'Internal Links', 'Internal Links', 'administrator', __CLASS__ . '-menu', array($this, 'admin') );
	}
	
	
	/**
	 * Keywords Metabox
	 *
	 * Add a metabox to posts that displays keywords added to the system
	 */
	function meta_box() {
	
		// Get all public post types
		$post_types = get_post_types( array(
			'publicly_queryable' => true
		), 'names' ); 
		unset( $post_types['attachment'] );

		foreach ( $post_types as $type ) {
			add_meta_box( __CLASS__, 'Optimized Keywords', array($this, 'meta_box_content'), $type, 'side', 'high' );
		}
	}
	
	function meta_box_content( $post ) {
	
		// Get stored keywords
	    $keywords = get_option( __CLASS__ . '-keywords');
		
		// Inline CSS
		$html = '<style type="text/css">';
		$html .= '.keywords-list li { line-height:16px; margin:0; font-size:11px; padding:3px 0; border-bottom:1px solid #eee; }';
		$html .= '.keywords-list img { vertical-align:text-bottom; margin:0 5px; }';
		$html .= '.keywords-list span { color:#999; }';
		$html .= '</style>';
		
		// Keywords list
		$html .= '<ul class="keywords-list">';
		foreach ( $keywords as $keyword => $url ) {
		
			// Check for keyword in current post
			$found_in_post = substr_count( $post->post_content, $keyword );
			$found = ( $found_in_post > 0 ) ? ' <img src="' . esc_url( admin_url( 'images/yes.png' ) ) . '" alt="Yes" />' : ' <img src="' . esc_url( admin_url( 'images/no.png' ) ) . '" alt="No" />';
			$found_in_post = ( $found_in_post > 0 ) ? " <span class='hint'>($found_in_post times)</span>" : '';
			$html .= '<li>' . $keyword . $found . $found_in_post . '</li>';
		}
		$html .= '</ul>';
		
		echo $html;
	}
} // end class Internal_Link_Manager()

// Instantiate class
$Internal_Link_Manager = new Internal_Link_Manager();

endif; // end class_exists()