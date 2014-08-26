<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This file is part of the MailWizz EMA application.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
?>
# BEGIN rewrite rules
<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase <?php echo $baseUrl;?>
    
    
    <?php foreach ($webApps as $app) { ?>
# <?php echo strtoupper($app);?> APP
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} !.*\.(ico|gif|jpg|jpeg|png|js|css)
    RewriteCond %{REQUEST_URI} ^<?php echo $baseUrl;?><?php echo $app;?>(/.*)?$
    RewriteRule <?php echo $app;?>/.* <?php echo $app;?>/index.php
    
    <?php } ?>

    # FRONTEND APP
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} !.*\.(ico|gif|jpg|jpeg|png|js|css)
    RewriteRule . index.php
</IfModule>
# END rewrite rules