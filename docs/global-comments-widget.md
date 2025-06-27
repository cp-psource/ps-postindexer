# Recent Comments Widget

**INACTIVE NOTICE: This plugin is unsupported by WPMUDEV, we've published it here for those technical types who might want to fork and maintain it for their needs.**


Display all the latest comments from across an entire Multisite network using a simple powerful widget.

* Displays comments from across a network 
* Set number of comments displayed 
* Choose number of characters 
* Powered by Comment Indexer 
* Display avatars 
* Preset avatar size 

## Recent Comments Widget brings all the latest comments from across your entire network to your main site.

### Advanced Comments for Multisite

Using the incredible power of Comment Indexer, this widget allows you to display a list of all the latest comments from across your entire network on any main site widget area.

##### Features for this simple but powerful plugin include:

* Displays comments from across your entire network
* Option to change the number of comments displayed
* Choose number of characters
* Display avatars
* Preset avatar size

### Drag, Drop and Configure

Drop the Recent Global Comments widget into any** **of the widget areas on the main site of your network.

![Recent Global Comment widget][35]

Choose settings and the latest comments will be pulled onto your network homepage

Create a more connected network by sharing comments from across your entire network.

For help with installing plugins please refer to our [Plugin installation guide.][36]

### To Install:

1\. Install the Comment Indexer

* The Comment Indexer is designed to index comment from the time it is installed on your network and needs to be installed for the Recent Global Comments Widget plugin to work.
* The Comment Indexer can’t index commentss that were published prior to being installed. i.e. _comments published prior to it being installed won’t display in your Recent Global Comment Widget._

2\. Install the Recent Comments Plugin.

* Once uploaded visit **Network Admin -> Plugins** to Network Activate the Recent Global Comments Widget Plugin.
* Your Recent Global Comment widget is added to **Appearance > Widgets **of your main site.
* By default, the widget is available only on the main site of your network. This behavior can be changed by following the instructions below to edit your **widget-recent-global-comments.php**
* The Recent Global comment widget list comments on public blogs only. Comments from privates sites aren’t displayed.

3\. Install the avatars plugin (if you want to display avatars).

### Enabling Widget for all sites

By default the Recent Global Comments widget is only enabled for use by the main site.

Here’s how you can enable it for all sites on your network:

1.  Open up **widget-recent-global-comments.php **using a text editing program like [EditPlus][39] or [Notepad++][40]

2.  Change ‘yes’ to ‘no’ in the following line:

$recent_global_comments_widget_main_blog_only = ‘yes’;

3.  Save your amended **widget-recent-global-comments.php **and use this file when you upload **widget-recent-global-comments **directory to **wp-content/plugins/ **

![Editing the php file][41]

### To Use:

1.  Once installed you just go **Appearance > Widgets**

2.  Add the Recent Global comments widget to your sidebar and select your configuration options.

![Recent Global Comment widget][42]

[35]: https://premium.wpmudev.org/wp-content/uploads/2009/03/recent-global-comments-widget.png
[41]: https://premium.wpmudev.org/wp-content/uploads/2009/03/recent-global-comments-widget-config.png
[42]: https://premium.wpmudev.org/wp-content/uploads/2009/03/recent-global-comments-widget1.png
