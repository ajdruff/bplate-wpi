<?php

/**
 * BlueDog Support WordPress Installation Class
 *
 * Provides methods to aid in installing WordPress.
 *
 * @author Andrew Druffner <andrew@bluedogsupport.com>
 * @copyright  2016 BlueDog Support
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, 
 * @package BlueDogWPI
 * @filesource
 */
class bluedog_wpinstall {

    //helper objects
    private $_PHP_LIB = null;
    //trackers
    private $_WP_INCLUDED = null; //tracks whether the WordPress API libraries are included
    //config globals
    public $SITE_CONFIG = null; //contains all the variables in site-config.php
    public $PROFILE_CONFIG = null; //contains all the variables in profile-config.php 
    //messages
    private $_SUCCESS_MESSAGES = array(); //an array of success messages
    private $_LOG_MESSAGES = array(); //an array of log messages
    private $_WARNING_MESSAGES = array(); //an array of warning messages
    private $_ERROR_MESSAGES = array(); //an array of error messages
    //installation
    private $_INSTALL_ORDER = array(); //an array of the installation method names in the order they should be executed.
    //cache
    private $_WP_API_CORE = null;
    private $_WPQI_CACHE_PATH = null;
    private $_WPQI_CACHE_CORE_PATH = null;
    private $_WPQI_CACHE_PLUGINS_PATH = null;
    //front end helpers
    public $EDITABLE_PROPS = array(); //properties that are allowed to be editable on webconsole
    //configuration
    private $_RETRY = 0; //action retry.
    private $_RETRY_MAX = null; //action retry.
    //paths
    public $WP_DIRECTORY = null; //the full path to the WordPress install directory
    public $PROFILES_DIRECTORY = null;
    public $PROFILE_DIRECTORY = null;
    public $INSTALLER_DIRECTORY = null;
    public $SITE_CONFIG_FILE = null;
    public $PROFILE_CONFIG_FILE = null;
    private $_UPDATED_OPTIONS=array(); //keeps track of options that have already been updated

    function __construct() {

        $this->_config(); //setup class instance


        $this->_init(); //initialize class instance
        
   
    }

    /**
     * Configure 
     *
     * Configure class properties
     *
     * @param none
     * @return void
     */
    private function _config() {

        @set_time_limit( 0 );
        $this->_WP_API_CORE = 'http://api.wordpress.org/core/version-check/1.7/?locale=';
        $this->_WPQI_CACHE_PATH = 'cache/';
        $this->_WPQI_CACHE_CORE_PATH = $this->_WPQI_CACHE_PATH . 'core/';
        $this->_WPQI_CACHE_PLUGINS_PATH = $this->_WPQI_CACHE_PATH . 'plugins/';
        $this->_RETRY_MAX = 3; //number of retries on a failed ajax request
        $this->INSTALLER_DIRECTORY = dirname( __FILE__ ); //no trailing slash. The path to the installer script  
        $this->PROFILES_DIRECTORY = $this->INSTALLER_DIRECTORY . '/profiles';
        $this->SITE_CONFIG_FILE = $this->INSTALLER_DIRECTORY . '/site-config.php';

        //make a field editable by adding an identical property to editable props and setting its value to 1. 
        //e.g.$this->EDITABLE_PROPS['wp_options']['blogname'] =1, or $this->EDITABLE_PROPS['activate_plugins'] =1
        $this->EDITABLE_PROPS[ 'wp_options' ][ 'blogname' ] = true; //those config properties that will have editable form fields associated with them
        $this->EDITABLE_PROPS[ 'wp_options' ][ 'blogdescription' ] = true; 
        $this->EDITABLE_PROPS['reinstall'] =1;
        $this->EDITABLE_PROPS['wp_directory'] =1;
        $this->EDITABLE_PROPS['wpDownload'] =1;
        $this->EDITABLE_PROPS['wpConfig'] =1;
        $this->EDITABLE_PROPS['wpInstallCore'] =1;
        $this->EDITABLE_PROPS['wpInstallThemes'] =1;
        $this->EDITABLE_PROPS['wpInstallPlugins'] =1;
        $this->EDITABLE_PROPS['wpInstallThemes'] =1;
        
        #$this->EDITABLE_PROPS['wp_config']['AUTOSAVE_INTERVAL'] =1;
    }

    /**
     * Initialize
     *
     * Long Description
     *
     * @param none
     * @return void
     */
    private function _init() {


        if ( !$this->isCommandLine() ) { //set error handler when not in command line. setting an error handler within command line will cause loss of output after WP Library is included.
            set_error_handler( array( $this, 'errorHandler' ) );
        }




        if ( $this->isCommandLine() ) {


            ob_implicit_flush(); //required so echo output on command line is seen as it occurs instead of in blocks. also, it appears to be required so that you dont get errors when trying to create htaccess. see http://stackoverflow.com/questions/10273469/php-command-line-output-on-the-fly
        }






        require( 'inc/functions.php' );
        $this->_createCacheDirs();

        //define $this->SITE_CONFIG and $this->PROFILE_CONFIG
        $this->_setSiteConfig();


        $this->_setProfileConfig();

       


// Build the absolute path of the configured director. If not provided, use the parent directory of this file
        $this->WP_DIRECTORY = !empty( $this->SITE_CONFIG[ 'wp_directory' ] ) ? dirname( $this->INSTALLER_DIRECTORY ) . '/' . $this->SITE_CONFIG[ 'wp_directory' ] . '/' : dirname( $this->INSTALLER_DIRECTORY ) . '/';




        $this->_setInstallOrder();
    }

    /**
     * Create Cache Directories
     *
     * Creates the cache directories needed for downloading core and plugins
     *
     * @param none
     * @return void
     */
    private function _createCacheDirs() {
// Create cache directories
        if ( !is_dir( $this->_WPQI_CACHE_PATH ) ) {
            mkdir( $this->_WPQI_CACHE_PATH );
        }
        if ( !is_dir( $this->_WPQI_CACHE_CORE_PATH ) ) {
            mkdir( $this->_WPQI_CACHE_CORE_PATH );
        }
        if ( !is_dir( $this->_WPQI_CACHE_PLUGINS_PATH ) ) {
            mkdir( $this->_WPQI_CACHE_PLUGINS_PATH );
        }
    }

    /**
     * wpSuccessMessages
     *
     * Provides a success message and sets permalinks.
     * @param none
     * @return void
     */
    public function wpSuccessMessage() {


        /* -------------------------- */
        /* 	If we have a success we add the link to the admin and the website
          /*-------------------------- */



        $this->_LOG_MESSAGES[] = gettext( 'Finished Installing WordPress' );
#$this->_SUCCESS_MESSAGES[]='<a href="' . admin_url() . '" class="button" style="margin-right:5px;" target="_blank">' . _( 'Log In' ) . '</a>'; 
#$this->_SUCCESS_MESSAGES[]='<a href="' . home_url() . '" class="button" target="_blank">' . _( 'Go to website' ) . '</a>'; 
//   $this->_WARNING_MESSAGES[] = '<strong>' . _( 'Security Warning' ) . '</strong>: To keep your site secure: ' . _( 'Delete this     directory: <span class="well">' . $this->INSTALLER_DIRECTORY . '</span>' );





        $tags[ '{TITLE}' ] = gettext( 'Security Warning' );
        $tags[ '{POST_INSTALL_INSTRUCTIONS}' ] = gettext( "To protect your database password, delete the following file:" );
        $tags[ '{INSTALLER_DIRECTORY}' ] = $this->INSTALLER_DIRECTORY . '/site-config.php';


        $template = '<strong>{TITLE}</strong>{POST_INSTALL_INSTRUCTIONS}<code>{INSTALLER_DIRECTORY}</code>';
        if ( $this->isCommandLine() ) {

            $template = "\n#####################"
                    . "\n{TITLE}"
                    . "\n#####################"
                    . "\n{POST_INSTALL_INSTRUCTIONS}\n{INSTALLER_DIRECTORY}"
                    . "\n##################################################\n";
        }




        $this->_WARNING_MESSAGES[] = str_replace( array_keys( $tags ), array_values( $tags ), $template );







        $this->_LAST_ACTION = 'wpSuccessMessage';
        $this->_displayMessages();
    }

    /**
     * Update htaccess with permalinks
     *
     * Updates Permalink Structure Per Config file
     *
     * @param none
     * @return void
     */
    public function wpUpdateHtaccess() {



//include WordPress Libraries
        $this->_wpIncludeWP();

        if ( !$this->_WP_INCLUDED ) {

            return;
        }



        if ( !empty( $this->PROFILE_CONFIG[ 'wp_options' ][ 'permalink_structure' ] ) ) {

            file_put_contents( $this->WP_DIRECTORY . '.htaccess', null );
            flush_rewrite_rules();
        }

        //return a 200 status if requested over http, otherwise you'll get a 302 redirect
        if ( !$this->isCommandLine() ) {

            header( "Location: /", TRUE, 200 );
            die();
        }
    }

    /**
     * _wpCreateWPConfig
     * todo: re-write this entire mess so its simplified, that it doesn't use case statements but has indexes that match the define constant, so we can use the match as the index. we can then add the extra constants
     * todo: also, define all the constants in the default profile, clone them to an array that is reduced when one is found in the file. any extras, add them at the end.
     * Create WordPress Configuration File
     * @param string $content The shortcode content
     * @return string The parsed output of the form body tag
     */
    private function _wpCreateWPConfig( $environment ) {



        /* -------------------------- */
        /* 	Let's create the wp-config file
          /*-------------------------- */

// We retrieve each line as an array
        $wp_config = file( $this->WP_DIRECTORY . 'wp-config-sample.php' );

// Managing the security keys
        $secret_keys = explode( "\n", file_get_contents( 'https://api.wordpress.org/secret-key/1.1/salt/' ) );

        foreach ( $secret_keys as $k => $v ) {
            $secret_keys[ $k ] = substr( $v, 28, 64 );
        }

// We change the data
        $key = 0;
        foreach ( $wp_config as &$line ) {

            if ( '$table_prefix  =' == substr( $line, 0, 16 ) ) {
                $line = '$table_prefix  = \'' . sanit( $this->SITE_CONFIG[ 'wp_config' ][ 'table_prefix' ] ) . "';\r\n";
                continue;
            }

            if ( !preg_match( '/^define\(\'([A-Z_]+)\',([ ]+)/', $line, $match ) ) {
                continue;
            }

            $constant = $match[ 1 ];

            switch ( $constant ) {
                case 'WP_DEBUG' :

// Debug mod


                    $line = "\r\n\n " . "/** Capture PHP Errors */" . "\r\n";
                    $line .= "define('WP_DEBUG', " . (($this->PROFILE_CONFIG[ 'wp_config' ][ 'WP_DEBUG' ]) ? "true" : "false") . ");";

// Display error

                    $line .= "\r\n\n " . "/** Display Errors to Screen */" . "\r\n";

                    $line .= "define('WP_DEBUG_DISPLAY', " . (($this->PROFILE_CONFIG[ 'wp_config' ][ 'WP_DEBUG_DISPLAY' ]) ? "true" : "false") . ");";



// To write error in a log files

                    $line .= "\r\n\n " . "/** Enable Error Logging to File */" . "\r\n";

                    $line .= "define('WP_DEBUG_LOG', " . (($this->PROFILE_CONFIG[ 'wp_config' ][ 'WP_DEBUG_LOG' ]) ? "true" : "false") . ");";

// We add the extras constant
                    if ( !empty( $this->PROFILE_CONFIG[ 'wp_config' ][ 'media' ] ) ) {
                        $line .= "\r\n\n " . "/** Destination folder of files uploaded */" . "\r\n";
                        $line .= "define('media', '" . sanit( $this->PROFILE_CONFIG[ 'wp_config' ][ 'media' ] ) . "');";
                    }

                    if ( ( int ) $this->PROFILE_CONFIG[ 'wp_config' ][ 'WP_POST_REVISIONS' ] >= 0 ) {
                        $line .= "\r\n\n " . "/** Disables Post Revisions */" . "\r\n";
                        $line .= "define('WP_POST_REVISIONS', " . ( int ) $this->PROFILE_CONFIG[ 'wp_config' ][ 'WP_POST_REVISIONS' ] . ");";
                    }


                    $line .= "\r\n\n " . "/** Disables Theme Editor */" . "\r\n";
                    $line .= "define('DISALLOW_FILE_EDIT', " . (($this->PROFILE_CONFIG[ 'wp_config' ][ 'DISALLOW_FILE_EDIT' ]) ? "true" : "false") . ");";


                    if ( ( int ) $this->PROFILE_CONFIG[ 'wp_config' ][ 'AUTOSAVE_INTERVAL' ] >= 60 ) {
                        $line .= "\r\n\n " . "/** Automatic Save Interval */" . "\r\n";
                        $line .= "define('AUTOSAVE_INTERVAL', " . ( int ) $this->PROFILE_CONFIG[ 'wp_config' ][ 'AUTOSAVE_INTERVAL' ] . ");";
                    }

                    if ( !empty( $this->PROFILE_CONFIG[ 'wp_config' ][ 'WPCOM_API_KEY' ] ) ) {
                        $line .= "\r\n\n " . "/** WordPress.com API Key */" . "\r\n";
                        $line .= "define('WPCOM_API_KEY', '" . $this->PROFILE_CONFIG[ 'wp_config' ][ 'WPCOM_API_KEY' ] . "');";
                    }

                    $line .= "\r\n\n " . "/** Memory Limit */" . "\r\n";
                    $line .= "define('WP_MEMORY_LIMIT', '96M');" . "\r\n";

                    break;
                case 'DB_NAME' :
                    if ( $environment != 'dev' )
                        break;
                    $line = "define('DB_NAME', '" . sanit( $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] ) . "');\r\n";
                    break;
                case 'DB_USER' :
                    if ( $environment != 'dev' )
                        break;
                    $line = "define('DB_USER', '" . sanit( $this->SITE_CONFIG[ 'wp_config' ][ 'DB_USER' ] ) . "');\r\n";
                    break;
                case 'DB_PASSWORD' :
                    if ( $environment != 'dev' )
                        break;
                    $line = "define('DB_PASSWORD', '" . sanit( $this->SITE_CONFIG[ 'wp_config' ][ 'DB_PASSWORD' ] ) . "');\r\n";
                    break;
                case 'DB_HOST' :
                    if ( $environment != 'dev' )
                        break;
                    $line = "define('DB_HOST', '" . sanit( $this->SITE_CONFIG[ 'wp_config' ][ 'DB_HOST' ] ) . "');\r\n";
                    break;
                case 'SCRIPT_DEBUG':
                    if ( !empty( $this->PROFILE_CONFIG[ 'script_debug' ] ) ) {
                        $line .= "\r\n\n " . "/** SCRIPT_DEBUG = true for non-minified javascript */" . "\r\n\n\n";
                        $line .= "define('SCRIPT_DEBUG', " . sanit( $this->PROFILE_CONFIG[ 'script_debug' ] ) . ");\r\n";
                    }
                    break;
                case 'AUTH_KEY' :
                case 'SECURE_AUTH_KEY' :
                case 'LOGGED_IN_KEY' :
                case 'NONCE_KEY' :
                case 'AUTH_SALT' :
                case 'SECURE_AUTH_SALT' :
                case 'LOGGED_IN_SALT' :
                case 'NONCE_SALT' :
                    $line = "define('" . $constant . "', '" . $secret_keys[ $key++ ] . "');\r\n";
                    break;

                case 'WPLANG' :
                    $line = "define('WPLANG', '" . sanit( $this->PROFILE_CONFIG[ 'wp_config' ][ 'WPLANG' ] ) . "');\r\n";
                    break;
            }
        }
        unset( $line );


        switch ( $environment ) {

            case "dev":
                $config_directory = realpath( dirname( $this->WP_DIRECTORY ) ) . "/config";
                if ( !file_exists( $config_directory ) ) {

                    mkdir( $config_directory );
                }
                break;
            case "live":
                $config_directory_parent = realpath( dirname( $this->WP_DIRECTORY ) ) . "/_live";
                if ( !file_exists( $config_directory_parent ) ) {

                    mkdir( $config_directory_parent );
                    $config_directory = $config_directory_parent . "/config";
                    mkdir( $config_directory );
                }

                break;

            case "stage":
                $config_directory_parent = realpath( dirname( $this->WP_DIRECTORY ) ) . "/_stage";
                if ( !file_exists( $config_directory_parent ) ) {

                    mkdir( $config_directory_parent );
                    $config_directory = $config_directory_parent . "/config";
                    mkdir( $config_directory );
                }
                break;
        }




#write out the configuration file if the directories exist
        if ( isset( $config_directory ) && file_exists( $config_directory ) ) {


            $handle = fopen( $config_directory . '/wp-config.php', 'w' );
            foreach ( $wp_config as $line ) {
                fwrite( $handle, $line );
            }
            fclose( $handle );

// We set the good rights to the wp-config file
            chmod( $config_directory . '/wp-config.php', 0666 );
        }

//make config directory
    }

    /**
     * _wpCreateWPConfigHome
     *
     * Adds a wp_config file to the WordPress home directory. This file will include the wp-config file contained in the config directory above the webroot.
     * @param none
     * @return void
     */
    private function _wpCreateWPConfigHome() {

        $contents = "<?php
	

	if ( !defined('ABSPATH') )
	define('ABSPATH', (dirname(__FILE__)) );

//include ('../config/wp-config.php');
include realpath(dirname(__FILE__). '/../config/wp-config.php')  ;

?>";


        file_put_contents( $this->WP_DIRECTORY . '/wp-config.php', $contents );
    }

    /**
     * display_messages
     *
     * Display Messges
     * @param array $messages An array of messages
     * @return void
     */
    private function _displayMessages() {

        $line_ending = '<br>';
        $error_messages_string = '';
        $success_messages_string = '';
        $warning_messages_string = '';
        $log_messages_string = '';

#if called from a commmand line, output with a return character
        if ( $this->isCommandLine() ) {
            $line_ending = "\n";



            $success_messages_string = strip_tags( implode( $this->_SUCCESS_MESSAGES, $line_ending ) );
            $log_messages_string = strip_tags( implode( $this->_LOG_MESSAGES, $line_ending ) );


            $error_messages = '';
            if ( !empty( $this->_ERROR_MESSAGES ) ) {
                $error_messages_string = strip_tags( "ERRORS:" . implode( $this->_ERROR_MESSAGES, $line_ending ) );
            }
            $warning_messages_string = strip_tags( implode( $this->_WARNING_MESSAGES, $line_ending ) );

            echo $log_messages_string
            . $warning_messages_string
            . $success_messages_string . $line_ending;


//exit if there were any errors at all
            if ( !empty( $this->_ERROR_MESSAGES ) ) {

                die( $error_messages_string . $line_ending );
            }


            /*
             * Must clear messages or you'll get duplicates
             */
            $this->_LOG_MESSAGES = array();
            $this->_ERROR_MESSAGES = array();
            $this->_SUCCESS_MESSAGES = array();
            $this->_WARNING_MESSAGES = array();
        }

#otherwise, output json so javascript can read it.
        else
        if ( $this->phpLib()->isAjax() ) {
            $json[ 'retry' ] = $this->_RETRY;
            $json[ 'log_messages' ] = $this->_LOG_MESSAGES;
            $json[ 'success_messages' ] = $this->_SUCCESS_MESSAGES;
            $json[ 'error_messages' ] = $this->_ERROR_MESSAGES;
            $json[ 'warning_messages' ] = $this->_WARNING_MESSAGES;
            if ( !empty( $this->_ERROR_MESSAGES ) ) { //need to tell front end we're done if there was an error.
                $this->_LAST_ACTION = 'completed';
            }

            $json[ 'last_action' ] = $this->_LAST_ACTION;
            $messages_string = json_encode( $json );
            echo $messages_string;
            die();
        } else {



            echo $this->_getMessagesHTML(
                    $this->_LOG_MESSAGES, //$messages, 
                    'info'//$type
            );

            echo $this->_getMessagesHTML(
                    $this->_ERROR_MESSAGES, //$messages, 
                    'danger'//$type
            );


            echo $this->_getMessagesHTML(
                    $this->_WARNING_MESSAGES, //$messages, 
                    'warning'//$type
            );

            echo $this->_getMessagesHTML(
                    $this->_SUCCESS_MESSAGES, //$messages, 
                    'success'//$type
            );
        }
    }

    /**
     * wpCheck
     *
     * Checks database connection, WordPress previous installation,etc.
     *
     * @param boolean $dbconnect True to check that database connection works (returns error on connection failure)
     * @param boolean $dbempty True to check if database is empty (returns error if it isn't)
     * @param boolean $files_overwrite True to check if files were downloaded but are in danger of being overwritten  (returns error if files exist)
     * @param boolean $files_available True to check if files are available for including (returns error if files don't exist)
     * @return void
     */
    public function wpCheck( $dbconnect, $dbempty, $files_overwrite, $files_available ) {

        $db = null;
        /* -------------------------- */
        /* 	We verify if we can connect to DB or WP is not installed yet
          /*-------------------------- */

// DB Test
        if ( $dbconnect ) {

            $this->_getDbConnection(); //attempts to connect to a database and throws an error if it can't.
        }


       
// WordPress Downloaded but in danger of being overwritten?
        if ( $files_overwrite && file_exists( $this->WP_DIRECTORY . 'wp-includes' ) && $this->SITE_CONFIG[ 'reinstall' ] !== true ) {




            $this->_ERROR_MESSAGES[] = gettext( "Cannot download and over-write an existing WordPress Installation. To fix this issue, specify a different install directory, or delete the contents of the existing directory, or set `wpDownload` to false to skip download." );
            $this->_displayMessages();
            die();
        }
// WordPress Available?
        if ( $files_available && !file_exists( $this->WP_DIRECTORY . 'wp-includes' ) ) {
            $this->_ERROR_MESSAGES[] = gettext( "WordPress has not been downloaded yet into the configured directory set by `\$site[ 'wp_directory' ]` (" . $this->SITE_CONFIG[ 'wp_directory' ] . ") Please download WordPress by editing `site-config.php` and setting `\$site[ 'wpDownload' ]=true` or set `\$site[ 'wp_directory' ] to a directory that contains the downloaded WordPress files." );
            $this->_displayMessages();
            die();
        }

// Database Empty?
// Don't use $wpdb to check or you'll run into redirect errors when including wp library.
        if ( $dbempty ) {

//if not empty and reinstall is true, delete the database.

            if ( !$this->_isDBEmpty() && $this->SITE_CONFIG[ 'reinstall' ] ) {

                $this->_deleteDB();
            }

            if ( !$this->_isDBEmpty() ) {




                $this->_ERROR_MESSAGES[] = gettext( 'WordPress database `' ) . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] . gettext( "` is not empty. Either edit site-config.php and set \$site[ 'wpInstallCore' ] to false, empty the existing database `" ) . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] . gettext( "`, or edit site-config.php and set `\$site[ 'wp_config' ][ 'DB_NAME' ]` to a different empty database to use for installation." );
                $this->_displayMessages();
            }
        }
    }

    /**
     * wpDownload
     *
     * Downloads WordPress core to cache
     *
     * @param none
     * @return void
     */
    public function wpDownload() {

        if ( !$this->SITE_CONFIG[ 'wpDownload' ] ) {
            $this->_LOG_MESSAGES[] = gettext( 'Skipped Download' );
            $this->_LAST_ACTION = 'wpDownload';
            $this->_displayMessages();
            return;
        }

        $this->wpCheck(
                false, //$dbconnect #check connection
                false, //$dbempty   #check whether database is empty
                false, //$files_overwrite #check whether download will overwrite existing files
                false//$files_available  #check whether downloaded files are available for installation
        );





// Get WordPress language
        $language = substr( $this->PROFILE_CONFIG[ 'wp_config' ][ 'WPLANG' ], 0, 6 );

// Get WordPress data
        $wp = json_decode( file_get_contents( $this->_WP_API_CORE . $language ) )->offers[ 0 ];

        /* -------------------------- */
        /* 	We download the latest version of WordPress
          /*-------------------------- */

        if ( !file_exists( $this->_WPQI_CACHE_CORE_PATH . 'wordpress-' . $wp->version . '-' . $language . '.zip' ) ) {
            file_put_contents( $this->_WPQI_CACHE_CORE_PATH . 'wordpress-' . $wp->version . '-' . $language . '.zip', file_get_contents( $wp->download ) );
        }




        $this->_LOG_MESSAGES[] = gettext( 'Downloaded Latest WordPress Version, ' ) . $wp->version;
        $this->_LAST_ACTION = 'wpDownload';
        $this->_displayMessages();
    }

    /**
     * Unzip Downloaded WordPress Files into Install Directory
     *
     * Unzip WordPress Core after its been downloaded
     *
     * @param none
     * @return void
     */
    public function wpUnzip() {

//unzip is really part of download, so its switched only if wpDownload is.
        if ( !$this->SITE_CONFIG[ 'wpDownload' ] ) {
            $this->_LAST_ACTION = 'wpUnzip';

            $this->_displayMessages();
            return;
        }

        $this->wpCheck(
                false, //$dbconnect #check connection
                false, //$dbempty   #check whether database is empty
                true, //$files_overwrite #check whether unzip will overwrite existing files
                false//$files_available  #check whether downloaded files are available for installation
        );


// Get WordPress language
        $language = substr( $this->PROFILE_CONFIG[ 'wp_config' ][ 'WPLANG' ], 0, 6 );

// Get WordPress data
        $wp = json_decode( file_get_contents( $this->_WP_API_CORE . $language ) )->offers[ 0 ];

        /* -------------------------- */
        /* 	We create the website folder with the files and the WordPress folder
          /*-------------------------- */

// If we want to put WordP&& !file_exists($this->WP_DIRECTORY)ress in a subfolder we create it
        if ( !empty( $this->WP_DIRECTORY ) && !file_exists( $this->WP_DIRECTORY ) ) {
// Let's create the folder



            mkdir( $this->WP_DIRECTORY );

// We set the good writing rights
            chmod( $this->WP_DIRECTORY, 0755 );
        }

        $zip = new ZipArchive;

// We verify if we can use the archive
        if ( $zip->open( $this->_WPQI_CACHE_CORE_PATH . 'wordpress-' . $wp->version . '-' . $language . '.zip' ) === true ) {

// Let's unzip
            $zip->extractTo( '.' );
            $zip->close();

// We scan the folder
            $files = scandir( 'wordpress' );

// We remove the "." and ".." from the current folder and its parent
            $files = array_diff( $files, array( '.', '..' ) );

// We move the files and folders
            foreach ( $files as $file ) {

//check if file object exists first, if it does, delete the destination file or directory. This is normal behavior for rename but it doesn't always work on windows systems so we need to do an explicit delete. we use the phpLib->rmDir because it deletes both files and directories.

                if ( file_exists( $this->WP_DIRECTORY . '/' . $file ) ) {


                    $this->phpLib()->rmDir( $this->WP_DIRECTORY . '/' . $file );
                }
                rename( 'wordpress/' . $file, $this->WP_DIRECTORY . '/' . $file );
            }

            rmdir( 'wordpress' ); // We remove WordPress temporary folder used for extraction
        }

        $reinstall_message = '';

        if ( $this->SITE_CONFIG[ 'reinstall' ] == true ) {

            $reinstall_message = gettext( ',overwrote old files (reinstall option set to true)' );
        }
        $this->_RETRY = 0; //reset retry 
        $this->_LOG_MESSAGES[] = gettext( 'Extracted WordPress to installation directory' ) . $reinstall_message;
        $this->_LAST_ACTION = 'wpUnzip';
        $this->_displayMessages();
    }

    /**
     * Remove File Cruft
     *
     * Removes Unnecessary Files from Install Directory
     *
     * @param none
     * @return void
     */
    public function _wpRemoveFileCruft() {
        if ( !$this->PROFILE_CONFIG[ 'remove_file_cruft' ] ) {
            return;
        }

        $this->phpLib()->rmFile( $this->WP_DIRECTORY . '/license.txt' ); // We remove licence.txt
        $this->phpLib()->rmFile( $this->WP_DIRECTORY . '/readme.html' ); // We remove readme.html

        $this->_LOG_MESSAGES[] = gettext( 'Removed unnecessary files from installation directory' );
    }

    /**
     * wpConfig
     *
     * Creates the wpConfig files  and adds the configuration information from config.php
     *
     * @param none
     * @return void
     */
    public function wpConfig() {

        if ( !$this->SITE_CONFIG[ 'wpConfig' ] ) {

            $this->_LOG_MESSAGES[] = gettext( 'Skipped WordPress Configuration' );
            $this->_LAST_ACTION = 'wpConfig';
            $this->_displayMessages();


            return;
        }

//check that files are available to configure
        $this->wpCheck(
                false, //$dbconnect #check connection
                false, //$dbempty   #check whether database is empty
                false, //$files_overwrite #check whether download will overwrite existing files
                true//$files_available  #check whether downloaded files are available for installation
        );



        $this->_wpCreateWPConfig( 'dev' );
        $this->_wpCreateWPConfig( 'live' );
        $this->_wpCreateWPConfig( 'stage' );
        $this->_wpCreateWPConfigHome();

        $this->_LOG_MESSAGES[] = gettext( 'Updated wp-config.php with database and site settings' );
        
        
        $this->_LAST_ACTION = 'wpConfig';
        $this->_displayMessages();
    }

    /**
     * wpInstall
     *
     * Install WordPress ( Public )
     *
     * @param none
     * @return void
     */
    public function wpInstall() {
        $install_key = null;
        $action = null;

        if ( $this->isCommandLine() ) {


            foreach ( $this->_INSTALL_ORDER as $action ) {
//execute each installation action in the order prescribed by $this->_INSTALL_ORDER;
                $this->$action();
            }
        } else {

//check for retries and set local variable.
            if ( (isset( $_GET[ 'retry' ] ) ) ) {
                $this->_RETRY = $_GET[ 'retry' ];
            }

#Look at the web request, find the next action and execute it.
            $install_key = array_search( $_GET[ 'last_action' ], $this->_INSTALL_ORDER );

//if there is no match (which is true for the first installation task), start with the first installation task
            if ( $install_key === false ) { $install_key = -1; }

//Now use the key to find the right action and execute it.


            $action = $this->_INSTALL_ORDER[ $install_key + 1 ]; //find the next task after the previous one.


            try {

                $this->$action();
            } catch ( Exception $exc ) {

                if ( $this->_RETRY < $this->_RETRY_MAX ) {
//$this->_RETRY=1;   
                    $this->_RETRY = $this->_RETRY + 1;
// die('executing on exception' . $this->_RETRY);
                    $this->_LOG_MESSAGES[] = "$action failed, retrying (retry count = " . $this->_RETRY . ')';
                    $this->_LAST_ACTION = $this->_INSTALL_ORDER[ $install_key ];
                    $this->_displayMessages();
                } else {
                    $this->_ERROR_MESSAGES[] = "Install failed during " . $action . ", please check your settings and try again.";
                    if ( $this->SITE_CONFIG[ 'debug-show-exceptions' ] ) {
                        $this->_ERROR_MESSAGES[] = $exc->getTraceAsString();
                        $this->_ERROR_MESSAGES[] = '<pre>' . print_r( $exc, true ) . '</pre>';
                    }



                    $this->_displayMessages();
                }
            }
        }
    }

    /**
     * wpInstallCore
     *
     * Install WordPress Core from the downloaded, unzipped copy
     *
     * @param none
     * @return void
     */
    public function wpInstallCore() {


        if ( !$this->SITE_CONFIG[ 'wpInstallCore' ] ) {

            $this->_LOG_MESSAGES[] = gettext( 'Skipped WordPress Core Installation' );
            $this->_LAST_ACTION = 'wpInstallCore';
            $this->_displayMessages();

            return;
        }



        $this->wpCheck(
                true, //$dbconnect #check connection
                true, //$dbempty   #check whether database is empty
                false, //$files_overwrite #check whether download will overwrite existing files
                true //$files_available  #check whether downloaded files are available for installation
        );



        if ( !defined( 'WP_INSTALLING' ) ) {
            define( 'WP_INSTALLING', true );
        }




// WordPress installation
        $install_success = null;


// Use Custom Install File
        $this->_wpAddInstallDropin();

        $this->_wpIncludeWP(); //has to come after _wpAddInstallDropin or it wont execute the dropin
//set WP_SITEURL before install otherwise (especially in command line mode) wp_install will 'guess' and incorrectly set it, resulting in a sockets error
        //doing it this way also eliminates the need to do a update_option to update the database for siteurl and home
        if ( !defined( 'WP_SITEURL' ) ) {
            define( 'WP_SITEURL', $this->_wpGetSiteUrl() );
        }


        wp_install(
                $this->SITE_CONFIG[ 'wp_options' ][ 'blogname' ], //$blog_title 
                $this->SITE_CONFIG[ 'wp_users' ][ 'user_login' ], //$user_name
                $this->SITE_CONFIG[ 'wp_users' ][ 'user_email' ], //$user_email
                $this->PROFILE_CONFIG[ 'allow_search_engines' ], //$public Whether site is public
                '', //$deprecated
                $this->SITE_CONFIG[ 'wp_users' ][ 'user_pass' ] //$user_password 
        );



//delete the install drop in now that its not needed      
//if you don't delete it, a reinstall may execute it even if install_dropin is set to false
        if ( file_exists( $this->WP_DIRECTORY . 'wp-content/install.php' ) ) {

            $this->phpLib()->rmDir( $this->WP_DIRECTORY . 'wp-content/install.php' );
        }




//remove unneccessary files
        $this->_wpRemoveFileCruft();

//Remove Default Content
        $this->_wpRemoveDefaultContent();
//remove_default_plugins
        $this->_wpRemoveDefaultPlugins();







//Update Permalink Settings 
        $this->_wpUpdatePermalinks();




//Update Media Settings
        $this->_wpSetMediaOptions();
        
//Update All Other options not yet used.      
        $this->_wpUpdateOptions();
        $this->_LOG_MESSAGES[] = gettext( 'Updated wp_options table' );
        
        
//Add custom content
        $this->_wpAddCustomContent();




//show password
        $this->_LOG_MESSAGES[] = gettext( 'Installed WordPress Core' );
        $this->_SUCCESS_MESSAGES[] = $this->_getPasswordMessage();


        $this->_LAST_ACTION = 'wpInstallCore';
        $this->_displayMessages();
    }

    /**
     *  Adds a customized install.php to wp-content/ to override WordPress wp_install_defaults()
     *
     * Copies the wp_install_defaults.php file to the wp-content/ directory. The installer will automatically use these options instead of executing its default.
     *
     * @param none
     * @return void
     */
    private function _wpAddInstallDropin() {

        if ( $this->PROFILE_CONFIG[ 'install_dropin' ] ) {


            copy( $this->INSTALLER_DIRECTORY . "/install_dropin.php", $this->WP_DIRECTORY . 'wp-content/install.php' );

            $this->_LOG_MESSAGES[] = "Added Install Dropin file to override wp_install_defaults()";
        }
    }

    /**
     * _wpAddCustomContent
     *
     * Add Custom Content from config.php
     *
     * @param none
     * @return void
     */
    private function _wpAddCustomContent() {

//return if user doesn't want to add
        if ( !$this->PROFILE_CONFIG[ 'add_custom_content' ] ) {
            return;
        }

//return if posts don't exist
        if ( !isset( $this->PROFILE_CONFIG[ 'posts' ] ) || !is_array( $this->PROFILE_CONFIG[ 'posts' ] ) ) {
            return;
        }




        foreach ( $this->PROFILE_CONFIG[ 'posts' ] as $post ) {



            if ( isset( $post[ 'title' ] ) && !empty( $post[ 'title' ] ) ) {

                $parent = get_page_by_title( trim( $post[ 'parent' ] ), OBJECT, $post[ 'type' ] );
                $parent = $parent ? $parent->ID : 0;

// Let's create the page
                $args = array(
                    'post_title' => trim( $post[ 'title' ] ),
                    'post_name' => $post[ 'slug' ],
                    'post_content' => trim( $post[ 'content' ] ),
                    'post_status' => $post[ 'status' ],
                    'post_type' => $post[ 'type' ],
                    'post_parent' => $parent,
                    'post_author' => 1,
                    'post_date' => date( 'Y-m-d H:i:s' ),
                    'post_date_gmt' => gmdate( 'Y-m-d H:i:s' ),
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                );
                wp_insert_post( $args );
            }
        }
        $this->_LOG_MESSAGES[] = "Added Custom Content";
    }

    /**
     * Add Custom Themes
     *
     * Add the themes from the themes directory
     *
     * @param none
     * @return void
     */
    private function _wpAddCustomThemes() {

        if ( !$this->PROFILE_CONFIG[ 'add_custom_themes' ] ) {

            return;
        }
//copy all extracted themes, and unzip any archived themes from the /themes directory to the /wp-content/themes directory.
//  $this->_copyDir( $this->INSTALLER_DIRECTORY . '/themes', $this->WP_DIRECTORY . '/wp-content/themes' );

        $custom_theme_directory = null;
        $target_theme_directory = null;
        $source_theme_path = null;
        $dest_theme_path = null;
        $source_theme_path = null;
        $themes = array();



        $custom_theme_directory = $this->PROFILE_DIRECTORY . '/themes';
        
     
        $target_theme_directory = $this->WP_DIRECTORY . '/wp-content/themes';

//scandir returns all the folder and file names in the directory (non-recursive), 
        $themes = scandir( $custom_theme_directory );

        if ( !is_array( $themes ) ) { $themes = array(); } //ensure its an array if nothing is returned
// We remove the "." and ".." corresponding to the current and parent folder
        $themes = array_diff( $themes, array( '.', '..' ) );


//Iterate through the list, copying directories and unzipping zip files
        foreach ( $themes as $theme ) {
            $source_theme_path = $custom_theme_directory . '/' . $theme;
            $dest_theme_path = $target_theme_directory . '/' . $theme;

//if already extracted, just copy the directory to the target theme directory
            if ( is_dir( $source_theme_path ) ) {
                $this->_copyDir( $source_theme_path, $dest_theme_path );
                continue;
            }

//if a zip file, extract it to the target.
            $file_parts = pathinfo( $source_theme_path );
// Unzip to target
            if ( isset( $file_parts[ 'extension' ] ) && $file_parts[ 'extension' ] === 'zip' ) {

                $zip = new ZipArchive;

// We verify we can use the archive
                if ( $zip->open( $source_theme_path ) === true ) {

// Unzip the archive in the plugin folder
                    $zip->extractTo( $target_theme_directory );
                    $zip->close();
                }
            }
        }

        $this->_LOG_MESSAGES[] = "Added Custom Themes";
    }

    /**
     * wpInstallThemes
     *
     * WordPress Install Theme
     *
     * @param none
     * @return void
     */
    public function wpInstallThemes() {

        if ( !$this->SITE_CONFIG[ 'wpInstallThemes' ] ) {
            $this->_LOG_MESSAGES[] = gettext( 'Skipped Theme Installation' );

            $this->_LAST_ACTION = 'wpInstallThemes';
            $this->_displayMessages();


            return;
        }

        $this->wpCheck(
                true, //$dbconnect #check connection
                false, //$dbempty   #check whether database is empty
                false, //$files_overwrite #check whether download will overwrite existing files
                true//$files_available  #check whether downloaded files are available for installation
        );




        $this->_wpInstallThemes();


        $this->_LOG_MESSAGES[] = gettext( 'Installed Themes' );

        $this->_LAST_ACTION = 'wpInstallThemes';
        $this->_displayMessages();
    }

    /**
     * wpInstallPlugins
     *
     * Install Plugins as defined by configuration file.
     *
     * @param none
     * @return void
     */
    public function wpInstallPlugins() {

        if ( !$this->SITE_CONFIG[ 'wpInstallPlugins' ] ) {


            $this->_LOG_MESSAGES[] = gettext( 'Skipped Plugin Installation' );
            $this->_LAST_ACTION = 'wpInstallPlugins';
            $this->_displayMessages();


            return;
        }


//check that files are available to configure
        $this->wpCheck(
                true, //$dbconnect #check connection
                false, //$dbempty   #check whether database is empty
                false, //$files_overwrite #check whether download will overwrite existing files
                true//$files_available  #check whether downloaded files are available for installation
        );


//include WordPress Libraries
        $this->_wpIncludeWP();


        /* -------------------------- */
        /* 	Let's retrieve the plugin folder
          /*-------------------------- */

        if ( isset( $this->PROFILE_CONFIG[ 'plugins' ] ) && !empty( $this->PROFILE_CONFIG[ 'plugins' ] ) ) {


            $plugins = $this->PROFILE_CONFIG[ 'plugins' ];


            $plugins_dir = $this->WP_DIRECTORY . 'wp-content/plugins/';


            foreach ( $plugins as $plugin ) {

// We retrieve the plugin XML file to get the link to downlad it
                $plugin_repo = file_get_contents( "http://api.wordpress.org/plugins/info/1.0/$plugin.json" );

                if ( $plugin_repo && $plugin = json_decode( $plugin_repo ) ) {

                    $plugin_path = $this->_WPQI_CACHE_PLUGINS_PATH . $plugin->slug . '-' . $plugin->version . '.zip';

                    if ( !file_exists( $plugin_path ) ) {

// We download the lastest version
                        if ( $download_link = file_get_contents( $plugin->download_link ) ) {
                            file_put_contents( $plugin_path, $download_link );
                        } }

// We unzip it
                    $zip = new ZipArchive;
                    if ( $zip->open( $plugin_path ) === true ) {

                        $zip->extractTo( $plugins_dir );
                        $zip->close();
                    }
                }
            }
        }

        if ( $this->PROFILE_CONFIG[ 'add_custom_plugins' ] == 1 ) {

// We scan the folder
            $plugins = scandir( $this->PROFILE_DIRECTORY . '/plugins' );

// We remove the "." and ".." corresponding to the current and parent folder
            $plugins = array_diff( $plugins, array( '.', '..' ) );

// We move the archives and we unzip
            foreach ( $plugins as $plugin ) {

// We verify if we have to retrive somes plugins via the WP Quick Install "plugins" folder
                if ( preg_match( '#(.*).zip$#', $plugin ) == 1 ) {

                    $zip = new ZipArchive;

// We verify we can use the archive
                    if ( $zip->open( 'plugins/' . $plugin ) === true ) {

// We unzip the archive in the plugin folder
                        $zip->extractTo( $plugins_dir );
                        $zip->close();
                    }
                }
            }
        }

        /* -------------------------- */
        /* 	We activate extensions
          /*-------------------------- */

        if ( $this->PROFILE_CONFIG[ 'activate_plugins' ] == 1 ) {

            /** Load WordPress Bootstrap */
            require_once( $this->WP_DIRECTORY . 'wp-load.php' );

            /** Load WordPress Plugin API */
            require_once( $this->WP_DIRECTORY . 'wp-admin/includes/plugin.php');

// Activation
            activate_plugins( array_keys( get_plugins() ) );
        }

        $this->_LOG_MESSAGES[] = gettext( 'Installed Plugins' );
        $this->_LAST_ACTION = 'wpInstallPlugins';
        $this->_displayMessages();
    }

    /**
     * _wpIncludeWP
     *
     * Include WordPress Libraries
     *
     * @param none
     * @return void
     */
    private function _wpIncludeWP() {


        if ( ($this->_WP_INCLUDED ) ) { return; };


        $wp_library = array(
            $this->WP_DIRECTORY . 'wp-admin/includes/functions.php',
            $this->WP_DIRECTORY . 'wp-load.php',
            $this->WP_DIRECTORY . 'wp-admin/includes/upgrade.php',
            $this->WP_DIRECTORY . 'wp-includes/wp-db.php'
        );

        foreach ( $wp_library as $file ) {



            if ( file_exists( $file ) ) {

                require_once( $file );
            }
        }


        $this->_WP_INCLUDED = true;
        return;
    }

    /**
     * isCommandLine
     *
     * Checks whether script is being called by command line interface or web request
     *
     * @param none
     * @return boolean
     */
    public function isCommandLine() {

        return (php_sapi_name() === 'cli');
    }

    /**
     * _getDbConnection
     *
     * Get Database Connection Object
     *
     * @param $db_exists - Whether the database exists.
     * @return PDO object Database Connection object
     */
    private function _getDbConnection( $db_exists = true ) {

        //use dbname only if it exists. If the db doesn't exist, we'll try to connect to it later 
        $dbname = ($db_exists) ? "dbname=" . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] : '';

        try {
            
      
            //$db = new PDO( 'mysql:host=' . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_HOST' ] . ';dbname=' . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ], $this->SITE_CONFIG[ 'wp_config' ][ 'DB_USER' ], $this->SITE_CONFIG[ 'wp_config' ][ 'DB_PASSWORD' ] );
         //    $connection_string='mysql:host=' . 'localhost' . ';dbname=' . 'bplate_wpi', 'root', '!5mPrg^UVj37';
            
             $db = new PDO('mysql:host=' . 'localhost' . ';dbname=' . 'bplate_wpi', 'root', '!5mPrg^UVj37');
           if (false){
             $db = new PDO(
                    'mysql:host=' . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_HOST' ] . ';' . $dbname, $this->SITE_CONFIG[ 'wp_config' ][ 'DB_USER' ], $this->SITE_CONFIG[ 'wp_config' ][ 'DB_PASSWORD' ]
            );
           }
            return $db;
        } catch ( Exception $e ) {

            //if get an error that the database doesn't exist ( if it hasn't been created yet) , try it again but without the dbname.
            $error_code = $e->getCode();
            if ( $error_code = 1049 ) {
                return $this->_getDbConnection( false );
            }

            $this->_ERROR_MESSAGES[] = gettext( "Error Establishing Database Connection. Check `site-config.php` and verify that the database connection settings are correct." );
            $this->_displayMessages( $this->_ERROR_MESSAGES, 'error' );
        }
    }

    /**
     * isDBEmpty
     *
     * Checks whether the WordPress database is empty
     *
     * @param none
     * @return void
     */
    private function _isDBEmpty() {


        $db = $this->_getDBConnection();

//returns false if no results, otherwise returns the first table name.
        $result = $db->query( "show tables like '%'", PDO::FETCH_ASSOC )->fetchColumn();
// echo ($result===false)?'database is empty':'database is full';

        return ($result === false);
    }

    /**
     * Delete Database
     *
     * Emptys out the database
     * Db user must have permissions to drop and create databases.
     * @param none
     * @return void
     */
    private function _deleteDB() {
        $db = $this->_getDBConnection();

        $result = true;

//returns false if any of the following statements fails, otherwise true
        $result = $result && $db->query( "DROP DATABASE " . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] );
#$this->_LOG_MESSAGES[]=($result)? 'dropped db successfully': 'dropped db failed';

        $result = $result && $db->query( "CREATE DATABASE " . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] );
#$this->_LOG_MESSAGES[]=($result)? 'created db successfully': 'created db failed';

        $result = $result && $db->query( "USE " . $this->SITE_CONFIG[ 'wp_config' ][ 'DB_NAME' ] );
#$this->_LOG_MESSAGES[]=($result)? 'USE db successfully': 'USE db failed';

        $this->_LOG_MESSAGES[] = ($result) ? 'Existing Database found and deleted' : 'Existing Database could not be deleted.';


        return ($result);
    }

    /**
     * _reset_Password (private)
     *
     * Resets WordPress password. Private function, wrapped by wpResetPassword
     *
     * @param none
     * @return void
     */
    private function _wpResetPassword() {
        $db = $this->_getDBConnection();
        $password = $this->getNewPassword();
        $query = "UPDATE wp_users SET user_pass = MD5('" . $password . "') WHERE user_login ='" . $this->SITE_CONFIG[ 'wp_users' ][ 'user_login' ] . "' LIMIT 1";
        $result = $db->exec( $query );

        $this->SITE_CONFIG[ 'wp_users' ][ 'user_pass' ] = $password;
    }

    /**
     * getNewPassword
     *
     * Get New Password . Uses a cryptographically secure function to generate a strong password.
     *
     * @param none
     * @return string The password.
     */
    public function getNewPassword() {

        if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
            $password = bin2hex( openssl_random_pseudo_bytes( 6 ) );
        } else {
            $password = 'password'; //replace this with // https://github.com/ircmaxell/RandomLib
        }

        return ($password);
    }

    /**
     * wpResetPassword
     *
     * Resets WordPress password for the configured user
     *
     * @param none
     * @return void
     */
    public function wpResetPassword() {

//if not resetting password, or if we are installing core, return
        if ( !($this->SITE_CONFIG[ 'wpResetPassword' ] && !$this->SITE_CONFIG[ 'wpInstallCore' ]) ) {

            $this->_LAST_ACTION = 'wpResetPassword';

            $this->_displayMessages();
            return;
        }



        $this->_wpResetPassword();

        $this->_LOG_MESSAGES[] = gettext( 'Reset WordPress Password ' );


        $this->_SUCCESS_MESSAGES[] = $this->_getPasswordMessage();

        $this->_LAST_ACTION = 'wpResetPassword';

        $this->_displayMessages();
    }

    /**
     * _getPasswordMessage
     *
     * Returns the password message used to show the username and password to the user.
     *
     * @param none
     * @return string
     */
    private function _getPasswordMessage() {



#$this->_SUCCESS_MESSAGES[]='<a href="' . admin_url() . '" class="button" style="margin-right:5px;" target="_blank">' . _( 'Log In' ) . '</a>'; 
#$this->_SUCCESS_MESSAGES[]='<a href="' . home_url() . '" class="button" target="_blank">' . _( 'Go to website' ) . '</a>'; 
//   $this->_WARNING_MESSAGES[] = '<strong>' . _( 'Security Warning' ) . '</strong>: To keep your site secure: ' . _( 'Delete this     directory: <span class="well">' . $this->INSTALLER_DIRECTORY . '</span>' );

        $this->_wpIncludeWP();



        $tags[ '{TITLE}' ] = gettext( 'WordPress Login' );
        $tags[ '{HOME_URL}' ] = get_home_url();
        $tags[ '{ADMIN_URL}' ] = get_admin_url();
        $tags[ '{LOGIN_TEXT}' ] = gettext( 'Log In' );
        $tags[ '{HOME_PAGE_LINK_TEXT}' ] = gettext( 'Go to website' );

        $tags[ '{USERNAME_LABEL}' ] = gettext( 'username:' );


        $tags[ '{USERNAME}' ] = $this->SITE_CONFIG[ 'wp_users' ][ 'user_login' ];
        $tags[ '{PASSWORD_LABEL}' ] = gettext( 'password:' );
        $tags[ '{PASSWORD}' ] = $this->SITE_CONFIG[ 'wp_users' ][ 'user_pass' ];
        $tags[ '{RESET_INSTRUCTIONS}' ] = gettext( "This password will not be emailed to you. If you need to reset it, re-run the setup script using \$site[ 'wpResetPassword' ]=true and  \$site[ 'wpInstallCore' ]=false or use WordPress\'s \'lost password\' feature." );

        $template = '<strong style="margin:5px">{TITLE}</strong>'
                . '<div><a href={HOME_URL} class="btn btn-default" target="_blank">{HOME_PAGE_LINK_TEXT}</a>'
                . '<a href={ADMIN_URL} class="btn btn-default" style="margin-right:5px;" target="_blank">{LOGIN_TEXT}</a></div>'
                . '<ul>'
                . '<li><strong>{USERNAME_LABEL}</strong> {USERNAME}</li>'
                . '<li><strong>{PASSWORD_LABEL}</strong> {PASSWORD}</li>'
                . '</ul>'
                . '<div>{RESET_INSTRUCTIONS}</div>';
        if ( $this->isCommandLine() ) {

            $template = "\n###########################"
                    . "\nWordPress Login Credentials"
                    . "\n############################"
                    . "\nWebsite URL:{HOME_URL}                "
                    . "\nLogin URL:{ADMIN_URL}             "
                    . "\n{USERNAME_LABEL}{USERNAME}"
                    . "\n{PASSWORD_LABEL}{PASSWORD}"
                    . "\n{RESET_INSTRUCTIONS}"
                    . "\n###########################################";
        }






        $message = str_replace( array_keys( $tags ), array_values( $tags ), $template );


        return $message;
    }

    /**
     * Get Site URL
     *
     * Returns a calculation of the site url 
     *
     * @param none
     * @return void
     */
    private function _wpGetSiteUrl() {
// We update the options with the right siteurl et homeurl value
        $protocol = !is_ssl() ? 'http' : 'https';
        if ( false ) { //removed old code from wp_quick_install
            $get = basename( $this->INSTALLER_DIRECTORY ) . '/index.php/wp-admin/install.php?action=install_wp';
            $dir = str_replace( '../', '', $this->WP_DIRECTORY );
            $link = $protocol . '://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
            $url = str_replace( $get, $dir, $link );
        }

        $url = $protocol . '://' . $_SERVER[ 'HTTP_HOST' ] . '/' . $this->SITE_CONFIG[ 'wp_directory' ];



        $url = trim( $url, '/' );
        return ($url);
    }

    /**
     * Remove Default Content
     *
     * Removes the default page and posts that come with a standard WP install
     *
     * @param none
     * @return void
     */
    private function _wpRemoveDefaultContent() {
        /* -------------------------- */
        /* 	We remove the default content
          /*-------------------------- */

        if ( $this->PROFILE_CONFIG[ 'remove_default_content' ] == 1 ) {
            wp_delete_post( 1, true ); // We remove the article "Hello World"
            wp_delete_post( 2, true ); // We remove the "Exemple page"
        }
    }

    /**
     *  Update Permalinks
     *
     * Sets Permalinks per our configuration settings in config.php
     *
     * @param none
     * @return void
     */
    private function _wpUpdatePermalinks() {

        /* -------------------------- */
        /* 	We update permalinks
          /*-------------------------- */
        if ( !empty( $this->PROFILE_CONFIG[ 'wp_options' ][ 'permalink_structure' ] ) ) {
            $this->_update_option( 'permalink_structure', $this->PROFILE_CONFIG[ 'wp_options' ][ 'permalink_structure' ] );
        }
        $this->_LOG_MESSAGES[] = gettext( 'Set permalinks to ' . $this->PROFILE_CONFIG[ 'wp_options' ][ 'permalink_structure' ] );
    }

    /**
     * Set Media Options
     *
     * Set WordPress Media options to match configuration in config.php
     *
     * @param none
     * @return void
     */
    public function _wpSetMediaOptions() {

        if ( !$this->PROFILE_CONFIG[ 'set_media_options' ] ) {
            return;
        }
        /* -------------------------- */
        /* 	We update the media settings
          /*-------------------------- */

        if ( !empty( $this->PROFILE_CONFIG[ 'wp_options' ][ 'thumbnail_size_w' ] ) || !empty( $this->PROFILE_CONFIG[ 'wp_options' ][ 'thumbnail_size_h' ] ) ) {
            $this->_update_option( 'thumbnail_size_w', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'thumbnail_size_w' ] );
            $this->_update_option( 'thumbnail_size_h', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'thumbnail_size_h' ] );
            $this->_update_option( 'thumbnail_crop', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'thumbnail_crop' ] );
        }

        if ( !empty( $this->PROFILE_CONFIG[ 'wp_options' ][ 'medium_size_w' ] ) || !empty( $this->PROFILE_CONFIG[ 'wp_options' ][ 'medium_size_h' ] ) ) {
            $this->_update_option( 'medium_size_w', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'medium_size_w' ] );
            $this->_update_option( 'medium_size_h', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'medium_size_h' ] );
        }

        if ( !empty( $this->PROFILE_CONFIG[ 'large_size_w' ] ) || !empty( $this->PROFILE_CONFIG[ 'large_size_h' ] ) ) {
            $this->_update_option( 'large_size_w', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'large_size_w' ] );
            $this->_update_option( 'large_size_h', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'large_size_h' ] );
        }

        $this->_update_option( 'uploads_use_yearmonth_folders', ( int ) $this->PROFILE_CONFIG[ 'wp_options' ][ 'uploads_use_yearmonth_folders' ] );

        $this->_LOG_MESSAGES[] = "Updated Media Settings";
    }

    /**
     * Remove Default Plugins
     *
     * Removes WordPress Default Plugins
     *
     * @param none
     * @return void
     */
    private function _wpRemoveDefaultPlugins() {

        $dir = $this->WP_DIRECTORY . '/wp-content/plugins';
        $di = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
        $ri = new RecursiveIteratorIterator( $di, RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $ri as $file ) {

            if ( $file->isDir() ) {
// echo( 'rmdir directory ' . $file). "\n";
                rmdir( $file );
            } else {
//if a file, delete it unless its wp-plugins/index.php
                if ( realpath( $file->getPathname() ) !== realpath( $this->WP_DIRECTORY . 'wp-content/plugins/index.php' ) ) {

                    unlink( $file );
                }
            }
        }
        return true;
    }

    /**
     * Remove Default Themes
     *
     * Removes WordPress Default Themes
     * ref: http://stackoverflow.com/a/24563703/3306354
     * 
     * @param none
     * @return void
     */
    private function _wpRemoveDefaultThemes() {


        $result = $this->phpLib()->rmDirMaybe(
                $this->WP_DIRECTORY . '/wp-content/themes', //the directory whose contents we want to delete
                array( 'index.php', $this->PROFILE_CONFIG[ 'wp_options' ][ 'template' ] ), //the file name and directory names to keep in place
                false //whether to delete /wp-content/themes directory even if empty
        );

        return $result;


        $dir = $this->WP_DIRECTORY . '/wp-content/themes';
        $di = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
        $ri = new RecursiveIteratorIterator( $di, RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $ri as $file ) {

            if ( $file->isDir() ) {
//remove directory only if it isn't the desired theme.
                if ( basename( $file->getPathname() ) !== $this->PROFILE_CONFIG[ 'wp_options' ][ 'template' ] ) {
                    rmdir( $file );
                }
            } else {
//if a file, delete it unless its wp-plugins/index.php
                if ( realpath( $file->getPathname() ) !== realpath( $this->WP_DIRECTORY . 'wp-content/themes/index.php' ) ) {

                    unlink( $file );
                }
            }
        }
        return true;
    }

    /**
     * Sandbox
     *
     * Delete this before release - use for testing only
     *
     * @param none
     * @return void
     */
    public function sandbox() {

   
        return;
    }

    /**
     * _install_theme
     *
     * Install Theme ( Deprecated ) from original quick install code. 
     * Add a theme.zip file, and will delete themes only when theme.zip file exists and activates.
     *
     * @param none
     * @return void
     */
    private function _installTheme_deprecated() {



        /* -------------------------- */
        /* 	We install the new theme
          /*-------------------------- */

// We verify if theme.zip exists
        if ( file_exists( 'theme.zip' ) ) {

            $zip = new ZipArchive;

// We verify we can use it
            if ( $zip->open( 'theme.zip' ) === true ) {

// We retrieve the name of the folder
                $stat = $zip->statIndex( 0 );
                $theme_name = str_replace( '/', '', $stat[ 'name' ] );

// We unzip the archive in the themes folder
                $zip->extractTo( $this->WP_DIRECTORY . 'wp-content/themes/' );
                $zip->close();

// Let's activate the theme
// Note : The theme is automatically activated if the user asked to remove the default theme
                if ( $this->PROFILE_CONFIG[ 'activate_theme' ] == 1 || $this->PROFILE_CONFIG[ 'remove_default_themes' ] == 1 ) {
                    switch_theme( $theme_name, $theme_name );
                }

// Let's remove the Twenty family
                if ( $this->PROFILE_CONFIG[ 'remove_default_themes' ] == 1 ) {
                    delete_theme( 'twentysixteen' );
                    delete_theme( 'twentyfifteen' );
                    delete_theme( 'twentyfourteen' );
                    delete_theme( 'twentythirteen' );
                    delete_theme( 'twentytwelve' );
                    delete_theme( 'twentyeleven' );
                    delete_theme( 'twentyten' );
                }

// We delete the _MACOSX folder (bug with a Mac)
                delete_theme( '__MACOSX' );
            }
        }
    }

    /**
     * Copy Directory
     *
     * Copies all the contents of a directory to another location
     * This is different then PHP's copy which only handles a single file.
     * ref: http://stackoverflow.com/a/2050909/3306354
     * @param none
     * @return void
     */
    private function _copyDir( $src, $dst ) {
        $dir = opendir( $src );
        @mkdir( $dst );
        while ( false !== ( $file = readdir( $dir )) ) {
            if ( ( $file != '.' ) && ( $file != '..' ) ) {
                if ( is_dir( $src . '/' . $file ) ) {
                    $this->_CopyDir( $src . '/' . $file, $dst . '/' . $file );
                } else {
                    copy( $src . '/' . $file, $dst . '/' . $file );
                }
            }
        }


        closedir( $dir );
    }

    /**
     * Install WP Themes
     *
     * Install Custom Themes, Delete Default Themes, Activate Configured theme
     *
     * @param none
     * @return void
     */
    public function _wpInstallThemes() {

        $this->_wpIncludeWP();

    $this->_UPDATED_OPTIONS[]='template'; //prevent theme from being updated using the update_options method which won't work if we've already deleted it. 

//if the theme to be activated exists, delete the default themes
        if ( $this->_wpThemeExists() && ($this->PROFILE_CONFIG[ 'remove_default_themes' ]) ) {
//delete default themes except the desired theme to be activated
            $this->_wpRemoveDefaultThemes();
        }


//Add Custom Themes from the themes folder
        $this->_wpAddCustomThemes();



//activate theme
        if ( $this->_wpThemeExists() ) {

//activate theme
            switch_theme( $this->PROFILE_CONFIG[ 'wp_options' ][ 'template' ], $this->PROFILE_CONFIG[ 'wp_options' ][ 'template' ] );
        }
    }

    /**
     * _wpThemeExists()
     *
     * Checks whether theme exists in themes directory or wp-content/themes directory
     * It also detects whether the theme exists within a zip file in one of those directories
     * 
     * @param none
     * @return void
     */
    private $_wp_theme_exists = null;

    private function _wpThemeExists() {

        if ( !is_null( $this->_wp_theme_exists ) ) {
            return($this->_wp_theme_exists);
        }

        $file_info = null;
//check zip
//check themes folder & wp-content/themes folder
        $dirs[] = $this->PROFILE_DIRECTORY . '/themes';
        $dirs[] = $this->WP_DIRECTORY . '/wp-content/themes';


        foreach ( $dirs as $dir ) {


            $di = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
            $ri = new RecursiveIteratorIterator( $di, RecursiveIteratorIterator::CHILD_FIRST );
            foreach ( $ri as $file ) {

                $file_info = pathinfo( $file );


//if file system object is a directory check if the directory is the theme
                if ( $file->isDir() ) {
//if found it,  return true.
                    if ( basename( $file->getPathname() ) === $this->PROFILE_CONFIG[ 'wp_options' ][ 'template' ] ) {
                        return true;
                    }


//check to see if the file object is a zip file and whether it holds the theme.
                } else if ( $file_info[ 'extension' ] === 'zip' ) {
                    $theme_name = null;
                    $zip = new ZipArchive;

// We verify we can use it
                    if ( $zip->open( $file->getPathname() ) === true ) {

// We retrieve the name of the folder
                        $stat = $zip->statIndex( 0 );
                        $theme_name = str_replace( '/', '', $stat[ 'name' ] );
                        if ( $theme_name === $this->PROFILE_CONFIG[ 'wp_options' ][ 'template' ] ) {
                            $this->_wp_theme_exists = true;
                            return ($this->_wp_theme_exists);
                        }
                    }
                }
            }
        }
//if it made it this far, it didn't find it.
        $this->_wp_theme_exists = false;
        return ($this->_wp_theme_exists);
    }

    /**
     * Set Site Configuration Property
     *
     * Reads the Configuration file into the property $this->SITE_CONFIG
     * 
     * @param none
     * @return void
     */
    private function _setSiteConfig() {
        if ( !is_null( $this->SITE_CONFIG ) ) {
            return;
        }


        if ( !file_exists( $this->INSTALLER_DIRECTORY . '/site-config.php' ) ) {

            $this->_ERROR_MESSAGES[] = gettext( 'Cannot proceed without a site-config.php file! Create one from `site-config-sample.php`' );


            $this->_displayMessages( $this->_ERROR_MESSAGES, 'error' );
            exit();
        }

        include($this->SITE_CONFIG_FILE); //include site configuration
        $this->SITE_CONFIG = $site; //$site is defined in the site configuration file site-config.php . 
        //set reinstall variable after $config file is read or it may be overwritten
//reinstall is not usually found in the config file, so define it here if not already defined by the config file
//reinstall set to true will force file and database overwrites
        if ( !isset( $this->SITE_CONFIG[ 'reinstall' ] ) ) {
            $this->SITE_CONFIG[ 'reinstall' ] = false;
        }

        //set admin passwords
        $this->SITE_CONFIG[ 'wp_users' ][ 'user_pass' ] = $this->getNewPassword();

        //replace any variables with those that are contained in the GET request (for initial page build/page refresh)
        if ( isset( $_GET[ 'SITE_CONFIG' ] ) ) {
            $this->SITE_CONFIG = $this->phpLib()->arrayMerge( $this->SITE_CONFIG, $_GET[ 'SITE_CONFIG' ] );
        }



//do the same with $_POST since you'll be posting the form  on submit      
        if ( isset( $_POST[ 'SITE_CONFIG' ] ) ) {
            $this->SITE_CONFIG = $this->phpLib()->arrayMerge( $this->SITE_CONFIG, $_POST[ 'SITE_CONFIG' ] );
        }
        //set HTTP_HOST server variable so variable will be available when running as command line
        if ( $this->isCommandLine() ) {
            $_SERVER[ 'HTTP_HOST' ] = $this->SITE_CONFIG[ 'HTTP_HOST' ];
        }



        $this->SITE_CONFIG = $this->phpLib()->convertToBool( $this->SITE_CONFIG );
        
        
        
         
         
    }

    /**
     * Set Profile Configuration Property
     *
     * Reads the Configuration file into the property $this->PROFILE_CONFIG
     *
     * @param none
     * @return void
     */
    private function _setProfileConfig() {
        if ( !is_null( $this->PROFILE_CONFIG ) ) {
            return;
        }

        $this->PROFILE_DIRECTORY = $this->PROFILES_DIRECTORY . '/' . $this->SITE_CONFIG[ 'profile' ];
        $this->PROFILE_CONFIG_FILE = $this->PROFILE_DIRECTORY . '/profile-config.php';
        if ( !file_exists( $this->PROFILE_CONFIG_FILE ) ) {

            $this->_ERROR_MESSAGES[] = gettext( 'Cannot proceed without a site-config.php file! Create one from `site-config-sample.php`' );


            $this->_displayMessages( $this->_ERROR_MESSAGES, 'error' );
            die();
        }

        include($this->PROFILE_CONFIG_FILE);


        $this->PROFILE_CONFIG = $profile; //$profile is defined in the profile configuration file profile-config.php . 
        //replace any variables with those that are contained in the GET request (for initial page build/page refresh)


        if ( isset( $_GET[ 'PROFILE_CONFIG' ] ) ) {
            $this->PROFILE_CONFIG = $this->phpLib()->arrayMerge( $this->PROFILE_CONFIG, $_GET[ 'PROFILE_CONFIG' ] );
        }
//do the same with $_POST since you'll be posting the form  on submit      
        if ( isset( $_POST[ 'PROFILE_CONFIG' ] ) ) {
            $this->PROFILE_CONFIG = $this->phpLib()->arrayMerge( $this->PROFILE_CONFIG, $_POST[ 'PROFILE_CONFIG' ] );
        }



        $this->PROFILE_CONFIG = $this->phpLib()->convertToBool( $this->PROFILE_CONFIG );
    }

    /**
     * PHP Library
     *
     * PHP Utilities
     *
     * @param none
     * @return void
     */
    public function phpLib() {
        if ( is_null( $this->_PHP_LIB ) ) {


            include($this->INSTALLER_DIRECTORY . '/libs/bluedog-phplib.class.php');

            $this->_PHP_LIB = new bluedog_phplib;
        }

        return $this->_PHP_LIB;
    }

    /**
     * Error Handler
     *
     * Sets an error handler so we can catch any error for retries.
     *
     * @param none
     * @return void
     */
    public function errorHandler( $errno, $errstr, $errfile, $errline, array $errcontext ) {


// error was suppressed with the @-operator
        if ( 0 === error_reporting() ) {
            return false;
        }

        throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
    }

    /**
     * Set Installation Task Order
     *
     * An array that is used to keep track of which installation task to do next. 
     * Using this array  greatly simplifies the javascript code so we don't need an ajax
     * call for every method. We just need to keep track of which index we are on.
     *
     * @param none
     * @return void
     */
    private function _setInstallOrder() {

        /*
         * Install Order
         * Add any new install methods here, or re-arrange them in the order needed.
         * When installation is started, we cycle through this array an execute each method in it.
         * wpSuccessMessages always has to be at the end.
         */
        $this->_INSTALL_ORDER = array(
            'wpDownload',
            'wpUnzip',
            'wpConfig',
            'wpInstallCore',
            'wpInstallThemes',
            'wpInstallPlugins',
            'wpResetPassword',
            'wpSuccessMessage',
            'wpUpdateHtaccess' //must be at end so rules can flush properly
        );
    }

    /**
     * Get Messages HTML
     *
     * Returns the HTML for errors, warnings,logs,
     *
     * @param $messages An array of message strings.
     * @param $type The bootstrap class for the alert (info,danger,warning,etc)
     * 
     * @return string The html of the error messages
     */
    private function _getMessagesHTML( $messages, $type ) {
        if ( empty( $messages ) ) {
            return;
        }
        $template = '<div class="alert alert-{TYPE}">{MESSAGES}</div>';
        $messages_string = '';


        foreach ( $messages as $message ) {

            $messages_string.='<li>' . $message . '</li>';
        }


        $tags[ '{MESSAGES}' ] = $messages_string;
        $tags[ '{TYPE}' ] = $type;
        return str_replace( array_keys( $tags ), array_values( $tags ), $template );
    }

    /**
     * Get Safe Configuration properties
     *
     * Scrubs the configuration array of any sensitive security data (anything in the PRIVATE_PROPS property)
     *
     * @param array $config The configuration paramaters to filter
     * @return void
     */
    public function getSafeConfig( $config ) {
        unset( $config[ 'wp_users' ] [ 'user_login' ] );
        unset( $config[ 'wp_users' ] [ 'user_email' ] );
        unset( $config[ 'wp_users' ] [ 'user_pass' ] );
        unset( $config[ 'wp_config' ] [ 'DB_NAME' ] );
        unset( $config[ 'wp_config' ] [ 'DB_HOST' ] );
        unset( $config[ 'wp_config' ] [ 'table_prefix' ] );
        unset( $config[ 'wp_config' ] [ 'DB_USER' ] );
        unset( $config[ 'wp_config' ] [ 'DB_PASSWORD' ] );
        unset( $config[ 'wp_config' ] [ 'DB_PASSWORD' ] );
        unset( $config[ 'posts' ] );

        return $config;
    }

    /**
     * Get Profiles 
     *
     * Returns an array of profiles by iterating through the profile directory names
     *
     * @param none
     * @return array
     */
    public function getProfiles() {
        $profiles = scandir( $this->PROFILES_DIRECTORY );
        // We remove the "." and ".." from the current folder and its parent
        $profiles = array_diff( $profiles, array( '.', '..' ) );
        return $profiles;
    }

    /**
     * Get Config as Json
     *
     * Outputs the Configuration variables as json
     *
     * @param boolean $safe True to strip sensitive passwords and other unwanted values.False to return everything
     * @return void
     */
    private function _getConfigAsJson($safe) {
        
          
        $json[ 'SITE_CONFIG' ] = ($safe) ? $this->getSafeConfig($this->SITE_CONFIG):$this->SITE_CONFIG;
        $json[ 'PROFILE_CONFIG' ] = ($safe) ? $this->getSafeConfig($this->PROFILE_CONFIG):$this->PROFILE_CONFIG;
        return json_encode( $json );
    }

    /**
     * Parse Query Variables
     *
     * Check for Query Vars to determine which action to take
     *
     * @param none
     * @return void
     */
    public function parseQueryVar() {
        
        
        if ( !isset($_GET['action']) ) {
           return; 
        }
        
   
        
        switch($_GET['action']){
            
            case 'install':
                $this->wpInstall();
                break;
            
            case 'config':
                echo $this->_getConfigAsJson(true);
                break;            
            
        }
    }
    
    /**
     * Update WordPress Option
     *
     * A wrapper around update_option which also tracks which options we've updated already
     *
     * @param string $option_name The name of the value in the option_name column
        * @param string $option_value The name of the value in the option_value column
     * @return void
     */
    private function _update_option( $option_name,$option_value ) {
        if ( array_search($option_name,$this->_UPDATED_OPTIONS)!==false) {
             return;
             
        }   
       
        $this->_wpIncludeWP(); //include WordPress Library
        $this->_UPDATED_OPTIONS[]=$option_name; //add the option name to our tracking array so we don't add it again
        update_option( $option_name, $option_value ); //update the option

    }
    
    /**
     * Update Options
     *
     * Updates all the configured options in the wp_options table
     *
     * @param none
     * @return void
     */
    private function _wpUpdateOptions() {
      
        $this->_getDbConnection();
        
        foreach ( $this->SITE_CONFIG['wp_options'] as $option_name=>$option_value ) {
            $this->_update_option( $option_name,$option_value );
        }
            
        foreach ( $this->PROFILE_CONFIG['wp_options'] as $option_name=>$option_value ) {
         //   $this->_update_option( $option_name,$option_value );
        }
        
    }

}

?>