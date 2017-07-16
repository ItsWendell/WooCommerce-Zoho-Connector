# WooCommerce-Zoho-Connector
A connector for WooCommerce To Zoho for Sales Orders

# Roadmap v0.1 BETA
Initial connector for 1 way multi-store, multi-site WooCommerce Sales Order To Zoho Sales Orders Connector.
 - Zoho Sales Order: Matching Clients Based on Emails.
 - Zoho Sales Order: Matching Products Based On EAN / Product Codes
 
 # Installation
Next to installing this plugin the regular way into WordPRess, you need to setup your cron-jobs properly.
This so your visitors won't be affected by the synchronizing between WooCommerce and Zoho.

 - wp-config.php: define('DISABLE_WP_CRON', true);
 - Google: How To Setup A Cron Job In [Your Server Configuration here. e.g. cPanel]
 - Set cronjob every 5 minutes to: /home/mydoodev/mydoo.nl/wp-cron.php
 - http://yourwebsite.com/wp-cron.php?doing_wp_cron
 
 # Notes
 More features / roadmap comming soon!
