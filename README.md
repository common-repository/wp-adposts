
# WordPress AdPosts Plugin.
This simply WordPress plugin allows you to manage ads using codes or images.
In fact, it creates a custom post type named **wpap_ad** and a custom taxonomy called **wpap_location**.

## How to install?
Simply unzip the package into plugins folder then head to your WordPress dashboard and activate the plugin.
Once activated, a menu item will be added to you dashboard menu like shown in the image below.

![Dashboard Menu Item](screenshot-1.png)

## What to do next?
The plugin creates three images sizes by default: **300x250**, **468x60** and **728x90**. You can choose as many ads banners sizes as you want by heading to plugin's settings page.

![Plugin Settings Page](screenshot-2.png)

## Creating Locations.
Head now to ads locations creation because they are really needed for ads to be displayed (this will be explained later).
Give your locations names and descriptions and please don't forget to select ads dimensions so the plugin can correctly display them. If you leave at as **undefined** the with will be set to **100%** and the height to **auto**.

![Ads Locations](screenshot-3.png)

## Creating Ads.
To create ads, click on the add button and simply fill required field.
**DO NOT** forget the select the location where the ad should be displayed.

![Creating Ads](screenshot-4.png)

- If you use images, make sure to use **Featured Image** and simply leave the content empty.
- If you use codes (javascript or other), make sure to paste them in yout editor on **Text** mode, **NOT Visual**.

Once that done, hit **Publish** and you're done!

![List Of Ads](screenshot-5.png)

## Displaying Ads.
A single ad is shown at the selected location at a time. If you have lots of ads assigned to the same location, they will be randomly shown.

It's true I am calling them _locations_, but you can use them as banners sizes. So instead of calling a location **header**, you can call it **banners-728x90**. Simply remember the location's slug because you're going to use it to display ads.

In your theme's files, go wherever you want to display ads and use the provided **wp_adposts** function like so:
```php
echo wp_adposts($location); // The location's slug.
```
It is up to you to display the ad the way you want. I personally check if a widget is set at a certain location first, if there is none, I display the ad. Let's suppose I want to display ads on the sidebar and that I have already a declared widgets area called **sidebar**. Here is how I, personally, proceed:
```php
// I first check if there are widgets in this area.
if (is_active_sidebar('sidebar')) {
	dynamic_sidebar('sidebar'); // Display widgets.
}
// If there are no widgets, I display ads (300x250 or 300x600).
// Remember to always check if the function exists in case you
// disable the plugin.
elseif (function_exists('wp_adposts')) {
	echo wp_adposts('sidebar'); // Display ads.
}
```

![Final Result](screenshot-6.png)

## Shortcode.
I have provided a shortcode that you can use in order to display ads within your posts, pages or even in text widgets. Simply do the following:
```
[wp-adposts location="sidebar"]
```
That's all folks. If you have questions or suggestions, feel free to contact me.
