<?php

if( !class_exists( 'WP_List_Table' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'inc/class-wp-list-table.php' );
}

/**
 * Renders the mails in a table list.
 * @author No3x
 * @since 1.0
 */
class Email_Logging_ListTable extends WP_List_Table {
	
	/**
	 * Initializes the List Table
	 * @since 1.0
	 */
	function __construct() {
		global $status, $page, $hook_suffix;
		
		parent::__construct( array(
			'singular' 	=> __( 'Email', 'wml' ),//singular name of the listed records
			'plural' 	=> __( 'Emails', 'wml' ),//plural name of the listed records
			'ajax' 		=> false				//does this table support ajax?
		) );
	}	
	
	/**
	 * Is displayed if no item is available to render
	 * @since 1.0
	 * @see WP_List_Table::no_items()
	 */
	function no_items() {
		_e( 'No ' . $this->_args['singular'] . ' found.' );
		return;
	}
	
	/** 
	 * Defines the available columns.
	 * @since 1.0
	 * @see WP_List_Table::get_columns()
	 */
	function get_columns() {
		$columns = array(
		 	'cb'			=> '<input type="checkbox" />',
			'mail_id'		=> __( 'ID', 'wml' ),
			'timestamp'		=> __( 'Time', 'wml' ),
			'receiver'		=> __( 'Receiver', 'wml' ),
			'subject'		=> __( 'Subject', 'wml' ),
			'message'		=> __( 'Message', 'wml' ),
			'headers'		=> __( 'Headers', 'wml' ),
			'attachments'	=> __( 'Attachments', 'wml' ),
			'plugin_version'=> __( 'Plugin Version', 'wml' )
		);
		
		// give a plugin the change to edit the columns 
		$columns = apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS, $columns );
		
		$reserved = array( '_title', 'comment', 'media', 'name', 'title', 'username', 'blogname' );
		
		// show message for reserved column names
		foreach ( $reserved as $reserved_key ) {
			if( array_key_exists( $reserved_key, $columns ) ) {
				echo "You should avoid $reserved_key as keyname since it is treated by WordPress specially: Your table would still work, but you won't be able to show/hide the columns. You can prefix your columns!";
				break;
			}
		}
		
		return $columns;
	}
	
	/**
	 * Define which columns are hidden
	 * @since 1.0
	 * @return Array
	 */
	function get_hidden_columns() {
		return array( 
			'plugin_version', 
			'mail_id' 
		);
	}
	
	/**
	 * Prepares the items for rendering
	 * @since 1.0
	 * @param string you want to search for
	 * @see WP_List_Table::prepare_items()
	 */
	function prepare_items( $search = false ) {
		global $wpdb;
		$tableName = WPML_Plugin::getTablename( 'mails' );
		
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$this->process_bulk_action();
		
		$per_page = $this->get_items_per_page( 'per_page', 25 );
		$current_page = $this->get_pagenum();
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM `$tableName`;" );
		
		$orderby_default = "mail_id";
		$order_default = "desc";
		$orderby = ( !empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : $orderby_default;
		$order = ( !empty($_GET['order'] ) ) ? $_GET['order'] : $order_default;
		$offset = ( $current_page-1 ) * $per_page;
		
		$search_query = '';
		if( $search ) {
			$search = esc_sql( sanitize_text_field( $search ) );
			$search_query = sprintf( "
				WHERE 
				(`receiver` LIKE '%%%1\$s%%') OR  
				(`subject` LIKE '%%%1\$s%%') OR 
				(`message` LIKE '%%%1\$s%%') OR 
				(`headers` LIKE '%%%1\$s%%') OR 
				(`attachments` LIKE '%%%1\$s%%')", $search );
		}
		
		$dataset = $wpdb->get_results( "SELECT * FROM `$tableName` $search_query ORDER BY $orderby $order LIMIT $per_page OFFSET $offset;", ARRAY_A);
		
		$this->set_pagination_args( array(
			'total_items' => $total_items, // the total number of items
			'per_page'    => $per_page // number of items per page
		) );
		
		$this->items = $dataset;
	}
	
	/**
	 * Renders the cell. 
	 * Note: We can easily add filter for all columns if you want to / need to manipulate the content. (currently only additional column manipulation is supported)
	 * @since 1.0
	 * @param array $item
	 * @param string $column_name
	 * @return string The cell content
	 */
	function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'mail_id':
			case 'timestamp':
			case 'subject':
			case 'message':
			case 'headers':
			case 'attachments':
			case 'plugin_version':
				return $item[$column_name];
			case 'receiver':
				return esc_html( $item[ $column_name ] );	
			default:
				// if we don't know this column maybe a hook does - if no hook extracted data (string) out of the array we can avoid the output of 'Array()' (array)
				return (is_array( $res = apply_filters( WPML_Plugin::HOOK_LOGGING_COLUMNS_RENDER, $item, $column_name ) ) ) ? "" : $res;
		}
	}
	
	/**
	 * Renders the message column.
	 * @since 1.3
	 * @param object $item The current item
	 * @return void|string
	 */
	function column_message( $item ) {
		if( empty( $item['message'] ) ) return;
		$message = "<a class=\"wp-mail-logging-view-message button button-secondary\" href=\"#\" data-message=\"" . $this->render_mail( $item )  . "\">View</a>";
		return $message;
	}

    /**
     * Renders the timestamp column.
     * @since 1.5.0
     * @param object $item The current item
     * @return void|string
     */
    function column_timestamp( $item ) {
        return date_i18n( apply_filters('wpml_get_date_time_format', ''), strtotime( $item['timestamp'] ) );
    }
	
	/**
	 * Determines appropirate fa icon for a file
	 * @sine 1.3
	 * @param string $path
	 * @return Ambigous <boolean, string> Returns the most suitable icon or false if not possible.
	 */
	function determine_fa_icon( $path ) {
		$supported = array(
				'archive' => array (
						'application/zip',
						'application/x-rar-compressed',
						'application/x-rar',
						'application/x-gzip',
						'application/x-msdownload',
						'application/x-msdownload',
						'application/vnd.ms-cab-compressed'
				),
				'audio',
				'code' => array(
						'text/x-c',
						'text/x-c++'
				),
				'excel' => array( 'application/vnd.ms-excel'
				),
				'image', 'text', 'movie', 'pdf', 'photo', 'picture',
				'powerpoint' => array( 
						'application/vnd.ms-powerpoint'
				), 'sound', 'video', 'word' => array( 
						'application/msword'
				), 'zip'  
		);
		
		$mime = mime_content_type( $path );
		$mime_parts = explode( '/', $mime );
		$attribute = $mime_parts[0];
		$type = $mime_parts[1];
		
		$fa_icon = false;
		if( ($key = $this->recursive_array_search( $mime, $supported ) ) !== FALSE ) {
			// search for specific mime first
			$fa_icon = $key;
		} elseif( in_array( $attribute, $supported ) ) {
			// generic file icons
			$fa_icon = $attribute;
		}
		
		if( $fa_icon === FALSE ) {
			return '<i class="fa fa-file-o"></i>';
		} else {
			return '<i class="fa fa-file-' . $fa_icon . '-o"></i>';
		}
	}
	
	/**
	 * Find appropriate fa icon from file path
	 * @since 1.3
	 * @param string $attachment_path
	 * @return string
	 */
	function generate_attachment_icon( $path ) {
		return $this->determine_fa_icon( $path );
	}
	
	/**
	 * Multilevel array_search
	 * @since 1.3
	 * @see array_search()
	 */
	function recursive_array_search( $needle, $haystack ) {
		foreach( $haystack as $key => $value ) {
			$current_key = $key;
			if($needle === $value OR ( is_array($value) && $this->recursive_array_search( $needle, $value ) !== false ) ) {
				return $current_key;
			}
		}
		return false;
	}
	
	/**
	 * Renders the attachment column.
	 * @since 1.3
	 * @param object $item The current item
	 */
	function column_attachments( $item ) {
		$attachment_append = '';
		$attachments = explode( ',\n', $item['attachments'] );
		$attachments = is_array( $attachments ) ? $attachments : array( $attachments );
		foreach ( $attachments as $attachment ) { 
			// attachment can be an empty string ''
			if( !empty( $attachment ) ) {
				$filename = basename( $attachment );
				$attachment_path = WP_CONTENT_DIR . $attachment;
				$attachment_url = WP_CONTENT_URL . $attachment;
				
				if( is_file( $attachment_path ) ) { 
					$attachment_append .= '<a href="' . $attachment_url . '" title="' . $filename . '">' .$this->generate_attachment_icon( $attachment_path ) . '</a> ';
				} else {
					$message = sprintf( __( 'Attachment %s is not present', 'wpml' ), $filename);
					$attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
				}
			}
		}
		return $attachment_append;
	}
	
	/**
	 * Renders all components of the mail.
	 * @since 1.3
	 * @param object $item The current item
	 * @return string The mail as html
	 */
	function render_mail( $item ) {
		$mailAppend = '';
		foreach ( $item as $key => $value ) {
			if( key_exists( $key, $this->get_columns() ) && !in_array($key, $this->get_hidden_columns() ) ) {
				$display = $this->get_columns();
				$column_name = $key;
				$mailAppend .= "<span class=\"title\">{$display[$key]}: </span>";
				if ( $column_name != 'message' && method_exists( $this, 'column_' . $column_name ) ) {
					$mailAppend .= call_user_func( array( $this, 'column_' . $column_name ), $item );
				} else {
					$mailAppend .= $this->column_default( $item, $column_name );
				}
			}
		}
		return htmlentities( $mailAppend );
	}

	/**
	 * Defines available bulk actions.
	 * @since 1.0
	 * @see WP_List_Table::get_bulk_actions()
	 */
	function get_bulk_actions() {
		$actions = array(
			'delete'    => 'Delete'
		);
		return $actions;
	}
	
	function process_bulk_action() {
		global $wpdb;
		$name = $this->_args['singular'];
		$tableName = WPML_Plugin::getTablename( 'mails' );
		
		//Detect when a bulk action is being triggered...
		if( 'delete' == $this->current_action() ) {
			foreach( $_REQUEST[$name] as $item_id) {
				$wpdb->query( $wpdb->prepare("DELETE FROM `$tableName` WHERE `mail_id` = %d", $item_id) );
			}
		}
	}
	
	/**
	 * Render the cb column
	 * @since 1.0
	 * @param object $item The current item
	 * @return string the rendered cb cell content
	 */
	function column_cb($item) {
		$name = $this->_args['singular'];
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />', $name, $item['mail_id']
		);
	}
	
	/**
	 * Define the sortable columns
	 * @since 1.0
	 * @return Array
	 */
	function get_sortable_columns() {
		return array(
			// column_name => array( 'display_name', true[asc] | false[desc] )
			'mail_id'  		=> array( 'mail_id', false ),
			'timestamp' 	=> array( 'timestamp', true ),
			'receiver' 		=> array( 'receiver', true ),
			'subject' 		=> array( 'subject', true ),
			'message' 		=> array( 'message', true ),
			'headers' 		=> array( 'headers', true ),
			'attachments' 	=> array( 'attachments', true ),
			'plugin_version'=> array( 'plugin_version', true )
		);
	}
}

?>
