# Recent Global Posts Feed

**INACTIVE NOTICE: This plugin is unsupported by WPMUDEV, we've published it here for those technical types who might want to fork and maintain it for their needs.**

## Translations

Translation files can be found at https://github.com/wpmudev/translations


## Usage

Recent Global Posts feed takes advantage of Post Indexer to add an RSS feed of all the posts on your entire Multisite or BuddyPress network - simply, efficiently and quickly.

##### This powerful plugin allows you to:

*   Creates a **Post feed URL for your entire network** which anyone can subscribe to
*   Provides a simple **Global Post Feed widget**

### Setup is easy

Once installed your new feed is created at:

<pre>http://yoursite.com/feed/globalpostsfeed</pre>

You can even access custom post type feeds by visiting:

<pre>http://yoursite.com/feed/globalpostsfeed?posttype=customposttype</pre>

Just substitute 'customposttype' with your preferred post type.

### Bundled widget for easy sharing

A built-in widget makes global feeds easy to share. Best of all you can add a simple '_Subscribe to our Network post feed'_ by adding the Recent Global Post feed widget.

### To Install:

1\. Install the Post Indexer

*   The Post Indexer is designed to index posts on your network and needs to be installed for the Recent Global Posts Feed plugin to work.

2. Install Recent Global Posts Feed.

3. Once installed your new feed is created at:

<pre>http://yoursite.com/feed/globalpostsfeed</pre>

*   You can access feeds for custom post types by using http://yoursite.com/feed/globalpostsfeed?posttype=customposttype, substituting customposttype with your custom post type
*   You can check if it is working correctly by posting some new posts and confirming they appear in your global feed.

4\.  Install Neueste Netzwerk Beiträge

*   Visit **Network Admin -> Plugins** to Network Activate the Neueste Netzwerk Beiträge
*   Your Neueste Netzwerk Beiträge is added to **Appearance > Widgets** of your main site.
*   By default, the widget is available only on the main site of your network. This behavior can be changed by following the instructions below.
*   The Recent Global Posts Feed is for public posts only. Posts from privates sites don't appear in the feed.

### Enabling Widget for all sites

By default the Neueste Netzwerk Beiträge is only enabled for use by the main site. You can enable it for all sites on your network as follows:

1.  Open your **wp-config.php** file using a text editing program like [EditPlus](http://www.editplus.com/) 

2.  Add the following constant to that file just before the line that says "That's all, stop editing" `define( 'RECENT_GLOBAL_POSTS_FEED_WIDGET_MAIN_BLOG_ONLY', false );` 

3.  Save and re-upload your wp-config.php file. Note: if you ever want to limit the use of the widget to the main site only again, simply change "false" to "true" in the code you just added.

### To Use

1.  Go to **Appearance > Widgets**

2.  Add the Neueste Netzwerk Beiträge to your sidebar. 

3.  Check out the configuration options 


### Known Issues

If you are having difficulty getting your feed to validate, please go to Settings > Permalinks in your admin and click Save Changes. That will re-save your settings and updated the rewrite rules. To check if your feed validates properly, visit [http://validator.w3.org/feed/](http://validator.w3.org/feed/)
