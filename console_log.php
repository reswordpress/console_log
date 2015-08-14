<?php
/*
Plugin Name: Console Log
Description: store the var_dump results as a text file.
Version: 0.7
Author: Yuya Tajima
*/

if ( ! function_exists('console_log') ) {
  function console_log( $dump, $any_time = false, $ajax = true, $index = 3,  $echo = false ) {
    global $wp_did_header;

    if ( ! $any_time ) {
      if ( ! defined( 'ABSPATH' ) || ( isset( $wp_did_header ) && $wp_did_header )  || ( ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && is_admin() )  ) {
        if ( ! isset( $_GET['debug'] ) ) {
          return;
        }
      }
    }

    if ( ! isset( $dump ) ) {
      $dump = NULL;
    }

    if( ! $ajax && ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ){
      die(2);
    }

    $debug_log = '';

    if ( defined('WP_CONTENT_DIR') ) {

      $debug_path_file = WP_CONTENT_DIR . '/console_log.php';

      if ( ! file_exists( $debug_path_file ) ) {
        touch( $debug_path_file );
        chmod( $debug_path_file, 0666 );
        $str = <<<EOD
<?php
  return '/var/log/console.log';
EOD;
        file_put_contents( $debug_path_file, $str, LOCK_EX );
      }

      $debug_log = include( $debug_path_file );
    }

    if ( defined( 'CONSOLE_LOG_FILE' ) ) {
      $debug_log = CONSOLE_LOG_FILE;
    }

    if ( ! file_exists( $debug_log ) ) {
      echo $debug_log . ' does not exist.' . PHP_EOL;
      return;
    }

    if ( ! is_writable( $debug_log ) ) {
      echo $debug_log . ' is not writable. please change the file permission. or use another log file.' . PHP_EOL;
      return;
    }

    $file_size = filesize( $debug_log );
    $file_size = (int) floor( $file_size / 1024 ) ;

    // if the log file size over 10MB, stop this flow immediately.
    if ( $file_size > 10240 ) {
      $fp = fopen( $debug_log, 'w+b' );
      if ( is_resource( $fp ) ) {
        flock( $fp, LOCK_EX );
        fflush( $fp );
        flock( $fp, LOCK_UN );
        fclose( $fp );
      }
      return;
    }

    if( ! file_exists( $debug_log ) ){
        if ( touch( $debug_log ) ) {
            chmod( $debug_log, 0666 );
        } else {
            return;
        }
    }

    ob_start();
    echo '*********************************************' . PHP_EOL;
    _console_log_backtrace($index);
    if( defined( 'DOING_AJAX' ) && DOING_AJAX  ){
      echo 'This is Ajax! by WordPress.' . PHP_EOL . PHP_EOL;
      var_dump($_POST);
      echo PHP_EOL;
    }
    var_dump( $dump );
    echo PHP_EOL;
    echo '*********************************************' . PHP_EOL;

    $out = ob_get_contents();

    ob_end_clean();

    file_put_contents( $debug_log, $out, FILE_APPEND | LOCK_EX );

    //if headers have not already been sent and $echo is true
    //echo $dump
    if( $echo && ! headers_sent() ){
      echo nl2br(  htmlspecialchars( $out , ENT_QUOTES ) );
    }
  }

  function _console_log_backtrace( $index, $LF = PHP_EOL  ) {

    $debug_traces = debug_backtrace();

    if ( function_exists('date_i18n') ) {
      echo 'time              : ' . date_i18n( 'Y-m-d H:i:s' ) . $LF;
    } else {
      date_default_timezone_set( 'Asia/Tokyo' );
      echo 'time              : ' . date( 'Y-m-d H:i:s' ) . $LF;
    }
    echo 'using memory(MB)  : ' . round( memory_get_usage() / ( 1024 * 1024 ), 2 ) . ' MB' . $LF;
    echo $LF;

    var_dump( $_SERVER );

    for ( $i = 0 ; ( $_index = $index - $i ) > 0 ; $i++ )  {
      echo isset( $debug_traces[$_index]['file'] ) ? 'file_name : ' . $debug_traces[$_index]['file']. $LF : '';
      echo isset( $debug_traces[$_index]['line'] ) ? 'file_line : ' . $debug_traces[$_index]['line'] . $LF : '';
      echo isset( $debug_traces[$_index]['class'] ) ? 'class_name : ' . $debug_traces[$_index]['class'] . $LF : '';
      echo isset( $debug_traces[$_index]['function'] ) ? 'func_name : ' . $debug_traces[$_index]['function'] . $LF : '';
      if ( isset( $debug_traces[$_index]['args'] ) )  {
        $args = $debug_traces[$_index]['args'];
        if ( $args ){
          $arg_string = trim( _getStringFromNotString( $args ) );
          echo 'func_args : ' . $arg_string . $LF;
        }
      }
      echo $LF;
    }
  }

  function _getStringFromNotString ( $arg )
  {
    $string = '';
    if ( is_array( $arg ) ) {
      foreach ( $arg as $v ) {
        $string .= _getStringFromNotString( $v );
      }
    } elseif ( is_object( $arg ) ) {
      $string .= ' (class) ' . get_class( $arg ) ;
    } elseif ( is_bool( $arg ) ) {
      if ( $arg ) {
        $string .= ' true';
      } else {
        $string .= ' false';
      }
    } else {
      if ( $arg === '' ) {
        $string .=  ' \'empty string\'';
      } else {
        $string .=  ' '. mb_strimwidth( $arg, 0, 200, '...' );
      }
    }

    return $string;
  }
}
