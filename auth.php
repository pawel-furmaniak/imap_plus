<?php

/**
 * AUTHOR: Pawel Furmaniak
 *
 * Authentication Plugin: IMAP Authentication Plus
 *
 * Authenticates against an IMAP server and assigns system-level roles
 *
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');

/**
 * IMAP authentication plugin.
 */
class auth_plugin_imap_plus extends auth_plugin_base {

    function deserialize_configuration( $config ) {
        
        $hosts = array();
        $roles = array();
        
        foreach ($config as $key => $val) {
            
          $match = array();
          if ( preg_match( "/host(\d)/", $key, $match ) ) {
             $hosts[$match[1]] = $val;
             unset( $config->$key );
          }
          
          $match = array();
          if ( preg_match( "/role(\d)/", $key, $match ) ) {
             $roles[$match[1]] = $val;
             unset( $config->$key );
          }
        }
        
        $config->hosts = $hosts;
        $config->roles = $roles;
        
        return $config;
    }
    
    /**
     * Constructor.
     */
    function auth_plugin_imap_plus() {
        $this->authtype = 'imap_plus';
		$this->auth_host_index = null;
        
        $config = get_config('auth/imap_plus');
        $this->config = $this->deserialize_configuration( $config );
       
    }
    
    function set_defaults( $object ) {

        // required params
        // set default values if undefined
        if (!isset ($object->type)) {
            $object->type = 'imap';
        }
        if (!isset ($object->port)) {
            $object->port = '143';
        }
        if (!isset($object->changepasswordurl)) {
            $object->changepasswordurl = '';
        }
        
        if ( !isset( $object->hosts ) ) {
            $object->hosts = array( '127.0.0.1' );
        }
        
        return $object;
    }
    
    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     * @return bool Authentication success or failure.
     */
    function user_login ($username, $password) {
        
        if (! function_exists('imap_open')) {
            print_error('auth_imapnotinstalled','mnet');
            return false;
        }

        global $CFG;
        
        foreach ($this->config->hosts as $index => $host) {                 // Try each host in turn
            if (is_null($host)) {
                continue;
            }

            switch ($this->config->type) {
                case 'imapssl':
                    $descriptor = '{'.$host.":{$this->config->port}/imap/ssl}";
                break;

                case 'imapcert':
                    $descriptor = '{'.$host.":{$this->config->port}/imap/ssl/novalidate-cert}";
                break;

                case 'imaptls':
                    $descriptor = '{'.$host.":{$this->config->port}/imap/tls}";
                break;

                default:
                    $descriptor = '{'.$host.":{$this->config->port}/imap}";
            }

            error_reporting(0);
            $connection = imap_open($descriptor, $username, $password, OP_HALFOPEN);
            
            error_reporting($CFG->debug);

            if ($connection) {
                imap_close($connection);
				
				//put the host to the instance namespace in order to use it later
				$this->auth_host_index = $index;
				
                return true;
            }
        }

        return false;  // No match
    }
	
	function sync_roles( $user ) {
		//get the host from instance namespace
		if ( array_key_exists( $this->auth_host_index, $this->config->roles) ) {
            $role_id = $this->config->roles[$this->auth_host_index];
            if ( $role_id != 0 ) {
                $systemcontext = get_context_instance( CONTEXT_SYSTEM );
                role_assign( $role_id, $user->id, $systemcontext->id, 'auth_imap_plus' );
            }
        }
	}
    
    function prevent_local_passwords() {
        return true;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return !empty($this->config->changepasswordurl);
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    function change_password_url() {
        return new moodle_url($this->config->changepasswordurl);
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        global $OUTPUT;
        
        //deserialize in case the form is being initialized with config from db
        if ( !isset( $config->hosts ) ) {
            $config = $this->deserialize_configuration( $config );
           
        }
        
        $config = $this->set_defaults( $config );
        // display  one extra fieldset
        array_push($config->hosts, 'extra');
        
        include "config.html";
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config( $config ) {
        
        // clean form data
        $config->hosts = array_map( 'trim', $config->hosts );
        $config->hosts = array_filter( $config->hosts );
        
        $config->roles = array_intersect_key( $config->roles, $config->hosts );
        
        $config->hosts = array_values( $config->hosts );
        $config->roles = array_values( $config->roles );
        
        $config->roles = array_map('trim', $config->roles );
        
        // set defaults
        $config = $this->set_defaults( $config );
        
        // delete previous hosts and roles configuration from db
        foreach ( range(0, 9) as $i ) {
          unset_config('host'.$i, 'auth/imap_plus');
          unset_config('role'.$i, 'auth/imap_plus');
        }
        
        // save to db
        foreach ( $config->hosts as $key=>$val ) {
          set_config( 'host'.$key, $val, 'auth/imap_plus' );
          set_config( 'role'.$key, $config->roles[$key], 'auth/imap_plus' );
        }
        
        set_config('type', $config->type, 'auth/imap_plus');
        set_config('port', $config->port, 'auth/imap_plus');
        set_config('changepasswordurl', $config->changepasswordurl, 'auth/imap_plus');
        
        return true;
    }

}


