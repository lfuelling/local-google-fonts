<?php

namespace EverPress;

class LGF {

	private static $instance = null;

	private $seed = AUTH_SALT;
	private $upload_dir;

	private function __construct() {

		register_deactivation_hook( LGF_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_filter( 'style_loader_src', array( $this, 'switch_stylesheet_src' ), 10, 2 );
		add_filter( 'switch_theme', array( $this, 'clear' ), 10, 2 );

	}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new LGF();
		}

		return self::$instance;
	}



	public function process_url( $src, $handle ) {

		$id = md5( $src );
		if ( ! function_exists( 'download_url' ) ) {
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		$WP_Filesystem = $this->wp_filesystem();

		$style = "/* Font file served by Local Google Fonts Plugin */\n";

		$urls[ $id ] = array();

		$fontDisplay = 'fallback';

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';

		$new_src = $folder_url . '/' . $id . '/font.css';
		$new_dir = $folder . '/' . $id . '/font.css';

		$class    = LGF_Admin::get_instance();
		$fontinfo = $class->get_font_info( $src );

		foreach ( $fontinfo as $font ) {
			$filename = $font->id . '-' . $font->version . '-' . $font->defSubset;
			foreach ( $font->variants as $v ) {
				$file = $filename . '-' . $v->id;

				foreach ( array( 'woff', 'svg', 'woff2', 'ttf', 'eot' ) as $ext ) {

					if ( ! is_dir( $folder . '/' . $id ) ) {
						wp_mkdir_p( $folder . '/' . $id );
					}
					$tmp_file = download_url( $v->{$ext} );
					$filepath = $folder . '/' . $id . '/' . $file . '.' . $ext;
					$WP_Filesystem->copy( $tmp_file, $filepath );
					$WP_Filesystem->delete( $tmp_file );

				}

				$style .= "@font-face {\n";
				$style .= "\tfont-family: " . $v->fontFamily . ";\n";
				$style .= "\tfont-style: " . $v->fontStyle . ";\n";
				$style .= "\tfont-weight: " . $v->fontWeight . ";\n";
				$style .= "\tfont-display: " . $fontDisplay . ";\n";
				$style .= "\tsrc: url('" . $file . ".eot');\n";
				$style .= "\tsrc: local(''),\n";
				$style .= "\t     url('" . $file . ".eot?#iefix') format('embedded-opentype'),\n";
				$style .= "\t     url('" . $file . ".woff2') format('woff2'),\n";
				$style .= "\t     url('" . $file . ".woff') format('woff'),\n";
				$style .= "\t     url('" . $file . ".ttf') format('truetype'),\n";
				$style .= "\t     url('" . $file . '.svg#' . $v->id . "') format('svg');\n";
				$style .= "}\n\n";

			}
		}

		$WP_Filesystem->put_contents( $new_dir, $style );

		return $new_src !== $src;

	}


	public function google_to_local_url( $src, $handle ) {

		$id = md5( $src );

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';

		$new_dir = $folder . '/' . $id . '/font.css';

		if ( ! file_exists( $new_dir ) ) {

			$buffer = get_option( 'local_google_fonts_buffer' );
			if ( empty( $buffer ) ) {
				$buffer = array();
			}
			$buffer[ $handle ] = array(
				'id'  => $id,
				'src' => $src,
			);
			update_option( 'local_google_fonts_buffer', $buffer );

		} else {
			$src = add_query_arg( 'v', filemtime( $new_dir ), $folder_url . '/' . $id . '/font.css' );
		}

		return $src;
	}


	public function switch_stylesheet_src( $src, $handle ) {

		if ( false !== strpos( $src, '//fonts.googleapis.com/css' ) ) {
			if ( ! is_customize_preview() ) {
				$src = $this->google_to_local_url( $src, $handle );
			}
		}
		return $src;
	}


	public function clear() {
		$folder = WP_CONTENT_DIR . '/uploads/fonts';
		if ( is_dir( $folder ) ) {
			$WP_Filesystem = $this->wp_filesystem();
			$WP_Filesystem->delete( $folder, true );
		}
		delete_option( 'local_google_fonts_buffer' );

	}

	private function wp_filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( '\WP_Filesystem' ) ) {
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		\WP_Filesystem();

		return $wp_filesystem;

	}

	public function deactivate() {
		$this->clear();
	}

}