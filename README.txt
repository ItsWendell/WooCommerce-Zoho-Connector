=== Connector for WooCommerce & Zoho Books ===
Contributors: DigiSpark
Donate link: https://digispark.nl/
Tags: woocommerce, zoho, synchronisation, api, connector
Requires at least: 4.2
Tested up to: 4.8.1
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A free & feature rich Zoho Books Connector for WooCommerce to dynamically synchronize orders, items, prices and contacts.

== Description ==

**This is the first open-beta for this connector, it's been tested on multiple live environments. Feedback would be highly appreciated**

This plugin connects WooCommerce with Zoho Books, it's feature rich and dynamically synchronizes new orders, contacts and products.

*   Routinely, Dynamically or Manually synchronize (new) orders to Zoho
*   Dynamically adds new contacts to Zoho.
*   Dynamically creates new items in Zoho if product is not found by SKU
*   Dynamically updates pricing in WooCommerce or Zoho
*   Set your own reference number using variables like %order_id% and %site_id%
*   API caching for Items & Tax rates (limiting API load and faster performance)
*   Email notifications for specific events

== The Order Synchronization Process ==
Order synchronization happens using the wp scheduler, and thus will happen in the background not slow down your users. Here's a quick overview of what happens when a new order has come in from WooCommerce;

1.  Plugin receives order from WooCommerce
2.  Plugin puts the order in a queue to keep track of API failures and new orders.
3.  Based on your setting it will push directly, every hour, 12 hours or 24 hours.
4.  Contact will be matched based on name, and if not found email address.
    - If no contact is found a new one is created based on order details.
5.  We'll loop through all the items and look them up in either the API cache or live API
5.  If item is not found we'll either add it as a comment, or create a new product (based on preference)
6.  Once all the proper information is found we'll attempt to Push to Zoho.
7.  If something goes wrong you'll receive a notification email (based on setting) and thanks to the queue it will try it again the next hour (or based on the settings)
8.  If all is good the item will show up in Zoho as a Sales Order concept.

== Notes ==
All synchronisation happens dynamically, which means orders placed before the activation of this plug-in won't be synchronized automatically.
(This CAN be done manually in the bulk options in WooCommerce Orders: Push To Zoho)
*   **THE PLUGIN WON'T WORK WITHOUT WOOCOMMERCE INSTALLED, AT ALL. IT WILL SELF-DEACTIVATE.**
*   Product matching is happening based on SKU.
*   Contact matching is based on contact name or email.
*   The Test Mode setting will still push to Zoho, but will change the salesorder ID to TEST-%order_id%
*   New orders will be pushed as a concept.
*   With debugging enabled it will save the log in the plug-in folder under the file-name: debug_log

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload all contents of the archive in the plug-ins folder in it's own map e.g. woozoho-connector.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Setup all your preferences and API data on the settings page:
   WooCommerce -> Settings -> Zoho Connector (tab)
4. Put plug-in in 'test mode', do a test order to make sure all is working.
5. Tada! Your WooCommerce & Zoho Books are now one!

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. Settings page
2. Bulk Option
2. This is the second screen shot

== Changelog ==

= 0.6 =
* First stable and production ready public beta.