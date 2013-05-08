
Enhanced moodle's built-in imap authentication plugin.

Features
########

    - Authenticates against multiple IMAP servers
    - Assigns system level role based on the authenticating server

Installation
############
    
    1. Make sure you have imap extension enabled in your php
       If it's Windows, just add 
       extension=php_imap.dll
       to your php.ini and put php_imap.dll (you can find it in php source release) in your php extension directory
    
    2. Copy imap_plus directory to <moodle>/auth/
    
Tested on Moodle 2.4.2+